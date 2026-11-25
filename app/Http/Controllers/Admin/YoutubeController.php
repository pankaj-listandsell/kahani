<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\PartCard;
use App\Models\Setting;
use App\Models\Story;
use App\Services\AiCaptionService;
use App\Services\YoutubeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class YoutubeController extends Controller
{
    public function __construct(private YoutubeService $youtube)
    {
    }

    public function index()
    {
        $settings = [
            'yt_channel_title' => Setting::get('yt_channel_title'),
            'yt_auto_enabled'  => Setting::get('yt_auto_enabled', '0'),
            'yt_cover_enabled' => Setting::get('yt_cover_enabled', '1'),
            'yt_cover_seconds' => (int) Setting::get('yt_cover_seconds', 2),
            'yt_privacy'       => Setting::get('yt_privacy', 'public'),
            'yt_title_suffix'  => Setting::get('yt_title_suffix'),
            'yt_auto_windows'  => json_decode((string) Setting::get('yt_auto_windows', '[]'), true) ?: [],
            'yt_music'         => Setting::get('yt_music'),
            'tts_audio_mode'   => Setting::get('tts_audio_mode', 'music'),
            'tts_voice'        => Setting::get('tts_voice', 'Kore'),
            'tts_configured'   => filled(config('services.gemini.key')),
        ];

        $storiesQuery = Story::with(['parts.cards'])->latest();
        if (! auth()->user()->isAdmin()) {
            $storiesQuery->where('user_id', auth()->id());
        }

        return view('admin.youtube.index', [
            'settings'      => $settings,
            'stories'       => $storiesQuery->get(),
            'configured'    => $this->youtube->isConfigured(),
            'appConfigured' => $this->youtube->appConfigured(),
            'diag'          => $this->autoPostDiagnosis(),
            'tokenFailed'   => $this->tokenFailedCards()->count(),
        ]);
    }

    /**
     * 🩺 Auto-post kyun nahi chal raha — har shart ek line: [ok?, title, detail].
     * Pehli laal line hi asli wajah hoti hai.
     *
     * @return list<array{0:bool|null,1:string,2:string}>
     */
    protected function autoPostDiagnosis(): array
    {
        $uid = auth()->id();
        $now = now();
        $rows = [];

        $beat = Cache::get('scheduler:last_run');
        $beatAgo = $beat ? Carbon::parse($beat)->diffInMinutes($now) : null;
        $rows[] = [$beat !== null && $beatAgo <= 5, 'Server cron (scheduler)', $beat
            ? 'Aakhri baar ' . Carbon::parse($beat)->diffForHumans() . ' chala' . ($beatAgo > 5 ? ' — cron band lagta hai! Hosting panel me "* * * * * php artisan schedule:run" wala cron job check karo.' : '.')
            : 'Abhi tak koi record nahi. Nayi files upload karke 2-3 minute ruko; fir bhi yahi dikhe to hosting ka cron job ("php artisan schedule:run" har minute) set nahi hai.'];

        // Cron chal raha hai par YouTube command nahi = scheduler lock me atka (process kill hua tha)
        $yt = Cache::get('autopost:yt:last_run');
        $ytAgo = $yt ? Carbon::parse($yt)->diffInMinutes($now) : null;
        $cronOk = $beat !== null && $beatAgo <= 5;
        $rows[] = [$yt && $ytAgo <= 5 ? true : ($cronOk ? false : null), 'YouTube auto-post command', $yt
            ? 'Aakhri baar ' . Carbon::parse($yt)->diffForHumans() . ' chala' . ($ytAgo > 5 && $cronOk ? ' — cron chal raha hai par ye nahi: lock me atka hai. "🔓 Lock hatao" dabao.' : '.')
            : 'Abhi tak nahi chala. Nayi files ke baad 2-3 minute me bhi ye line na badle, to lock me atka hai — "🔓 Lock hatao" dabao.'];

        $rows[] = [$this->youtube->isConfigured(), 'YouTube connected', $this->youtube->isConfigured()
            ? 'Channel juda hua hai.' : 'Channel connect nahi — upar "Connect YouTube" dabao.'];

        $enabled = Setting::getFor($uid, 'yt_auto_enabled', '0') === '1';
        $rows[] = [$enabled, 'Auto Post ON', $enabled ? 'Chalu hai.' : 'Band hai — Settings me "Auto Post" ON karke Save karo.'];

        $windows = json_decode((string) Setting::getFor($uid, 'yt_auto_windows', '[]'), true) ?: [];
        $mins = $now->hour * 60 + $now->minute;
        $inWindow = collect($windows)->first(function ($w) use ($mins) {
            $s = $this->hhmm($w['start'] ?? ''); $e = $this->hhmm($w['end'] ?? '');

            return $s !== null && $e !== null && $mins >= $s && $mins <= $e;
        });
        $list = collect($windows)->map(fn ($w) => ($w['start'] ?? '?') . '–' . ($w['end'] ?? '?') . ' (har ' . ($w['interval'] ?? 60) . ' min)')->implode(', ');
        $rows[] = [$windows ? ($inWindow ? true : null) : false, 'Time window', ! $windows
            ? 'Koi time window nahi — Settings me kam se kam ek window jodo.'
            : ($inWindow ? "Abhi window chalu hai: {$list}." : "Abhi ({$now->format('H:i')}) koi window nahi chal rahi. Windows: {$list}.")];

        $until = Setting::getFor($uid, 'yt_auto_cooldown_until');
        $cool = $until && Carbon::parse($until)->greaterThan($now);
        $rows[] = [! $cool, 'Cooldown (error ke baad rukna)', $cool
            ? 'Ruka hua hai ' . Carbon::parse($until)->format('d M H:i') . ' tak. Wajah: ' . (Setting::getFor($uid, 'yt_auto_cooldown_reason') ?: 'pichhle upload me error')
            : 'Koi rukawat nahi.'];

        $pending = Story::where('user_id', $uid)->where('status', 'published')
            ->withCount(['cards as yt_pending' => fn ($q) => $q->whereNull('yt_status')])
            ->get()
            ->filter(fn (Story $s) => $s->targetsPlatform('youtube') && ! in_array($s->type, ['rashifal_short', 'rashifal_week'], true)) // video ke frames card nahi
            ->sum('yt_pending');
        $rows[] = [$pending > 0, 'Post karne ke liye cards', $pending > 0
            ? "{$pending} cards baaki hain." : 'Koi pending card nahi (sab post ho chuke, ya collections me YouTube unchecked hai).'];

        // 📺 Aaj ke Rashifal Shorts (har part = ek video; status pehle frame par) — upar wali ginti me nahi aate
        $shortParts = Story::where('user_id', $uid)->where('status', 'published')
            ->where('type', 'rashifal_short')->where('category', $now->toDateString())
            ->with(['parts.cards' => fn ($q) => $q->orderBy('sort_order')])->get()
            ->flatMap->parts
            ->reject(fn ($p) => (string) $p->body === 'short:all'); // 12 Rashi Short band
        $byStatus = $shortParts->groupBy(fn ($p) => $p->cards->first()?->yt_status ?? 'pending')->map->count();
        $rows[] = [$shortParts->isEmpty() ? false : (($byStatus['failed'] ?? 0) ? false : true), 'Aaj ke Rashifal Shorts', $shortParts->isEmpty()
            ? 'Aaj (' . $now->format('d M') . ') ke Shorts save nahi hain — Rashifal page par "📺 YouTube Shorts pan banavo" tick karke aaj ka rashifal Save karo.'
            : ($byStatus['posted'] ?? 0) . ' upload ho gaye, ' . ($byStatus['pending'] ?? 0) . ' baaki'
                . (($byStatus['posting'] ?? 0) ? ', ' . $byStatus['posting'] . ' abhi upload ho raha' : '')
                . (($byStatus['failed'] ?? 0) ? ', ' . $byStatus['failed'] . ' fail (neeche ❌ dekho)' : '')
                . ' — kul ' . $shortParts->count() . '. Baaki wale agle time slots me jayenge.'];

        // 🎬 Is hafte ka Saptahik video
        $monday = $now->copy()->startOfWeek(Carbon::MONDAY);
        $weekFirst = Story::where('user_id', $uid)->where('status', 'published')
            ->where('type', 'rashifal_week')->where('category', $monday->toDateString())
            ->with(['parts.cards' => fn ($q) => $q->orderBy('sort_order')])->latest('id')->first()
            ?->parts->first()?->cards->first();
        $nextMonday = $monday->copy()->addWeek();
        $nextSaved = Story::where('user_id', $uid)->where('type', 'rashifal_week')->where('category', $nextMonday->toDateString())->exists();
        $rows[] = [
            $weekFirst ? $weekFirst->yt_status !== 'failed' : null,
            'Is hafte ka Saptahik Video',
            (! $weekFirst
                ? 'Is hafte (' . $monday->format('d M') . ') ka video save nahi hai — Rashifal page par "🎬 Saptahik YouTube Video" se banao.'
                : match ($weekFirst->yt_status) {
                    'posted'  => '✅ Upload ho gaya (' . $weekFirst->yt_video_id . ').',
                    'posting' => '⏳ Abhi ban ke upload ho raha hai (5-10 min).',
                    'failed'  => '❌ Fail: ' . Str::limit((string) $weekFirst->yt_error, 160),
                    default   => '⏳ Saved — agle time slot me upload hoga (Shorts se pehle).',
                })
            . ' Agle hafte (' . $nextMonday->format('d M') . ') ka: ' . ($nextSaved ? '✅ ready' : 'abhi nahi bana') . '.',
        ];

        $note = Cache::get("autopost:yt:note:{$uid}");
        $rows[] = [null, 'Command ka aakhri faisla', $note ?: 'Abhi record nahi (nayi files ke baad agle minute me aayega).'];

        $last = Setting::getFor($uid, 'yt_auto_last_post_at');
        $lastFail = PartCard::where('yt_status', 'failed')
            ->whereHas('part.story', fn ($q) => $q->where('user_id', $uid))
            ->latest('updated_at')->first();
        $rows[] = [null, 'Aakhri koshish', ($last ? Carbon::parse($last)->diffForHumans() : 'kabhi nahi')
            . ($lastFail && filled($lastFail->yt_error) ? ' • Aakhri error: ' . Str::limit($lastFail->yt_error, 220) : '')];

        return $rows;
    }

    private function hhmm(string $v): ?int
    {
        return preg_match('/^(\d{1,2}):(\d{2})$/', $v, $m) ? (int) $m[1] * 60 + (int) $m[2] : null;
    }

    /** Token toota hone ya roz ki upload limit ki wajah se 'failed' hue cards — card ki galti nahi thi, wapas pending karo. */
    protected function tokenFailedCards()
    {
        return PartCard::where('yt_status', 'failed')
            ->where(fn ($q) => $q->where('yt_error', 'like', '%dobara connect%')->orWhere('yt_error', 'like', '%token refresh%')
                ->orWhere('yt_error', 'like', '%exceeded the number of videos%')->orWhere('yt_error', 'like', '%quota%'))
            ->whereHas('part.story', fn ($q) => $q->where('user_id', auth()->id()));
    }

    public function retryTokenFailed()
    {
        $n = $this->tokenFailedCards()->update(['yt_status' => null, 'yt_error' => null]);
        Setting::putFor(auth()->id(), 'yt_auto_cooldown_until', null);
        Setting::putFor(auth()->id(), 'yt_auto_fail_count', '0');

        return back()->with('success', "🔁 {$n} cards dobara auto-post ki line me daal diye.");
    }

    /** 🔓 Scheduler ke phanse "chal raha hai" locks hatao — agle minute se auto-post fir chalega. */
    public function unlock()
    {
        try {
            Artisan::call('schedule:clear-cache');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Lock nahi hata: ' . $e->getMessage());
        }

        return back()->with('success', '🔓 Lock hat gaya — 1-2 minute me "YouTube auto-post command" line ✅ ho jaani chahiye.');
    }

    /** 🧪 Window/cooldown chhodkar abhi ek Short upload karo — command ka output dikhao. */
    public function runNow()
    {
        @set_time_limit(600);

        try {
            Artisan::call('youtube:auto-post', ['--force' => true, '--user' => auth()->id()]);
            $out = trim(Artisan::output()) ?: 'Koi pending card nahi mila.';
        } catch (\Throwable $e) {
            report($e);
            $out = 'Error: ' . $e->getMessage();
        }

        $failed = str_contains($out, 'failed') || str_starts_with($out, 'Error');

        return back()->with($failed ? 'error' : 'success', '🧪 Test upload: ' . Str::limit($out, 600));
    }

    /* ================= OAuth ================= */

    public function connect(Request $request)
    {
        if (! $this->youtube->appConfigured()) {
            return back()->with('error', 'Google client ID/secret .env me set nahi. Setup guide dekho: docs/youtube-setup.md');
        }

        $state = Str::random(40);
        $request->session()->put('yt_oauth_state', $state);

        return redirect()->away($this->youtube->authUrl($state));
    }

    public function callback(Request $request)
    {
        if ($err = $request->query('error')) {
            return redirect()->route('admin.youtube.index')->with('error', 'Google ne connect cancel kiya: ' . $err);
        }

        // CSRF: state match hona chahiye
        $state = $request->query('state');
        if (! $state || $state !== $request->session()->pull('yt_oauth_state')) {
            return redirect()->route('admin.youtube.index')->with('error', 'Security check fail (state mismatch). Dobara try karo.');
        }

        $code = $request->query('code');
        if (! $code) {
            return redirect()->route('admin.youtube.index')->with('error', 'Google se code nahi mila.');
        }

        try {
            $this->youtube->exchangeCode($code);
        } catch (\Throwable $e) {
            return redirect()->route('admin.youtube.index')->with('error', 'Connect fail: ' . $e->getMessage());
        }

        $title = $this->youtube->channelTitle();

        return redirect()->route('admin.youtube.index')
            ->with('success', 'YouTube channel connect ho gaya' . ($title ? ': ' . $title : '') . ' ✅');
    }

    public function disconnect()
    {
        $this->youtube->disconnect();

        return back()->with('success', 'YouTube channel disconnect ho gaya.');
    }

    public function test()
    {
        $result = $this->youtube->testConnection();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /* ================= Settings ================= */

    public function saveAutoPost(Request $request)
    {
        $data = $request->validate([
            'yt_privacy'         => ['nullable', 'in:public,unlisted,private'],
            'yt_cover_seconds'   => ['nullable', 'integer', 'min:1', 'max:10'],
            'yt_title_suffix'    => ['nullable', 'string', 'max:500'],
            'tts_audio_mode'     => ['nullable', 'in:music,voice,voice_music'],
            'tts_voice'          => ['nullable', 'string', 'max:50'],
            'windows'            => ['nullable', 'array'],
            'windows.*.start'    => ['required', 'date_format:H:i'],
            'windows.*.end'      => ['required', 'date_format:H:i'],
            'windows.*.interval' => ['required', 'integer', 'min:5', 'max:1440'],
        ]);

        $windows = collect($data['windows'] ?? [])
            ->map(fn ($w) => [
                'start'    => $w['start'],
                'end'      => $w['end'],
                'interval' => (int) $w['interval'],
            ])
            ->values()
            ->all();

        Setting::put('yt_auto_enabled', $request->boolean('yt_auto_enabled') ? '1' : '0');
        Setting::put('yt_privacy', $data['yt_privacy'] ?? 'public');
        Setting::put('yt_cover_enabled', $request->boolean('yt_cover_enabled') ? '1' : '0');
        Setting::put('yt_cover_seconds', (string) ($data['yt_cover_seconds'] ?? 2));
        Setting::put('yt_title_suffix', $data['yt_title_suffix'] ?? null);
        Setting::put('yt_auto_windows', json_encode($windows));

        // Voice-over (shared IG + YouTube ke beech)
        $prevVoice = Setting::get('tts_voice');
        Setting::put('tts_audio_mode', $data['tts_audio_mode'] ?? 'music');
        Setting::put('tts_voice', $data['tts_voice'] ?? null);
        // Voice badla to purani cached voice audio + videos hata do
        if (($data['tts_voice'] ?? null) !== $prevVoice) {
            (new \App\Services\GeminiTtsService())->clearCache();
            $this->youtube->clearVideoCache();
        }

        return back()->with('success', 'YouTube auto-post settings save ho gayi.');
    }

    public function saveMusic(Request $request)
    {
        $request->validate([
            'yt_music' => ['required', 'file', 'mimes:mp3,m4a,aac,wav,ogg', 'max:20480'],
        ]);

        if ($old = Setting::get('yt_music')) {
            Storage::disk('public')->delete($old);
        }

        $path = $request->file('yt_music')->store('audio', 'public');
        Setting::put('yt_music', $path);
        $this->youtube->clearVideoCache();

        return back()->with('success', 'YouTube music save ho gaya. 🎵');
    }

    public function removeMusic()
    {
        if ($old = Setting::get('yt_music')) {
            Storage::disk('public')->delete($old);
        }
        Setting::put('yt_music', null);
        $this->youtube->clearVideoCache();

        return back()->with('success', 'YouTube music hata diya.');
    }

    /* ================= Caption (title + description) ================= */

    /** Card ki saved YouTube caption laao (modal ke liye). */
    public function getCaption(PartCard $card)
    {
        $this->authorize('update', $card->part->story);

        return response()->json(['caption' => $card->yt_caption]);
    }

    /** AI se YouTube caption + trending hashtags generate karke save karo. */
    public function generateCaption(PartCard $card, AiCaptionService $ai)
    {
        $this->authorize('update', $card->part->story);

        try {
            $caption = $ai->forYoutube($card);
            $card->update(['yt_caption' => $caption]);

            return response()->json(['ok' => true, 'caption' => $caption]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** Haath se edit ki hui caption save karo (khaali = default title/description). */
    public function saveCaption(Request $request, PartCard $card)
    {
        $this->authorize('update', $card->part->story);

        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:5000'], // YouTube description limit
        ]);

        $card->update(['yt_caption' => $data['caption'] !== '' ? $data['caption'] : null]);

        return response()->json(['ok' => true]);
    }

    /**
     * Ek part ke saare cards (jinke paas abhi caption nahi + jo YouTube par post
     * nahi hue) me ek AI YouTube caption add karo — ek hi caption poore part ke
     * liye (fast + consistent). Instagram ke "All Captions" jaisa.
     */
    public function generatePartCaptions(Part $part, AiCaptionService $ai)
    {
        $this->authorize('update', $part->story);
        $part->load('cards');

        $targets = $part->cards->filter(
            fn (PartCard $c) => blank($c->yt_caption) && ! $c->isYtPosted()
        );

        if ($targets->isEmpty()) {
            return back()->with('success', 'Sabhi cards ke paas pehle se caption hai. 👍');
        }

        try {
            $caption = $ai->forYoutube($targets->first());
        } catch (\Throwable $e) {
            return back()->with('error', 'Caption nahi bani: ' . $e->getMessage());
        }

        foreach ($targets as $card) {
            $card->update(['yt_caption' => $caption]);
        }

        return back()->with('success', $targets->count() . ' card(s) me AI caption add ho gaya. ✨ (chahein to har card me alag edit kar sakte hain.)');
    }

    /* ================= Manual post ================= */

    /** Ek card → single-card Short. */
    public function postCard(PartCard $card)
    {
        $this->authorize('update', $card->part->story);

        try {
            $this->youtube->postCard($card);
        } catch (\Throwable $e) {
            return back()->with('error', 'Short upload nahi hua: ' . $e->getMessage());
        }

        return back()->with('success', 'Card YouTube Short ban ke upload ho gaya. ✅');
    }
}
