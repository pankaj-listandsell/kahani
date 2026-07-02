<?php

namespace App\Services;

use App\Models\PartCard;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Instagram par card ko IMAGE (post) ya REEL (video) ki tarah upload karta hai.
 *
 * Image/video ko ek TEMPORARY public host (0x0.st / tmpfiles.org / litterbox)
 * par upload karke uska URL Instagram ko diya jaata hai — isliye koi tunnel
 * (ngrok/cloudflared) ki zaroorat NAHI.
 *
 * Settings (Settings > Instagram):
 *  - ig_user_id      : (sirf purane EAA token ke liye) numeric account id
 *  - ig_access_token : access token (IGAA... ya EAA...)
 */
class InstagramService
{
    public function userId(): ?string
    {
        return Setting::get('ig_user_id');
    }

    public function token(): ?string
    {
        return Setting::get('ig_access_token');
    }

    /**
     * Instagram Login API (IGAA token) hai ya purana Facebook Graph API (EAA).
     */
    public function isIgLogin(): bool
    {
        return str_starts_with((string) $this->token(), 'IG');
    }

    public function apiBase(): string
    {
        $host = $this->isIgLogin() ? 'https://graph.instagram.com' : 'https://graph.facebook.com';

        return $host . '/v21.0';
    }

    /**
     * API endpoint ka node: Instagram Login → "me", warna numeric id.
     */
    public function nodeId(): string
    {
        return $this->isIgLogin() ? 'me' : (string) $this->userId();
    }

    public function isConfigured(): bool
    {
        if (! $this->token()) {
            return false;
        }

        return $this->isIgLogin() || (bool) $this->userId();
    }

    /**
     * Connection test — account ka username laao.
     *
     * @return array{ok:bool, message:string}
     */
    public function testConnection(): array
    {
        if (! $this->token()) {
            return ['ok' => false, 'message' => 'Access token missing.'];
        }

        $res = Http::get($this->apiBase() . '/' . $this->nodeId(), [
            'fields'       => 'username',
            'access_token' => $this->token(),
        ]);

        if ($res->successful() && $res->json('username')) {
            return ['ok' => true, 'message' => 'Connected as @' . $res->json('username')];
        }

        return ['ok' => false, 'message' => $res->json('error.message') ?? 'Connection failed.'];
    }

    /* ===================================================================
     |  IMAGE POST
     * =================================================================== */

    /**
     * Card ko ek normal Instagram IMAGE post ki tarah publish karo.
     */
    public function postCard(PartCard $card, ?string $caption = null): string
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Instagram is not configured. Fill the settings first.');
        }

        $caption ??= $this->buildCaption($card);

        // Instagram sirf JPEG leta hai → JPEG banao → temp host par upload karo
        $jpeg = $this->jpegPathFor($card);
        $imageUrl = $this->uploadToTempHost(Storage::disk('public')->path($jpeg));

        if (! $imageUrl) {
            $this->markFailed($card, 'Could not upload image to a public host.');
            throw new \RuntimeException('Could not upload image to a public host.');
        }

        try {
            $create = Http::asForm()->post($this->apiBase() . '/' . $this->nodeId() . '/media', [
                'image_url'    => $imageUrl,
                'caption'      => $caption,
                'access_token' => $this->token(),
            ]);

            if (! $create->successful() || ! $create->json('id')) {
                throw new \RuntimeException($create->json('error.message') ?? 'Media container create failed.');
            }

            $mediaId = $this->publishContainer($create->json('id'));
            $this->markPosted($card, $mediaId);

            return $mediaId;
        } catch (\Throwable $e) {
            $this->markFailed($card, $e->getMessage());
            throw $e;
        }
    }

    /* ===================================================================
     |  REEL (VIDEO) POST
     * =================================================================== */

    /**
     * Card image ko REEL (video) ki tarah publish karo.
     */
    public function postReel(PartCard $card, ?string $caption = null): string
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Instagram is not configured. Fill the settings first.');
        }

        @set_time_limit(300);
        $caption ??= $this->buildCaption($card);

        try {
            // 1) Card image → MP4 video
            $mp4 = $this->mp4PathFor($card);

            // 2) Video ko temp public host par upload
            $videoUrl = $this->uploadToTempHost(Storage::disk('public')->path($mp4));
            if (! $videoUrl) {
                throw new \RuntimeException('Could not upload video to a public host.');
            }

            // 2b) Story ki AI cover image → reel ka cover (thumbnail)
            $params = [
                'media_type'    => 'REELS',
                'video_url'     => $videoUrl,
                'caption'       => $caption,
                'share_to_feed' => 'true',
                'access_token'  => $this->token(),
            ];

            if ($coverUrl = $this->coverUrlFor($card)) {
                $params['cover_url'] = $coverUrl;
            }

            // 3) REELS container banao
            $create = Http::asForm()->post($this->apiBase() . '/' . $this->nodeId() . '/media', $params);

            if (! $create->successful() || ! $create->json('id')) {
                throw new \RuntimeException($create->json('error.message') ?? 'Reel container create failed.');
            }

            $containerId = $create->json('id');

            // 4) Instagram ke processing hone tak wait karo (status poll)
            $this->waitUntilFinished($containerId);

            // 5) Publish
            $mediaId = $this->publishContainer($containerId, retry: true);
            $this->markPosted($card, $mediaId);

            return $mediaId;
        } catch (\Throwable $e) {
            $this->markFailed($card, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Container ko FINISHED hone tak poll karo (reels/video ke liye).
     */
    protected function waitUntilFinished(string $containerId, int $maxAttempts = 30): void
    {
        for ($i = 1; $i <= $maxAttempts; $i++) {
            sleep(3);

            $status = Http::get($this->apiBase() . '/' . $containerId, [
                'fields'       => 'status_code,status',
                'access_token' => $this->token(),
            ]);

            $code = $status->json('status_code');

            if ($code === 'FINISHED') {
                return;
            }
            if ($code === 'ERROR') {
                throw new \RuntimeException($status->json('status') ?? 'Instagram rejected the video.');
            }
        }

        throw new \RuntimeException('Video processing timed out. Try again later.');
    }

    /**
     * media_publish — transient error par retry ke saath.
     */
    protected function publishContainer(string $containerId, bool $retry = false): string
    {
        $attempts = $retry ? 5 : 1;
        $lastError = 'Publish failed.';

        for ($p = 1; $p <= $attempts; $p++) {
            $publish = Http::asForm()->post($this->apiBase() . '/' . $this->nodeId() . '/media_publish', [
                'creation_id'  => $containerId,
                'access_token' => $this->token(),
            ]);

            if ($publish->successful() && $publish->json('id')) {
                return $publish->json('id');
            }

            $err = $publish->json('error');
            $lastError = $err['message'] ?? 'Publish failed.';
            $isTransient = ! empty($err['is_transient']) || (($err['code'] ?? null) === 2);

            if (! $isTransient || $p >= $attempts) {
                break;
            }
            sleep(5 * $p);
        }

        throw new \RuntimeException($lastError);
    }

    /* ===================================================================
     |  IMAGE → JPEG / MP4 helpers
     * =================================================================== */

    /**
     * Card ka JPEG version ka storage path (Instagram sirf JPEG leta hai).
     */
    public function jpegPathFor(PartCard $card): string
    {
        $path = $card->image_path;

        if (preg_match('/\.jpe?g$/i', $path)) {
            return $path;
        }

        $jpegPath = preg_replace('/\.[a-z0-9]+$/i', '.jpg', $path);
        $disk = Storage::disk('public');

        if (! $disk->exists($jpegPath)) {
            $src = @imagecreatefrompng($disk->path($path));
            if (! $src) {
                throw new \RuntimeException('Could not read card image for JPEG conversion.');
            }
            $w = imagesx($src);
            $h = imagesy($src);
            $canvas = imagecreatetruecolor($w, $h);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $w, $h, $white);
            imagecopy($canvas, $src, 0, 0, 0, 0, $w, $h);
            imagejpeg($canvas, $disk->path($jpegPath), 90);
            imagedestroy($src);
            imagedestroy($canvas);
        }

        return $jpegPath;
    }

    /**
     * Is card ki story ki cover image ko ek public URL me badlo — reel ka
     * cover (thumbnail) banane ke liye. Cover na ho to null.
     */
    protected function coverUrlFor(PartCard $card): ?string
    {
        $cover = $card->part?->story?->cover_image;

        if (! $cover) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($cover)) {
            return null;
        }

        try {
            // Instagram reel cover sirf JPEG reliably leta hai — PNG/WebP ko convert karo
            $jpeg = $this->ensureCoverJpeg($cover);

            return $this->uploadToTempHost($disk->path($jpeg));
        } catch (\Throwable $e) {
            Log::warning('Reel cover upload failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Cover image ka JPEG version do. Agar pehle se .jpg/.jpeg hai to wahi,
     * warna GD se convert karke covers/ me cache kar do (ek hi baar banta hai).
     */
    protected function ensureCoverJpeg(string $path): string
    {
        if (preg_match('/\.jpe?g$/i', $path)) {
            return $path;
        }

        $disk = Storage::disk('public');
        $jpegPath = preg_replace('/\.[a-z0-9]+$/i', '.jpg', $path);

        if ($disk->exists($jpegPath)) {
            return $jpegPath;
        }

        // imagecreatefromstring PNG/WebP/GIF/BMP sab auto-detect kar leta hai
        $src = @imagecreatefromstring($disk->get($path));
        if (! $src) {
            throw new \RuntimeException('Could not read cover image for JPEG conversion.');
        }

        $w = imagesx($src);
        $h = imagesy($src);

        // Transparent PNG ko white background par flatten karo
        $canvas = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $w, $h, $white);
        imagecopy($canvas, $src, 0, 0, 0, 0, $w, $h);
        imagejpeg($canvas, $disk->path($jpegPath), 90);
        imagedestroy($src);
        imagedestroy($canvas);

        return $jpegPath;
    }

    /**
     * Card image se ek 1080x1920 reel MP4 banao (ffmpeg). Cache ho jaata hai.
     */
    public function mp4PathFor(PartCard $card, int $seconds = 6): string
    {
        $jpeg = $this->jpegPathFor($card);
        $mp4Path = preg_replace('/\.[a-z0-9]+$/i', '.mp4', str_replace('cards/', 'reels/', $jpeg));
        $disk = Storage::disk('public');

        if ($disk->exists($mp4Path)) {
            return $mp4Path;
        }

        $disk->makeDirectory('reels');

        $ffmpeg = config('services.ffmpeg.path', 'ffmpeg');
        $in  = $disk->path($jpeg);
        $out = $disk->path($mp4Path);

        // Reel frame 1080x1920 (9:16). Black bars ki jagah card ka blur kiya
        // hua bada version background me bharo, aur asli card upar center me.
        // Agar card khud 9:16 hai to wo poora frame bhar dega (blur nahi dikhega).
        $filter = '[0:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,boxblur=24:3[bg];'
            . '[0:v]scale=1080:1920:force_original_aspect_ratio=decrease[fg];'
            . '[bg][fg]overlay=(W-w)/2:(H-h)/2,format=yuv420p[v]';

        $result = Process::timeout(180)->run([
            $ffmpeg, '-y',
            '-loop', '1', '-i', $in,
            '-f', 'lavfi', '-i', 'anullsrc=channel_layout=stereo:sample_rate=44100',
            '-t', (string) $seconds,
            '-filter_complex', $filter,
            '-map', '[v]', '-map', '1:a',
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-r', '30',
            '-c:a', 'aac', '-b:a', '128k', '-shortest', '-movflags', '+faststart',
            $out,
        ]);

        if (! $result->successful() || ! $disk->exists($mp4Path)) {
            Log::error('ffmpeg reel conversion failed', ['err' => $result->errorOutput()]);
            throw new \RuntimeException('Video (reel) banane me dikkat — ffmpeg fail. Path sahi hai? ' . Str::limit($result->errorOutput(), 200));
        }

        return $mp4Path;
    }

    /* ===================================================================
     |  TEMP PUBLIC HOST (tunnel ki jagah)
     * =================================================================== */

    /**
     * Local file ko ek temporary public host par upload karke direct URL do.
     * Kai providers try karta hai jab tak koi chal na jaye.
     */
    public function uploadToTempHost(string $absolutePath): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $contents = file_get_contents($absolutePath);
        $name = basename($absolutePath);

        $providers = [
            '0x0'       => fn () => $this->uploadTo0x0($contents, $name),
            'tmpfiles'  => fn () => $this->uploadToTmpfiles($contents, $name),
            'litterbox' => fn () => $this->uploadToLitterbox($contents, $name),
        ];

        foreach ($providers as $label => $fn) {
            try {
                $url = $fn();
                if ($url) {
                    return $url;
                }
            } catch (\Throwable $e) {
                Log::warning("Temp host {$label} failed", ['error' => $e->getMessage()]);
            }
        }

        return null;
    }

    protected function uploadTo0x0(string $contents, string $name): ?string
    {
        $res = Http::timeout(120)
            ->withHeaders(['User-Agent' => 'KahaniApp/1.0'])
            ->attach('file', $contents, $name)
            ->post('https://0x0.st');

        $url = trim($res->body());

        return ($res->successful() && str_starts_with($url, 'https://')) ? $url : null;
    }

    protected function uploadToTmpfiles(string $contents, string $name): ?string
    {
        $res = Http::timeout(120)
            ->attach('file', $contents, $name)
            ->post('https://tmpfiles.org/api/v1/upload');

        $url = $res->json('data.url');
        if (! $url) {
            return null;
        }

        // page URL → direct download URL
        return str_replace('tmpfiles.org/', 'tmpfiles.org/dl/', $url);
    }

    protected function uploadToLitterbox(string $contents, string $name): ?string
    {
        $res = Http::timeout(120)
            ->attach('fileToUpload', $contents, $name)
            ->post('https://litterbox.catbox.moe/resources/internals/api.php', [
                'reqtype' => 'fileupload',
                'time'    => '1h',
            ]);

        $url = trim($res->body());

        return ($res->successful() && str_starts_with($url, 'https://')) ? $url : null;
    }

    /* ===================================================================
     |  Status helpers + caption
     * =================================================================== */

    protected function markPosted(PartCard $card, string $mediaId): void
    {
        $card->update([
            'ig_status'    => 'posted',
            'ig_media_id'  => $mediaId,
            'ig_posted_at' => now(),
            'ig_error'     => null,
        ]);
    }

    protected function markFailed(PartCard $card, string $error): void
    {
        $card->update([
            'ig_status' => 'failed',
            'ig_error'  => Str::limit($error, 480),
        ]);
    }

    /**
     * Default caption: story title + part/card info + optional suffix (hashtags).
     */
    public function buildCaption(PartCard $card): string
    {
        $part  = $card->part;
        $story = $part->story;
        $total = $part->cards()->count();

        $lines = [$story->title];
        $lines[] = 'Part ' . $part->sort_order . ' (' . $card->sort_order . '/' . $total . ')';

        if ($suffix = Setting::get('ig_caption_suffix')) {
            $lines[] = '';
            $lines[] = $suffix;
        }

        return implode("\n", $lines);
    }
}
