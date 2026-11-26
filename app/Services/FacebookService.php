<?php

namespace App\Services;

use App\Exceptions\CardBusyException;
use App\Exceptions\FacebookRateLimitException;
use App\Models\Part;
use App\Models\PartCard;
use App\Models\Setting;
use App\Models\Story;
use Illuminate\Support\Carbon;
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
        // Reel mp4 InstagramService banata hai — usko bhi same user ki
        // voice/music settings chahiye (cron me koi logged-in user nahi hota).
        $this->instagram->forUser($userId);

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

        // Koi aur (button/cron) isi card ko abhi post kar raha ho to yahin ruk jao
        $previous = $card->claimFor('fb');

        try {
            $caption ??= $this->buildCaption($card);

            $jpeg = $this->instagram->jpegPathFor($card);
            $imageUrl = $this->publicUrl($jpeg)
                ?? $this->instagram->uploadToTempHost(Storage::disk('public')->path($jpeg));

            if (! $imageUrl) {
                throw new \RuntimeException('Could not upload image to a public host.');
            }

            $res = Http::asForm()->post($this->apiBase() . '/' . $this->pageId() . '/photos', [
                'url'          => $imageUrl,
                'caption'      => $caption,
                'access_token' => $this->token(),
            ]);

            if (! $res->successful() || ! $res->json('id')) {
                Log::error('FB photo post failed', ['card' => $card->id, 'body' => $res->json()]);
                throw $this->graphException($res->json('error'), 'Photo post fail.');
            }

            $postId = $res->json('post_id') ?? $res->json('id');
            $this->markPosted($card, $postId);

            return $postId;
        } catch (\Throwable $e) {
            Log::error('FB photo post error', ['card' => $card->id, 'error' => $e->getMessage()]);
            // Rate-limit (FB block) par card ko pending rehne do — baad me retry ho.
            if ($e instanceof FacebookRateLimitException) {
                $card->releaseClaim('fb', $previous);
            } else {
                $this->markFailed($card, $e->getMessage());
            }
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

        // Koi aur (button/cron) isi card ko abhi post kar raha ho to yahin ruk jao
        $previous = $card->claimFor('fb');

        try {
            $caption ??= $this->buildCaption($card);

            // 1) Instagram wala hi reel mp4 (720x1280, voice/music ke saath)
            $mp4 = $this->instagram->mp4PathFor($card);
            $absolute = Storage::disk('public')->path($mp4);

            $videoId = $this->publishReel($mp4, $caption, ['card' => $card->id]);

            $this->markPosted($card, $videoId);

            return $videoId;
        } catch (\Throwable $e) {
            Log::error('FB reel post error', ['card' => $card->id, 'error' => $e->getMessage()]);
            // Rate-limit (FB block) par card ko pending rehne do — baad me retry ho.
            if ($e instanceof FacebookRateLimitException) {
                $card->releaseClaim('fb', $previous);
            } else {
                $this->markFailed($card, $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * mp4 (storage/public relative) → Facebook Reel. Return: video_id.
     * Start → bytes upload (fallback: hosted URL) → finish + publish.
     */
    protected function publishReel(string $mp4, string $caption, array $logCtx = []): string
    {
        $absolute = Storage::disk('public')->path($mp4);

        // 1) Reel session start → video_id
        $start = Http::asForm()->post($this->apiBase() . '/' . $this->pageId() . '/video_reels', [
            'upload_phase' => 'start',
            'access_token' => $this->token(),
        ]);

        $videoId = $start->json('video_id');
        if (! $videoId) {
            Log::error('FB reel start failed', $logCtx + ['body' => $start->json()]);
            throw $this->graphException($start->json('error'), 'Reel start fail.');
        }

        // 2) Upload — pehle file ke bytes seedhe bhejo. Ye `file_url` se
        //    zyada reliable hai: shared hosting ka WAF Facebook ke rupload
        //    fetcher ko 403 de sakta hai, aur localhost par to koi public
        //    URL hi nahi hota. Bytes bhejne me ye dono problem nahi.
        $upload = $this->uploadReelBytes($videoId, $absolute);

        // Bytes wala raasta na chale to purana hosted-URL tareeka try karo.
        if (! $upload->successful()) {
            Log::warning('FB reel byte-upload fail — file_url fallback', $logCtx + [
                'body' => $upload->json() ?: $upload->body(),
            ]);

            $videoUrl = $this->publicUrl($mp4) ?? $this->instagram->uploadToTempHost($absolute);

            if (! $videoUrl) {
                throw new \RuntimeException('Could not upload video to a public host.');
            }

            Log::info('FB reel video_url', $logCtx + ['url' => $videoUrl]);

            $upload = Http::withHeaders([
                'Authorization' => 'OAuth ' . $this->token(),
                'file_url'      => $videoUrl,
            ])->timeout(180)->post('https://rupload.facebook.com/video-upload/v21.0/' . $videoId);
        }

        if (! $upload->successful()) {
            Log::error('FB reel upload failed', $logCtx + ['body' => $upload->json() ?: $upload->body()]);
            // rupload `error` ki jagah `debug_info` deta hai — asli wajah
            // wahi hoti hai (jaise "Unable to fetch media from URL … 403").
            $detail = $upload->json('debug_info.message') ?? $upload->json('error.message');
            throw $this->graphException(
                $upload->json('error'),
                'Reel upload fail.' . ($detail ? ' — ' . $detail : '')
            );
        }

        // 3) Finish + publish
        $finish = Http::asForm()->post($this->apiBase() . '/' . $this->pageId() . '/video_reels', [
            'video_id'     => $videoId,
            'upload_phase' => 'finish',
            'video_state'  => 'PUBLISHED',
            'description'  => $caption,
            'access_token' => $this->token(),
        ]);

        if (! $finish->successful() || ! ($finish->json('success') || $finish->json('post_id'))) {
            Log::error('FB reel finish failed', $logCtx + ['body' => $finish->json()]);
            throw $this->graphException($finish->json('error'), 'Reel publish fail.');
        }

        return $videoId;
    }

    /* ===================================================================
     |  🔯 RASHIFAL — Facebook ke liye khaas posts
     * =================================================================== */

    /**
     * Aaj ke 12 rashi cards → EK post me saari photos (album). 12 alag posts se
     * page spam jaisa lagta hai aur reach girti hai. Saare cards ek saath claim
     * hote hain (aadha album kabhi na bane); safal = sab 'posted'.
     *
     * @param  iterable<PartCard>  $cards
     */
    public function postRashifalAlbum(Part $part, iterable $cards): string
    {
        $part->loadMissing('story');
        $story = $part->story;
        $this->forUser($story?->user_id);

        if (! $this->isConfigured()) {
            throw new \RuntimeException('Facebook configured nahi. Pehle Page ID + token daalo.');
        }

        $cards = collect($cards)->sortBy('sort_order')->values();
        if ($cards->isEmpty()) {
            throw new \RuntimeException('Album ke liye koi card nahi.');
        }

        @set_time_limit(600);

        // Sab claim — koi ek busy ho to jo claim hue unhe wapas chhod do
        $claimed = [];
        try {
            foreach ($cards as $card) {
                $claimed[] = [$card, $card->claimFor('fb')];
            }
        } catch (\Throwable $e) {
            foreach ($claimed as [$c, $prev]) {
                $c->releaseClaim('fb', $prev);
            }
            throw $e;
        }

        try {
            // 1) Har photo unpublished upload (bytes — public URL ki zaroorat nahi)
            $mediaIds = [];
            foreach ($cards as $card) {
                $jpeg = Storage::disk('public')->path($this->instagram->jpegPathFor($card));
                $res = Http::timeout(120)
                    ->attach('source', file_get_contents($jpeg), 'rashi-' . $card->sort_order . '.jpg')
                    ->post($this->apiBase() . '/' . $this->pageId() . '/photos', [
                        'published'    => 'false',
                        'access_token' => $this->token(),
                    ]);
                if (! $res->successful() || ! $res->json('id')) {
                    Log::error('FB album photo upload failed', ['card' => $card->id, 'body' => $res->json()]);
                    throw $this->graphException($res->json('error'), 'Album photo upload fail.');
                }
                $mediaIds[] = $res->json('id');
            }

            // 2) Ek post, saari photos ke saath
            $res = Http::asForm()->timeout(120)->post($this->apiBase() . '/' . $this->pageId() . '/feed', [
                'message'        => $this->rashifalAlbumCaption($story, $cards),
                'attached_media' => array_map(fn ($id) => json_encode(['media_fbid' => $id]), $mediaIds),
                'access_token'   => $this->token(),
            ]);
            if (! $res->successful() || ! $res->json('id')) {
                Log::error('FB album post failed', ['part' => $part->id, 'body' => $res->json()]);
                throw $this->graphException($res->json('error'), 'Album post fail.');
            }

            $postId = $res->json('id');
            foreach ($cards as $card) {
                $this->markPosted($card, $postId);
            }

            return $postId;
        } catch (\Throwable $e) {
            Log::error('FB rashifal album error', ['part' => $part->id, 'error' => $e->getMessage()]);
            foreach ($claimed as [$c, $prev]) {
                $e instanceof FacebookRateLimitException
                    ? $c->releaseClaim('fb', $prev)
                    : $this->markFailed($c, $e->getMessage());
            }
            throw $e;
        }
    }

    /** Album caption: tareekh + har rashi ki ek line + "apni rashi comment karo" + hashtags. */
    public function rashifalAlbumCaption(?Story $story, iterable $cards): string
    {
        $lang = $story?->language ?: 'gujarati';
        $date = $story?->category ? Carbon::parse($story->category)->format('d-m-Y') : now()->format('d-m-Y');
        [$head, $ask] = match ($lang) {
            'hindi'    => ['🔯 आज का राशिफल', '👇 अपनी राशि कमेंट में लिखें और परिवार के साथ शेयर करें 🙏'],
            'hinglish' => ['🔯 Aaj ka Rashifal', '👇 Apni rashi comment me likho aur family ke saath share karo 🙏'],
            default    => ['🔯 આજનું રાશિફળ', '👇 તમારી રાશિ કૉમેન્ટમાં લખો અને પરિવાર સાથે શેર કરો 🙏'],
        };

        $lines = [];
        $tags = '';
        foreach ($cards as $card) {
            $rows = collect(preg_split('/\R/u', (string) $card->ig_caption))->map(fn ($l) => trim($l))->filter();
            $text = $rows->first(fn ($l) => ! Str::startsWith($l, '#'));
            if ($text) {
                $lines[] = Str::limit($text, 160);
            }
            $tags = $tags ?: (string) $rows->first(fn ($l) => Str::startsWith($l, '#'));
        }

        $suffix = trim((string) $this->setting('fb_caption_suffix'));

        return trim(implode("\n\n", array_filter([
            "{$head} — {$date}",
            implode("\n", $lines),
            $ask,
            $suffix !== '' ? $suffix : $tags,
        ])));
    }

    /**
     * Aaj ka Rashifal Short (3 rashi) → Facebook Reel. Wahi video jo YouTube ke liye
     * bana (yt/part-{id}.mp4) — maujood ho to dobara nahi banta. Status pehle card par.
     */
    public function postShortPart(Part $part): string
    {
        $part->loadMissing(['story', 'cards' => fn ($q) => $q->orderBy('sort_order')]);
        $cards = $part->cards->sortBy('sort_order')->values();
        $first = $cards->first();
        if (! $first) {
            throw new \RuntimeException('Is Short me koi frame nahi hai.');
        }

        $this->forUser($part->story?->user_id);
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Facebook configured nahi. Pehle Page ID + token daalo.');
        }

        @set_time_limit(900);
        $previous = $first->claimFor('fb');

        try {
            $mp4 = 'yt/part-' . $part->id . '.mp4';
            if (! Storage::disk('public')->exists($mp4)) {
                $mp4 = $this->youtube($part)->mp4ForPart($part);
            }

            $videoId = $this->publishReel($mp4, $this->videoCaption($first, $part), ['part' => $part->id]);
            foreach ($cards as $card) {
                $this->markPosted($card, $videoId);
            }

            return $videoId;
        } catch (\Throwable $e) {
            Log::error('FB rashifal reel error', ['part' => $part->id, 'error' => $e->getMessage()]);
            $e instanceof FacebookRateLimitException
                ? $first->releaseClaim('fb', $previous)
                : $this->markFailed($first, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Saptahik Rashifal (16:9, 12 rashi) → Facebook Page video. YouTube wala bana hua
     * video (yt/week-{id}.mp4) reuse — dobara banane me Gemini voice ki limit lagti hai.
     * Chapters ke time description me (Facebook par text ki tarah dikhte hain) + thumbnail.
     */
    public function postWeekVideo(Part $part): string
    {
        $part->loadMissing(['story', 'cards' => fn ($q) => $q->orderBy('sort_order')]);
        $cards = $part->cards->sortBy('sort_order')->values();
        $first = $cards->first();
        if (! $first) {
            throw new \RuntimeException('Is video me koi frame nahi hai.');
        }

        $this->forUser($part->story?->user_id);
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Facebook configured nahi. Pehle Page ID + token daalo.');
        }

        @set_time_limit(1800);
        $previous = $first->claimFor('fb');

        try {
            $disk = Storage::disk('public');
            $yt = $this->youtube($part);
            $mp4 = 'yt/week-' . $part->id . '.mp4';
            $metaFile = 'yt/week-' . $part->id . '.json';
            $meta = $disk->exists($metaFile) ? (json_decode((string) $disk->get($metaFile), true) ?: []) : [];
            if ($disk->exists($mp4) && ! empty($meta['durations'])) {
                $durations = $meta['durations'];
            } else {
                $built = $yt->mp4ForWeekVideo($part);
                [$mp4, $durations] = [$built['mp4'], $built['durations']];
            }

            $caption = trim((string) $first->yt_caption);
            $chapters = $yt->weekChapters($part, $durations);
            $description = str_contains($caption, '[CHAPTERS]')
                ? str_replace('[CHAPTERS]', $chapters, $caption)
                : $caption . ($chapters !== '' ? "\n\n" . $chapters : '');
            $description = $this->forFacebook(preg_replace("/\n{3,}/", "\n\n", $description));

            $req = Http::timeout(1200)
                ->attach('source', fopen($disk->path($mp4), 'r'), 'saptahik-rashifal.mp4');
            if ($part->image_path && $disk->exists($part->image_path)) {
                $req = $req->attach('thumb', file_get_contents($disk->path($part->image_path)), 'thumb.jpg');
            }
            $res = $req->post('https://graph-video.facebook.com/v21.0/' . $this->pageId() . '/videos', [
                'title'        => $yt->buildTitle($first, $part),
                'description'  => $description,
                'access_token' => $this->token(),
            ]);

            if (! $res->successful() || ! $res->json('id')) {
                Log::error('FB week video upload failed', ['part' => $part->id, 'body' => $res->json()]);
                throw $this->graphException($res->json('error'), 'Video upload fail.');
            }

            $videoId = $res->json('id');
            foreach ($cards as $card) {
                $this->markPosted($card, $videoId);
            }

            return $videoId;
        } catch (\Throwable $e) {
            Log::error('FB week video error', ['part' => $part->id, 'error' => $e->getMessage()]);
            // Rate-limit / doosri jagah video ban raha → card pending rahe, baad me retry
            $e instanceof FacebookRateLimitException || $e instanceof CardBusyException
                ? $first->releaseClaim('fb', $previous)
                : $this->markFailed($first, $e->getMessage());
            throw $e;
        }
    }

    /** Short ka YouTube title+description → Facebook caption (#Shorts hata ke, suffix ant me). */
    protected function videoCaption(PartCard $first, Part $part): string
    {
        $text = trim((string) $first->yt_caption) ?: $this->youtube($part)->buildTitle($first, $part);

        return $this->forFacebook($text);
    }

    /** YouTube wala text Facebook ke hisaab se: #Shorts nahi, "Subscribe" → "Follow", suffix ant me. */
    public function forFacebook(string $text): string
    {
        $text = preg_replace('/[ \t]*#shorts\b/iu', '', $text);
        $text = str_ireplace(['subscribe', 'સબ્સ્ક્રાઇબ', 'सब्सक्राइब'], ['Follow', 'ફોલો', 'फॉलो'], $text);
        $suffix = trim((string) $this->setting('fb_caption_suffix'));

        return trim($text . ($suffix !== '' && ! str_contains($text, $suffix) ? "\n\n" . $suffix : ''));
    }

    /** Usi user ki YouTube settings (music/voice/outro) wala video builder. */
    protected function youtube(Part $part): YoutubeService
    {
        return app(YoutubeService::class)->forUser($part->story?->user_id);
    }

    /* ===================================================================
     |  Helpers
     * =================================================================== */

    /**
     * Reel mp4 ke raw bytes rupload par bhejo (resumable upload, single chunk).
     * Koi public URL ki zaroorat nahi — isliye localhost par bhi chalta hai.
     */
    protected function uploadReelBytes(string $videoId, string $absolutePath): \Illuminate\Http\Client\Response
    {
        $bytes = @file_get_contents($absolutePath);

        if ($bytes === false) {
            throw new \RuntimeException('Reel video file padhi nahi ja saki: ' . basename($absolutePath));
        }

        return Http::withHeaders([
            'Authorization' => 'OAuth ' . $this->token(),
            'offset'        => '0',
            'file_size'     => (string) strlen($bytes),
        ])
            ->withBody($bytes, 'application/octet-stream')
            ->timeout(300)
            ->post('https://rupload.facebook.com/video-upload/v21.0/' . $videoId);
    }

    /**
     * Graph API error → sahi exception. Rate-limit / spam-block ("We limit how
     * often you can post…") ko FacebookRateLimitException me convert karta hai
     * taaki caller back-off kare (card ko permanently fail na kare).
     */
    protected function graphException(mixed $error, string $fallback): \RuntimeException
    {
        $error   = is_array($error) ? $error : [];
        $code    = (int) ($error['code'] ?? 0);
        $subcode = (int) ($error['error_subcode'] ?? 0);
        $message = (string) ($error['message'] ?? '') ?: $fallback;

        // FB rate-limit / temporary-block codes:
        //  4=app limit, 17=user limit, 32=page limit, 341=app temp block,
        //  368=policy block ("We limit how often…"), 613=API rate limit.
        $rateCodes    = [4, 17, 32, 341, 368, 613];
        $rateSubcodes = [1390008, 1390019, 1404006, 2207051];

        $looksRate = in_array($code, $rateCodes, true)
            || in_array($subcode, $rateSubcodes, true)
            || Str::contains(Str::lower($message), [
                'limit how often', 'rate limit', 'temporarily blocked',
                'too many', 'try again later',
            ]);

        return $looksRate
            ? new FacebookRateLimitException($message)
            : new \RuntimeException($message);
    }

    /**
     * Apne domain ka public HTTPS URL (localhost/http par null → temp host).
     *
     * Server par URL ka Content-Type bhi verify hota hai: `storage:link`
     * missing ho to /storage/... par Laravel ka 404 HTML page milta hai aur
     * Meta "Content type … text/html is not supported" de deta hai. Aise
     * case me null return karke temp host par fallback ho jaata hai.
     */
    protected function publicUrl(string $storageRelativePath): ?string
    {
        $url = Storage::disk('public')->url($storageRelativePath);

        if (! Str::startsWith($url, 'https://') || Str::contains($url, ['localhost', '127.0.0.1'])) {
            return null;
        }

        if (! $this->instagram->servesMedia($url)) {
            Log::warning('FB: apna public URL media nahi de raha (storage:link missing?)', ['url' => $url]);

            return null;
        }

        return $url;
    }

    /**
     * Caption: Instagram wali (ig_caption) reuse, warna default.
     */
    public function buildCaption(PartCard $card): string
    {
        // 1. Agar Facebook Default Caption Suffix dala hua hai, to caption me hamesha vahi jana chahiye
        $suffix = $this->setting('fb_caption_suffix');
        if (filled($suffix)) {
            return $suffix;
        }

        // 2. Agar suffix nahi hai, to Instagram ki custom/AI caption use karein
        $igCaption = $this->instagram->buildCaption($card);
        if (filled($igCaption)) {
            return $igCaption;
        }

        // Fallback agar dono na milein
        $part  = $card->part;
        $story = $part->story;
        $total = $part->cards()->count();

        $lines = [$story->title];
        $lines[] = 'Part ' . $part->sort_order . ' (' . $card->sort_order . '/' . $total . ')';

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
