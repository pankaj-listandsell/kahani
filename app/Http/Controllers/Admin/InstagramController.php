<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\PartCard;
use App\Models\Setting;
use App\Models\Story;
use App\Services\InstagramService;
use Illuminate\Http\Request;

class InstagramController extends Controller
{
    public function __construct(private InstagramService $instagram)
    {
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
        ];

        $stories = Story::with(['parts.cards'])->latest()->get();

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

    public function test()
    {
        $result = $this->instagram->testConnection();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Ek card ko Instagram par post karo.
     */
    public function postCard(PartCard $card)
    {
        try {
            $this->instagram->postCard($card);
            return back()->with('success', 'Card posted to Instagram. 🎉');
        } catch (\Throwable $e) {
            return back()->with('error', 'Post failed: ' . $e->getMessage());
        }
    }

    /**
     * Ek card ko REEL (video) ki tarah post karo.
     */
    public function postReel(PartCard $card)
    {
        try {
            $this->instagram->postReel($card);
            return back()->with('success', 'Reel posted to Instagram. 🎬');
        } catch (\Throwable $e) {
            return back()->with('error', 'Reel failed: ' . $e->getMessage());
        }
    }

    /**
     * Ek part ke saare cards ko IMAGE posts (alag-alag) ki tarah bhejo.
     */
    public function postPart(Part $part)
    {
        return $this->bulk($part, 'postCard', 'card');
    }

    /**
     * Ek part ke saare cards ko REELS (alag-alag) ki tarah bhejo.
     */
    public function postPartReels(Part $part)
    {
        return $this->bulk($part, 'postReel', 'reel');
    }

    private function bulk(Part $part, string $method, string $noun)
    {
        $part->load('cards');
        $ok = 0;
        $fail = 0;

        foreach ($part->cards as $card) {
            if ($card->isPosted()) {
                continue;
            }
            try {
                $this->instagram->{$method}($card);
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
            }
        }

        $msg = "Posted {$ok} {$noun}(s)." . ($fail ? " {$fail} failed." : '');

        return back()->with($fail ? 'error' : 'success', $msg);
    }
}
