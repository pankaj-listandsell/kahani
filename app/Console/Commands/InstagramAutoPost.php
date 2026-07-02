<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Story;
use App\Models\User;
use App\Services\InstagramService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class InstagramAutoPost extends Command
{
    protected $signature = 'instagram:auto-post {--force : Ignore enabled + window + interval checks and post one now} {--user= : Sirf is user id ke liye chalao}';

    protected $description = 'Har user ke apne Instagram account par next pending card auto-post karo (unke time windows ke andar)';

    public function handle(InstagramService $instagram): int
    {
        $force = (bool) $this->option('force');
        $now = Carbon::now();

        // Jin users ne apna IG access token set kiya hai, unhi ke liye chalao.
        $userIds = Setting::where('key', 'ig_access_token')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->pluck('user_id')
            ->unique();

        if ($only = $this->option('user')) {
            $userIds = $userIds->filter(fn ($id) => (int) $id === (int) $only);
        }

        if ($userIds->isEmpty()) {
            $this->info('Kisi user ne Instagram configure nahi kiya.');

            return self::SUCCESS;
        }

        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $this->runForUser($instagram, $user, $now, $force);
        }

        return self::SUCCESS;
    }

    /**
     * Ek user ke liye poori auto-post logic.
     */
    protected function runForUser(InstagramService $instagram, User $user, Carbon $now, bool $force): void
    {
        $uid = $user->id;

        if (! $force && Setting::getFor($uid, 'ig_auto_enabled', '0') !== '1') {
            return;
        }

        if (! $instagram->forUser($uid)->isConfigured()) {
            $this->warn("User #{$uid}: Instagram configured nahi.");

            return;
        }

        if (! $force) {
            $window = $this->activeWindow($uid, $now);
            if (! $window) {
                return; // is user ke window ke bahar
            }

            // Interval respect karo (per-user last post time)
            $last = Setting::getFor($uid, 'ig_auto_last_post_at');
            if ($last) {
                $mins = Carbon::parse($last)->diffInMinutes($now);
                if ($mins < (int) $window['interval']) {
                    return;
                }
            }
        }

        $card = $this->nextPendingCard($uid);
        if (! $card) {
            return; // is user ki saari cards post ho chuki
        }

        $type = Setting::getFor($uid, 'ig_post_type', 'image') === 'reel' ? 'reel' : 'image';

        try {
            $id = $type === 'reel' ? $instagram->postReel($card) : $instagram->postCard($card);
            Setting::putFor($uid, 'ig_auto_last_post_at', $now->toIso8601String());
            $this->info("User #{$uid}: posted {$type} card #{$card->id} → media {$id}");
        } catch (\Throwable $e) {
            $this->error("User #{$uid} card #{$card->id} failed: " . $e->getMessage());
        }
    }

    /**
     * Is user ke abhi ke time par active window (agar koi hai).
     */
    protected function activeWindow(int $userId, Carbon $now): ?array
    {
        $windows = json_decode((string) Setting::getFor($userId, 'ig_auto_windows', '[]'), true) ?: [];
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
     * Sirf is user ki published stories → parts (order) → cards (order): pehla unposted card.
     */
    protected function nextPendingCard(int $userId)
    {
        $stories = Story::where('status', 'published')
            ->where('user_id', $userId)
            ->with(['parts' => fn ($q) => $q->orderBy('sort_order'), 'parts.cards'])
            ->orderBy('id')
            ->get();

        foreach ($stories as $story) {
            foreach ($story->parts as $part) {
                foreach ($part->cards as $card) {
                    // Sirf fresh cards (jo abhi tak kabhi post/queue/fail nahi hue).
                    // Isse manual-queue ya failed cards dubara auto-post nahi honge.
                    if ($card->ig_status === null) {
                        return $card;
                    }
                }
            }
        }

        return null;
    }
}
