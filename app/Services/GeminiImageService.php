<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Google Gemini image model ("Nano Banana", gemini-2.5-flash-image) se image
 * banata hai. Output ko ffmpeg se exact 9:16 (720x1280) me crop/scale karke
 * cover ke liye save karta hai.
 */
class GeminiImageService
{
    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * Prompt se image banao aur 9:16 cover ke roop me save karo.
     * Storage-relative path return karta hai.
     *
     * @throws \RuntimeException
     */
    public function generate(string $prompt, string $folder = 'covers', int $w = 720, int $h = 1280): string
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Gemini API key set nahi (.env GEMINI_API_KEY).');
        }

        $bytes = $this->fetchImage($prompt);

        $disk = Storage::disk('public');
        $disk->makeDirectory($folder);

        // Raw image save, phir ffmpeg se exact 9:16 crop/scale
        $tmp = $folder . '/_raw_' . Str::uuid() . '.png';
        $disk->put($tmp, $bytes);

        $final  = $folder . '/' . Str::uuid() . '.png';
        $ffmpeg = config('services.ffmpeg.path', 'ffmpeg');

        $res = Process::timeout(120)->run([
            $ffmpeg, '-y', '-i', $disk->path($tmp),
            // 9:16 bharo: bada karke center crop
            '-vf', "scale={$w}:{$h}:force_original_aspect_ratio=increase,crop={$w}:{$h}",
            $disk->path($final),
        ]);

        $disk->delete($tmp);

        if (! $res->successful() || ! $disk->exists($final)) {
            Log::error('Gemini cover ffmpeg crop failed', ['err' => $res->errorOutput()]);
            throw new \RuntimeException('Cover image process nahi hui (ffmpeg).');
        }

        return $final;
    }

    /**
     * Gemini image model se raw image bytes lao.
     */
    protected function fetchImage(string $prompt): string
    {
        $model = (string) config('services.gemini.image_model', 'gemini-2.5-flash-image');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $res = Http::timeout(180)
            ->retry(1, 2000, throw: false)
            ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->post($url, [
                'contents' => [[
                    'parts' => [['text' => $prompt]],
                ]],
                'generationConfig' => [
                    'responseModalities' => ['TEXT', 'IMAGE'],
                ],
            ]);

        if (! $res->successful()) {
            Log::error('Gemini image failed', ['status' => $res->status(), 'body' => $res->json() ?: $res->body()]);
            throw new \RuntimeException($res->json('error.message') ?? ('Gemini image error (HTTP ' . $res->status() . ').'));
        }

        foreach (($res->json('candidates.0.content.parts') ?? []) as $part) {
            if (! empty($part['inlineData']['data'])) {
                $bin = base64_decode($part['inlineData']['data'], true);
                if ($bin !== false && $bin !== '') {
                    return $bin;
                }
            }
        }

        Log::error('Gemini image: no inlineData', ['body' => $res->json()]);
        throw new \RuntimeException('Gemini se image nahi mila (shayad is key/tier par image gen available nahi).');
    }
}
