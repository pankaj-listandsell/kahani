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
    /**
     * Kis user ki IG settings use karni hain. null = abhi ka logged-in user
     * (request context). Har user ka apna alag Instagram account hota hai.
     */
    protected ?int $settingsUserId = null;

    /**
     * Is service instance ko ek particular user ki settings par set karo.
     */
    public function forUser(?int $userId): static
    {
        $this->settingsUserId = $userId;

        return $this;
    }

    /**
     * Setting fetch — agar user set hai to uski, warna current logged-in user ki.
     */
    protected function setting(string $key, mixed $default = null): mixed
    {
        return $this->settingsUserId !== null
            ? Setting::getFor($this->settingsUserId, $key, $default)
            : Setting::get($key, $default);
    }

    public function userId(): ?string
    {
        return $this->setting('ig_user_id');
    }

    public function token(): ?string
    {
        return $this->setting('ig_access_token');
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
     * Instagram content-publishing rate limit (rolling 24-ghante).
     *  - used  = pichhle 24h me kitne post publish hue
     *  - total = account ka cap (aksar 50, kabhi 100)
     * Ye GET quota consume NAHI karta (sirf read).
     *
     * @return array{ok:bool, used:int, total:int, error?:string}
     */
    public function publishingLimit(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'used' => 0, 'total' => 50, 'error' => 'Instagram connected nahi.'];
        }

        try {
            $res = Http::timeout(15)->get(
                $this->apiBase() . '/' . $this->nodeId() . '/content_publishing_limit',
                ['fields' => 'quota_usage,config', 'access_token' => $this->token()],
            );

            $data = $res->json();

            if ($res->failed() || isset($data['error'])) {
                $err = $data['error']['message'] ?? 'Limit fetch fail.';
                Log::warning('IG publishing-limit fail', ['error' => $err]);

                return ['ok' => false, 'used' => 0, 'total' => 50, 'error' => $err];
            }

            $row   = $data['data'][0] ?? [];
            $used  = (int) ($row['quota_usage'] ?? 0);
            $total = (int) ($row['config']['quota_total'] ?? 50);

            return ['ok' => true, 'used' => $used, 'total' => $total > 0 ? $total : 50];
        } catch (\Throwable $e) {
            Log::warning('IG publishing-limit error', ['error' => $e->getMessage()]);

            return ['ok' => false, 'used' => 0, 'total' => 50, 'error' => $e->getMessage()];
        }
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
        // Card apne story-owner ke Instagram account par jaata hai
        $this->forUser($card->part?->story?->user_id);

        if (! $this->isConfigured()) {
            throw new \RuntimeException('Instagram is not configured. Fill the settings first.');
        }

        $caption ??= $this->buildCaption($card);

        // Instagram sirf JPEG leta hai → JPEG banao → public URL do
        // (pehle apna hosting URL, warna temp host).
        $jpeg = $this->jpegPathFor($card);
        $imageUrl = $this->publicMediaUrl($jpeg)
            ?? $this->uploadToTempHost(Storage::disk('public')->path($jpeg));

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
                Log::error('IG image container create failed', ['card' => $card->id, 'image_url' => $imageUrl, 'body' => $create->json()]);
                throw new \RuntimeException($create->json('error.message') ?? 'Media container create failed.');
            }

            $mediaId = $this->publishContainer($create->json('id'));
            $this->markPosted($card, $mediaId);

            // NOTE: upload ke baad media files JAAN-BUJHKAR delete NAHI karte —
            // same card doosre platform (YouTube/Instagram) par bhi post ho sake.

            return $mediaId;
        } catch (\Throwable $e) {
            Log::error('IG image post failed', ['card' => $card->id, 'error' => $e->getMessage()]);
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
        // Card apne story-owner ke Instagram account par jaata hai
        $this->forUser($card->part?->story?->user_id);

        if (! $this->isConfigured()) {
            throw new \RuntimeException('Instagram is not configured. Fill the settings first.');
        }

        @set_time_limit(300);
        $caption ??= $this->buildCaption($card);

        try {
            // 1) Card image → MP4 video
            $mp4 = $this->mp4PathFor($card);

            // 2) Video ka public URL (pehle apna hosting URL, warna temp host)
            $videoUrl = $this->publicMediaUrl($mp4)
                ?? $this->uploadToTempHost(Storage::disk('public')->path($mp4));
            if (! $videoUrl) {
                throw new \RuntimeException('Could not upload video to a public host.');
            }

            // Diagnostics: exact video URL log karo (browser me khol kar test kar sakte hain)
            Log::info('IG reel video_url', ['card' => $card->id, 'url' => $videoUrl]);

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

            // 6) Setting ON ho to card ki IMAGE (text card) Instagram Story me bhi
            //    daal do (video nahi). Story fail ho to reel post fir bhi safe.
            if ($this->alsoStory()) {
                $this->postStoryImage($card);
            }

            // NOTE: upload ke baad media files JAAN-BUJHKAR delete NAHI karte —
            // same card doosre platform (YouTube/Instagram) par bhi post ho sake.

            return $mediaId;
        } catch (\Throwable $e) {
            Log::error('IG reel post failed', ['card' => $card->id, 'error' => $e->getMessage()]);
            $this->markFailed($card, $e->getMessage());
            throw $e;
        }
    }

    /** "Reel ke saath Story me bhi daalo" setting ON hai? */
    protected function alsoStory(): bool
    {
        return (string) $this->setting('ig_also_story', '0') === '1';
    }

    /**
     * Ek card ki IMAGE (text card) ko Instagram Story (24h) ki tarah publish karo
     * — reel video nahi, sirf card ki photo. Reel ke saath bonus — fail ho to
     * sirf log (reel post par asar nahi).
     *
     * @return string|null  story media id, ya null agar fail
     */
    public function postStoryImage(PartCard $card): ?string
    {
        try {
            $jpeg = $this->jpegPathFor($card);
            $imageUrl = $this->publicMediaUrl($jpeg)
                ?? $this->uploadToTempHost(Storage::disk('public')->path($jpeg));

            if (! $imageUrl) {
                throw new \RuntimeException('Story image ka public URL nahi bana.');
            }

            $create = Http::asForm()->post($this->apiBase() . '/' . $this->nodeId() . '/media', [
                'media_type'   => 'STORIES',
                'image_url'    => $imageUrl,
                'access_token' => $this->token(),
            ]);

            if (! $create->successful() || ! $create->json('id')) {
                throw new \RuntimeException($create->json('error.message') ?? 'Story container create fail.');
            }

            // Image container turant ready hota hai — seedha publish
            $storyId = $this->publishContainer($create->json('id'), retry: true);

            Log::info('IG Story (card image) post ho gayi', ['card' => $card->id, 'story_media' => $storyId]);

            return $storyId;
        } catch (\Throwable $e) {
            Log::warning('IG Story post skip (reel safe hai)', ['card' => $card->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Ek card se juda saara local media delete karo:
     *  - asli card image (PNG/JPG)
     *  - Instagram ke liye bana JPEG version
     *  - reel ke liye bana MP4 (reels/ folder me)
     *
     * Story ki cover image yahan JAAN-BUJHKAR nahi hatti — wo poori story ki
     * hai aur baaki cards ke reel-cover me kaam aati hai.
     */
    public function deleteMediaFiles(PartCard $card): void
    {
        $path = $card->image_path;
        if (! $path) {
            return;
        }

        $disk = Storage::disk('public');

        // 1) Asli card image
        $disk->delete($path);

        // 2) Instagram JPEG version (agar alag file hai)
        $jpeg = preg_replace('/\.[a-z0-9]+$/i', '.jpg', $path);
        if ($jpeg && $jpeg !== $path) {
            $disk->delete($jpeg);
        }

        // 3) Reel MP4 (cards/foo.png -> reels/foo.mp4)
        $mp4 = preg_replace('/\.[a-z0-9]+$/i', '.mp4', str_replace('cards/', 'reels/', $path));
        if ($mp4) {
            $disk->delete($mp4);
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
                // Instagram ka poora jawab log karo taaki asli wajah (sub-code) pata chale
                Log::error('IG reel container ERROR', ['container' => $containerId, 'body' => $status->json()]);

                $detail = $status->json('status')
                    ?: ($status->json('error.message') ?: json_encode($status->json()));

                throw new \RuntimeException('Instagram ne video reject ki: ' . $detail);
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

            return $this->publicMediaUrl($jpeg) ?? $this->uploadToTempHost($disk->path($jpeg));
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
     * Saari cached reel MP4 files delete karo (music badalne par naya lag jaaye).
     */
    public function clearReelCache(): void
    {
        $disk = Storage::disk('public');
        foreach ($disk->files('reels') as $file) {
            $disk->delete($file);
        }
    }

    /**
     * Card image se ek 1080x1920 reel MP4 banao (ffmpeg). Cache ho jaata hai.
     */
    public function mp4PathFor(PartCard $card, int $seconds = 6): string
    {
        // Story par audio mode/voice set ho to wahi (warna global) — har card par reset
        $this->withAudioMode($card->part?->story?->tts_mode);
        $this->withVoice($card->part?->story?->tts_voice);

        $jpeg = $this->jpegPathFor($card);
        $mp4Path = preg_replace('/\.[a-z0-9]+$/i', '.mp4', str_replace('cards/', 'reels/', $jpeg));
        $disk = Storage::disk('public');

        // Voice mode ON + card text ho to voice-over reel; warna music/silent.
        $voice = $this->voiceFor($card);

        // Music-mode: cache reuse. Voice-mode: hamesha regenerate (mode/voice/text badal sakta hai).
        if (! $voice && $disk->exists($mp4Path)) {
            return $mp4Path;
        }

        $disk->makeDirectory('reels');

        $ffmpeg = config('services.ffmpeg.path', 'ffmpeg');
        $in  = $disk->path($jpeg);
        $out = $disk->path($mp4Path);

        // Reel frame 720x1280 (9:16). Card ko fit karke black background par
        // center me pad karo. in_range=full:out_range=tv → JPEG ke full-range ko
        // TV/limited range me badlo, warna output yuvj420p ban jaata hai jise
        // Instagram reject karta hai.
        $vfilter = '[0:v]scale=720:1280:force_original_aspect_ratio=decrease:in_range=full:out_range=tv,'
            . 'pad=720:1280:(ow-iw)/2:(oh-ih)/2:color=black,format=yuv420p[v]';

        // Instagram Reels spec: H.264 High profile, closed GOP, yuv420p, AAC 44.1k stereo.
        // ultrafast + stillimage + single thread → sabse kam RAM/CPU (shared host safe).
        $enc = [
            '-c:v', 'libx264', '-preset', 'ultrafast', '-tune', 'stillimage', '-threads', '1',
            '-x264-params', 'ref=1:bframes=0:rc-lookahead=10:sync-lookahead=0',
            '-profile:v', 'high', '-level', '3.1',
            '-pix_fmt', 'yuv420p', '-color_range', 'tv', '-r', '25',
            '-g', '50', '-keyint_min', '50', '-sc_threshold', '0', '-flags', '+cgop',
            '-c:a', 'aac', '-b:a', '128k', '-ar', '44100', '-ac', '2',
            '-movflags', '+faststart',
        ];

        if ($voice) {
            // Reel kam se kam 3 sec (IG chhote reels reject karta hai)
            $dur = $this->fnum(max(3.0, $voice['seconds'] + 0.6));
            $cmd = [$ffmpeg, '-y', '-loop', '1', '-i', $in, '-i', $disk->path($voice['path'])];

            $music     = $this->setting('ig_reel_music');
            $withMusic = $this->ttsMode() === 'voice_music' && $music && $disk->exists($music);

            $filter = $vfilter . ';[1:a]aresample=44100,apad,atrim=duration=' . $dur . ','
                . 'aformat=sample_rates=44100:channel_layouts=stereo[sp]';

            if ($withMusic) {
                $cmd = array_merge($cmd, ['-stream_loop', '-1', '-i', $disk->path($music)]);
                $filter .= ';[2:a]volume=0.15[mus];[sp][mus]amix=inputs=2:duration=first:normalize=0[a]';
            } else {
                $filter .= ';[sp]anull[a]';
            }

            $cmd = array_merge($cmd, [
                '-t', $dur,
                '-filter_complex', $filter,
                '-map', '[v]', '-map', '[a]',
                ...$enc,
                $out,
            ]);
        } else {
            // Music (loop) ya silent — pehle jaisa
            $cmd = [$ffmpeg, '-y', '-loop', '1', '-i', $in];
            $music = $this->setting('ig_reel_music');
            $musicPath = ($music && $disk->exists($music)) ? $disk->path($music) : null;
            if ($musicPath) {
                $cmd = array_merge($cmd, ['-stream_loop', '-1', '-i', $musicPath]);
            } else {
                $cmd = array_merge($cmd, ['-f', 'lavfi', '-i', 'anullsrc=channel_layout=stereo:sample_rate=44100']);
            }

            $cmd = array_merge($cmd, [
                '-t', (string) $seconds,
                '-filter_complex', $vfilter,
                '-map', '[v]', '-map', '1:a',
                ...$enc, '-shortest',
                $out,
            ]);
        }

        $result = Process::timeout(300)->run($cmd);

        if (! $result->successful() || ! $disk->exists($mp4Path)) {
            Log::error('ffmpeg reel conversion failed', ['err' => $result->errorOutput()]);
            throw new \RuntimeException('Video (reel) banane me dikkat — ffmpeg fail. Path sahi hai? ' . Str::limit($result->errorOutput(), 200));
        }

        return $mp4Path;
    }

    /* ===================================================================
     |  VOICE-OVER (Gemini TTS) helpers
     * =================================================================== */

    /** Per-call audio-mode override (story ka tts_mode) — null = global setting. */
    protected ?string $audioModeOverride = null;

    /** Per-call voice override (story ka tts_voice) — null = global setting. */
    protected ?string $voiceOverride = null;

    public function withAudioMode(?string $mode): static
    {
        $this->audioModeOverride = in_array($mode, ['music', 'voice', 'voice_music'], true) ? $mode : null;

        return $this;
    }

    public function withVoice(?string $voice): static
    {
        $this->voiceOverride = filled($voice) ? $voice : null;

        return $this;
    }

    protected function ttsMode(): string
    {
        // Story ka per-collection override pehle, warna user ka global setting
        $m = $this->audioModeOverride ?? (string) $this->setting('tts_audio_mode', 'music');

        return in_array($m, ['music', 'voice', 'voice_music'], true) ? $m : 'music';
    }

    /** Narration ka andaaz — shayari/quote/joke expressive, warna kahani. */
    protected function voiceStyle(PartCard $card): string
    {
        $type = $card->part?->story?->type;

        return in_array($type, ['shayari', 'quote', 'joke'], true) ? $type : 'story';
    }

    /**
     * Card text → voice audio (agar voice mode ON aur TTS configured). Warna null.
     *
     * @return array{path:string,seconds:float}|null
     */
    protected function voiceFor(PartCard $card): ?array
    {
        if ($this->ttsMode() === 'music' || blank($card->text)) {
            return null;
        }

        $tts = new GeminiTtsService();
        if (! $tts->isConfigured()) {
            return null;
        }

        try {
            $voice = $this->voiceOverride ?: ($this->setting('tts_voice') ?: null);
            $lang  = $card->part?->story?->language ?: 'hindi';

            return $tts->speak($card->text, $voice, $this->voiceStyle($card), $lang);
        } catch (\Throwable $e) {
            Log::warning('IG voice-over skip', ['card' => $card->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Float ko locale-safe ffmpeg string me. */
    protected function fnum(float $x): string
    {
        return rtrim(rtrim(sprintf('%.3f', $x), '0'), '.') ?: '0';
    }

    /* ===================================================================
     |  TEMP PUBLIC HOST (tunnel ki jagah)
     * =================================================================== */

    /**
     * Agar app ek asli public host par chal raha hai (APP_URL sahi set hai),
     * to media ko seedhe apne domain se serve karo — Instagram yahi se fetch
     * kar lega. Ye 0x0.st jaise flaky temp hosts se zyada reliable hai.
     *
     * localhost / http (dev) par null return hota hai → temp host fallback.
     */
    protected function publicMediaUrl(string $storageRelativePath): ?string
    {
        $url = Storage::disk('public')->url($storageRelativePath);

        // Instagram sirf public HTTPS URL fetch kar sakta hai
        if (! Str::startsWith($url, 'https://') || Str::contains($url, ['localhost', '127.0.0.1'])) {
            return null;
        }

        return $url;
    }

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
     * Caption: agar card par saved (AI/manual) caption hai to wahi, warna
     * default (story title + part/card info + optional suffix).
     */
    public function buildCaption(PartCard $card): string
    {
        // Saved caption ho to sabse pehle wahi
        if (filled($card->ig_caption)) {
            return $card->ig_caption;
        }

        $part  = $card->part;
        $story = $part->story;
        $total = $part->cards()->count();

        $lines = [$story->title];
        $lines[] = 'Part ' . $part->sort_order . ' (' . $card->sort_order . '/' . $total . ')';

        if ($suffix = $this->setting('ig_caption_suffix')) {
            $lines[] = '';
            $lines[] = $suffix;
        }

        return implode("\n", $lines);
    }
}
