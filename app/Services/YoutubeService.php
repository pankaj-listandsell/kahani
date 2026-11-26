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
 * Ek card ko YouTube Short (vertical video) ki tarah upload karta hai —
 * har card = ek alag Short.
 *
 * Auth: Google OAuth (per-user refresh token). Instagram jaisa "token paste"
 * nahi — user apna channel connect karta hai, refresh token settings me save
 * hota hai, aur access token zaroorat par refresh ho jaata hai.
 *
 * Settings (per-user, Settings::putFor):
 *  - yt_refresh_token / yt_access_token / yt_token_expires  (OAuth)
 *  - yt_channel_title   : connected channel ka naam (display)
 *  - yt_privacy         : public | unlisted | private
 *  - yt_music           : (path) background music (optional)
 *  - yt_title_suffix    : description me hashtags (e.g. "#Shorts #hindi")
 *  - yt_auto_enabled / yt_auto_windows : auto-post scheduling
 */
class YoutubeService
{
    protected ?int $settingsUserId = null;

    /** Per-call audio-mode override (story ka tts_mode) — null = global setting. */
    protected ?string $audioModeOverride = null;

    /** Per-call voice override (story ka tts_voice) — null = global setting. */
    protected ?string $voiceOverride = null;

    public function forUser(?int $userId): static
    {
        $this->settingsUserId = $userId;

        return $this;
    }

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
        return filled($this->clientId()) && filled($this->clientSecret());
    }

    /** Is user ka apna Google project set hai? (warna server ka .env wala) */
    public function usesOwnApp(): bool
    {
        return filled($this->setting('yt_client_id')) && filled($this->ownSecret());
    }

    /** Google OAuth Client ID — user ka apna (Settings) ho to wahi, warna .env. Har channel ka alag project = alag quota. */
    public function clientId(): ?string
    {
        return $this->usesOwnApp() ? trim((string) $this->setting('yt_client_id')) : config('services.youtube.client_id');
    }

    public function clientSecret(): ?string
    {
        return $this->usesOwnApp() ? $this->ownSecret() : config('services.youtube.client_secret');
    }

    /** Secret DB me encrypt hokar rehta hai. */
    protected function ownSecret(): ?string
    {
        $enc = $this->setting('yt_client_secret');
        if (blank($enc)) {
            return null;
        }
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($enc);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** User ka apna Google project save karo (dono khaali = server ka default). Project badla to purana connection hatao. */
    public function saveOwnApp(?string $clientId, ?string $secret): bool
    {
        $oldId = $this->clientId();
        $clientId = trim((string) $clientId);
        $secret = trim((string) $secret);
        if ($clientId === '') {
            $this->putSetting('yt_client_id', null);
            $this->putSetting('yt_client_secret', null);
        } else {
            $this->putSetting('yt_client_id', $clientId);
            if ($secret !== '') { // khaali = purana secret rehne do
                $this->putSetting('yt_client_secret', \Illuminate\Support\Facades\Crypt::encryptString($secret));
            }
        }
        $changed = $oldId !== $this->clientId();
        if ($changed) {
            $this->disconnect(); // purane project ka token naye project par nahi chalta
        }

        return $changed;
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
            'client_id'              => $this->clientId(),
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
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
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
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'refresh_token' => $refresh,
            'grant_type'    => 'refresh_token',
        ]);

        if (! $res->successful() || ! $res->json('access_token')) {
            Log::error('YT token refresh failed', ['body' => $res->json()]);
            // Google ne token revoke/expire kar diya (e.g. testing app 7-din baad)
            // "token refresh" shabd zaroori — auto-post ise account error maanta hai (card 'failed' nahi hota)
            throw new \RuntimeException('YouTube token refresh fail (' . ($res->json('error') ?? 'error') . ': '
                . ($res->json('error_description') ?? '?') . '). Channel dobara connect karo.');
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

    /**
     * Channel ke subscribers. YouTube 1000+ par ginti round karke deta hai (jaise 12,345 → 12,300),
     * aur "subscribers chhupao" ON ho to null.
     */
    public function subscriberCount(): ?int
    {
        $res = Http::withToken($this->accessToken())->timeout(20)
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'statistics',
                'mine' => 'true',
            ]);

        $s = $res->json('items.0.statistics');
        if (! $s || ! empty($s['hiddenSubscriberCount'])) {
            return null;
        }

        return (int) ($s['subscriberCount'] ?? 0);
    }

    /**
     * Videos ke views/likes/comments (YouTube Data API, 50 id ek call me).
     *
     * @param  list<string>  $videoIds
     * @return array<string, array{views:int,likes:?int,comments:?int}>
     */
    public function videoStats(array $videoIds): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($videoIds)), 50) as $chunk) {
            $res = Http::withToken($this->accessToken())->timeout(20)
                ->get('https://www.googleapis.com/youtube/v3/videos', [
                    'part' => 'statistics',
                    'id'   => implode(',', $chunk),
                ]);

            foreach ($res->json('items', []) as $item) {
                $s = $item['statistics'] ?? [];
                $out[$item['id']] = [
                    'views'    => (int) ($s['viewCount'] ?? 0),
                    'likes'    => isset($s['likeCount']) ? (int) $s['likeCount'] : null,
                    'comments' => isset($s['commentCount']) ? (int) $s['commentCount'] : null,
                ];
            }
        }

        return $out;
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

    /**
     * Check YouTube token health for dashboard badges.
     *
     * @return array{status: 'active'|'expired'|'missing', message: string, channel: ?string}
     */
    public function checkTokenHealth(): array
    {
        if (! $this->setting('yt_refresh_token')) {
            return [
                'status'  => 'missing',
                'message' => 'YouTube not connected',
                'channel' => null,
            ];
        }

        try {
            $channel = $this->fetchChannel();

            return $channel
                ? ['status' => 'active', 'message' => 'Connected: ' . $channel['title'], 'channel' => $channel['title']]
                : ['status' => 'expired', 'message' => 'Channel access expired. Reconnect YouTube.', 'channel' => null];
        } catch (\Throwable $e) {
            return ['status' => 'expired', 'message' => $e->getMessage(), 'channel' => null];
        }
    }

    /* ===================================================================
     |  VIDEO GENERATION (ffmpeg)
     * =================================================================== */

    /** Ek card → 1080x1920 Short mp4. Storage-relative path return karta hai. */
    public function mp4ForCard(PartCard $card, ?int $seconds = null): string
    {
        // Quiz card jiske paas answer image hai → question + countdown + answer
        // wali ek hi reel (viral quiz format). Baaki sab pehle jaisa.
        $quiz = app(QuizReelService::class);
        if ($quiz->supports($card)) {
            return $quiz->mp4For($card);
        }

        // Story par audio mode/voice set ho to wahi (warna global) — har card par reset
        $this->withAudioMode($card->part?->story?->tts_mode);
        $this->withVoice($card->part?->story?->tts_voice);

        $seconds = $seconds ?: max(2, (int) $this->setting('yt_card_seconds', 6));

        $disk = Storage::disk('public');
        $src  = $card->image_path;
        if (! $src || ! $disk->exists($src)) {
            throw new \RuntimeException('Card image nahi mili — pehle card banao.');
        }

        $disk->makeDirectory('yt');
        $mp4 = 'yt/card-' . $card->id . '.mp4';

        // Cover intro (agar on hai) + card (voice-aware)
        $segments = array_merge(
            $this->coverSegments($card->part),
            [$this->cardSegment($card, $seconds)],
            $this->outroSegments($card),
        );

        $this->encodeSegments($segments, $mp4);

        return $mp4;
    }

    /**
     * Kai cards ko ek hi reel me jodo (jaise Quiz: Question card + Answer card).
     * $extraDur[i] = us card par extra pause (seconds) — jaise question ke baad
     * viewer ke sochne ka time. Voice-over per-card lagta hai (agar mode ON).
     *
     * @param  list<PartCard>  $cards
     */
    public function mp4ForCards(array $cards, string $key, array $extraDur = []): string
    {
        $first = $cards[0] ?? null;
        // Story ka audio mode/voice override (har call par reset)
        $this->withAudioMode($first?->part?->story?->tts_mode);
        $this->withVoice($first?->part?->story?->tts_voice);

        $seconds = max(2, (int) $this->setting('yt_card_seconds', 6));
        $disk = Storage::disk('public');
        $disk->makeDirectory('yt');
        $mp4 = 'yt/' . $key . '.mp4';

        $segments = [];
        foreach ($cards as $i => $card) {
            if (! $card->image_path || ! $disk->exists($card->image_path)) {
                continue;
            }
            $seg = $this->cardSegment($card, $seconds);
            if (! empty($extraDur[$i])) {
                $seg['dur'] += (float) $extraDur[$i]; // extra pause
            }
            $segments[] = $seg;
        }

        if (empty($segments)) {
            throw new \RuntimeException('In cards ki koi image nahi mili.');
        }

        $segments = array_merge($segments, $this->outroSegments($first));

        $this->encodeSegments($segments, $mp4);

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
     * End me "Follow karo" outro segment (Settings me ON ho to), warna khaali.
     *
     * @return list<array{path:string,dur:float,voice?:string}>
     */
    protected function outroSegments(?PartCard $card): array
    {
        $story = $card?->part?->story;
        $seg   = ReelOutro::segment($story?->user_id, $story?->language, $story?->tts_voice);

        // Music mode: voice wala segment poori video ko voice-path par le jaata (music hat jaati)
        if ($seg && $this->ttsMode() === 'music') {
            unset($seg['voice']);
            $seg['dur'] = (float) max(ReelOutro::MIN_SECONDS, (float) $this->setting('reel_outro_seconds', 3));
        }

        return $seg ? [$seg] : [];
    }

    /**
     * Ek card ke liye video segment. Voice mode ON + card ka text ho to voice
     * attach hoti hai aur duration = voice-length + gap; warna fixed seconds.
     *
     * @return array{path:string,dur:float,voice?:string}
     */
    protected function cardSegment(PartCard $card, int $fallbackSeconds): array
    {
        $disk = Storage::disk('public');
        $seg  = ['path' => $disk->path($card->image_path), 'dur' => (float) $fallbackSeconds];

        if ($voice = $this->voiceFor($card)) {
            $seg['voice'] = $disk->path($voice['path']);
            $seg['dur']   = $voice['seconds'] + 0.6; // narration + chhota tail
        }

        return $seg;
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
            Log::warning('YT voice-over skip', ['card' => $card->id, 'error' => $e->getMessage()]);

            return null; // voice fail → chup-chaap music/silent par gir jao
        }
    }

    protected function ttsMode(): string
    {
        // Story ka per-collection override pehle, warna user ka global setting
        $m = $this->audioModeOverride ?? (string) $this->setting('tts_audio_mode', 'music');

        return in_array($m, ['music', 'voice', 'voice_music'], true) ? $m : 'music';
    }

    /** Narration ka andaaz — shayari/quote/joke expressive, yoga shaant, warna kahani. */
    protected function voiceStyle(PartCard $card): string
    {
        return $card->part?->story?->voiceStyle() ?? 'story';
    }

    /**
     * Segments (har ek {path, dur, voice?}) → ek 1080x1920 video command.
     *
     * Agar kisi segment me voice hai → audio track per-segment banti hai (voice
     * segment = us card ki narration, warna silence), phir mode voice_music me
     * music dheemi (15%) mix hoti hai. Voice na ho to pehle jaisa music/silent.
     *
     * @param  list<array{path:string,dur:float,voice?:string}>  $segments
     */
    protected function videoCommand(array $segments, string $outAbs, int $w = 720, int $h = 1280): array
    {
        $ffmpeg   = config('services.ffmpeg.path', 'ffmpeg');
        $disk     = Storage::disk('public');
        $n        = count($segments);
        $hasVoice = collect($segments)->contains(fn ($s) => ! empty($s['voice']));

        // Shared hosting par 1080p ffmpeg ko OOM/CPU-limit signal-9 se kill kar deta
        // hai. 720x1280 (9:16) + ultrafast + single-thread + kam ref/lookahead =
        // sabse halka aur reliable (YouTube 720p Shorts accept karta hai). OOM
        // (signal 9) par encodeSegments() chhoti res (540/480) par retry karta hai.
        $enc = [
            '-c:v', 'libx264', '-preset', 'ultrafast', '-tune', 'stillimage', '-threads', '1',
            '-x264-params', 'ref=1:bframes=0:rc-lookahead=10:sync-lookahead=0',
            '-profile:v', 'high', '-level', '3.1',
            '-pix_fmt', 'yuv420p', '-color_range', 'tv', '-r', '25',
            '-g', '50', '-keyint_min', '50', '-sc_threshold', '0', '-flags', '+cgop',
            '-c:a', 'aac', '-b:a', '128k', '-ar', '44100', '-ac', '2',
            '-movflags', '+faststart',
        ];

        // Image inputs
        $cmd = [$ffmpeg, '-y'];
        foreach ($segments as $seg) {
            $cmd = array_merge($cmd, ['-loop', '1', '-t', $this->fnum((float) $seg['dur']), '-i', $seg['path']]);
        }

        // Video chain (dono cases me same). Motion ON ho to har slide par halka
        // Ken Burns zoom — baari-baari andar/bahar, taaki sab slides ek jaisi na lagein.
        $motion  = ReelMotion::enabled($this->settingsUserId);
        $chain   = '';
        $vlabels = '';
        foreach ($segments as $i => $seg) {
            $chain .= "[{$i}:v]" . ReelMotion::chain($w, $h, (float) $seg['dur'], $i, $motion) . "[v{$i}];";
            $vlabels .= "[v{$i}]";
        }
        $chain .= $vlabels . "concat=n={$n}:v=1:a=0[v]";

        // ---- No voice: pehle jaisa (music loop ya silent, -shortest) ----
        if (! $hasVoice) {
            $musicPath = ReelMusic::path($this->settingsUserId, 'yt_music');
            if ($musicPath) {
                $cmd = array_merge($cmd, ['-stream_loop', '-1', '-i', $musicPath]);
            } else {
                $cmd = array_merge($cmd, ['-f', 'lavfi', '-i', 'anullsrc=channel_layout=stereo:sample_rate=44100']);
            }

            return array_merge($cmd, [
                '-filter_complex', $chain,
                '-map', '[v]', '-map', $n . ':a',
                ...$enc, '-shortest', $outAbs,
            ]);
        }

        // ---- Voice path ----
        // Voice inputs (sirf voice-wale segments), input index track karo
        $vInput = [];
        $k = 0;
        foreach ($segments as $i => $seg) {
            if (! empty($seg['voice'])) {
                $cmd = array_merge($cmd, ['-i', $seg['voice']]);
                $vInput[$i] = $n + $k;
                $k++;
            }
        }

        // Music (optional, sirf voice_music mode)
        $music     = $this->ttsMode() === 'voice_music' ? ReelMusic::path($this->settingsUserId, 'yt_music') : null;
        $withMusic = $music !== null;
        $musicIdx  = null;
        if ($withMusic) {
            $cmd = array_merge($cmd, ['-stream_loop', '-1', '-i', $music]);
            $musicIdx = $n + $k;
        }

        // Per-segment audio: voice (padded to dur) ya silence (dur tak)
        $chain .= ';';
        $alabels = '';
        foreach ($segments as $i => $seg) {
            $dur = $this->fnum((float) $seg['dur']);
            if (isset($vInput[$i])) {
                $vi = $vInput[$i];
                $chain .= "[{$vi}:a]aresample=44100,apad,atrim=duration={$dur},"
                    . "aformat=sample_rates=44100:channel_layouts=stereo,asetpts=N/SR/TB[a{$i}];";
            } else {
                $chain .= "anullsrc=r=44100:cl=stereo,atrim=duration={$dur},asetpts=N/SR/TB[a{$i}];";
            }
            $alabels .= "[a{$i}]";
        }
        $chain .= $alabels . "concat=n={$n}:v=0:a=1[speech]";

        if ($withMusic) {
            $chain .= ";[{$musicIdx}:a]volume=0.15[mus];[speech][mus]amix=inputs=2:duration=first:normalize=0[a]";
            $audioOut = '[a]';
        } else {
            $audioOut = '[speech]';
        }

        return array_merge($cmd, [
            '-filter_complex', $chain,
            '-map', '[v]', '-map', $audioOut,
            ...$enc, $outAbs,
        ]);
    }

    /** Float ko locale-safe ffmpeg string me. */
    /** ffmpeg ka asli error aakhri lines me hota hai (shuru me sirf version-banner) — wahi dikhao. */
    protected function ffTail(?string $out, int $max = 300): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $out)), fn ($l) => $l !== ''
            && ! preg_match('/^(ffmpeg version|built with|configuration:|lib\w+\s+\d|Copyright|Press \[q\])/i', $l)));

        return Str::limit(implode(' | ', array_slice($lines, -4)), $max);
    }

    protected function fnum(float $x): string
    {
        return rtrim(rtrim(sprintf('%.3f', $x), '0'), '.') ?: '0';
    }

    /**
     * Segments ko video me encode karo. Shared hosting par ffmpeg ko OOM par
     * signal 9 (SIGKILL) se kill kar diya jaata hai — Laravel Process tab
     * ProcessSignaledException throw karta hai. Is case me (aur kisi bhi ffmpeg
     * fail par) progressively chhoti resolution par retry karta hai taaki memory
     * kam lage aur video ban jaaye.
     */
    protected function encodeSegments(array $segments, string $mp4): void
    {
        $disk   = Storage::disk('public');
        $absOut = $disk->path($mp4);

        // Resolution tiers: pehle 720p, OOM/fail par halki res par retry.
        $tiers   = [[720, 1280], [540, 960], [480, 854]];
        $lastErr = '';

        foreach ($tiers as [$w, $h]) {
            $cmd = $this->videoCommand($segments, $absOut, $w, $h);

            try {
                $result = Process::timeout(600)->run($cmd);
            } catch (\Throwable $e) {
                // Signal 9 (OOM kill) / timeout / process crash — agli chhoti res try karo
                $lastErr = $e->getMessage();
                Log::warning('YT ffmpeg process crash, lower-res retry', ['res' => "{$w}x{$h}", 'error' => $lastErr]);
                continue;
            }

            if ($result->successful() && $disk->exists($mp4)) {
                return;
            }

            $lastErr = $result->errorOutput() ?: $result->output();
            Log::warning('YT ffmpeg failed, lower-res retry', ['res' => "{$w}x{$h}", 'err' => Str::limit($lastErr, 300)]);
        }

        Log::error('YT ffmpeg failed (all resolutions)', ['err' => $lastErr]);
        throw new \RuntimeException(
            'Video nahi ban paayi — server ne memory/CPU limit cross ki (ffmpeg signal 9 / OOM). '
            . 'Slideshow me kam cards rakho ya slide-seconds ghatao. ' . Str::limit($lastErr, 150)
        );
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
    public function uploadShort(string $absPath, string $title, string $description, string $privacy = 'public', string $categoryId = '24'): string
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
                'categoryId'  => $categoryId, // 24 = Entertainment, 22 = People & Blogs
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

        // Koi aur (button/cron) isi card ko abhi upload kar raha ho to yahin ruk jao
        $card->claimFor('yt');

        try {
            $mp4 = $this->mp4ForCard($card);
            $id  = $this->uploadShort(
                Storage::disk('public')->path($mp4),
                $this->buildTitle($card),
                $this->buildDescription($card),
                (string) $this->setting('yt_privacy', 'public'),
            );

            $this->markCardPosted($card, $id);
            // Video file rakhi jaati hai (delete nahi) — same card/part doosre
            // platform par bhi post ho sake, aur dobara upload ho sake.

            return $id;
        } catch (\Throwable $e) {
            Log::error('YT card post failed', ['card' => $card->id, 'error' => $e->getMessage()]);
            $this->markCardFailed($card, $e->getMessage());
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

        // 1. YouTube caption set hai to wahi as-is
        if (filled($card?->yt_caption)) {
            return $this->ensureShorts(trim($card->yt_caption));
        }

        // 2. Agar YouTube Default Caption Suffix (yt_title_suffix) set hai, to wahi jana chahiye
        $suffix = $this->setting('yt_title_suffix');
        if (filled($suffix)) {
            return $this->ensureShorts(trim($suffix));
        }

        // 3. Agar nahi dala hai, to AI se create kar ke jaye
        if ($card) {
            if (blank($card->ig_caption)) {
                try {
                    $caption = app(AiCaptionService::class)->forCard($card);
                    if (filled($caption)) {
                        $card->update(['ig_caption' => $caption]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('AI caption generation failed during YouTube post', ['error' => $e->getMessage()]);
                }
            }

            if (filled($card->ig_caption)) {
                return $this->ensureShorts(trim($card->ig_caption));
            }
        }

        // Fallback description
        $lines = [];
        $lines[] = $story?->title;
        if ($part) {
            $lines[] = 'Part ' . $part->sort_order;
        }

        return $this->ensureShorts(trim(implode("\n", array_filter($lines))));
    }

    /** #Shorts na ho to add kar do (YouTube ise Short maane iske liye zaroori). */
    protected function ensureShorts(string $description): string
    {
        if (! Str::contains(Str::lower($description), '#shorts')) {
            $description = rtrim($description) . "\n#Shorts";
        }

        return $description;
    }

    /* ===================================================================
     |  POORA PART = EK SHORT (Rashifal YouTube Shorts: 12 rashi / Top 3)
     * =================================================================== */

    /**
     * Part ke saare cards (frames) ko ek Short bana ke upload karo. Lock/status pehle card par
     * (auto-post ka failure handling usi par chalta hai); safal hone par saare cards 'posted'.
     */
    public function postPart(Part $part): string
    {
        $part->loadMissing(['story', 'cards' => fn ($q) => $q->orderBy('sort_order')]);
        $cards = $part->cards->values();
        $first = $cards->first();
        if (! $first) {
            throw new \RuntimeException('Is Short me koi frame nahi hai.');
        }

        $this->forUser($part->story?->user_id);
        if (! $this->isConfigured()) {
            throw new \RuntimeException('YouTube configured nahi. Pehle channel connect karo.');
        }

        @set_time_limit(900);
        $first->claimFor('yt');

        try {
            $mp4 = $this->mp4ForPart($part);
            $id  = $this->uploadShort(
                Storage::disk('public')->path($mp4),
                $this->buildTitle($first, $part),
                $this->buildDescription($first, $part),
                (string) $this->setting('yt_privacy', 'public'),
            );

            foreach ($cards as $card) {
                $this->markCardPosted($card, $id);
            }

            return $id;
        } catch (\Throwable $e) {
            Log::error('YT part (Short) post failed', ['part' => $part->id, 'error' => $e->getMessage()]);
            $this->markCardFailed($first, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Frames → ek Short. Shared host par 13+ tasveeron wala ek bada ffmpeg graph OOM (signal 9)
     * se kill hota hai, isliye: har frame ka chhota clip alag → concat (copy, bina re-encode)
     * → music → "Follow karo" outro. Ek waqt me sirf ek tasveer memory me rehti hai.
     */
    public function mp4ForPart(Part $part): string
    {
        $story = $part->story;
        $this->withAudioMode($story?->tts_mode);
        $this->withVoice($story?->tts_voice);

        $cards = $part->cards->sortBy('sort_order')->values();
        $disk  = Storage::disk('public');
        $disk->makeDirectory('yt');
        $tmpDir = 'yt/part-' . $part->id . '-tmp';
        $disk->deleteDirectory($tmpDir);
        $disk->makeDirectory($tmpDir);
        $mp4 = 'yt/part-' . $part->id . '.mp4';

        $ffmpeg = config('services.ffmpeg.path', 'ffmpeg');
        $enc = [
            '-c:v', 'libx264', '-preset', 'ultrafast', '-tune', 'stillimage', '-threads', '1',
            '-x264-params', 'ref=1:bframes=0:rc-lookahead=10:sync-lookahead=0',
            '-profile:v', 'high', '-level', '3.1',
            '-pix_fmt', 'yuv420p', '-color_range', 'tv', '-r', '25',
            '-g', '50', '-keyint_min', '50', '-sc_threshold', '0', '-flags', '+cgop',
            '-c:a', 'aac', '-b:a', '128k', '-ar', '44100', '-ac', '2',
        ];

        try {
            $lastErr = '';
            foreach ([[720, 1280], [540, 960], [480, 854]] as [$w, $h]) {
                $list = [];
                $ok = true;

                foreach ($cards as $i => $card) {
                    if (! $card->image_path || ! $disk->exists($card->image_path)) {
                        continue;
                    }
                    // Frame ka time: pehla (intro) / aakhri (Like-Share-Subscribe) 3 sec, rashi frame 4.5
                    // (3-rashi Shorts me 5.5 — kam frames, aaram se padhe). Voice ho to us jitna.
                    $edge = $i === 0 || $i === $cards->count() - 1;
                    $seg = ['path' => $disk->path($card->image_path),
                        'dur' => $edge ? 3.0 : ((string) $part->body === 'short:all' ? 4.5 : 5.5)];
                    if ($voice = $this->voiceFor($card)) {
                        $seg['voice'] = $disk->path($voice['path']);
                        $seg['dur'] = max($seg['dur'], $voice['seconds'] + 0.5);
                    }

                    $clip = $disk->path("{$tmpDir}/c{$i}.mp4");
                    $dur  = $this->fnum($seg['dur']);
                    $cmd  = [$ffmpeg, '-y', '-loop', '1', '-t', $dur, '-i', $seg['path']];
                    $cmd  = ! empty($seg['voice'])
                        ? array_merge($cmd, ['-i', $seg['voice']])
                        : array_merge($cmd, ['-f', 'lavfi', '-t', $dur, '-i', 'anullsrc=channel_layout=stereo:sample_rate=44100']);
                    $cmd = array_merge($cmd, [
                        '-filter_complex', '[0:v]' . ReelMotion::chain($w, $h, $seg['dur'], $i, false) . '[v];'
                            . "[1:a]aresample=44100,apad,atrim=duration={$dur},aformat=sample_rates=44100:channel_layouts=stereo[a]",
                        '-map', '[v]', '-map', '[a]', '-t', $dur, ...$enc, $clip,
                    ]);

                    try {
                        $res = Process::timeout(180)->run($cmd);
                    } catch (\Throwable $e) {
                        $res = null;
                        $lastErr = $e->getMessage();
                    }
                    if (! $res || ! $res->successful() || ! is_file($clip)) {
                        $lastErr = $res ? ($res->errorOutput() ?: $res->output()) : $lastErr;
                        $ok = false;
                        break;
                    }
                    $list[] = "file '" . str_replace("'", "'\\''", str_replace('\\', '/', $clip)) . "'";
                }

                if ($ok && $list) {
                    break;
                }
                Log::warning('YT part clip fail, lower-res retry', ['part' => $part->id, 'res' => "{$w}x{$h}", 'err' => Str::limit($lastErr, 300)]);
                $list = [];
            }

            if (! $list) {
                throw new \RuntimeException('Short nahi ban paaya (ffmpeg). ' . $this->ffTail($lastErr));
            }

            // Clips jodo (sab same settings ke — re-encode ki zaroorat nahi)
            $listFile = $disk->path("{$tmpDir}/list.txt");
            file_put_contents($listFile, implode("\n", $list) . "\n");
            $joined = $disk->path("{$tmpDir}/joined.mp4");
            $res = Process::timeout(180)->run([$ffmpeg, '-y', '-f', 'concat', '-safe', '0', '-i', $listFile, '-c', 'copy', $joined]);
            if (! $res->successful() || ! is_file($joined)) {
                throw new \RuntimeException('Short ke clips jud nahi paaye. ' . $this->ffTail($res->errorOutput()));
            }

            // Music: music-mode me poori awaaz, voice_music me voice ke peeche halka; sirf voice → bina music
            $out = $disk->path($mp4);
            $mode = $this->ttsMode();
            $music = $mode === 'voice' ? null : ReelMusic::path($this->settingsUserId, 'yt_music');
            if ($music) {
                $vol = $mode === 'music' ? '1.0' : '0.15';
                $res = Process::timeout(300)->run([
                    $ffmpeg, '-y', '-i', $joined, '-stream_loop', '-1', '-i', $music,
                    '-filter_complex', "[1:a]volume={$vol},aformat=sample_rates=44100:channel_layouts=stereo[m];[0:a][m]amix=inputs=2:duration=first:normalize=0[a]",
                    '-map', '0:v', '-map', '[a]', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '128k', '-ar', '44100', '-ac', '2',
                    '-movflags', '+faststart', '-shortest', $out,
                ]);
                if (! $res->successful() || ! is_file($out)) {
                    throw new \RuntimeException('Short me music nahi jud paaya. ' . $this->ffTail($res->errorOutput()));
                }
            } else {
                $res = Process::timeout(120)->run([$ffmpeg, '-y', '-i', $joined, '-c', 'copy', '-movflags', '+faststart', $out]);
                if (! $res->successful() || ! is_file($out)) {
                    throw new \RuntimeException('Short save nahi hua. ' . $this->ffTail($res->errorOutput()));
                }
            }
        } finally {
            $disk->deleteDirectory($tmpDir);
        }

        return ReelOutro::append($mp4, $story?->user_id, $story?->language, $story?->tts_voice);
    }

    /* ===================================================================
     |  🎬 SAPTAHIK RASHIFAL — lamba 16:9 video (Short nahi)
     * =================================================================== */

    /** Frame ka samay (bina voice): intro 10s (YouTube chapter kam se kam 10s), rashi 22s, aakhri 8s. */
    protected const WEEK_BASE = ['intro' => 10.0, 'rashi' => 22.0, 'end' => 8.0];

    /**
     * Frames (1920×1080) → 720p video. Voice mode me awaaz 1 + 4 Gemini calls me banti hai
     * (intro, phir 3-3 rashi) — free plan ki ~10/din limit me rahe. Har group ke frames ka samay
     * text ki lambai ke hisaab se baanta jaata hai. Koi bhi voice fail ho to poora video sirf music par.
     * Return: ['mp4' => rel path, 'durations' => [sec, …]] (durations chapters ke liye json me bhi).
     */
    public function mp4ForWeekVideo(Part $part): array
    {
        // Ek video ek waqt me ek hi jagah bane ("▶ Video dekho" + "⬆ Upload" / cron ek saath dabe to)
        $lock = \Illuminate\Support\Facades\Cache::lock('yt-week-build-' . $part->id, 1800);
        if (! $lock->get()) {
            throw new \App\Exceptions\CardBusyException('Ye video abhi kahin aur ban raha hai (preview / upload / auto-post) — 5-10 minute baad dobara try karo.');
        }

        try {
            return $this->buildWeekVideo($part);
        } finally {
            $lock->release();
        }
    }

    protected function buildWeekVideo(Part $part): array
    {
        $story = $part->story;
        $this->withAudioMode($story?->tts_mode);
        $this->withVoice($story?->tts_voice);

        $cards = $part->cards->sortBy('sort_order')->values();
        $n = $cards->count();
        if ($n < 3) {
            throw new \RuntimeException('Video ke liye frames kam hain.');
        }

        $disk = Storage::disk('public');
        $disk->makeDirectory('yt');
        // Har build ka apna temp folder — koi doosra build iski files nahi mita sakta
        $tmpDir = 'yt/week-' . $part->id . '-tmp-' . Str::lower(Str::random(8));
        $disk->makeDirectory($tmpDir);
        $mp4 = 'yt/week-' . $part->id . '.mp4';
        $ffmpeg = config('services.ffmpeg.path', 'ffmpeg');

        $kindOf = fn (int $i) => $i === 0 ? 'intro' : ($i === $n - 1 ? 'end' : 'rashi');
        $durs = [];
        for ($i = 0; $i < $n; $i++) {
            $durs[$i] = self::WEEK_BASE[$kindOf($i)];
        }

        // ---- Voice-over (groups: [intro], [1,2,3], [4,5,6], …; aakhri frame bina voice) ----
        $voices = []; // [[absWav, firstIndex], …]
        if ($this->ttsMode() !== 'music' && (new GeminiTtsService())->isConfigured()) {
            // Intro + (special frame ho to wo bhi) ek call me, baaki 3-3 — 15 frames par bhi 5 calls
            $middle = range(1, $n - 2);
            $groups = [count($middle) % 3 === 1 ? [0, array_shift($middle)] : [0]];
            foreach (array_chunk($middle, 3) as $g) {
                $groups[] = $g;
            }
            $tts   = new GeminiTtsService();
            $voice = $this->voiceOverride ?: ($this->setting('tts_voice') ?: null);
            $lang  = $story?->language ?: 'hindi';
            $newDurs = $durs;

            try {
                foreach ($groups as $g) {
                    $texts = array_map(fn ($i) => trim((string) $cards[$i]->text), $g);
                    $res   = $tts->speak(implode("\n\n", $texts), $voice, 'rashifal', $lang);
                    $total = $res['seconds'] + 1.2;
                    $weights = array_map(fn ($t) => max(1, mb_strlen($t)), $texts);
                    $sum = array_sum($weights);
                    foreach ($g as $k => $i) {
                        $min = $kindOf($i) === 'intro' ? 10.0 : 12.0;
                        $newDurs[$i] = max($min, round($total * $weights[$k] / $sum, 2));
                    }
                    $voices[] = [$disk->path($res['path']), $g[0]];
                }
                $durs = $newDurs;
            } catch (\Throwable $e) {
                Log::warning('Weekly video voice fail — sirf music', ['part' => $part->id, 'error' => $e->getMessage()]);
                $voices = [];
            }
        }
        $withVoice = $voices !== [];

        // Har frame kab shuru hota hai
        $starts = [];
        $t = 0.0;
        foreach ($durs as $i => $d) {
            $starts[$i] = $t;
            $t += $d;
        }
        $total = $t;

        $venc = [
            '-c:v', 'libx264', '-preset', 'ultrafast', '-tune', 'stillimage', '-threads', '1',
            '-profile:v', 'high', '-level', '4.0', '-pix_fmt', 'yuv420p', '-r', '25',
            '-g', '250', '-keyint_min', '25', '-an',
        ];

        try {
            // ---- 1) Har frame ka chhota (bina awaaz) clip — ek waqt me ek tasveer memory me ----
            $list = [];
            $lastErr = '';
            foreach ([[1280, 720], [854, 480]] as [$w, $h]) {
                $list = [];
                foreach ($cards as $i => $card) {
                    if (! $card->image_path || ! $disk->exists($card->image_path)) {
                        throw new \RuntimeException('Frame ' . ($i + 1) . ' ki image nahi mili.');
                    }
                    $clip = $disk->path("{$tmpDir}/c{$i}.mp4");
                    $dur  = $this->fnum($durs[$i]);
                    try {
                        $res = Process::timeout(300)->run([
                            $ffmpeg, '-y', '-loop', '1', '-framerate', '25', '-t', $dur, '-i', $disk->path($card->image_path),
                            '-vf', "scale={$w}:{$h}:flags=lanczos,setsar=1,format=yuv420p", '-t', $dur, ...$venc, $clip,
                        ]);
                    } catch (\Throwable $e) {
                        $res = null;
                        $lastErr = $e->getMessage();
                    }
                    if (! $res || ! $res->successful() || ! is_file($clip)) {
                        $lastErr = $res ? ($res->errorOutput() ?: $res->output()) : $lastErr;
                        $list = [];
                        break;
                    }
                    $list[] = "file '" . str_replace("'", "'\\''", str_replace('\\', '/', $clip)) . "'";
                }
                if ($list) {
                    break;
                }
                Log::warning('Weekly video clip fail, lower-res retry', ['part' => $part->id, 'res' => "{$w}x{$h}", 'err' => Str::limit($lastErr, 300)]);
            }
            if (! $list) {
                throw new \RuntimeException('Video nahi ban paaya (ffmpeg). ' . $this->ffTail($lastErr));
            }

            $listFile = $disk->path("{$tmpDir}/list.txt");
            file_put_contents($listFile, implode("\n", $list) . "\n");
            $joined = $disk->path("{$tmpDir}/joined.mp4");
            $res = Process::timeout(300)->run([$ffmpeg, '-y', '-f', 'concat', '-safe', '0', '-i', $listFile, '-c', 'copy', $joined]);
            if (! $res->successful() || ! is_file($joined)) {
                throw new \RuntimeException('Video ke clips jud nahi paaye. ' . $this->ffTail($res->errorOutput()));
            }

            // ---- 2) Awaaz: voice (apne samay par) + music (voice ho to halka) ----
            $music = ReelMusic::path($this->settingsUserId, 'yt_music');
            $cmd = [$ffmpeg, '-y', '-i', $joined];
            $filters = [];
            $mixIn = [];
            $idx = 1;
            foreach ($voices as $k => [$wav, $first]) {
                $cmd = array_merge($cmd, ['-i', $wav]);
                $ms = (int) round($starts[$first] * 1000) + 300;
                $filters[] = "[{$idx}:a]aresample=44100,aformat=sample_rates=44100:channel_layouts=stereo,adelay={$ms}|{$ms}[v{$k}]";
                $mixIn[] = "[v{$k}]";
                $idx++;
            }
            if ($music) {
                $cmd = array_merge($cmd, ['-stream_loop', '-1', '-i', $music]);
                $vol = $withVoice ? '0.12' : '0.9';
                $filters[] = "[{$idx}:a]volume={$vol},aformat=sample_rates=44100:channel_layouts=stereo[m]";
                $mixIn[] = '[m]';
                $idx++;
            }
            if (! $mixIn) {
                $cmd = array_merge($cmd, ['-f', 'lavfi', '-i', 'anullsrc=channel_layout=stereo:sample_rate=44100']);
                $filters[] = "[{$idx}:a]anull[s]";
                $mixIn[] = '[s]';
            }
            $tot = $this->fnum($total);
            $filters[] = implode('', $mixIn) . 'amix=inputs=' . count($mixIn) . ":duration=longest:normalize=0,apad,atrim=duration={$tot}[a]";

            // Pehle temp me banao, poora ban jaye tab asli naam par — adhoora video kabhi upload na ho
            $out = $disk->path("{$tmpDir}/final.mp4");
            $res = Process::timeout(600)->run(array_merge($cmd, [
                '-filter_complex', implode(';', $filters),
                '-map', '0:v', '-map', '[a]', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '160k', '-ar', '44100', '-ac', '2',
                '-t', $tot, '-movflags', '+faststart', $out,
            ]));
            if (! $res->successful() || ! is_file($out)) {
                throw new \RuntimeException('Video me awaaz nahi jud paayi. ' . $this->ffTail($res->errorOutput()));
            }
            $disk->delete($mp4);
            if (! @rename($out, $disk->path($mp4))) {
                throw new \RuntimeException('Bana hua video save nahi hua (file rename).');
            }
        } finally {
            $disk->deleteDirectory($tmpDir);
        }

        $disk->put('yt/week-' . $part->id . '.json', json_encode(['durations' => $durs, 'voice' => $withVoice]));

        return ['mp4' => $mp4, 'durations' => $durs];
    }

    /** "0:00 Shuruaat\n0:12 Mesh …" — YouTube chapters (pehla 0:00, har ek 10s+, kam se kam 3). */
    public function weekChapters(Part $part, array $durations): string
    {
        $meta = json_decode((string) $part->body, true) ?: [];
        $labels = $meta['chapters'] ?? [];
        $rows = [];
        $t = 0.0;
        foreach ($durations as $i => $d) {
            $label = $labels[$i] ?? null;
            if (filled($label) && $d >= 10) {
                $s = (int) floor($t);
                $stamp = $s >= 3600
                    ? sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60)
                    : sprintf('%d:%02d', intdiv($s, 60), $s % 60);
                $rows[] = ($rows ? $stamp : '0:00') . ' ' . $label;
            }
            $t += $d;
        }
        if (count($rows) < 3) {
            return '';
        }

        return trim(($meta['head'] ?? '') . "\n" . implode("\n", $rows));
    }

    /** Lamba video upload: bina #Shorts, chapters description me, phir custom thumbnail. */
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
            throw new \RuntimeException('YouTube configured nahi. Pehle channel connect karo.');
        }

        @set_time_limit(1800);

        // Preview / doosra upload abhi bana raha ho to card ko 'failed' kiye bina ruk jao
        $lock = \Illuminate\Support\Facades\Cache::lock('yt-week-build-' . $part->id, 1800);
        if (! $lock->get()) {
            throw new \App\Exceptions\CardBusyException('Ye video abhi kahin aur ban raha hai (preview / upload) — 5-10 minute baad dobara try karo.');
        }

        try {
            $first->claimFor('yt');
        } catch (\Throwable $e) {
            $lock->release();
            throw $e;
        }

        try {
            $built = $this->buildWeekVideo($part);
            $caption = trim((string) $first->yt_caption);
            $chapters = $this->weekChapters($part, $built['durations']);
            $description = str_contains($caption, '[CHAPTERS]')
                ? str_replace('[CHAPTERS]', $chapters, $caption)
                : $caption . ($chapters !== '' ? "\n\n" . $chapters : '');
            $description = preg_replace("/\n{3,}/", "\n\n", $description);

            $id = $this->uploadShort(
                Storage::disk('public')->path($built['mp4']),
                $this->buildTitle($first, $part),
                $description,
                (string) $this->setting('yt_privacy', 'public'),
                '22', // People & Blogs
            );

            foreach ($cards as $card) {
                $this->markCardPosted($card, $id);
            }

            // Thumbnail na lage (channel verified nahi / limit) to bhi video upload ho chuka — sirf log
            if ($part->image_path && Storage::disk('public')->exists($part->image_path)) {
                try {
                    $this->setThumbnail($id, Storage::disk('public')->path($part->image_path));
                } catch (\Throwable $e) {
                    Log::warning('YT thumbnail set nahi hua', ['video' => $id, 'error' => $e->getMessage()]);
                }
            }

            return $id;
        } catch (\Throwable $e) {
            Log::error('YT weekly video post failed', ['part' => $part->id, 'error' => $e->getMessage()]);
            $this->markCardFailed($first, $e->getMessage());
            throw $e;
        } finally {
            $lock->release();
        }
    }

    /** Custom thumbnail (JPG/PNG, 2MB tak). Channel phone-verified hona chahiye. */
    public function setThumbnail(string $videoId, string $absImagePath): void
    {
        $ext  = strtolower(pathinfo($absImagePath, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
        $res = Http::withToken($this->accessToken())
            ->timeout(120)
            ->withBody(file_get_contents($absImagePath), $mime)
            ->post('https://www.googleapis.com/upload/youtube/v3/thumbnails/set?uploadType=media&videoId=' . urlencode($videoId));

        if (! $res->successful()) {
            throw new \RuntimeException($res->json('error.message') ?? ('HTTP ' . $res->status()));
        }
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
