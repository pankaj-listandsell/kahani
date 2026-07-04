<?php

namespace App\Services;

use App\Models\Part;
use App\Models\PartCard;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Card image(s) ko YouTube Short (vertical video) ki tarah upload karta hai.
 *
 * 2 modes:
 *  - single    : ek card = ek Short
 *  - slideshow : poore part ke saare cards jodkar ek Short
 *
 * Auth: Google OAuth (per-user refresh token). Instagram jaisa "token paste"
 * nahi — user apna channel connect karta hai, refresh token settings me save
 * hota hai, aur access token zaroorat par refresh ho jaata hai.
 *
 * Settings (per-user, Settings::putFor):
 *  - yt_refresh_token / yt_access_token / yt_token_expires  (OAuth)
 *  - yt_channel_title   : connected channel ka naam (display)
 *  - yt_post_mode       : single | slideshow
 *  - yt_slide_seconds   : slideshow me har card kitne second dikhe
 *  - yt_privacy         : public | unlisted | private
 *  - yt_music           : (path) background music (optional)
 *  - yt_title_suffix    : description me hashtags (e.g. "#Shorts #hindi")
 *  - yt_auto_enabled / yt_auto_windows : auto-post scheduling
 */
class YoutubeService
{
    protected ?int $settingsUserId = null;

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

    protected function putSetting(string $key, ?string $value): void
    {
        if ($this->settingsUserId !== null) {
            Setting::putFor($this->settingsUserId, $key, $value);
        } else {
            Setting::put($key, $value);
        }
    }

    /* ===================================================================
     |  CONFIG / STATUS
     * =================================================================== */

    /** App-level Google credentials set hain? (.env) */
    public function appConfigured(): bool
    {
        return filled(config('services.youtube.client_id'))
            && filled(config('services.youtube.client_secret'));
    }

    /** Is user ka channel connect hai (refresh token maujood)? */
    public function isConfigured(): bool
    {
        return $this->appConfigured() && filled($this->setting('yt_refresh_token'));
    }

    public function channelTitle(): ?string
    {
        return $this->setting('yt_channel_title');
    }

    protected function redirectUri(): string
    {
        return config('services.youtube.redirect') ?: route('admin.youtube.callback');
    }

    /* ===================================================================
     |  OAUTH
     * =================================================================== */

    /** Google consent screen ka URL (Connect button). */
    public function authUrl(string $state): string
    {
        $params = http_build_query([
            'client_id'              => config('services.youtube.client_id'),
            'redirect_uri'           => $this->redirectUri(),
            'response_type'          => 'code',
            'scope'                  => 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly',
            'access_type'            => 'offline',
            'include_granted_scopes' => 'true',
            'prompt'                 => 'consent', // hamesha refresh token milta rahe
            'state'                  => $state,
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
    }

    /**
     * Callback ka `code` → tokens. Refresh token + access token save karo,
     * phir channel ka naam laakर save karo.
     */
    public function exchangeCode(string $code): void
    {
        $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id'     => config('services.youtube.client_id'),
            'client_secret' => config('services.youtube.client_secret'),
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->redirectUri(),
        ]);

        if (! $res->successful() || ! $res->json('access_token')) {
            Log::error('YT token exchange failed', ['body' => $res->json()]);
            throw new \RuntimeException($res->json('error_description') ?? 'Google se token nahi mila.');
        }

        // Refresh token sirf pehli baar (ya prompt=consent par) aata hai
        if ($refresh = $res->json('refresh_token')) {
            $this->putSetting('yt_refresh_token', $refresh);
        }

        $this->putSetting('yt_access_token', $res->json('access_token'));
        $this->putSetting('yt_token_expires', (string) (time() + (int) $res->json('expires_in', 3600)));

        // Channel naam save karo (dashboard par dikhane ke liye)
        try {
            if ($channel = $this->fetchChannel()) {
                $this->putSetting('yt_channel_title', $channel['title']);
            }
        } catch (\Throwable $e) {
            Log::warning('YT channel fetch failed', ['error' => $e->getMessage()]);
        }
    }

    public function disconnect(): void
    {
        foreach (['yt_refresh_token', 'yt_access_token', 'yt_token_expires', 'yt_channel_title'] as $k) {
            $this->putSetting($k, null);
        }
    }

    /** Valid access token — expire hone par refresh kar leta hai. */
    protected function accessToken(): string
    {
        $token   = $this->setting('yt_access_token');
        $expires = (int) $this->setting('yt_token_expires', 0);

        if ($token && time() < ($expires - 60)) {
            return $token;
        }

        return $this->refreshAccessToken();
    }

    protected function refreshAccessToken(): string
    {
        $refresh = $this->setting('yt_refresh_token');
        if (! $refresh) {
            throw new \RuntimeException('YouTube connected nahi. Pehle apna channel connect karo.');
        }

        $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id'     => config('services.youtube.client_id'),
            'client_secret' => config('services.youtube.client_secret'),
            'refresh_token' => $refresh,
            'grant_type'    => 'refresh_token',
        ]);

        if (! $res->successful() || ! $res->json('access_token')) {
            Log::error('YT token refresh failed', ['body' => $res->json()]);
            // Google ne token revoke/expire kar diya (e.g. testing app 7-din baad)
            throw new \RuntimeException(($res->json('error_description') ?? 'Token refresh fail.') . ' Channel dobara connect karo.');
        }

        $token = $res->json('access_token');
        $this->putSetting('yt_access_token', $token);
        $this->putSetting('yt_token_expires', (string) (time() + (int) $res->json('expires_in', 3600)));

        return $token;
    }

    /** @return array{id:string,title:string}|null */
    public function fetchChannel(): ?array
    {
        $res = Http::withToken($this->accessToken())
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'snippet',
                'mine' => 'true',
            ]);

        $item = $res->json('items.0');
        if (! $item) {
            return null;
        }

        return ['id' => $item['id'], 'title' => $item['snippet']['title'] ?? 'YouTube'];
    }

    /** Connection test — channel naam laao. @return array{ok:bool,message:string} */
    public function testConnection(): array
    {
        if (! $this->appConfigured()) {
            return ['ok' => false, 'message' => 'Google client ID/secret .env me set nahi (setup guide dekho).'];
        }
        if (! $this->setting('yt_refresh_token')) {
            return ['ok' => false, 'message' => 'Channel connect nahi. "Connect YouTube" dabao.'];
        }

        try {
            $channel = $this->fetchChannel();

            return $channel
                ? ['ok' => true, 'message' => 'Connected: ' . $channel['title']]
                : ['ok' => false, 'message' => 'Channel nahi mila.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /* ===================================================================
     |  VIDEO GENERATION (ffmpeg)
     * =================================================================== */

    /** Ek card → 1080x1920 Short mp4. Storage-relative path return karta hai. */
    public function mp4ForCard(PartCard $card, ?int $seconds = null): string
    {
        $seconds = $seconds ?: max(2, (int) $this->setting('yt_card_seconds', 6));

        $disk = Storage::disk('public');
        $src  = $card->image_path;
        if (! $src || ! $disk->exists($src)) {
            throw new \RuntimeException('Card image nahi mili — pehle card banao.');
        }

        $disk->makeDirectory('yt');
        $mp4 = 'yt/card-' . $card->id . '.mp4';

        // Cover intro (agar on hai) + card
        $segments = array_merge(
            $this->coverSegments($card->part),
            [['path' => $disk->path($src), 'dur' => $seconds]],
        );

        $this->runFfmpeg($this->videoCommand($segments, $disk->path($mp4)), $mp4);

        return $mp4;
    }

    /** Poore part ke cards → ek slideshow Short mp4. */
    public function mp4ForPart(Part $part, ?int $secondsPerCard = null): string
    {
        $secondsPerCard = $secondsPerCard ?: max(2, (int) $this->setting('yt_slide_seconds', 4));

        $disk  = Storage::disk('public');
        $part->loadMissing('cards');

        $cardSegments = $part->cards->sortBy('sort_order')
            ->filter(fn (PartCard $c) => $c->image_path && $disk->exists($c->image_path))
            ->map(fn (PartCard $c) => ['path' => $disk->path($c->image_path), 'dur' => $secondsPerCard])
            ->values()
            ->all();

        if (empty($cardSegments)) {
            throw new \RuntimeException('Is part me koi card image nahi mili.');
        }

        $disk->makeDirectory('yt');
        $mp4 = 'yt/part-' . $part->id . '.mp4';

        // Cover intro (agar on hai) + saare cards
        $segments = array_merge($this->coverSegments($part), $cardSegments);

        $this->runFfmpeg($this->videoCommand($segments, $disk->path($mp4)), $mp4);

        return $mp4;
    }

    /**
     * Story ki cover image ko ek intro-segment ki tarah do (agar cover on hai
     * aur file maujood hai). Warna khaali array.
     *
     * @return list<array{path:string,dur:int}>
     */
    protected function coverSegments(?Part $part): array
    {
        if ($this->setting('yt_cover_enabled', '1') !== '1') {
            return [];
        }

        $cover = $part?->story?->cover_image;
        $disk  = Storage::disk('public');

        if (! $cover || ! $disk->exists($cover)) {
            return [];
        }

        $dur = max(1, (int) $this->setting('yt_cover_seconds', 2));

        return [['path' => $disk->path($cover), 'dur' => $dur]];
    }

    /**
     * Segments (har ek {path, dur}) → ek 1080x1920 video command. Har segment
     * apne duration tak dikhta hai, hard-cut se joti jaati hai, upar se music
     * (ya silent) loop hota hai.
     *
     * @param  list<array{path:string,dur:int}>  $segments
     */
    protected function videoCommand(array $segments, string $outAbs): array
    {
        $ffmpeg = config('services.ffmpeg.path', 'ffmpeg');
        $disk   = Storage::disk('public');

        $cmd = [$ffmpeg, '-y'];
        foreach ($segments as $seg) {
            $cmd = array_merge($cmd, ['-loop', '1', '-t', (string) $seg['dur'], '-i', $seg['path']]);
        }

        // Music input (loop) ya silent
        $music     = $this->setting('yt_music');
        $musicPath = ($music && $disk->exists($music)) ? $disk->path($music) : null;
        if ($musicPath) {
            $cmd = array_merge($cmd, ['-stream_loop', '-1', '-i', $musicPath]);
        } else {
            $cmd = array_merge($cmd, ['-f', 'lavfi', '-i', 'anullsrc=channel_layout=stereo:sample_rate=44100']);
        }
        $audioIdx = count($segments); // music = last input

        // Har image ko 1080x1920 me fit + pad + yuv420p, phir sabko concat
        $chain  = '';
        $labels = '';
        foreach ($segments as $i => $_) {
            $chain .= "[{$i}:v]scale=1080:1920:force_original_aspect_ratio=decrease:in_range=full:out_range=tv,"
                . "pad=1080:1920:(ow-iw)/2:(oh-ih)/2:color=black,setsar=1,format=yuv420p[v{$i}];";
            $labels .= "[v{$i}]";
        }
        $n = count($segments);
        $filter = $chain . $labels . "concat=n={$n}:v=1:a=0[v]";

        return array_merge($cmd, [
            '-filter_complex', $filter,
            '-map', '[v]', '-map', "{$audioIdx}:a",
            '-c:v', 'libx264', '-preset', 'veryfast', '-threads', '2',
            '-profile:v', 'high', '-level', '4.0',
            '-pix_fmt', 'yuv420p', '-color_range', 'tv', '-r', '30',
            '-g', '60', '-keyint_min', '60', '-sc_threshold', '0',
            '-c:a', 'aac', '-b:a', '128k', '-ar', '44100', '-ac', '2',
            '-shortest', '-movflags', '+faststart',
            $outAbs,
        ]);
    }

    protected function runFfmpeg(array $cmd, string $expectRelPath): void
    {
        $result = Process::timeout(600)->run($cmd);

        if (! $result->successful() || ! Storage::disk('public')->exists($expectRelPath)) {
            Log::error('YT ffmpeg failed', ['err' => $result->errorOutput()]);
            throw new \RuntimeException('Video banane me dikkat (ffmpeg). ' . Str::limit($result->errorOutput(), 200));
        }
    }

    /** yt/ folder ke saare cached videos delete karo (music badalne par). */
    public function clearVideoCache(): void
    {
        $disk = Storage::disk('public');
        foreach ($disk->files('yt') as $file) {
            $disk->delete($file);
        }
    }

    /* ===================================================================
     |  UPLOAD (YouTube Data API v3 — resumable)
     * =================================================================== */

    /**
     * mp4 file ko YouTube par upload karo, video id return karo.
     */
    public function uploadShort(string $absPath, string $title, string $description, string $privacy = 'public'): string
    {
        if (! is_file($absPath)) {
            throw new \RuntimeException('Video file nahi mili: ' . $absPath);
        }

        $token   = $this->accessToken();
        $size    = filesize($absPath);
        $privacy = in_array($privacy, ['public', 'unlisted', 'private'], true) ? $privacy : 'public';

        $meta = [
            'snippet' => [
                'title'       => Str::limit($title, 95, ''),
                'description' => Str::limit($description, 4900, ''),
                'categoryId'  => '24', // Entertainment
            ],
            'status' => [
                'privacyStatus'           => $privacy,
                'selfDeclaredMadeForKids' => false,
            ],
        ];

        // 1) Resumable session shuru karo → Location header me upload URL
        $init = Http::withToken($token)
            ->withHeaders([
                'X-Upload-Content-Length' => (string) $size,
                'X-Upload-Content-Type'   => 'video/mp4',
            ])
            ->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', $meta);

        if (! $init->successful()) {
            Log::error('YT upload init failed', ['body' => $init->json() ?: $init->body()]);
            throw new \RuntimeException($init->json('error.message') ?? 'YouTube upload shuru nahi hua (quota/permission?).');
        }

        $location = $init->header('Location');
        if (! $location) {
            throw new \RuntimeException('YouTube ne upload URL nahi diya.');
        }

        // 2) Video bytes bhejo
        $put = Http::withToken($token)
            ->timeout(900)
            ->withBody(file_get_contents($absPath), 'video/mp4')
            ->put($location);

        if (! $put->successful() || ! $put->json('id')) {
            Log::error('YT upload put failed', ['body' => $put->json() ?: $put->body()]);
            throw new \RuntimeException($put->json('error.message') ?? 'Video upload complete nahi hua.');
        }

        return $put->json('id');
    }

    /* ===================================================================
     |  HIGH-LEVEL POST
     * =================================================================== */

    /** Ek card ko single-card Short ki tarah upload karo. */
    public function postCard(PartCard $card): string
    {
        $this->forUser($card->part?->story?->user_id);

        if (! $this->isConfigured()) {
            throw new \RuntimeException('YouTube configured nahi. Pehle channel connect karo.');
        }

        @set_time_limit(900);

        try {
            $mp4 = $this->mp4ForCard($card);
            $id  = $this->uploadShort(
                Storage::disk('public')->path($mp4),
                $this->buildTitle($card),
                $this->buildDescription($card),
                (string) $this->setting('yt_privacy', 'public'),
            );

            $this->markCardPosted($card, $id);
            Storage::disk('public')->delete($mp4);

            return $id;
        } catch (\Throwable $e) {
            Log::error('YT card post failed', ['card' => $card->id, 'error' => $e->getMessage()]);
            $this->markCardFailed($card, $e->getMessage());
            throw $e;
        }
    }

    /** Poore part ko ek slideshow Short ki tarah upload karo. */
    public function postPart(Part $part): string
    {
        $this->forUser($part->story?->user_id);

        if (! $this->isConfigured()) {
            throw new \RuntimeException('YouTube configured nahi. Pehle channel connect karo.');
        }

        @set_time_limit(1200);
        $part->loadMissing('cards');

        try {
            $mp4   = $this->mp4ForPart($part);
            $first = $part->cards->sortBy('sort_order')->first();

            $id = $this->uploadShort(
                Storage::disk('public')->path($mp4),
                $this->buildTitle($first, $part),
                $this->buildDescription($first, $part),
                (string) $this->setting('yt_privacy', 'public'),
            );

            // Ek part = ek Short. Saare cards ko posted maan lo (dobara post na hon).
            foreach ($part->cards as $c) {
                $this->markCardPosted($c, $id);
            }
            Storage::disk('public')->delete($mp4);

            return $id;
        } catch (\Throwable $e) {
            Log::error('YT part post failed', ['part' => $part->id, 'error' => $e->getMessage()]);
            foreach ($part->cards as $c) {
                $this->markCardFailed($c, $e->getMessage());
            }
            throw $e;
        }
    }

    /* ===================================================================
     |  Title / description + status
     * =================================================================== */

    public function buildTitle(?PartCard $card, ?Part $part = null): string
    {
        $part ??= $card?->part;
        $story = $part?->story;

        // Priority: YouTube caption ki pehli line → IG caption ki pehli line → story title.
        // Title me hashtag line nahi chahiye, isliye pehli non-hashtag line lo.
        $source = filled($card?->yt_caption) ? $card->yt_caption
            : (filled($card?->ig_caption) ? $card->ig_caption : ($story?->title ?? 'Kahani'));

        $firstLine = Str::of($source)->explode("\n")
            ->map(fn ($l) => trim($l))
            ->first(fn ($l) => $l !== '' && ! Str::startsWith($l, '#'), $story?->title ?? 'Kahani');

        return Str::limit(trim((string) $firstLine), 90, '');
    }

    public function buildDescription(?PartCard $card, ?Part $part = null): string
    {
        $part ??= $card?->part;
        $story = $part?->story;

        // YouTube caption set hai to wahi as-is (usme hashtags pehle se hote hain).
        if (filled($card?->yt_caption)) {
            return $this->ensureShorts(trim($card->yt_caption));
        }

        $lines = [];
        if (filled($card?->ig_caption)) {
            $lines[] = $card->ig_caption;
        } else {
            $lines[] = $story?->title;
            if ($part) {
                $lines[] = 'Part ' . $part->sort_order;
            }
        }

        // Hashtags — #Shorts hona zaroori hai taaki YouTube ise Short maane
        $tags = trim((string) $this->setting('yt_title_suffix')) ?: '#Shorts #hindi #kahani #story';
        $lines[] = '';
        $lines[] = $tags;

        return $this->ensureShorts(trim(implode("\n", array_filter($lines, fn ($l) => $l !== null))));
    }

    /** #Shorts na ho to add kar do (YouTube ise Short maane iske liye zaroori). */
    protected function ensureShorts(string $description): string
    {
        if (! Str::contains(Str::lower($description), '#shorts')) {
            $description = rtrim($description) . "\n#Shorts";
        }

        return $description;
    }

    protected function markCardPosted(PartCard $card, string $videoId): void
    {
        $card->update([
            'yt_status'    => 'posted',
            'yt_video_id'  => $videoId,
            'yt_posted_at' => now(),
            'yt_error'     => null,
        ]);
    }

    protected function markCardFailed(PartCard $card, string $error): void
    {
        $card->update([
            'yt_status' => 'failed',
            'yt_error'  => Str::limit($error, 480),
        ]);
    }
}
