<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\PartCard;
use App\Models\Setting;
use App\Models\Story;
use App\Services\AiCaptionService;
use App\Services\InstagramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InstagramController extends Controller
{
    public function __construct(private InstagramService $instagram)
    {
    }

    /**
     * Card ki saved caption laao (modal me dikhane ke liye).
     */
    public function getCaption(PartCard $card)
    {
        $this->authorize('update', $card->part->story);

        return response()->json(['caption' => $card->ig_caption]);
    }

    /**
     * AI se caption + hashtags generate karke card par save karo.
     */
    public function generateCaption(PartCard $card, AiCaptionService $ai)
    {
        $this->authorize('update', $card->part->story);

        try {
            $caption = $ai->forCard($card);
            $card->update(['ig_caption' => $caption]);

            return response()->json(['ok' => true, 'caption' => $caption]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Haath se edit ki hui caption save karo (khaali = default caption use hogi).
     */
    public function saveCaption(Request $request, PartCard $card)
    {
        $this->authorize('update', $card->part->story);

        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:2200'], // IG caption limit
        ]);

        $card->update(['ig_caption' => $data['caption'] !== '' ? $data['caption'] : null]);

        return response()->json(['ok' => true]);
    }

    public function index()
    {
        $settings = [
            'ig_user_id'        => Setting::get('ig_user_id'),
            'ig_access_token'   => Setting::get('ig_access_token'),
            'ig_caption_suffix' => Setting::get('ig_caption_suffix'),
            'ig_auto_enabled'   => Setting::get('ig_auto_enabled', '0'),
            'ig_post_type'      => Setting::get('ig_post_type', 'image'),
            'ig_auto_windows'   => json_decode((string) Setting::get('ig_auto_windows', '[]'), true) ?: [],
            'ig_reel_music'     => Setting::get('ig_reel_music'),
        ];

        $storiesQuery = Story::with(['parts.cards'])->latest();

        // Regular user sirf apni stories post kar sake
        if (! auth()->user()->isAdmin()) {
            $storiesQuery->where('user_id', auth()->id());
        }

        $stories = $storiesQuery->get();

        return view('admin.instagram.index', [
            'settings'   => $settings,
            'stories'    => $stories,
            'configured' => $this->instagram->isConfigured(),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'ig_user_id'        => ['nullable', 'string', 'max:255'],
            'ig_access_token'   => ['nullable', 'string', 'max:1000'],
            'ig_caption_suffix' => ['nullable', 'string', 'max:1000'],
        ]);

        // Copy-paste me aksar aage/peeche space ya newline aa jaate hain — hata do
        $clean = fn ($v) => $v !== null ? trim($v) : null;

        Setting::put('ig_user_id', $clean($data['ig_user_id'] ?? null));
        Setting::put('ig_access_token', $clean($data['ig_access_token'] ?? null));
        Setting::put('ig_caption_suffix', $data['ig_caption_suffix'] ?? null);

        return back()->with('success', 'Instagram settings saved.');
    }

    /**
     * Auto-post settings (enable, post type, time windows) save karo.
     */
    public function saveAutoPost(Request $request)
    {
        $data = $request->validate([
            'ig_post_type'          => ['nullable', 'in:image,reel'],
            'windows'               => ['nullable', 'array'],
            'windows.*.start'       => ['required', 'date_format:H:i'],
            'windows.*.end'         => ['required', 'date_format:H:i'],
            'windows.*.interval'    => ['required', 'integer', 'min:5', 'max:1440'],
        ]);

        $windows = collect($data['windows'] ?? [])
            ->map(fn ($w) => [
                'start'    => $w['start'],
                'end'      => $w['end'],
                'interval' => (int) $w['interval'],
            ])
            ->values()
            ->all();

        Setting::put('ig_auto_enabled', $request->boolean('ig_auto_enabled') ? '1' : '0');
        Setting::put('ig_post_type', $data['ig_post_type'] ?? 'image');
        Setting::put('ig_auto_windows', json_encode($windows));

        return back()->with('success', 'Auto-post settings saved.');
    }

    public function test(Request $request)
    {
        $result = $this->instagram->testConnection();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Reel ke liye default music (mp3) upload karo — sabhi reels par bajega.
     */
    public function saveReelMusic(Request $request)
    {
        $request->validate([
            'reel_music' => ['required', 'file', 'mimes:mp3,m4a,aac,wav,ogg', 'max:20480'],
        ]);

        // Purana music hata do
        if ($old = Setting::get('ig_reel_music')) {
            Storage::disk('public')->delete($old);
        }

        $path = $request->file('reel_music')->store('audio', 'public');
        Setting::put('ig_reel_music', $path);

        // Purani cached reel videos delete — taaki naya music lag jaye
        $this->instagram->clearReelCache();

        return back()->with('success', 'Reel music save ho gaya. 🎵');
    }

    /**
     * Reel music hata do.
     */
    public function removeReelMusic(Request $request)
    {
        if ($old = Setting::get('ig_reel_music')) {
            Storage::disk('public')->delete($old);
        }
        Setting::put('ig_reel_music', null);
        $this->instagram->clearReelCache();

        return back()->with('success', 'Reel music hata diya.');
    }

    /**
     * Ek card ko Instagram par post karo (turant, seedhe).
     */
    public function postCard(PartCard $card)
    {
        $this->authorize('update', $card->part->story);

        try {
            $this->instagram->postCard($card);
        } catch (\Throwable $e) {
            return back()->with('error', 'Card post nahi hua: ' . $e->getMessage());
        }

        return back()->with('success', 'Card Instagram par post ho gaya. ✅');
    }

    /**
     * Ek card ko REEL (video) ki tarah post karo (turant, seedhe).
     */
    public function postReel(PartCard $card)
    {
        $this->authorize('update', $card->part->story);

        try {
            $this->instagram->postReel($card);
        } catch (\Throwable $e) {
            return back()->with('error', 'Reel post nahi hua: ' . $e->getMessage());
        }

        return back()->with('success', 'Reel Instagram par post ho gaya. ✅');
    }

    /**
     * Ek part ke saare cards ko IMAGE posts (alag-alag) ki tarah bhejo.
     */
    public function postPart(Part $part)
    {
        $this->authorize('update', $part->story);

        return $this->bulk($part, 'image', 'card');
    }

    /**
     * Ek part ke saare cards ko REELS (alag-alag) ki tarah bhejo.
     */
    public function postPartReels(Part $part)
    {
        $this->authorize('update', $part->story);

        return $this->bulk($part, 'reel', 'reel');
    }

    private function bulk(Part $part, string $type, string $noun)
    {
        $part->load('cards');
        $count = 0;
        $failed = 0;

        foreach ($part->cards as $card) {
            // Jo post ho chuke, unhe chhod do
            if ($card->isPosted()) {
                continue;
            }

            try {
                $type === 'reel' ? $this->instagram->postReel($card) : $this->instagram->postCard($card);
                $count++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        $msg = "{$count} {$noun}(s) Instagram par post ho gaye. ✅";
        if ($failed) {
            $msg .= " ({$failed} fail hue — dobara try karein.)";
        }

        return back()->with('success', $msg);
    }
}
