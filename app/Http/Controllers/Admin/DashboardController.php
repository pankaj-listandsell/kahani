<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\PartCard;
use App\Models\Story;
use App\Services\InstagramService;
use App\Services\YoutubeService;

class DashboardController extends Controller
{
    public function index(InstagramService $instagramService, YoutubeService $youtubeService)
    {
        $user = auth()->user();

        // Regular user sirf apni stories/parts/cards ginta hai; admin sabki
        $storyIds = $user->isAdmin()
            ? Story::pluck('id')
            : Story::where('user_id', $user->id)->pluck('id');

        $stats = [
            'stories' => $storyIds->count(),
            'parts'   => Part::whereIn('story_id', $storyIds)->count(),
            'cards'   => PartCard::whereHas('part', fn ($q) => $q->whereIn('story_id', $storyIds))->count(),
        ];

        $recentQuery = Story::withCount('parts')->latest()->take(5);
        if (! $user->isAdmin()) {
            $recentQuery->where('user_id', $user->id);
        }
        $recent = $recentQuery->get();

        // Social Media Token Health Checks
        $igHealth = $instagramService->forUser($user->id)->checkTokenHealth();
        $ytHealth = $youtubeService->forUser($user->id)->checkTokenHealth();

        // Failed auto-post cards for 1-click retry
        $failedCards = PartCard::with(['part.story'])
            ->whereHas('part', fn ($q) => $q->whereIn('story_id', $storyIds))
            ->where(function ($q) {
                $q->where('ig_status', 'failed')
                  ->orWhere('yt_status', 'failed')
                  ->orWhere('fb_status', 'failed');
            })
            ->latest()
            ->take(6)
            ->get();

        return view('admin.dashboard', compact('stats', 'recent', 'igHealth', 'ytHealth', 'failedCards'));
    }
}
