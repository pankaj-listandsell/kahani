<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\HandlesAutoPostFailures;
use App\Exceptions\CardBusyException;
use App\Models\PartCard;
use App\Models\Setting;
use App\Models\Story;
use App\Models\User;
use App\Services\FacebookService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class FacebookAutoPost extends Command
{
    use HandlesAutoPostFailures;

    protected $signature = 'facebook:auto-post {--force : Window/interval check ignore karke abhi ek post karo} {--user= : Sirf is user id ke liye}';

    protected $description = 'Har user ke Facebook Page par next pending card auto-post karo (unke time windows me)';

    public function handle(FacebookService $facebook): int
    {
        $force = (bool) $this->option('force');
        $now = Carbon::now();

        $userIds = Setting::where('key', 'fb_page_token')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->pluck('user_id')
            ->unique();

        if ($only = $this->option('user')) {
            $userIds = $userIds->filter(fn ($id) => (int) $id === (int) $only);
        }

        if ($userIds->isEmpty()) {
            $this->info('Kisi user ne Facebook configure nahi kiya.');

            return self::SUCCESS;
        }

        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $this->runForUser($facebook, $user, $now, $force);
        }

        return self::SUCCESS;
    }

    protected function runForUser(FacebookService $facebook, User $user, Carbon $now, bool $force): void
    {
        $uid = $user->id;

        if (! $force && Setting::getFor($uid, 'fb_auto_enabled', '0') !== '1') {
            return;
        }

        if (! $facebook->forUser($uid)->isConfigured()) {
            $this->warn("User #{$uid}: Facebook configured nahi.");

            return;
        }

        // Cooldown active hai? (FB rate-limit, ya lagaatar failures)
        if ($cooldown = $this->cooldownUntil($uid, 'fb', $now)) {
            $this->warn("User #{$uid}: Facebook cooldown (until {$cooldown}) — skip.");

            return;
        }

        if (! $force) {
            $window = $this->activeWindow($uid, $now);
            if (! $window) {
                return;
            }

            $slot = $this->currentSlot($window, $now);
            if ($slot === null) {
                return;
            }

            $slotTime = $now->copy()->startOfDay()->addMinutes($slot);
            $last = Setting::getFor($uid, 'fb_auto_last_post_at');
            if ($last && Carbon::parse($last)->greaterThanOrEqualTo($slotTime)) {
                return;
            }
        }

        $card = $this->nextPendingCard($uid);
        if (! $card) {
            return;
        }

        $type = Setting::getFor($uid, 'fb_post_type', 'image') === 'reel' ? 'reel' : 'image';

        try {
            $id = $type === 'reel' ? $facebook->postReel($card) : $facebook->postPhoto($card);
            $this->recordSuccess($uid, 'fb', $now);
            $this->info("User #{$uid}: posted {$type} card #{$card->id} → {$id}");
        } catch (\App\Exceptions\FacebookRateLimitException $e) {
            // FB ne "We limit how often you can post…" diya — 2 ghante ruk jao.
            // Card 'failed' nahi hua (pending hai), cooldown ke baad dobara try hoga.
            $this->startCooldown($uid, 'fb', $now->copy()->addHours(2), 'rate-limit: ' . $e->getMessage());
        } catch (CardBusyException $e) {
            $this->warn("User #{$uid}: " . $e->getMessage()); // button se post ho raha hai — slot rehne do
        } catch (\Throwable $e) {
            $this->error("User #{$uid} card #{$card->id} failed: " . $e->getMessage());
            $this->recordFailure($uid, 'fb', $card, $e, $now);
        }
    }

    protected function activeWindow(int $userId, Carbon $now): ?array
    {
        $windows = json_decode((string) Setting::getFor($userId, 'fb_auto_windows', '[]'), true) ?: [];
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
                    'interval' => max(15, (int) ($w['interval'] ?? 30)),
                ];
            }
        }

        return null;
    }

    protected function currentSlot(array $window, Carbon $now): ?int
    {
        $start = $this->toMinutes($window['start']);
        $end   = $this->toMinutes($window['end']);
        $iv    = max(15, (int) $window['interval']);

        if ($start === null || $end === null) {
            return null;
        }

        $nowMin = $now->hour * 60 + $now->minute;
        if ($nowMin < $start || $nowMin > $end) {
            return null;
        }

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

    /** Sirf is user ki published stories → parts → cards: pehla fb-unposted card. */
    protected function nextPendingCard(int $userId): ?PartCard
    {
        // Aaj ka rashifal sabse pehle (sirf apne din, Mesh→Meen kram me)
        if ($rashi = Story::nextRashifalCard($userId, 'facebook', 'fb_status')) {
            return $rashi;
        }

        $stories = Story::where('status', 'published')
            ->where('user_id', $userId)
            ->with(['parts' => fn ($q) => $q->orderBy('sort_order'), 'parts.cards' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('id')
            ->get();

        $randomPool = []; // shayari/joke/quote/quiz — random order me post honge

        foreach ($stories as $story) {
            if (! $story->targetsPlatform('facebook')) {
                continue; // ye story Facebook ke liye target nahi
            }
            if ($story->type === 'rashifal') {
                continue; // upar nextRashifalCard() sambhalta hai — doosre din ka rashifal kabhi nahi
            }
            $isCollection = $story->isCollection();
            foreach ($story->parts as $part) {
                foreach ($part->cards as $card) {
                    if ($card->fb_status !== null) {
                        continue;
                    }
                    if ($isCollection) {
                        $randomPool[] = $card;
                    } else {
                        return $card; // story: sequence me
                    }
                }
            }
        }

        // Story cards khatam — collection se RANDOM ek
        return $randomPool ? $randomPool[array_rand($randomPool)] : null;
    }
}
