<?php

namespace App\Console\Commands;

use App\Models\CardMetric;
use App\Models\PartCard;
use App\Models\Setting;
use App\Services\InstagramService;
use App\Services\YoutubeService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Har user ke posted cards ke asli numbers laao aur `card_metrics` me save karo.
 *  - Instagram: likes, comments + insights (views, reach, saved, shares)
 *  - YouTube:   views, likes, comments
 * Sirf pichhle N din ke posts (purane posts ke numbers kam badalte hain, API calls bachti hain).
 */
class FetchAnalytics extends Command
{
    protected $signature = 'analytics:fetch {--user= : Sirf is user id ke liye} {--days=60 : Itne din purane posts tak}';

    protected $description = 'Instagram / YouTube se har post ke views, likes, comments laakar save karo';

    /** Insights me pehle ye metrics maango; koi metric media type par na chale to chhota set. */
    private const IG_METRICS = ['views,reach,saved,shares', 'reach,saved,shares', 'reach'];

    public function handle(InstagramService $instagram, YoutubeService $youtube): int
    {
        $since = now()->subDays(max(1, (int) $this->option('days')));

        foreach ($this->userIds() as $uid) {
            $this->fetchInstagram($instagram->forUser($uid), $uid, $since);
            $this->fetchYoutube($youtube->forUser($uid), $uid, $since);
        }

        return self::SUCCESS;
    }

    /** Jin users ka IG token ya YT refresh token hai. */
    private function userIds(): Collection
    {
        $ids = Setting::whereIn('key', ['ig_access_token', 'yt_refresh_token'])
            ->whereNotNull('value')->where('value', '!=', '')
            ->pluck('user_id')->unique();

        if ($only = $this->option('user')) {
            $ids = $ids->filter(fn ($id) => (int) $id === (int) $only);
        }

        return $ids->values();
    }

    private function cardsFor(int $uid, string $prefix, string $idColumn, $since): Collection
    {
        return PartCard::whereHas('part.story', fn ($q) => $q->where('user_id', $uid))
            ->where("{$prefix}_status", 'posted')
            ->whereNotNull($idColumn)
            ->where("{$prefix}_posted_at", '>=', $since)
            ->get();
    }

    private function fetchInstagram(InstagramService $ig, int $uid, $since): void
    {
        if (! $ig->isConfigured()) {
            return;
        }

        $cards = $this->cardsFor($uid, 'ig', 'ig_media_id', $since);
        $base = $ig->apiBase();
        $token = (string) $ig->token();
        $saved = 0;

        foreach ($cards->chunk(20) as $chunk) {
            // Pool me network error exception ki tarah nahi, value ki tarah aata hai (neeche Response check)
            $fields = Http::pool(fn (Pool $pool) => $chunk->map(
                fn (PartCard $c) => $pool->as((string) $c->id)->timeout(20)
                    ->get("{$base}/{$c->ig_media_id}", ['fields' => 'like_count,comments_count,permalink', 'access_token' => $token])
            )->all());

            foreach ($chunk as $c) {
                $res = $fields[(string) $c->id] ?? null;
                if (! $res instanceof Response || ! $res->successful()) {
                    continue; // post delete ho gaya / token issue
                }

                $insights = $this->igInsights($base, $token, (string) $c->ig_media_id);

                $attrs = [
                    'likes'      => $res->json('like_count'),
                    'comments'   => $res->json('comments_count'),
                    'permalink'  => $res->json('permalink'),
                    'fetched_at' => now(),
                ];
                // Insights na mile (network/permission) to pichhle saved numbers mat mitao
                if ($insights) {
                    $attrs += [
                        'views'  => $insights['views'] ?? null,
                        'reach'  => $insights['reach'] ?? null,
                        'saves'  => $insights['saved'] ?? null,
                        'shares' => $insights['shares'] ?? null,
                    ];
                }

                CardMetric::updateOrCreate(['part_card_id' => $c->id, 'platform' => 'ig'], $attrs);
                $saved++;
            }
        }

        $this->info("User #{$uid}: Instagram {$saved}/{$cards->count()} posts updated");
    }

    /** @return array<string,int> metric name → value */
    private function igInsights(string $base, string $token, string $mediaId): array
    {
        foreach (self::IG_METRICS as $metric) {
            try {
                $res = Http::timeout(20)->get("{$base}/{$mediaId}/insights", ['metric' => $metric, 'access_token' => $token]);
            } catch (\Throwable $e) {
                return []; // network issue — is post ke insights chhod do, baaki chalte rahein
            }
            if (! $res->successful()) {
                continue; // is media type par koi metric support nahi — chhota set try karo
            }

            $out = [];
            foreach ($res->json('data', []) as $m) {
                $value = $m['values'][0]['value'] ?? $m['total_value']['value'] ?? null;
                if (is_numeric($value)) {
                    $out[$m['name']] = (int) $value;
                }
            }

            return $out;
        }

        return [];
    }

    private function fetchYoutube(YoutubeService $yt, int $uid, $since): void
    {
        if (! $yt->isConfigured()) {
            return;
        }

        $cards = $this->cardsFor($uid, 'yt', 'yt_video_id', $since);
        if ($cards->isEmpty()) {
            return;
        }

        try {
            $stats = $yt->videoStats($cards->pluck('yt_video_id')->all());
        } catch (\Throwable $e) {
            $this->warn("User #{$uid}: YouTube stats fail — " . $e->getMessage());

            return;
        }

        $saved = 0;
        foreach ($cards as $c) {
            if (! $s = $stats[$c->yt_video_id] ?? null) {
                continue;
            }

            CardMetric::updateOrCreate(
                ['part_card_id' => $c->id, 'platform' => 'yt'],
                [
                    'views'      => $s['views'],
                    'likes'      => $s['likes'],
                    'comments'   => $s['comments'],
                    'permalink'  => 'https://youtube.com/shorts/' . $c->yt_video_id,
                    'fetched_at' => now(),
                ],
            );
            $saved++;
        }

        $this->info("User #{$uid}: YouTube {$saved}/{$cards->count()} videos updated");
    }
}
