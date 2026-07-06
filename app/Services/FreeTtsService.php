<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Free (bina API key) TTS — Google Translate TTS se raw PCM audio banata hai.
 * Gemini TTS ki 10/din limit khatam hone par GeminiTtsService isi par gir jaata
 * hai. Quality Gemini se kam par UNLIMITED. Hindi (hi) + Gujarati (gu) support.
 *
 * translate_tts ~200 char/request leta hai — isliye text ko chunks me tod kar
 * har chunk ka MP3 laate hain, phir ffmpeg se sabko ek raw PCM (24kHz mono,
 * s16le) me jodते hain — taaki GeminiTtsService uska WAV bana sake (same pipeline).
 */
class FreeTtsService
{
    private const SAMPLE_RATE = 24000;
    private const MAX_CHARS = 180;

    /**
     * Text → raw PCM bytes (signed 16-bit LE, mono, 24kHz).
     *
     * @throws \RuntimeException
     */
    public function fetchPcm(string $text, string $language = 'hindi'): string
    {
        $text = trim($text);
        if ($text === '') {
            throw new \RuntimeException('Free TTS: text khaali.');
        }

        // Google Translate voice code
        $tl = match ($language) {
            'gujarati' => 'gu',
            default    => 'hi', // hindi + hinglish
        };

        $disk = Storage::disk('public');
        $disk->makeDirectory('tts/tmp');
        $base     = 'tts/tmp/' . Str::uuid();
        $pcmAbs   = $disk->path($base . '.pcm');
        $mp3Files = [];

        try {
            foreach ($this->chunk($text) as $i => $chunk) {
                $rel = "{$base}-{$i}.mp3";
                $disk->put($rel, $this->fetchMp3($chunk, $tl));
                $mp3Files[] = $disk->path($rel);
            }

            if (empty($mp3Files)) {
                throw new \RuntimeException('Free TTS: koi audio nahi bana.');
            }

            return $this->mp3sToPcm($mp3Files, $pcmAbs);
        } finally {
            foreach ($mp3Files as $f) {
                @unlink($f);
            }
            @unlink($pcmAbs);
            @unlink(preg_replace('/\.pcm$/', '.txt', $pcmAbs));
        }
    }

    /** Text ko <=180 char chunks me todo (words na toote). */
    protected function chunk(string $text): array
    {
        $text  = preg_replace('/\s+/u', ' ', trim($text));
        $words = explode(' ', $text);

        $chunks = [];
        $cur    = '';
        foreach ($words as $w) {
            $candidate = $cur === '' ? $w : $cur . ' ' . $w;
            if (mb_strlen($candidate) > self::MAX_CHARS && $cur !== '') {
                $chunks[] = $cur;
                $cur = $w;
            } else {
                $cur = $candidate;
            }
        }
        if ($cur !== '') {
            $chunks[] = $cur;
        }

        return $chunks;
    }

    /** Ek chunk ka MP3 Google Translate TTS se. */
    protected function fetchMp3(string $chunk, string $tl): string
    {
        $res = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                    . '(KHTML, like Gecko) Chrome/120.0 Safari/537.36',
                'Referer'    => 'https://translate.google.com/',
            ])
            ->timeout(30)
            ->retry(2, 1000, throw: false)
            ->get('https://translate.google.com/translate_tts', [
                'ie'      => 'UTF-8',
                'client'  => 'tw-ob',
                'tl'      => $tl,
                'q'       => $chunk,
                'total'   => 1,
                'idx'     => 0,
                'textlen' => mb_strlen($chunk),
            ]);

        if (! $res->successful() || strlen($res->body()) < 200) {
            throw new \RuntimeException('Free TTS fetch fail (HTTP ' . $res->status() . ').');
        }

        return $res->body();
    }

    /** MP3 files → ek raw PCM (s16le 24kHz mono) via ffmpeg concat. */
    protected function mp3sToPcm(array $mp3Files, string $pcmAbs): string
    {
        $ffmpeg  = config('services.ffmpeg.path', 'ffmpeg');
        $listAbs = preg_replace('/\.pcm$/', '.txt', $pcmAbs);

        // concat demuxer list (single ya multiple — dono ke liye)
        $lines = array_map(fn ($f) => "file '" . str_replace("'", "'\\''", $f) . "'", $mp3Files);
        file_put_contents($listAbs, implode("\n", $lines));

        $result = Process::timeout(120)->run([
            $ffmpeg, '-y',
            '-f', 'concat', '-safe', '0', '-i', $listAbs,
            '-f', 's16le', '-ar', (string) self::SAMPLE_RATE, '-ac', '1',
            $pcmAbs,
        ]);

        if (! $result->successful() || ! is_file($pcmAbs) || filesize($pcmAbs) < 1000) {
            throw new \RuntimeException('Free TTS: audio convert fail (ffmpeg).');
        }

        $pcm = file_get_contents($pcmAbs);
        if ($pcm === false || $pcm === '') {
            throw new \RuntimeException('Free TTS: PCM khaali.');
        }

        return $pcm;
    }
}
