<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * AI se image banane ke liye service.
 * Pollinations.ai use karta hai — bilkul free, koi API key nahi chahiye.
 */
class ImageService
{
    /**
     * Diye gaye prompt se image banao aur storage/app/public/<folder> mein save karo.
     *
     * @param  int     $width   Image width  (default 1024)
     * @param  int     $height  Image height (default 768). Cover 9:16 ke liye 720x1280.
     * @param  string  $folder  Kahan save karein — "parts" ya "covers".
     *
     * @return string  Saved image ka path (jaise "covers/abcd.jpg")
     *
     * @throws \RuntimeException agar image nahi ban paayi.
     */
    public function generate(string $prompt, int $width = 1024, int $height = 768, string $folder = 'parts'): string
    {
        $prompt = trim($prompt);

        if ($prompt === '') {
            throw new \RuntimeException('Image prompt khaali hai.');
        }

        // Har baar naya result mile isliye random seed
        $seed = random_int(1, 999999);

        $url = 'https://image.pollinations.ai/prompt/' . rawurlencode($prompt);

        $response = Http::timeout(150)
            ->retry(2, 2000, throw: false)
            ->get($url, [
                'width'   => $width,
                'height'  => $height,
                'nologo'  => 'true',
                'model'   => 'flux',
                'seed'    => $seed,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('AI image service ne error diya (HTTP ' . $response->status() . '). Thodi der baad try karein.');
        }

        $bytes = $response->body();

        // Kabhi-kabhi service HTML/error text de deti hai — verify karo ki image hai
        if (strlen($bytes) < 1000 || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
            throw new \RuntimeException('AI service se valid image nahi mili. Prompt badal kar try karein.');
        }

        $filename = trim($folder, '/') . '/' . Str::uuid() . '.jpg';
        Storage::disk('public')->put($filename, $bytes);

        return $filename;
    }

    /**
     * Purani image delete karo (agar hai).
     */
    public function delete(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
