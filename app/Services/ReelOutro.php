<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * 🔔 Reel ke end me "Follow karo" outro — Settings me bana CTA card (image) +
 * optional AI voice line, har reel (Instagram / Facebook / YouTube) ke aakhir me.
 *
 * Image browser canvas me banti hai (Gujarati/Hindi shaping ke liye), yahan sirf
 * ffmpeg se jodte hain. Outro fail ho to reel bina outro ke jaati hai — posting kabhi na ruke.
 */
class ReelOutro
{
    public const MIN_SECONDS = 2.5;

    public static function enabled(?int $userId): bool
    {
        if (! $userId || Setting::getFor($userId, 'reel_outro_enabled', '0') !== '1') {
            return false;
        }

        $img = (string) Setting::getFor($userId, 'reel_outro_image', '');

        return $img !== '' && Storage::disk('public')->exists($img);
    }

    /**
     * YouTube segment format me outro: ['path','dur','voice'?]. Off ho to null.
     *
     * @return array{path:string,dur:float,voice?:string}|null
     */
    public static function segment(?int $userId, ?string $language = null, ?string $voiceName = null): ?array
    {
        if (! static::enabled($userId)) {
            return null;
        }

        $disk = Storage::disk('public');
        $seg  = [
            'path' => $disk->path((string) Setting::getFor($userId, 'reel_outro_image')),
            'dur'  => (float) max(static::MIN_SECONDS, (float) Setting::getFor($userId, 'reel_outro_seconds', 3)),
        ];

        if ($voice = static::voice($userId, $language, $voiceName)) {
            $seg['voice'] = $disk->path($voice['path']);
            $seg['dur']   = max($seg['dur'], $voice['seconds'] + 0.5);
        }

        return $seg;
    }

    /**
     * Bani hui reel (storage-relative mp4) ke end me outro jodo — same file replace.
     * Off ho ya ffmpeg fail ho to file jaisi thi waisi.
     */
    public static function append(string $mp4Rel, ?int $userId, ?string $language = null, ?string $voiceName = null): string
    {
        $seg = static::segment($userId, $language, $voiceName);
        if (! $seg) {
            return $mp4Rel;
        }

        $disk = Storage::disk('public');
        $in   = $disk->path($mp4Rel);
        $tmp  = preg_replace('/\.mp4$/i', '', $in) . '.outro.mp4';

        [$w, $h] = static::videoSize($in);
        $dur = number_format($seg['dur'], 2, '.', '');
        $ffmpeg = config('services.ffmpeg.path', 'ffmpeg');

        $cmd = [$ffmpeg, '-y', '-i', $in, '-loop', '1', '-t', $dur, '-i', $seg['path']];
        if (! empty($seg['voice'])) {
            $cmd = array_merge($cmd, ['-i', $seg['voice']]);
            $outroAudio = "[2:a]aresample=44100,apad,atrim=duration={$dur},aformat=sample_rates=44100:channel_layouts=stereo[oa]";
        } else {
            $cmd = array_merge($cmd, ['-f', 'lavfi', '-t', $dur, '-i', 'anullsrc=channel_layout=stereo:sample_rate=44100']);
            $outroAudio = '[2:a]aformat=sample_rates=44100:channel_layouts=stereo[oa]';
        }

        // Dono hisso ka size/fps/sar same — warna concat fail hota hai
        $filter = implode(';', [
            "[0:v]scale={$w}:{$h},setsar=1,fps=25,format=yuv420p[mv]",
            '[0:a]aresample=44100,aformat=sample_rates=44100:channel_layouts=stereo[ma]',
            "[1:v]scale={$w}:{$h}:force_original_aspect_ratio=decrease,pad={$w}:{$h}:(ow-iw)/2:(oh-ih)/2:color=black,setsar=1,fps=25,format=yuv420p[ov]",
            $outroAudio,
            '[mv][ma][ov][oa]concat=n=2:v=1:a=1[v][a]',
        ]);

        $cmd = array_merge($cmd, [
            '-filter_complex', $filter,
            '-map', '[v]', '-map', '[a]',
            '-c:v', 'libx264', '-preset', 'ultrafast', '-threads', '1',
            '-x264-params', 'ref=1:bframes=0:rc-lookahead=10:sync-lookahead=0',
            '-profile:v', 'high', '-level', '3.1',
            '-pix_fmt', 'yuv420p', '-color_range', 'tv', '-r', '25',
            '-g', '50', '-keyint_min', '50', '-sc_threshold', '0', '-flags', '+cgop',
            '-c:a', 'aac', '-b:a', '128k', '-ar', '44100', '-ac', '2',
            '-movflags', '+faststart',
            $tmp,
        ]);

        try {
            $result = Process::timeout(300)->run($cmd);
            if ($result->successful() && is_file($tmp) && filesize($tmp) > 0) {
                @unlink($in);
                rename($tmp, $in);

                return $mp4Rel;
            }
            Log::warning('Reel outro ffmpeg fail — bina outro ke', ['err' => substr($result->errorOutput(), -400)]);
        } catch (\Throwable $e) {
            Log::warning('Reel outro crash — bina outro ke', ['error' => $e->getMessage()]);
        }

        @unlink($tmp);

        return $mp4Rel;
    }

    /**
     * Outro ki voice line (Settings) → audio. Text khaali / TTS off / fail → null.
     *
     * @return array{path:string,seconds:float}|null
     */
    protected static function voice(int $userId, ?string $language, ?string $voiceName): ?array
    {
        $text = trim((string) Setting::getFor($userId, 'reel_outro_voice', ''));
        $tts  = app(GeminiTtsService::class);
        if ($text === '' || ! $tts->isConfigured()) {
            return null;
        }

        try {
            return $tts->speak(
                $text,
                $voiceName ?: (Setting::getFor($userId, 'tts_voice') ?: null),
                'story',
                $language ?: 'gujarati',
            );
        } catch (\Throwable $e) {
            Log::warning('Reel outro voice skip', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** Reel ka WxH (ffmpeg -i ka stderr padh kar). Na mile to 720x1280. */
    protected static function videoSize(string $path): array
    {
        try {
            $out = Process::timeout(30)->run([config('services.ffmpeg.path', 'ffmpeg'), '-hide_banner', '-i', $path]);
            if (preg_match('/Video:.*?(\d{3,4})x(\d{3,4})/', $out->errorOutput() . $out->output(), $m)) {
                return [(int) $m[1] & ~1, (int) $m[2] & ~1];
            }
        } catch (\Throwable $e) {
            // default neeche
        }

        return [720, 1280];
    }
}
