<?php

namespace App\Services;

use App\Models\PartCard;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Quiz ka "timer reel" — ek hi video me:
 *
 *   [ Question + 4 options + countdown 5..4..3..2..1 ]  →  [ ✅ Answer reveal ]
 *
 * Yahi format viral quiz reels use karte hain — log answer comment karte hain,
 * isse engagement badhta hai. Do alag posts (Q phir A) me ye kaam nahi karta.
 *
 * Sirf un cards par chalta hai jinke paas `answer_image_path` ho (naye quiz).
 * Purane cards {@see InstagramService} / {@see YoutubeService} ke normal
 * single-image reel se hi bante rehte hain.
 */
class QuizReelService
{
    /** Countdown ke liye default seconds (Settings se override ho sakta hai). */
    public const DEFAULT_TIMER = 5;

    /** Timer ki allowed range. Lambe timer (20-25s) mushkil sawaalon ke liye —
     *  reel tab bhi Instagram/Shorts ki 90s limit ke aaram se andar rehti hai. */
    public const MIN_TIMER = 3;

    public const MAX_TIMER = 25;

    /** Timer dropdown me dikhne wale options. */
    public const TIMER_CHOICES = [3, 5, 7, 10, 15, 20, 25];

    /** Answer reveal kitni der screen par rahe (voice ho to uske hisab se badhta hai). */
    private const ANSWER_MIN_SECONDS = 3.0;

    public function __construct(private GeminiTtsService $tts)
    {
    }

    /** Is card ka timer-reel ban sakta hai ya nahi. */
    public function supports(?PartCard $card): bool
    {
        if (! $card || blank($card->image_path) || blank($card->answer_image_path)) {
            return false;
        }

        $disk = Storage::disk('public');

        return $disk->exists($card->image_path) && $disk->exists($card->answer_image_path);
    }

    /**
     * Timer reel banao (cache ke saath) aur storage path do.
     *
     * @throws \RuntimeException
     */
    public function mp4For(PartCard $card): string
    {
        if (! $this->supports($card)) {
            throw new \RuntimeException('Is card me answer image nahi hai — timer reel nahi ban sakti.');
        }

        $disk = Storage::disk('public');
        $disk->makeDirectory('reels');
        $mp4 = 'reels/quiz-' . $card->id . '.mp4';

        $userId = $card->part?->story?->user_id;
        $timer  = $this->timerSeconds($userId);

        // Voice (agar mode on ho) — question ke liye aur answer ke liye alag
        $qVoice = $this->voice($card, $card->text, $userId);
        $aVoice = $this->voice($card, $card->answer_text, $userId);

        // Question segment kam se kam timer jitna lamba — warna countdown kat jaata
        $qDur = round(max($timer, $qVoice ? $qVoice['seconds'] + 0.8 : 0), 2);
        $aDur = round(max(self::ANSWER_MIN_SECONDS, $aVoice ? $aVoice['seconds'] + 0.8 : 0), 2);

        // Resolution tiers: pehle 720p, OOM/fail par halki res par retry.
        $tiers      = [[720, 1280], [540, 960], [480, 854]];
        $lastErr    = '';
        $sfxEnabled = (string) Setting::getFor($userId, 'quiz_sfx_enabled', '1') !== '0';
        $bgmTrack   = (string) Setting::getFor($userId, 'quiz_bgm_track', 'suspense');

        foreach ($tiers as [$w, $h]) {
            $cmd = $this->buildCommand(
                $disk->path($card->image_path),
                $disk->path($card->answer_image_path),
                $qVoice ? $disk->path($qVoice['path']) : null,
                $aVoice ? $disk->path($aVoice['path']) : null,
                $qDur,
                $aDur,
                $timer,
                $disk->path($mp4),
                ReelMotion::enabled($userId),
                $w,
                $h,
                $sfxEnabled,
                $bgmTrack,
            );

            try {
                $result = Process::timeout(600)->run($cmd);
            } catch (\Throwable $e) {
                // Signal 9 (OOM kill) / timeout / process crash — agli chhoti res try karo
                $lastErr = $e->getMessage();
                Log::warning('Quiz ffmpeg process crash, lower-res retry', ['res' => "{$w}x{$h}", 'error' => $lastErr]);
                continue;
            }

            if ($result->successful() && $disk->exists($mp4)) {
                return $mp4;
            }

            $lastErr = $result->errorOutput() ?: $result->output();
            Log::warning('Quiz ffmpeg failed, lower-res retry', ['res' => "{$w}x{$h}", 'err' => Str::limit($lastErr, 300)]);
        }

        Log::error('Quiz ffmpeg failed (all resolutions)', ['err' => $lastErr]);
        throw new \RuntimeException('Quiz reel nahi ban paayi (ffmpeg signal 9 / OOM / crash). ' . Str::limit($lastErr, 150));
    }

    /** Countdown kitne second ka — Settings me badal sakte hain. */
    public function timerSeconds(?int $userId): int
    {
        $val = (int) Setting::getFor($userId, 'quiz_timer_seconds', self::DEFAULT_TIMER);

        return max(self::MIN_TIMER, min(self::MAX_TIMER, $val ?: self::DEFAULT_TIMER));
    }

    /**
     * Text → voice. Mode 'music' ho, text khaali ho ya TTS configured na ho to null.
     *
     * @return array{path:string,seconds:float}|null
     */
    protected function voice(PartCard $card, ?string $text, ?int $userId): ?array
    {
        $mode = Setting::getFor($userId, 'tts_audio_mode', 'music');
        $mode = $card->part?->story?->tts_mode ?: $mode;

        if ($mode === 'music' || blank($text) || ! $this->tts->isConfigured()) {
            return null;
        }

        try {
            return $this->tts->speak(
                $text,
                $card->part?->story?->tts_voice ?: (Setting::getFor($userId, 'tts_voice') ?: null),
                'quiz',
                $card->part?->story?->language ?: 'hindi',
            );
        } catch (\Throwable $e) {
            // Voice na bane to bhi reel banni chahiye (TTS quota aksar khatam hota hai)
            Log::warning('Quiz reel voice fail, bina voice ke bana rahe hain', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * ffmpeg command — 2 video segments concat, voiceover + countdown tick/ding SFX + BGM mixing.
     *
     * Instagram Reels spec ke hisab se: 720x1280, H.264 high, yuv420p, TV range,
     * AAC 44.1k stereo.
     *
     * @return list<string>
     */
    protected function buildCommand(
        string $qImg,
        string $aImg,
        ?string $qVoice,
        ?string $aVoice,
        float $qDur,
        float $aDur,
        int $timer,
        string $out,
        bool $motion = false,
        int $w = 720,
        int $h = 1280,
        bool $sfx = true,
        string $bgmTrack = 'suspense',
    ): array {
        $ffmpeg = config('services.ffmpeg.path', 'ffmpeg');

        $cmd = [
            $ffmpeg, '-y',
            '-loop', '1', '-t', $this->num($qDur), '-i', $qImg,   // 0 = question
            '-loop', '1', '-t', $this->num($aDur), '-i', $aImg,   // 1 = answer
        ];

        // Audio inputs — voice ho to wahi, warna us segment ke liye silence
        $audioIdx = 2;
        $qAudio   = $this->addAudioInput($cmd, $audioIdx, $qVoice, $qDur);
        $aAudio   = $this->addAudioInput($cmd, $audioIdx, $aVoice, $aDur);

        $qFit = ReelMotion::chain($w, $h, $qDur, 0, $motion);
        $aFit = ReelMotion::chain($w, $h, $aDur, 1, $motion);

        $totalDur = $qDur + $aDur;

        // Video filter
        $filterParts = [
            '[0:v]' . $qFit . $this->countdownFilter($timer, $qDur) . '[v0]',
            '[1:v]' . $aFit . '[v1]',
            '[v0][v1]concat=n=2:v=1:a=0[v]',
        ];

        // Audio filter
        $audioFilters = [
            rtrim($qAudio, ';'),
            rtrim($aAudio, ';'),
            '[aq][aa]concat=n=2:v=0:a=1[avoice]',
        ];

        $currentAudioLabel = '[avoice]';

        // 1. Sound Effects (Tick-Tick during countdown & Ding on Answer Reveal)
        if ($sfx) {
            $tickStart = max(0.0, $qDur - $timer);
            $sfxNodes = [];
            for ($k = 0; $k < $timer; $k++) {
                $tTime = $tickStart + $k;
                $delayMs = (int) round($tTime * 1000);
                $sfxNodes[] = "sine=frequency=1600:duration=0.04,afade=t=out:st=0.01:d=0.03,volume=1.5,adelay={$delayMs}|{$delayMs}[sfx_t{$k}]";
            }
            // Ding at exact answer reveal ($qDur)
            $dingDelayMs = (int) round($qDur * 1000);
            $sfxNodes[] = "sine=frequency=1760:duration=1.1,afade=t=out:st=0.05:d=1.05,volume=1.9,adelay={$dingDelayMs}|{$dingDelayMs}[sfx_ding]";

            $sfxInputsStr = '';
            for ($k = 0; $k < $timer; $k++) {
                $sfxInputsStr .= "[sfx_t{$k}]";
            }
            $sfxInputsStr .= '[sfx_ding]';
            $sfxTotalCount = $timer + 1;

            $audioFilters[] = implode(';', $sfxNodes);
            $audioFilters[] = $sfxInputsStr . "amix=inputs={$sfxTotalCount}:duration=longest,aformat=sample_rates=44100:channel_layouts=stereo[sfx_all]";
            $audioFilters[] = "[avoice][sfx_all]amix=inputs=2:duration=first[avoice_sfx]";
            $currentAudioLabel = '[avoice_sfx]';
        }

        // 2. Background Music (BGM)
        $bgmFile = public_path("audio/bgm/{$bgmTrack}.mp3");
        if ($bgmTrack !== 'none' && file_exists($bgmFile)) {
            $cmd = array_merge($cmd, ['-stream_loop', '-1', '-t', $this->num($totalDur), '-i', $bgmFile]);
            $bgmIdx = $audioIdx;
            $audioIdx++;

            $audioFilters[] = "[{$bgmIdx}:a]atrim=duration={$this->num($totalDur)},volume=0.22,aformat=sample_rates=44100:channel_layouts=stereo[abgm]";
            $audioFilters[] = "{$currentAudioLabel}[abgm]amix=inputs=2:duration=first:dropout_transition=2[afinal]";
            $currentAudioLabel = '[afinal]';
        }

        $audioFilters[] = "{$currentAudioLabel}aformat=sample_rates=44100:channel_layouts=stereo[a]";
        $filter = implode(';', array_merge($filterParts, array_filter($audioFilters)));

        return array_merge($cmd, [
            '-filter_complex', $filter,
            '-map', '[v]', '-map', '[a]',
            '-c:v', 'libx264', '-preset', 'ultrafast', '-tune', 'stillimage', '-threads', '1',
            '-x264-params', 'ref=1:bframes=0:rc-lookahead=10:sync-lookahead=0',
            '-profile:v', 'high', '-level', '3.1',
            '-pix_fmt', 'yuv420p', '-color_range', 'tv', '-r', '25',
            '-g', '50', '-keyint_min', '50', '-sc_threshold', '0', '-flags', '+cgop',
            '-c:a', 'aac', '-b:a', '128k', '-ar', '44100', '-ac', '2',
            '-movflags', '+faststart',
            $out,
        ]);
    }

    /**
     * Ek segment ka audio input jodo aur uska filter-chunk return karo.
     * Voice ho to use pad/trim karke exact segment length me fit karte hain.
     */
    protected function addAudioInput(array &$cmd, int &$idx, ?string $voice, float $dur): string
    {
        $label = $idx === 2 ? 'aq' : 'aa';

        if ($voice) {
            $cmd = array_merge($cmd, ['-i', $voice]);
            $chunk = "[{$idx}:a]aresample=44100,apad,atrim=duration=" . $this->num($dur)
                . ',aformat=sample_rates=44100:channel_layouts=stereo[' . $label . '];';
        } else {
            $cmd = array_merge($cmd, [
                '-f', 'lavfi', '-t', $this->num($dur),
                '-i', 'anullsrc=channel_layout=stereo:sample_rate=44100',
            ]);
            $chunk = "[{$idx}:a]aformat=sample_rates=44100:channel_layouts=stereo[" . $label . '];';
        }

        $idx++;

        return $chunk;
    }

    /**
     * Countdown overlay — bada number (5,4,3,2,1) + neeche ghatati hui progress line.
     *
     * Font na mile to khaali string — reel phir bhi ban jaati hai, bas timer ke
     * bina (Linux/shared host par font path alag hota hai).
     *
     * Progress bar har second ke liye ek alag drawbox hai (ghatati hui width),
     * ek time-based expression ke bajaye. Wajah: drawbox ke expression me `t`
     * ka matlab THICKNESS hota hai, time nahi — isliye `iw*(1-t/5)` jaisi width
     * hamesha poori width de deti thi. `n` (frame number) drawbox support hi
     * nahi karta ("Error when evaluating the expression").
     */
    protected function countdownFilter(int $timer, float $qDur): string
    {
        $font = $this->fontFile();

        if ($font === null) {
            return '';
        }

        // Bacha hua time. ceil isliye ki pehle poore second me "5" dikhe — bina
        // iske eif value truncate kar deta hai aur countdown 4 se shuru lagta hai.
        $text = sprintf('%%{eif\\:ceil(max(0\\,%d-t))\\:d}', $timer);

        // Countdown UPAR-DAAYE kone me. Pehle ye neeche beech me tha (y=h-th-118)
        // jahan card ka apna footer ("jawab comment me...") aur handle hote hain —
        // number seedha unke upar chadh jaata tha aur dono padhe nahi jaate the.
        // Upar-daaya kona khaali rehta hai kyunki header text center-aligned hai.
        //
        // fontfile ko quote karna zaroori hai — bina quote ke ffmpeg escaped colon
        // ke baawajood path ko "C:" par tod deta hai ("No option name near ...").
        $filter = ',drawtext=fontfile=\'' . $font . '\':text=\'' . $text . '\''
            . ':fontcolor=white:fontsize=84:box=1:boxcolor=black@0.62:boxborderw=22'
            . ':x=w-tw-46:y=42:enable=\'lt(t,' . $timer . ')\'';

        for ($i = 0; $i < $timer; $i++) {
            $width = number_format(1 - $i / $timer, 4, '.', '');
            // y me `ih` (input height) — `h` drawbox me BOX ki height hoti hai,
            // usse y negative ho jaata hai aur bar frame ke upar clip ho jaati hai.
            $filter .= ',drawbox=x=0:y=ih-26:w=\'iw*' . $width . '\':h=14:color=0x22c55e:t=fill'
                . ':enable=\'between(t,' . $i . ',' . ($i + 1) . ')\'';
        }

        return $filter;
    }

    /**
     * drawtext ke liye font file. ffmpeg filter parser me ':' ka matlab option
     * separator hota hai, isliye path me use escape karna padta hai.
     */
    protected function fontFile(): ?string
    {
        $candidates = array_filter([
            config('services.ffmpeg.font'),
            'C:/Windows/Fonts/arialbd.ttf',
            'C:/Windows/Fonts/arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        ]);

        foreach ($candidates as $path) {
            if (is_file($path)) {
                // Pehle backslash → forward slash, PHIR colon escape. Ulta karne par
                // abhi-abhi lagaya hua escape-backslash khud hi replace ho jaata hai
                // ("C\:/..." → "C/:/...") aur ffmpeg filter parse fail kar deta hai.
                return str_replace(':', '\\:', str_replace('\\', '/', $path));
            }
        }

        Log::warning('Quiz reel: drawtext ke liye koi font nahi mila — timer ke bina reel banegi.');

        return null;
    }

    /** ffmpeg ko locale-safe decimal do (kahin comma na aa jaye). */
    protected function num(float $n): string
    {
        return number_format($n, 2, '.', '');
    }
}
