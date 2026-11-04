<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\HandlesAutoPostFailures;
use App\Exceptions\CardBusyException;
use App\Models\PartCard;
use App\Models\Setting;
use App\Models\Story;
use App\Models\User;
use App\Services\YoutubeService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class YoutubeAutoPost extends Command
{
    use HandlesAutoPostFailures;

    protected $signature = 'youtube:auto-post {--force : Window/interval check ignore karke abhi ek upload karo} {--user= : Sirf is user id ke liye}';

    protected $description = 'Har user ke YouTube channel par next pending card/part ko Short bana ke auto-upload karo (unke time windows me)';

    public function handle(YoutubeService $youtube): int
    {
        $force = (bool) $this->option('force');
        $now = Carbon::now();

        // Jin users ne YouTube connect kiya hai (refresh token maujood)
        $userIds = Setting::where('key', 'yt_refresh_token')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->pluck('user_id')
            ->unique();

        if ($only = $this->option('user')) {
            $userIds = $userIds->filter(fn ($id) => (int) $id === (int) $only);
        }

        if ($userIds->isEmpty()) {
            $this->info('Kisi user ne YouTube connect nahi kiya.');

            return self::SUCCESS;
        }

        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $this->runForUser($youtube, $user, $now, $force);
        }

        return self::SUCCESS;
    }

    protected function runForUser(YoutubeService $youtube, User $user, Carbon $now, bool $force): void
    {
        $uid = $user->id;

        if (! $force && Setting::getFor($uid, 'yt_auto_enabled', '0') !== '1') {
            return;
        }

        if (! $youtube->forUser($uid)->isConfigured()) {
            $this->warn("User #{$uid}: YouTube configured nahi.");

            return;
        }

        if (! $force && $until = $this->cooldownUntil($uid, 'yt', $now)) {
            $this->warn("User #{$uid}: YouTube cooldown (until {$until}) — skip.");

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

            // Ek slot = ek upload (Instagram command jaisa hi align logic)
            $slotTime = $now->copy()->startOfDay()->addMinutes($slot);
            $last = Setting::getFor($uid, 'yt_auto_last_post_at');
            if ($last && Carbon::parse($last)->greaterThanOrEqualTo($slotTime)) {
                return;
            }
        }

        $card = $this->nextPendingCard($uid);
        if (! $card) {
            return;
        }

        try {
            $id = $youtube->postCard($card);
            $this->info("User #{$uid}: uploaded Short (card #{$card->id}) → {$id}");
            $this->recordSuccess($uid, 'yt', $now);
        } catch (CardBusyException $e) {
            $this->warn("User #{$uid}: " . $e->getMessage()); // button se upload ho raha hai — slot rehne do
        } catch (\Throwable $e) {
            $this->error("User #{$uid} card #{$card->id} YouTube upload failed: " . $e->getMessage());
            $this->recordFailure($uid, 'yt', $card, $e, $now);
        }
    }

    /* ================= scheduling helpers (IG command jaise) ================= */

    protected function activeWindow(int $userId, Carbon $now): ?array
    {
        $windows = json_decode((string) Setting::getFor($userId, 'yt_auto_windows', '[]'), true) ?: [];
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
                    'interval' => max(5, (int) ($w['interval'] ?? 60)),
                ];
            }
        }

        return null;
    }

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

    /* ================= next pending card ================= */

    /** Pehla card jise abhi tak YouTube par post nahi kiya. */
    protected function nextPendingCard(int $userId): ?PartCard
    {
        // Aaj ka rashifal sabse pehle (sirf apne din, Mesh→Meen kram me)
        if ($rashi = Story::nextRashifalCard($userId, 'youtube', 'yt_status')) {
            return $rashi;
        }

        $randomPool = []; // shayari/joke/quote/quiz — random order me post honge

        foreach ($this->publishedStories($userId) as $story) {
            if (! $story->targetsPlatform('youtube')) {
                continue; // ye story YouTube ke liye target nahi
            }
            if ($story->type === 'rashifal') {
                continue; // upar nextRashifalCard() sambhalta hai — doosre din ka rashifal kabhi nahi
            }
            $isCollection = $story->isCollection();
            foreach ($story->parts as $part) {
                foreach ($part->cards as $card) {
                    // 'posting' 30+ min purana = pichhla upload beech me kill hua — dobara try karo
                    $stuck = $card->yt_status === 'posting'
                        && $card->updated_at?->lt(now()->subMinutes(PartCard::POSTING_STALE_MINUTES));
                    if ($card->yt_status !== null && ! $stuck) {
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

    protected function publishedStories(int $userId)
    {
        return Story::where('status', 'published')
            ->where('user_id', $userId)
            ->with(['parts' => fn ($q) => $q->orderBy('sort_order'), 'parts.cards' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('id')
            ->get();
    }
}
