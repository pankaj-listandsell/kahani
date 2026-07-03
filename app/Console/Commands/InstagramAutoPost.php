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

            // Post sirf FIXED clock-slots par ho: window start se interval ke
            // exact multiples (jaise 2:00, 2:10, 2:20 … 3:00). "Jab bhi mauka
            // mila" wali approach nahi.
            $slot = $this->currentSlot($window, $now);
            if ($slot === null) {
                return;
            }

            // Aaj ke is slot ka asli time. Agar last post is slot ke time par
            // ya uske baad hua hai, matlab ye slot pehle hi serve ho chuka —
            // dobara mat post karo. (Ek slot = ek post.)
            $slotTime = $now->copy()->startOfDay()->addMinutes($slot);
            $last = Setting::getFor($uid, 'ig_auto_last_post_at');
            if ($last && Carbon::parse($last)->greaterThanOrEqualTo($slotTime)) {
                return;
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

    /**
     * Abhi ke time par is window ka current fixed slot (minutes-from-midnight),
     * ya null agar koi slot due nahi.
     *
     * Slots = start, start+interval, start+2·interval, … (<= end).
     * Return karta hai sabse recent slot jo `now` par ya usse pehle aa chuka
     * — isse ek tick miss hone par bhi catch-up ho jaata hai, par post hamesha
     * slot-time se align rehta hai.
     */
    protected function currentSlot(array $window, Carbon $now): ?int
    {
        $start = $this->toMinutes($window['start']);
        $end   = $this->toMinutes($window['end']);
        $iv    = max(5, (int) $window['interval']);

        if ($start === null || $end === null) {
            return null;
        }

        $nowMin = $now->hour * 60 + $now->minute;
        if ($nowMin < $start || $nowMin > $end) {
            return null; // window ke bahar
        }

        // now par ya usse pehle aane wala sabse recent slot
        $slot = $start + intdiv($nowMin - $start, $iv) * $iv;

        return $slot <= $end ? $slot : null;
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
