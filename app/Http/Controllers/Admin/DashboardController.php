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
        $stats = [
            'stories' => Story::count(),
            'parts'   => Part::count(),
            'cards'   => PartCard::count(),
        ];

        $recent = Story::withCount('parts')->latest()->take(5)->get();

        return view('admin.dashboard', compact('stats', 'recent'));
    }
}
