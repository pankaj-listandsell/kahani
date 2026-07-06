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
    public function speak(string $text, ?string $voice = null, string $style = 'story'): array
    {
        // Emoji/symbols hata do — warna TTS "red heart", "face with tears" bol deta
        $text = $this->stripForSpeech($text);
        if ($text === '') {
            throw new \RuntimeException('Voice ke liye text khaali hai.');
        }
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Gemini API key set nahi (.env GEMINI_API_KEY).');
        }

        $voice = $voice ?: (string) config('services.gemini.tts_voice', 'Kore');
        $disk  = Storage::disk('public');

        // Style bhi cache key me — expressive shayari vs normal kahani alag audio
        $wavPath = 'tts/' . sha1($voice . '|' . $style . '|' . $text) . '.wav';
        if ($disk->exists($wavPath)) {
            return ['path' => $wavPath, 'seconds' => $this->durationOf($disk->path($wavPath))];
        }

        $pcm = $this->fetchPcm($text, $voice, $this->instructionFor($style));

        $disk->makeDirectory('tts');
        $disk->put($wavPath, $this->wrapWav($pcm));

        return ['path' => $wavPath, 'seconds' => $this->pcmSeconds(strlen($pcm))];
    }

    /**
     * Gemini API se raw PCM audio bytes laao.
     */
    protected function fetchPcm(string $text, string $voice, string $instruction): string
    {
        $model = (string) config('services.gemini.tts_model', 'gemini-2.5-flash-preview-tts');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $response = Http::timeout(120)
            ->retry(2, 2000, throw: false)
            ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->post($url, [
                'contents' => [[
                    'parts' => [[
                        // Style instruction + text
                        'text' => $instruction . "\n\n" . $text,
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

    /**
     * Content type ke hisaab se narration ka andaaz (style instruction).
     * Shayari/quote = dheere, gehre ehsaas ke saath; joke = comic timing;
     * warna normal kahani-sunane wala andaaz.
     */
    protected function instructionFor(string $style): string
    {
        return match ($style) {
            'shayari' => 'Neeche di gayi Hindi shayari ko bahut dheere, gehre ehsaas aur thehraav ke saath, '
                . 'har line ke baad halka pause lete hue, dil se — jaise ek mushayare me sunate hain — padho:',
            'quote'   => 'Is Hindi suvichar ko shaant, prerak aur gambhir andaaz me, thehraav ke saath, '
                . 'har shabd par zor dete hue padho:',
            'joke'    => 'Is Hindi joke ko halke-phulke, mazedaar andaaz me, achhi comic timing ke saath padho — '
                . 'punchline se thoda pehle chhota pause do:',
            default   => 'Is Hindi kahani ko ek natural, expressive kahani-sunane wale andaaz me padho:',
        };
    }

    /**
     * Voice-over se pehle text saaf karo — emoji/pictographs/symbols hata do taaki
     * TTS unhe naam se ("red heart", "face with tears") na bole. Devanagari text +
     * normal punctuation aur newlines (natural pause) intact rehte hain.
     */
    protected function stripForSpeech(string $text): string
    {
        $clean = preg_replace(
            '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{2300}-\x{23FF}'
            . '\x{2190}-\x{21FF}\x{FE00}-\x{FE0F}\x{200D}\x{20E3}\x{1F1E6}-\x{1F1FF}]/u',
            '',
            $text
        ) ?? $text;

        // Spaces tidy (newlines rakho — natural pause ke liye)
        $clean = preg_replace('/[ \t]+/', ' ', $clean);
        $clean = preg_replace('/ *\n */', "\n", $clean);
        $clean = trim($clean);

        // Agar sab kuch emoji hi tha (khaali ho gaya) to original hi rakho
        return $clean !== '' ? $clean : trim($text);
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
