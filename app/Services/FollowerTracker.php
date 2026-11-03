<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 👥 Followers ka roz ka hisaab — Instagram followers, YouTube subscribers, Facebook page followers.
 *
 * Har ghante (followers:fetch) ginti laakar Setting 'follower_history' (JSON) me rakhte hain —
 * naya table/migration nahi:
 *   { "ig": { "2026-11-03": {"first": 1200, "last": 1237}, ... }, "yt": {...}, "fb": {...},
 *     "ig_daily": { "2026-11-02": 41, ... } }   // Instagram insights ke roz ke naye followers
 *
 * "Aaj kitne aaye" = aaj ki aakhri ginti − kal ki aakhri ginti (kal ka data na ho to aaj ki pehli).
 */
class FollowerTracker
{
    public const PLATFORMS = ['ig', 'yt', 'fb'];

    private const KEY = 'follower_history';

    private const KEEP_DAYS = 90;

    public function __construct(
        private InstagramService $instagram,
        private YoutubeService $youtube,
        private FacebookService $facebook,
    ) {
    }

    /** Teeno platform se abhi ki ginti laakar save karo. @return array<string,int|null> */
    public function fetch(int $userId): array
    {
        $history = $this->history($userId);
        $today   = now()->toDateString();
        $counts  = [
            'ig' => $this->instagramCount($userId),
            'yt' => $this->youtubeCount($userId),
            'fb' => $this->facebookCount($userId),
        ];

        foreach ($counts as $p => $count) {
            if ($count === null) {
                continue;
            }
            $day = $history[$p][$today] ?? ['first' => $count];
            $day['last'] = $count;
            $history[$p][$today] = $day;
        }

        if ($daily = $this->instagramDaily($userId)) {
            $history['ig_daily'] = array_merge($history['ig_daily'] ?? [], $daily);
        }

        // Purana data hatao (JSON chhota rahe)
        $cutoff = now()->subDays(self::KEEP_DAYS)->toDateString();
        foreach ([...self::PLATFORMS, 'ig_daily'] as $p) {
            $history[$p] = array_filter($history[$p] ?? [], fn ($d) => $d >= $cutoff, ARRAY_FILTER_USE_KEY);
            ksort($history[$p]);
        }

        Setting::putFor($userId, self::KEY, json_encode($history));

        return $counts;
    }

    /**
     * Dashboard / Analytics ke liye: har platform ka kul, aaj, kal, 7 din, aur pichhle $days din ka roz ka badhaav.
     *
     * @return array<string, array{total:?int, today:?int, yesterday:?int, week:?int, days:array<string,?int>, updated:?string}>
     */
    public function summary(int $userId, int $days = 30): array
    {
        $history = $this->history($userId);
        $dates = collect(range($days - 1, 0))->map(fn ($i) => now()->subDays($i)->toDateString());
        $out = [];

        foreach (self::PLATFORMS as $p) {
            $snap = $history[$p] ?? [];
            $gain = [];
            foreach ($dates as $d) {
                // Instagram ka apna roz ka hisaab ho (insights) to wahi — warna ginti ka farak
                $gain[$d] = ($p === 'ig' && isset($history['ig_daily'][$d]))
                    ? (int) $history['ig_daily'][$d]
                    : $this->dayGain($snap, $d);
            }
            // Aaj ka hamesha ginti se (insights me aaj ka din abhi poora nahi hota)
            $todayKey = now()->toDateString();
            $gain[$todayKey] = $this->dayGain($snap, $todayKey);

            $last = $snap ? end($snap) : null;
            $week = collect(array_slice($gain, -7, 7, true))->filter(fn ($v) => $v !== null);

            $out[$p] = [
                'total'     => $last['last'] ?? null,
                'today'     => $gain[$todayKey],
                'yesterday' => $gain[now()->subDay()->toDateString()] ?? null,
                'week'      => $week->isEmpty() ? null : (int) $week->sum(),
                'days'      => $gain,
                'updated'   => $snap ? array_key_last($snap) : null,
            ];
        }

        return $out;
    }

    /** Ek din ka badhaav: us din ki aakhri − pichhle din ki aakhri (na ho to us din ki pehli). */
    private function dayGain(array $snap, string $date): ?int
    {
        if (! isset($snap[$date])) {
            return null;
        }

        $prevDates = array_filter(array_keys($snap), fn ($d) => $d < $date);
        $prev = $prevDates ? $snap[max($prevDates)]['last'] : $snap[$date]['first'];

        return (int) $snap[$date]['last'] - (int) $prev;
    }

    private function history(int $userId): array
    {
        $data = json_decode((string) Setting::getFor($userId, self::KEY, '{}'), true);

        return is_array($data) ? $data : [];
    }

    private function instagramCount(int $userId): ?int
    {
        $ig = $this->instagram->forUser($userId);
        if (! $ig->isConfigured()) {
            return null;
        }

        try {
            $res = Http::timeout(20)->get($ig->apiBase() . '/' . $ig->nodeId(), [
                'fields'       => 'followers_count',
                'access_token' => $ig->token(),
            ]);
            if ($res->successful() && $res->json('followers_count') !== null) {
                return (int) $res->json('followers_count');
            }
            Log::warning('Followers: Instagram count nahi mila', ['user' => $userId, 'error' => $res->json('error.message')]);
        } catch (\Throwable $e) {
            Log::warning('Followers: Instagram error', ['user' => $userId, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Instagram insights "follower_count" — pichhle 30 din ke roz ke naye followers.
     * Sirf 100+ followers wale account aur insights permission par milta hai; warna khaali.
     *
     * @return array<string,int>
     */
    private function instagramDaily(int $userId): array
    {
        $ig = $this->instagram->forUser($userId);
        if (! $ig->isConfigured()) {
            return [];
        }

        try {
            $res = Http::timeout(20)->get($ig->apiBase() . '/' . $ig->nodeId() . '/insights', [
                'metric'       => 'follower_count',
                'period'       => 'day',
                'since'        => now()->subDays(29)->startOfDay()->timestamp,
                'until'        => now()->startOfDay()->timestamp,
                'access_token' => $ig->token(),
            ]);
            if (! $res->successful()) {
                return [];
            }

            $out = [];
            foreach ($res->json('data.0.values', []) as $v) {
                if (isset($v['end_time'], $v['value'])) {
                    // end_time = din khatam hone ka samay (agle din 07:00 UTC) — us din ka hisaab
                    $date = \Illuminate\Support\Carbon::parse($v['end_time'])->timezone(config('app.timezone'))->subDay()->toDateString();
                    $out[$date] = (int) $v['value'];
                }
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function youtubeCount(int $userId): ?int
    {
        $yt = $this->youtube->forUser($userId);
        if (! $yt->isConfigured()) {
            return null;
        }

        try {
            return $yt->subscriberCount();
        } catch (\Throwable $e) {
            Log::warning('Followers: YouTube error', ['user' => $userId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function facebookCount(int $userId): ?int
    {
        $fb = $this->facebook->forUser($userId);
        if (! $fb->isConfigured()) {
            return null;
        }

        try {
            $res = Http::timeout(20)->get($fb->apiBase() . '/' . $fb->pageId(), [
                'fields'       => 'followers_count,fan_count',
                'access_token' => $fb->token(),
            ]);
            $n = $res->json('followers_count') ?? $res->json('fan_count');
            if ($res->successful() && $n !== null) {
                return (int) $n;
            }
            Log::warning('Followers: Facebook count nahi mila', ['user' => $userId, 'error' => $res->json('error.message')]);
        } catch (\Throwable $e) {
            Log::warning('Followers: Facebook error', ['user' => $userId, 'error' => $e->getMessage()]);
        }

        return null;
    }
}
