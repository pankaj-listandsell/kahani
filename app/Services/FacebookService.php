<?php

namespace App\Services;

use App\Models\PartCard;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Facebook Page par card ko PHOTO ya REEL (video) ki tarah post karta hai
 * (Meta Graph API). Reel ka video Instagram wale se hi banta hai (same 720x1280
 * mp4, voice-over/music ke saath) — `InstagramService::mp4PathFor()` reuse.
 *
 * Settings (per-user, Settings > Facebook):
 *  - fb_page_id       : Facebook Page ki numeric ID
 *  - fb_page_token    : Page access token (long-lived behtar)
 *  - fb_caption_suffix: default hashtags
 *
 * Caption Instagram wali (`ig_caption`) reuse hoti hai — same content dono par.
 */
class FacebookService
{
    protected ?int $settingsUserId = null;

    public function __construct(private InstagramService $instagram)
    {
    }

    public function forUser(?int $userId): static
    {
        $this->settingsUserId = $userId;

        return $this;
    }

    protected function setting(string $key, mixed $default = null): mixed
    {
        return $this->settingsUserId !== null
            ? Setting::getFor($this->settingsUserId, $key, $default)
            : Setting::get($key, $default);
    }

    public function pageId(): ?string
    {
        return $this->setting('fb_page_id');
    }

    public function token(): ?string
    {
        return $this->setting('fb_page_token');
    }

    public function isConfigured(): bool
    {
        return (bool) $this->token() && (bool) $this->pageId();
    }

    public function apiBase(): string
    {
        return 'https://graph.facebook.com/v21.0';
    }

    /**
     * Connection test — Page ka naam laao.
     *
     * @return array{ok:bool, message:string}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Page ID ya access token missing.'];
        }

        $res = Http::get($this->apiBase() . '/' . $this->pageId(), [
            'fields'       => 'name',
            'access_token' => $this->token(),
        ]);

        if ($res->successful() && $res->json('name')) {
            return ['ok' => true, 'message' => 'Connected: ' . $res->json('name')];
        }

        return ['ok' => false, 'message' => $res->json('error.message') ?? 'Connection failed.'];
    }

    /* ===================================================================
     |  PHOTO POST
     * =================================================================== */

    public function postPhoto(PartCard $card, ?string $caption = null): string
    {
        $this->forUser($card->part?->story?->user_id);

        if (! $this->isConfigured()) {
            throw new \RuntimeException('Facebook configured nahi. Pehle Page ID + token daalo.');
        }

        $caption ??= $this->buildCaption($card);

        $jpeg = $this->instagram->jpegPathFor($card);
        $imageUrl = $this->publicUrl($jpeg)
            ?? $this->instagram->uploadToTempHost(Storage::disk('public')->path($jpeg));

        if (! $imageUrl) {
            $this->markFailed($card, 'Could not upload image to a public host.');
            throw new \RuntimeException('Could not upload image to a public host.');
        }

        try {
            $res = Http::asForm()->post($this->apiBase() . '/' . $this->pageId() . '/photos', [
                'url'          => $imageUrl,
                'caption'      => $caption,
                'access_token' => $this->token(),
            ]);

            if (! $res->successful() || ! $res->json('id')) {
                Log::error('FB photo post failed', ['card' => $card->id, 'body' => $res->json()]);
                throw new \RuntimeException($res->json('error.message') ?? 'Photo post fail.');
            }

            $postId = $res->json('post_id') ?? $res->json('id');
            $this->markPosted($card, $postId);

            return $postId;
        } catch (\Throwable $e) {
            Log::error('FB photo post error', ['card' => $card->id, 'error' => $e->getMessage()]);
            $this->markFailed($card, $e->getMessage());
            throw $e;
        }
    }

    /* ===================================================================
     |  REEL (VIDEO) POST — Facebook Reels API (resumable, file_url)
     * =================================================================== */

    public function postReel(PartCard $card, ?string $caption = null): string
    {
        $this->forUser($card->part?->story?->user_id);

        if (! $this->isConfigured()) {
            throw new \RuntimeException('Facebook configured nahi. Pehle Page ID + token daalo.');
        }

        @set_time_limit(300);
        $caption ??= $this->buildCaption($card);

        try {
            // 1) Instagram wala hi reel mp4 (720x1280, voice/music ke saath)
            $mp4 = $this->instagram->mp4PathFor($card);
            $videoUrl = $this->publicUrl($mp4)
                ?? $this->instagram->uploadToTempHost(Storage::disk('public')->path($mp4));

            if (! $videoUrl) {
                throw new \RuntimeException('Could not upload video to a public host.');
            }

            // 2) Reel session start → video_id
            $start = Http::asForm()->post($this->apiBase() . '/' . $this->pageId() . '/video_reels', [
                'upload_phase' => 'start',
                'access_token' => $this->token(),
            ]);

            $videoId = $start->json('video_id');
            if (! $videoId) {
                Log::error('FB reel start failed', ['card' => $card->id, 'body' => $start->json()]);
                throw new \RuntimeException($start->json('error.message') ?? 'Reel start fail.');
            }

            // 3) Hosted file se upload (rupload) — file_url header
            $upload = Http::withHeaders([
                'Authorization' => 'OAuth ' . $this->token(),
                'file_url'      => $videoUrl,
            ])->timeout(180)->post('https://rupload.facebook.com/video-upload/v21.0/' . $videoId);

            if (! $upload->successful()) {
                Log::error('FB reel upload failed', ['card' => $card->id, 'body' => $upload->json() ?: $upload->body()]);
                throw new \RuntimeException($upload->json('error.message') ?? 'Reel upload fail.');
            }

            // 4) Finish + publish
            $finish = Http::asForm()->post($this->apiBase() . '/' . $this->pageId() . '/video_reels', [
                'video_id'     => $videoId,
                'upload_phase' => 'finish',
                'video_state'  => 'PUBLISHED',
                'description'  => $caption,
                'access_token' => $this->token(),
            ]);

            if (! $finish->successful() || ! ($finish->json('success') || $finish->json('post_id'))) {
                Log::error('FB reel finish failed', ['card' => $card->id, 'body' => $finish->json()]);
                throw new \RuntimeException($finish->json('error.message') ?? 'Reel publish fail.');
            }

            $this->markPosted($card, $videoId);

            return $videoId;
        } catch (\Throwable $e) {
            Log::error('FB reel post error', ['card' => $card->id, 'error' => $e->getMessage()]);
            $this->markFailed($card, $e->getMessage());
            throw $e;
        }
    }

    /* ===================================================================
     |  Helpers
     * =================================================================== */

    /**
     * Apne domain ka public HTTPS URL (localhost/http par null → temp host).
     */
    protected function publicUrl(string $storageRelativePath): ?string
    {
        $url = Storage::disk('public')->url($storageRelativePath);

        if (! Str::startsWith($url, 'https://') || Str::contains($url, ['localhost', '127.0.0.1'])) {
            return null;
        }

        return $url;
    }

    /**
     * Caption: Instagram wali (ig_caption) reuse, warna default.
     */
    public function buildCaption(PartCard $card): string
    {
        if (filled($card->ig_caption)) {
            return $card->ig_caption;
        }

        $part  = $card->part;
        $story = $part->story;
        $total = $part->cards()->count();

        $lines = [$story->title];
        $lines[] = 'Part ' . $part->sort_order . ' (' . $card->sort_order . '/' . $total . ')';

        if ($suffix = $this->setting('fb_caption_suffix')) {
            $lines[] = '';
            $lines[] = $suffix;
        }

        return implode("\n", $lines);
    }

    protected function markPosted(PartCard $card, string $postId): void
    {
        $card->update([
            'fb_status'    => 'posted',
            'fb_post_id'   => $postId,
            'fb_posted_at' => now(),
            'fb_error'     => null,
        ]);
    }

    protected function markFailed(PartCard $card, string $error): void
    {
        $card->update([
            'fb_status' => 'failed',
            'fb_error'  => Str::limit($error, 480),
        ]);
    }
}
