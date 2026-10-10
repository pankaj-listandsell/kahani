<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartCard;
use App\Models\Story;
use App\Services\InstagramService;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Sirf ASLI data: post counts DB se, likes/comments Instagram Graph API se.
 * (Pehle reach/likes "posts × 450" jaise formulas se bante the — wo hata diye.)
 */
class AnalyticsController extends Controller
{
    /** Kitne recent IG posts ka live data laana hai. */
    private const IG_SAMPLE = 25;

    public function index(Request $request, InstagramService $instagram)
    {
        $user = auth()->user();
        $scope = fn ($q) => $q->whereHas('part.story', fn ($s) => $user->isAdmin() ? $s : $s->where('user_id', $user->id));

        $stats = [
            'ig_posts' => PartCard::where($scope)->where('ig_status', 'posted')->whereNotNull('ig_media_id')->count(),
            'yt_posts' => PartCard::where($scope)->where('yt_status', 'posted')->whereNotNull('yt_video_id')->count(),
            'fb_posts' => PartCard::where($scope)->where('fb_status', 'posted')->count(),
        ];
        $stats['total_posts'] = $stats['ig_posts'] + $stats['yt_posts'] + $stats['fb_posts'];

        $igConnected = (bool) $instagram->forUser($user->id)->token();
        $insights = $igConnected ? $this->igInsights($instagram, $user->id, $scope) : [];

        $sampled = count($insights);
        $likes = array_sum(array_column($insights, 'likes'));
        $comments = array_sum(array_column($insights, 'comments'));

        $stats += [
            'ig_sampled'        => $sampled,
            'total_likes'       => $likes,
            'total_comments'    => $comments,
            // Engagement per post (likes + comments) — reach ke bina % nahi nikal sakte
            'avg_interactions'  => $sampled ? round(($likes + $comments) / $sampled, 1) : null,
        ];

        $categoryBreakdown = Story::when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->selectRaw('category, COUNT(*) as count')
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderByDesc('count')
            ->take(5)
            ->get();

        return view('admin.analytics.index', [
            'stats'             => $stats,
            'igConnected'       => $igConnected,
            'recentIgInsights'  => array_slice($insights, 0, 10),
            'bestHours'         => $this->bestHours($insights),
            'categoryBreakdown' => $categoryBreakdown,
        ]);
    }

    /**
     * Recent IG posts ke likes/comments (parallel requests, 15 min cache).
     *
     * @return array<int, array{id:int,title:string,type:string,language:string,likes:int,comments:int,permalink:?string,posted_at:string,hour:?int}>
     */
    private function igInsights(InstagramService $instagram, int $userId, \Closure $scope): array
    {
        $cards = PartCard::with('part.story')
            ->where($scope)
            ->where('ig_status', 'posted')
            ->whereNotNull('ig_media_id')
            ->latest('ig_posted_at')
            ->take(self::IG_SAMPLE)
            ->get();

        if ($cards->isEmpty()) {
            return [];
        }

        $key = "analytics.ig.{$userId}." . md5($cards->pluck('ig_media_id')->implode(','));

        return Cache::remember($key, now()->addMinutes(15), function () use ($cards, $instagram) {
            $base = $instagram->apiBase();
            $token = $instagram->token();

            $responses = Http::pool(fn (Pool $pool) => $cards->map(
                fn (PartCard $c) => $pool->as((string) $c->id)->timeout(15)->get("{$base}/{$c->ig_media_id}", [
                    'fields'       => 'like_count,comments_count,permalink',
                    'access_token' => $token,
                ])
            )->all());

            $rows = [];
            foreach ($cards as $c) {
                $res = $responses[(string) $c->id] ?? null;
                if (! $res instanceof \Illuminate\Http\Client\Response || ! $res->successful()) {
                    continue; // media delete ho gaya / token issue — is post ko chhod do
                }

                $story = $c->part?->story;
                $rows[] = [
                    'id'        => $c->id,
                    'title'     => $story?->title ?? 'Post',
                    'type'      => $story?->type ?? '—',
                    'language'  => $story?->language ?? '—',
                    'likes'     => (int) $res->json('like_count', 0),
                    'comments'  => (int) $res->json('comments_count', 0),
                    'permalink' => $res->json('permalink'),
                    'posted_at' => $c->ig_posted_at?->diffForHumans() ?? '—',
                    'hour'      => $c->ig_posted_at?->hour,
                ];
            }

            return $rows;
        });
    }

    /**
     * Aapke apne IG posts ke hisaab se kis ghante post karne par sabse zyada
     * likes+comments aaye. Kam se kam 2 posts wale ghante hi gine jaate hain.
     *
     * @return array<int, array{time:string, posts:int, avg:float}>
     */
    private function bestHours(array $insights): array
    {
        $byHour = [];
        foreach ($insights as $row) {
            if ($row['hour'] === null) {
                continue;
            }
            $byHour[$row['hour']][] = $row['likes'] + $row['comments'];
        }

        $out = [];
        foreach ($byHour as $hour => $vals) {
            if (count($vals) < 2) {
                continue;
            }
            $out[] = [
                'time'  => sprintf('%02d:00 – %02d:00', $hour, ($hour + 1) % 24),
                'posts' => count($vals),
                'avg'   => round(array_sum($vals) / count($vals), 1),
            ];
        }

        usort($out, fn ($a, $b) => $b['avg'] <=> $a['avg']);

        return array_slice($out, 0, 4);
    }
}
