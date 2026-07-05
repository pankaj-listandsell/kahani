<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\PartCard;
use App\Models\Setting;
use App\Models\Story;
use App\Services\FacebookService;
use Illuminate\Http\Request;

class FacebookController extends Controller
{
    public function __construct(private FacebookService $facebook)
    {
    }

    public function index()
    {
        $settings = [
            'fb_page_id'         => Setting::get('fb_page_id'),
            'fb_page_token'      => Setting::get('fb_page_token'),
            'fb_caption_suffix'  => Setting::get('fb_caption_suffix'),
            'fb_auto_enabled'    => Setting::get('fb_auto_enabled', '0'),
            'fb_post_type'       => Setting::get('fb_post_type', 'image'),
            'fb_auto_windows'    => json_decode((string) Setting::get('fb_auto_windows', '[]'), true) ?: [],
        ];

        $storiesQuery = Story::with(['parts.cards'])->latest();
        if (! auth()->user()->isAdmin()) {
            $storiesQuery->where('user_id', auth()->id());
        }

        return view('admin.facebook.index', [
            'settings'   => $settings,
            'stories'    => $storiesQuery->get(),
            'configured' => $this->facebook->isConfigured(),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'fb_page_id'        => ['nullable', 'string', 'max:255'],
            'fb_page_token'     => ['nullable', 'string', 'max:1000'],
            'fb_caption_suffix' => ['nullable', 'string', 'max:1000'],
        ]);

        $clean = fn ($v) => $v !== null ? trim($v) : null;

        Setting::put('fb_page_id', $clean($data['fb_page_id'] ?? null));
        Setting::put('fb_page_token', $clean($data['fb_page_token'] ?? null));
        Setting::put('fb_caption_suffix', $data['fb_caption_suffix'] ?? null);

        return back()->with('success', 'Facebook settings saved.');
    }

    public function saveAutoPost(Request $request)
    {
        $data = $request->validate([
            'fb_post_type'          => ['nullable', 'in:image,reel'],
            'windows'               => ['nullable', 'array'],
            'windows.*.start'       => ['required', 'date_format:H:i'],
            'windows.*.end'         => ['required', 'date_format:H:i'],
            'windows.*.interval'    => ['required', 'integer', 'min:15', 'max:1440'],
        ]);

        $windows = collect($data['windows'] ?? [])
            ->map(fn ($w) => [
                'start'    => $w['start'],
                'end'      => $w['end'],
                'interval' => (int) $w['interval'],
            ])
            ->values()
            ->all();

        Setting::put('fb_auto_enabled', $request->boolean('fb_auto_enabled') ? '1' : '0');
        Setting::put('fb_post_type', $data['fb_post_type'] ?? 'image');
        Setting::put('fb_auto_windows', json_encode($windows));

        return back()->with('success', 'Facebook auto-post settings saved.');
    }

    public function test()
    {
        $result = $this->facebook->testConnection();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function postCard(PartCard $card)
    {
        $this->authorize('update', $card->part->story);

        try {
            $this->facebook->postPhoto($card);
        } catch (\Throwable $e) {
            return back()->with('error', 'Photo post nahi hua: ' . $e->getMessage());
        }

        return back()->with('success', 'Photo Facebook par post ho gayi. ✅');
    }

    public function postReel(PartCard $card)
    {
        $this->authorize('update', $card->part->story);

        try {
            $this->facebook->postReel($card);
        } catch (\Throwable $e) {
            return back()->with('error', 'Reel post nahi hua: ' . $e->getMessage());
        }

        return back()->with('success', 'Reel Facebook par post ho gaya. ✅');
    }

    public function postPart(Part $part)
    {
        $this->authorize('update', $part->story);

        return $this->bulk($part, 'image', 'photo');
    }

    public function postPartReels(Part $part)
    {
        $this->authorize('update', $part->story);

        return $this->bulk($part, 'reel', 'reel');
    }

    private function bulk(Part $part, string $type, string $noun)
    {
        @set_time_limit(0);
        $part->load('cards');

        $pending = $part->cards->reject(fn (PartCard $c) => $c->isFbPosted())->values();
        $count = 0;
        $failed = 0;
        $rateLimited = false;

        foreach ($pending as $i => $card) {
            try {
                $type === 'reel' ? $this->facebook->postReel($card) : $this->facebook->postPhoto($card);
                $count++;
            } catch (\App\Exceptions\FacebookRateLimitException $e) {
                // FB ne rate-limit laga di — aur post mat karo warna block barhega.
                $rateLimited = true;
                break;
            } catch (\Throwable $e) {
                $failed++;
            }

            // FB rapid-fire posting ko spam maanta hai — posts ke beech thoda gap.
            if ($i < $pending->count() - 1) {
                sleep(5);
            }
        }

        $msg = "{$count} {$noun}(s) Facebook par post ho gaye. ✅";
        if ($failed) {
            $msg .= " ({$failed} fail hue — dobara try karein.)";
        }

        if ($rateLimited) {
            return back()->with('error', $msg
                . " ⚠️ Facebook ne rate-limit laga di ('We limit how often you can post…') — baaki cards abhi post nahi hue."
                . ' Kuch ghante baad dobara try karein.');
        }

        return back()->with('success', $msg);
    }
}
