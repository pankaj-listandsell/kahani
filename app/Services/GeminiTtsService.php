<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Google AI Studio (Gemini) TTS se text ko Hindi voice-over (WAV) me badalta hai.
 *
 * Gemini TTS raw PCM (signed 16-bit LE, mono, 24kHz) deta hai — usko WAV header
 * ke saath file me likhte hain taaki ffmpeg use kar sake. Result cache hota hai
 * (same text+voice = dobara API call nahi).
 *
 * Free API key: https://aistudio.google.com/apikey  → .env GEMINI_API_KEY
 */
class GeminiTtsService
{
    private const SAMPLE_RATE = 24000; // Gemini TTS output rate
    private const CHANNELS = 1;
    private const BITS = 16;

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * Text → voice WAV. Storage-relative path + duration (seconds) return karta hai.
     *
     * @return array{path:string, seconds:float}
     * @throws \RuntimeException
     */
    public function speak(string $text, ?string $voice = null): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new \RuntimeException('Voice ke liye text khaali hai.');
        }
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Gemini API key set nahi (.env GEMINI_API_KEY).');
        }

        $voice = $voice ?: (string) config('services.gemini.tts_voice', 'Kore');
        $disk  = Storage::disk('public');

        $wavPath = 'tts/' . sha1($voice . '|' . $text) . '.wav';
        if ($disk->exists($wavPath)) {
            return ['path' => $wavPath, 'seconds' => $this->durationOf($disk->path($wavPath))];
        }

        $pcm = $this->fetchPcm($text, $voice);

        $disk->makeDirectory('tts');
        $disk->put($wavPath, $this->wrapWav($pcm));

        return ['path' => $wavPath, 'seconds' => $this->pcmSeconds(strlen($pcm))];
    }

    /**
     * Gemini API se raw PCM audio bytes laao.
     */
    protected function fetchPcm(string $text, string $voice): string
    {
        $model = (string) config('services.gemini.tts_model', 'gemini-2.5-flash-preview-tts');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $response = Http::timeout(120)
            ->retry(2, 2000, throw: false)
            ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->post($url, [
                'contents' => [[
                    'parts' => [[
                        // Instruction + text — natural Hindi narration
                        'text' => "Is Hindi kahani ko ek natural, expressive kahani-sunane wale andaaz me padho:\n\n" . $text,
                    ]],
                ]],
                'generationConfig' => [
                    'responseModalities' => ['AUDIO'],
                    'speechConfig' => [
                        'voiceConfig' => [
                            'prebuiltVoiceConfig' => ['voiceName' => $voice],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::error('Gemini TTS failed', ['status' => $response->status(), 'body' => $response->json() ?: $response->body()]);
            throw new \RuntimeException($response->json('error.message') ?? ('Gemini TTS error (HTTP ' . $response->status() . ').'));
        }

        $b64 = $response->json('candidates.0.content.parts.0.inlineData.data');
        if (! $b64) {
            Log::error('Gemini TTS: no audio in response', ['body' => $response->json()]);
            throw new \RuntimeException('Gemini se audio nahi mila.');
        }

        $pcm = base64_decode($b64, true);
        if ($pcm === false || $pcm === '') {
            throw new \RuntimeException('Gemini audio decode nahi hua.');
        }

        return $pcm;
    }

    /** Saara cached TTS audio delete karo (voice badalne par). */
    public function clearCache(): void
    {
        $disk = Storage::disk('public');
        foreach ($disk->files('tts') as $file) {
            $disk->delete($file);
        }
    }

    protected function pcmSeconds(int $byteLen): float
    {
        return $byteLen / (self::SAMPLE_RATE * self::CHANNELS * (self::BITS / 8));
    }

    protected function durationOf(string $absWavPath): float
    {
        $bytes = max(0, filesize($absWavPath) - 44); // WAV header hataao
        return $this->pcmSeconds($bytes);
    }

    /**
     * Raw PCM ko ek valid WAV file (44-byte header) me lapet do.
     */
    protected function wrapWav(string $pcm): string
    {
        $dataLen   = strlen($pcm);
        $byteRate  = self::SAMPLE_RATE * self::CHANNELS * (self::BITS / 8);
        $blockAlign = self::CHANNELS * (self::BITS / 8);

        $header = 'RIFF'
            . pack('V', 36 + $dataLen)          // ChunkSize
            . 'WAVE'
            . 'fmt '
            . pack('V', 16)                     // Subchunk1Size (PCM)
            . pack('v', 1)                      // AudioFormat = PCM
            . pack('v', self::CHANNELS)
            . pack('V', self::SAMPLE_RATE)
            . pack('V', $byteRate)
            . pack('v', $blockAlign)
            . pack('v', self::BITS)
            . 'data'
            . pack('V', $dataLen);

        return $header . $pcm;
    }
}
