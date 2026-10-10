<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CardMetric;
use App\Models\PartCard;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

/**
 * Sirf ASLI data: post counts DB se, views/likes/comments `card_metrics` se
 * (jo `analytics:fetch` har 6 ghante Instagram / YouTube se laata hai).
 */
class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $scope = fn ($q) => $q->whereHas('part.story', fn ($s) => $user->isAdmin() ? $s : $s->where('user_id', $user->id));

        $counts = [
            'ig_posts' => PartCard::where($scope)->where('ig_status', 'posted')->count(),
            'yt_posts' => PartCard::where($scope)->where('yt_status', 'posted')->count(),
            'fb_posts' => PartCard::where($scope)->where('fb_status', 'posted')->count(),
        ];
        $counts['total_posts'] = array_sum($counts);

        $metrics = CardMetric::with('card.part.story')
            ->whereHas('card', $scope)
            ->get()
            ->filter(fn (CardMetric $m) => $m->card?->part?->story);

        $platform = fn (string $p) => $metrics->where('platform', $p);

        return view('admin.analytics.index', [
            'counts'      => $counts,
            'totals'      => ['ig' => $this->totals($platform('ig')), 'yt' => $this->totals($platform('yt'))],
            'byType'      => $this->groupStats($metrics, fn (CardMetric $m) => $m->card->part->story->type ?: 'story'),
            'byLanguage'  => $this->groupStats($metrics, fn (CardMetric $m) => $m->card->part->story->language ?: 'hindi'),
            'bestHours'   => $this->bestHours($platform('ig')),
            'topPosts'    => $metrics->sortByDesc(fn (CardMetric $m) => [(int) $m->views, $m->interactions()])->take(10)->values(),
            'lastFetched' => $metrics->max('fetched_at'),
            'categoryBreakdown' => Story::when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
                ->selectRaw('category, COUNT(*) as count')
                ->whereNotNull('category')
                ->groupBy('category')
                ->orderByDesc('count')
                ->take(5)
                ->get(),
        ]);
    }

    /** "Abhi update karo" — sirf is user ke posts ke numbers turant laao. */
    public function refresh()
    {
        @set_time_limit(300);

        try {
            Artisan::call('analytics:fetch', ['--user' => auth()->id()]);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Analytics update nahi ho paya: ' . $e->getMessage());
        }

        return back()->with('success', '🔄 Analytics update ho gaya.');
    }

    /** @return array{posts:int,views:int,likes:int,comments:int,saves:int,shares:int} */
    private function totals(Collection $rows): array
    {
        return [
            'posts'    => $rows->count(),
            'views'    => (int) $rows->sum('views'),
            'likes'    => (int) $rows->sum('likes'),
            'comments' => (int) $rows->sum('comments'),
            'saves'    => (int) $rows->sum('saves'),
            'shares'   => (int) $rows->sum('shares'),
        ];
    }

    /**
     * Group (studio type / language) ke hisaab se: posts, avg views, avg interactions.
     *
     * @return Collection<int, array{name:string,posts:int,avg_views:float,avg_interactions:float}>
     */
    private function groupStats(Collection $metrics, \Closure $key): Collection
    {
        return $metrics->groupBy($key)->map(fn (Collection $rows, $name) => [
            'name'             => (string) $name,
            'posts'            => $rows->count(),
            'avg_views'        => round($rows->avg(fn ($m) => (int) $m->views), 1),
            'avg_interactions' => round($rows->avg(fn ($m) => $m->interactions()), 1),
        ])->sortByDesc('avg_views')->values();
    }

    /**
     * Kis ghante post kiye gaye IG posts ko sabse zyada views mile (kam se kam 2 posts wale ghante).
     *
     * @return Collection<int, array{time:string,posts:int,avg_views:float,avg_interactions:float}>
     */
    private function bestHours(Collection $igRows): Collection
    {
        return $igRows->filter(fn ($m) => $m->card->ig_posted_at)
            ->groupBy(fn ($m) => $m->card->ig_posted_at->hour)
            ->filter(fn ($rows) => $rows->count() >= 2)
            ->map(fn ($rows, $hour) => [
                'time'             => sprintf('%02d:00 – %02d:00', $hour, ($hour + 1) % 24),
                'posts'            => $rows->count(),
                'avg_views'        => round($rows->avg(fn ($m) => (int) $m->views), 1),
                'avg_interactions' => round($rows->avg(fn ($m) => $m->interactions()), 1),
            ])
            ->sortByDesc(fn ($r) => [$r['avg_views'], $r['avg_interactions']])
            ->take(5)
            ->values();
    }
}
