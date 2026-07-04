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
            'yt_post_mode'     => Setting::get('yt_post_mode', 'single'),
            'yt_slide_seconds' => (int) Setting::get('yt_slide_seconds', 4),
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
        ]);
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
            'yt_post_mode'       => ['nullable', 'in:single,slideshow'],
            'yt_privacy'         => ['nullable', 'in:public,unlisted,private'],
            'yt_slide_seconds'   => ['nullable', 'integer', 'min:2', 'max:15'],
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
        Setting::put('yt_post_mode', $data['yt_post_mode'] ?? 'single');
        Setting::put('yt_privacy', $data['yt_privacy'] ?? 'public');
        Setting::put('yt_slide_seconds', (string) ($data['yt_slide_seconds'] ?? 4));
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

    /** Poora part → ek slideshow Short. */
    public function postPart(Part $part)
    {
        $this->authorize('update', $part->story);

        try {
            $this->youtube->postPart($part);
        } catch (\Throwable $e) {
            return back()->with('error', 'Slideshow Short upload nahi hua: ' . $e->getMessage());
        }

        return back()->with('success', 'Part ka slideshow Short upload ho gaya. ✅');
    }
}
