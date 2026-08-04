<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pixabay se free vector/illustration images — Kids Yoga ke pose images ke liye.
 *
 * Pixabay License commercial use allow karti hai aur attribution zaroori nahi,
 * isliye reels/shorts me safe hai. Key na ho to sab kuch gracefully band rehta
 * hai (Yoga studio ka baaki flow chalta rehta hai).
 */
class PixabayService
{
    /** Sirf inhi hosts se download karte hain — warna koi bhi URL bhej kar
     *  server se andar ki service hit karwa sakta hai (SSRF). */
    private const ALLOWED_HOSTS = ['pixabay.com', 'cdn.pixabay.com', 'pixabay.org'];

    public function isConfigured(): bool
    {
        return filled(config('services.pixabay.key'));
    }

    /**
     * Illustration/vector dhoondo — bachchon ke content (Kids Yoga, Ukhana) ke
     * liye. Photos jaan-boojh kar bahar rakhte hain taaki asli bachchon ki
     * tasveerein na aayein.
     *
     * @return list<array{id:int, preview:string, full:string, tags:string}>
     *
     * @throws \RuntimeException
     */
    public function searchIllustrations(string $query, int $perPage = 24): array
    {
        return $this->search($query, 'illustration', $perPage);
    }

    /**
     * Asli photo dhoondo — shayari/suvichar cards ke background aur story cover
     * ke liye (barish, sunset, chai, pahaad...).
     *
     * @return list<array{id:int, preview:string, full:string, tags:string}>
     *
     * @throws \RuntimeException
     */
    public function searchPhotos(string $query, int $perPage = 24): array
    {
        // vertical — cards/reels 9:16 hote hain, isliye khadi photo hi kaam ki hai
        return $this->search($query, 'photo', $perPage, 'vertical');
    }

    /**
     * @param  string  $type  photo | illustration | vector | all
     * @return list<array{id:int, preview:string, full:string, tags:string}>
     *
     * @throws \RuntimeException
     */
    public function search(string $query, string $type = 'all', int $perPage = 24, string $orientation = 'all'): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Pixabay key set nahi (.env me PIXABAY_KEY daalein).');
        }

        $res = Http::timeout(30)->get('https://pixabay.com/api/', [
            'key'         => config('services.pixabay.key'),
            'q'           => Str::limit(trim($query), 95, ''),
            'image_type'  => in_array($type, ['photo', 'illustration', 'vector', 'all'], true) ? $type : 'all',
            'orientation' => in_array($orientation, ['all', 'horizontal', 'vertical'], true) ? $orientation : 'all',
            'safesearch'  => 'true',
            'per_page'    => max(3, min(50, $perPage)),
            'order'       => 'popular',
        ]);

        if (! $res->successful()) {
            throw new \RuntimeException('Pixabay ne error diya (HTTP ' . $res->status() . ').');
        }

        $out = [];
        foreach ((array) $res->json('hits', []) as $hit) {
            $full = $hit['largeImageURL'] ?? $hit['webformatURL'] ?? null;
            if (! $full) {
                continue;
            }
            $out[] = [
                'id'      => (int) ($hit['id'] ?? 0),
                'preview' => (string) ($hit['previewURL'] ?? $full),
                'full'    => (string) $full,
                'tags'    => (string) ($hit['tags'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Chuni hui Pixabay image apne server par save karo.
     *
     * Seedha Pixabay URL canvas me daalna kaam nahi karta — cross-origin image
     * canvas ko taint kar deti hai aur `toDataURL()` fail ho jaata hai, yaani card
     * save hi nahi hota. Isliye pehle yahan laate hain.
     *
     * @return string  storage path (jaise "yoga/abcd.jpg")
     *
     * @throws \RuntimeException
     */
    public function download(string $url, string $folder = 'yoga'): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $ok   = false;
        foreach (self::ALLOWED_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                $ok = true;
                break;
            }
        }

        if (! $ok) {
            throw new \RuntimeException('Sirf Pixabay ki image download ho sakti hai.');
        }

        $res = Http::timeout(60)->get($url);

        if (! $res->successful() || ! str_starts_with((string) $res->header('Content-Type'), 'image/')) {
            throw new \RuntimeException('Image download nahi hui. Dusri image try karein.');
        }

        $bytes = $res->body();
        if (strlen($bytes) < 1000) {
            throw new \RuntimeException('Image kharab lagi. Dusri image try karein.');
        }

        $ext  = str_contains((string) $res->header('Content-Type'), 'png') ? 'png' : 'jpg';
        $path = trim($folder, '/') . '/' . Str::uuid() . '.' . $ext;
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }
}
