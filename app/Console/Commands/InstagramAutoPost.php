<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Story;
use App\Services\InstagramService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class InstagramAutoPost extends Command
{
    protected $signature = 'instagram:auto-post {--force : Ignore enabled + window + interval checks and post one now}';

    protected $description = 'Auto-post the next pending card as image/reel, within the configured time windows';

    public function handle(InstagramService $instagram): int
    {
        $force = (bool) $this->option('force');

        if (! $force && Setting::get('ig_auto_enabled', '0') !== '1') {
            $this->info('Auto-post is disabled.');
            return self::SUCCESS;
        }

        if (! $instagram->isConfigured()) {
            $this->error('Instagram is not configured.');
            return self::FAILURE;
        }

        $now = Carbon::now();

        if (! $force) {
            $window = $this->activeWindow($now);
            if (! $window) {
                $this->info('Outside all posting windows right now (' . $now->format('H:i') . ').');
                return self::SUCCESS;
            }

            // Interval respect karo — pichhle post se itne minute beet jaayein
            $last = Setting::get('ig_auto_last_post_at');
            if ($last) {
                $mins = Carbon::parse($last)->diffInMinutes($now);
                if ($mins < (int) $window['interval']) {
                    $this->info("Waiting for interval ({$mins}/{$window['interval']} min since last post).");
                    return self::SUCCESS;
                }
            }
        }

        $card = $this->nextPendingCard();
        if (! $card) {
            $this->info('Nothing pending — all cards already posted. ✅');
            return self::SUCCESS;
        }

        $type = Setting::get('ig_post_type', 'image') === 'reel' ? 'reel' : 'image';

        try {
            $id = $type === 'reel' ? $instagram->postReel($card) : $instagram->postCard($card);
            Setting::put('ig_auto_last_post_at', $now->toIso8601String());
            $this->info("Posted {$type} card #{$card->id} → media {$id}");
        } catch (\Throwable $e) {
            $this->error("Card #{$card->id} failed: " . $e->getMessage());
        }

        return self::SUCCESS;
    }

    /**
     * Abhi ke time par active window (agar koi hai) laao.
     */
    protected function activeWindow(Carbon $now): ?array
    {
        $windows = json_decode((string) Setting::get('ig_auto_windows', '[]'), true) ?: [];
        $mins = $now->hour * 60 + $now->minute;

        foreach ($windows as $w) {
            $start = $this->toMinutes($w['start'] ?? '');
            $end   = $this->toMinutes($w['end'] ?? '');
            if ($start === null || $end === null) {
                continue;
            }
            if ($mins >= $start && $mins <= $end) {
                return [
                    'start'    => $w['start'],
                    'end'      => $w['end'],
                    'interval' => max(5, (int) ($w['interval'] ?? 30)),
                ];
            }
        }

        return null;
    }

    protected function toMinutes(string $hhmm): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $hhmm, $m)) {
            return null;
        }
        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    /**
     * Published stories → parts (order) → cards (order): pehla unposted card.
     */
    protected function nextPendingCard()
    {
        $stories = Story::where('status', 'published')
            ->with(['parts' => fn ($q) => $q->orderBy('sort_order'), 'parts.cards'])
            ->orderBy('id')
            ->get();

        foreach ($stories as $story) {
            foreach ($story->parts as $part) {
                foreach ($part->cards as $card) {
                    if ($card->ig_status !== 'posted') {
                        return $card;
                    }
                }
            }
        }

        return null;
    }
}
