@extends('layouts.admin')
@section('title', 'Social Media Analytics & Performance')

@section('content')
<div class="space-y-6">
    {{-- Header with Insights Summary --}}
    <div class="bg-gradient-to-r from-violet-600 to-indigo-700 rounded-2xl p-6 text-white shadow-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="inline-block px-2.5 py-1 bg-white/20 rounded-full text-xs font-semibold uppercase tracking-wider mb-2">📊 Real-Time Performance</span>
                <h1 class="text-2xl font-bold">Social Reach &amp; Audience Insights</h1>
                <p class="text-violet-100 text-sm mt-1">Track Instagram, YouTube, and Facebook engagement across all auto-posts.</p>
            </div>
            <div class="bg-white/10 backdrop-blur-md rounded-xl p-3 border border-white/20 text-center">
                <p class="text-xs text-violet-200">Avg. Engagement Rate</p>
                <p class="text-2xl font-extrabold text-white mt-0.5">{{ $stats['avg_engagement'] }}</p>
                <span class="text-[11px] text-emerald-300">▲ +1.2% this week</span>
            </div>
        </div>
    </div>

    {{-- 4 Big Metric Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Total Published</span>
                <span class="text-lg">📢</span>
            </div>
            <p class="text-3xl font-extrabold text-slate-800">{{ $stats['total_posts'] }}</p>
            <p class="text-xs text-slate-400 mt-1">{{ $stats['ig_posts'] }} IG • {{ $stats['yt_posts'] }} YT • {{ $stats['fb_posts'] }} FB</p>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Estimated Reach</span>
                <span class="text-lg">👁️</span>
            </div>
            <p class="text-3xl font-extrabold text-indigo-600">{{ number_format($stats['total_reach']) }}+</p>
            <p class="text-xs text-emerald-600 mt-1 font-medium">🔥 High viral potential</p>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Likes &amp; Reactions</span>
                <span class="text-lg">❤️</span>
            </div>
            <p class="text-3xl font-extrabold text-rose-600">{{ number_format($stats['total_likes']) }}</p>
            <p class="text-xs text-slate-400 mt-1">Across reels &amp; posts</p>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Comments &amp; Answers</span>
                <span class="text-lg">💬</span>
            </div>
            <p class="text-3xl font-extrabold text-amber-600">{{ number_format($stats['total_comments']) }}</p>
            <p class="text-xs text-slate-400 mt-1">Quiz options &amp; replies</p>
        </div>
    </div>

    {{-- Main Grid: Recent Instagram Insights & Best Times --}}
    <div class="grid lg:grid-cols-3 gap-6">
        {{-- Live Instagram Post Insights --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                    <span>📸</span> Recent Instagram Posts &amp; Live Engagement
                </h2>
                <span class="text-xs bg-pink-50 text-pink-700 px-2.5 py-1 rounded-full font-semibold border border-pink-200">Graph API Connected</span>
            </div>

            @if (empty($recentIgInsights))
                <div class="text-center py-8 text-slate-400 text-sm">
                    <p class="text-3xl mb-2">📸</p>
                    <p>No recent Instagram post insights to display yet.</p>
                    <p class="text-xs text-slate-400 mt-1">As you auto-post cards or reels, real-time likes and comments will appear here.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-400 uppercase font-semibold">
                                <th class="pb-2.5">Post Title</th>
                                <th class="pb-2.5">Type / Language</th>
                                <th class="pb-2.5 text-center">Likes ❤️</th>
                                <th class="pb-2.5 text-center">Comments 💬</th>
                                <th class="pb-2.5 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recentIgInsights as $ins)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-3 font-semibold text-slate-800 max-w-[180px] truncate">
                                        {{ $ins['title'] }}
                                    </td>
                                    <td class="py-3 text-slate-500">
                                        <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium">{{ ucfirst($ins['type']) }}</span>
                                        <span class="text-slate-400">({{ ucfirst($ins['language']) }})</span>
                                    </td>
                                    <td class="py-3 text-center font-bold text-rose-600">{{ $ins['likes'] }}</td>
                                    <td class="py-3 text-center font-bold text-amber-600">{{ $ins['comments'] }}</td>
                                    <td class="py-3 text-right">
                                        @if ($ins['permalink'])
                                            <a href="{{ $ins['permalink'] }}" target="_blank" class="text-pink-600 hover:underline font-semibold">View Post ↗</a>
                                        @else
                                            <span class="text-slate-400">Posted</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Best Posting Hours & Topic Breakdown --}}
        <div class="space-y-6">
            {{-- Best Posting Times Recommendation --}}
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <h3 class="font-bold text-slate-800 text-sm mb-3 flex items-center gap-2">
                    <span>⏰</span> Best Time to Post (AI Suggested)
                </h3>
                <div class="space-y-3">
                    @foreach ($bestHours as $h)
                        <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-100 text-xs">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-bold text-slate-800">{{ $h['time'] }}</span>
                                <span class="font-semibold text-emerald-600">{{ $h['engagement'] }}</span>
                            </div>
                            <p class="text-slate-500 text-[11px]">{{ $h['type'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Top Categories Breakdown --}}
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <h3 class="font-bold text-slate-800 text-sm mb-3 flex items-center gap-2">
                    <span>🔥</span> Most Generated Topics
                </h3>
                @if ($categoryBreakdown->isEmpty())
                    <p class="text-xs text-slate-400">No categories found.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($categoryBreakdown as $cat)
                            <div class="flex items-center justify-between text-xs py-1 border-b border-slate-50">
                                <span class="font-medium text-slate-700 capitalize">🎯 {{ $cat->category }}</span>
                                <span class="px-2 py-0.5 bg-violet-50 text-violet-700 font-bold rounded-full">{{ $cat->count }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
