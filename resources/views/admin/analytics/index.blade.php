@extends('layouts.admin')
@section('title', 'Social Media Analytics & Performance')

@php
    $typeLabels = [
        'story' => '📖 Kahani', 'shayari' => '✍️ Shayari', 'joke' => '😂 Jokes', 'quote' => '💬 Quotes',
        'status' => '📱 Status', 'fact' => '🤯 Facts', 'ukhana' => '🧩 Ukhana', 'quiz' => '🎯 Quiz',
        'yoga' => '🧘 Yoga', 'puzzle' => '🔍 Puzzle', 'mind_reader' => '🔮 Mind Reader',
        'this_or_that' => '⚖️ This or That', 'name_secret' => '🔤 Name Secrets',
    ];
    $n = fn ($v) => number_format((int) $v);
@endphp

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-violet-600 to-indigo-700 rounded-2xl p-6 text-white shadow-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="inline-block px-2.5 py-1 bg-white/20 rounded-full text-xs font-semibold uppercase tracking-wider mb-2">📊 Performance</span>
                <h1 class="text-2xl font-bold">Social Reach &amp; Audience Insights</h1>
                <p class="text-violet-100 text-sm mt-1">
                    Instagram / YouTube ke asli numbers (pichhle 60 din ke posts, har 6 ghante update).
                    @if ($lastFetched)
                        Last update: {{ $lastFetched->diffForHumans() }}.
                    @else
                        Abhi tak data nahi aaya — "Abhi update karo" dabao.
                    @endif
                </p>
            </div>
            <form method="POST" action="{{ route('admin.analytics.refresh') }}" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='⏳ Update ho raha hai…'">
                @csrf
                <button class="bg-white text-indigo-700 font-bold px-4 py-2.5 rounded-xl shadow hover:bg-indigo-50 transition whitespace-nowrap">🔄 Abhi update karo</button>
            </form>
        </div>
    </div>

    {{-- Totals --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Total Published</span><span class="text-lg">📢</span>
            </div>
            <p class="text-3xl font-extrabold text-slate-800">{{ $n($counts['total_posts']) }}</p>
            <p class="text-xs text-slate-400 mt-1">{{ $counts['ig_posts'] }} IG • {{ $counts['yt_posts'] }} YT • {{ $counts['fb_posts'] }} FB</p>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Views</span><span class="text-lg">👁️</span>
            </div>
            <p class="text-3xl font-extrabold text-indigo-600">{{ $n($totals['ig']['views'] + $totals['yt']['views']) }}</p>
            <p class="text-xs text-slate-400 mt-1">IG {{ $n($totals['ig']['views']) }} • YT {{ $n($totals['yt']['views']) }}</p>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Likes</span><span class="text-lg">❤️</span>
            </div>
            <p class="text-3xl font-extrabold text-rose-600">{{ $n($totals['ig']['likes'] + $totals['yt']['likes']) }}</p>
            <p class="text-xs text-slate-400 mt-1">IG {{ $n($totals['ig']['likes']) }} • YT {{ $n($totals['yt']['likes']) }}</p>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Comments / Saves / Shares</span><span class="text-lg">💬</span>
            </div>
            <p class="text-3xl font-extrabold text-amber-600">{{ $n($totals['ig']['comments'] + $totals['yt']['comments']) }}</p>
            <p class="text-xs text-slate-400 mt-1">IG saves {{ $n($totals['ig']['saves']) }} • shares {{ $n($totals['ig']['shares']) }}</p>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        {{-- Studio wise --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
            <h2 class="font-bold text-slate-800 text-sm mb-1">🏆 Kaunsa content sabse achha chal raha hai</h2>
            <p class="text-[11px] text-slate-400 mb-3">Har post ke average views aur interactions (likes + comments + saves + shares).</p>
            @if ($byType->isEmpty())
                <p class="text-sm text-slate-400 py-6 text-center">Abhi data nahi — "Abhi update karo" dabao.</p>
            @else
                @php $maxViews = max(1, $byType->max('avg_views')); @endphp
                <div class="space-y-2.5">
                    @foreach ($byType as $row)
                        <div class="text-xs">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-semibold text-slate-700">{{ $typeLabels[$row['name']] ?? ucfirst($row['name']) }} <span class="text-slate-400 font-normal">({{ $row['posts'] }} posts)</span></span>
                                <span class="text-slate-600"><b>{{ $n($row['avg_views']) }}</b> views • <b>{{ $row['avg_interactions'] }}</b> interactions / post</span>
                            </div>
                            <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-2 bg-gradient-to-r from-violet-500 to-indigo-600" style="width: {{ round($row['avg_views'] / $maxViews * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Best time + language --}}
        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <h3 class="font-bold text-slate-800 text-sm mb-1">⏰ Best Time to Post</h3>
                <p class="text-[11px] text-slate-400 mb-3">Aapke IG posts — kis ghante post kiye gaye posts ko sabse zyada views mile.</p>
                @if ($bestHours->isEmpty())
                    <p class="text-xs text-slate-400">Abhi kaafi data nahi — har ghante ke kam se kam 2 posts chahiye.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($bestHours as $h)
                            <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-100 text-xs flex items-center justify-between">
                                <span class="font-bold text-slate-800">{{ $h['time'] }}</span>
                                <span class="text-slate-500">{{ $n($h['avg_views']) }} views • {{ $h['posts'] }} posts</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <h3 class="font-bold text-slate-800 text-sm mb-3">🌐 Language</h3>
                @forelse ($byLanguage as $row)
                    <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-50">
                        <span class="font-medium text-slate-700">{{ ucfirst($row['name']) }} <span class="text-slate-400">({{ $row['posts'] }})</span></span>
                        <span class="text-slate-600">{{ $n($row['avg_views']) }} views / post</span>
                    </div>
                @empty
                    <p class="text-xs text-slate-400">Abhi data nahi.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Top posts --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <h2 class="font-bold text-slate-800 text-sm mb-4">🔥 Top 10 Posts</h2>
        @if ($topPosts->isEmpty())
            <p class="text-sm text-slate-400 py-6 text-center">Abhi data nahi — "Abhi update karo" dabao.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase font-semibold">
                            <th class="pb-2.5">Post</th>
                            <th class="pb-2.5">Type</th>
                            <th class="pb-2.5 text-right">Views</th>
                            <th class="pb-2.5 text-right">Likes</th>
                            <th class="pb-2.5 text-right">Comments</th>
                            <th class="pb-2.5 text-right">Saves</th>
                            <th class="pb-2.5 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($topPosts as $m)
                            @php $story = $m->card->part->story; @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="py-2.5 font-semibold text-slate-800 max-w-[220px] truncate">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold {{ $m->platform === 'yt' ? 'bg-red-100 text-red-700' : 'bg-pink-100 text-pink-700' }}">{{ strtoupper($m->platform) }}</span>
                                    {{ $story->title }}
                                </td>
                                <td class="py-2.5 text-slate-500">{{ $typeLabels[$story->type] ?? ucfirst((string) $story->type) }}</td>
                                <td class="py-2.5 text-right font-bold text-indigo-600">{{ $m->views !== null ? $n($m->views) : '—' }}</td>
                                <td class="py-2.5 text-right text-rose-600">{{ $m->likes !== null ? $n($m->likes) : '—' }}</td>
                                <td class="py-2.5 text-right text-amber-600">{{ $m->comments !== null ? $n($m->comments) : '—' }}</td>
                                <td class="py-2.5 text-right text-slate-600">{{ $m->saves !== null ? $n($m->saves) : '—' }}</td>
                                <td class="py-2.5 text-right">
                                    @if ($m->permalink)
                                        <a href="{{ $m->permalink }}" target="_blank" rel="noopener" class="text-indigo-600 hover:underline font-semibold">Dekho ↗</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Topics --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <h3 class="font-bold text-slate-800 text-sm mb-3">🗂 Most Generated Topics</h3>
        @forelse ($categoryBreakdown as $cat)
            <div class="flex items-center justify-between text-xs py-1 border-b border-slate-50">
                <span class="font-medium text-slate-700 capitalize">🎯 {{ $cat->category }}</span>
                <span class="px-2 py-0.5 bg-violet-50 text-violet-700 font-bold rounded-full">{{ $cat->count }}</span>
            </div>
        @empty
            <p class="text-xs text-slate-400">No categories found.</p>
        @endforelse
    </div>
</div>
@endsection
