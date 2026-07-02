<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\PartCard;
use App\Models\Story;

class DashboardController extends Controller
{
    public function index()
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

        return view('admin.dashboard', compact('stats', 'recent'));
    }
}
