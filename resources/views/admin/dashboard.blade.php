@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
{{-- Social Media Token Health Bar --}}
<div class="bg-white rounded-xl border border-slate-200 p-4 mb-6 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <span class="text-lg">🛡️</span>
            <h3 class="text-sm font-bold text-slate-800">Social Accounts Health &amp; Token Status</h3>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            {{-- Instagram Health Badge --}}
            <a href="{{ route('admin.instagram.index') }}" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border text-xs font-semibold transition {{ $igHealth['status'] === 'active' ? 'bg-emerald-50 border-emerald-300 text-emerald-800 hover:bg-emerald-100' : ($igHealth['status'] === 'expiring_soon' ? 'bg-amber-50 border-amber-300 text-amber-800 hover:bg-amber-100' : 'bg-rose-50 border-rose-300 text-rose-800 hover:bg-rose-100') }}">
                <span>📸 Instagram:</span>
                @if ($igHealth['status'] === 'active')
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>{{ $igHealth['username'] ? '@'.$igHealth['username'] : 'Active' }}</span>
                    @if ($igHealth['days_left'])
                        <span class="opacity-75 font-normal">({{ $igHealth['days_left'] }}d left)</span>
                    @endif
                @elseif ($igHealth['status'] === 'expiring_soon')
                    <span class="inline-block w-2 h-2 rounded-full bg-amber-500"></span>
                    <span>⚠️ Expiring ({{ $igHealth['days_left'] }}d left)</span>
                @else
                    <span class="inline-block w-2 h-2 rounded-full bg-rose-500"></span>
                    <span>🔴 Token Expired (Click to Fix)</span>
                @endif
            </a>

            {{-- YouTube Health Badge --}}
            <a href="{{ route('admin.youtube.index') }}" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border text-xs font-semibold transition {{ $ytHealth['status'] === 'active' ? 'bg-emerald-50 border-emerald-300 text-emerald-800 hover:bg-emerald-100' : 'bg-rose-50 border-rose-300 text-rose-800 hover:bg-rose-100' }}">
                <span>▶️ YouTube:</span>
                @if ($ytHealth['status'] === 'active')
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span>{{ $ytHealth['channel'] ?? 'Connected' }}</span>
                @else
                    <span class="inline-block w-2 h-2 rounded-full bg-rose-500"></span>
                    <span>🔴 Disconnected (Connect)</span>
                @endif
            </a>
        </div>
    </div>
</div>

{{-- Failed Auto-Post Alerts / Retry Box (If Any) --}}
@if (isset($failedCards) && $failedCards->isNotEmpty())
<div class="bg-rose-50 border border-rose-200 rounded-xl p-4 mb-6">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-bold text-rose-800 flex items-center gap-2">
            <span>⚠️</span> Failed Auto-Posts ({{ $failedCards->count() }})
        </h3>
        <span class="text-xs text-rose-600 font-medium">Please review or retry</span>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach ($failedCards as $fCard)
            <div class="bg-white p-3 rounded-lg border border-rose-100 text-xs shadow-sm flex flex-col justify-between">
                <div>
                    <p class="font-bold text-slate-800 truncate mb-1">{{ $fCard->part?->story?->title ?? 'Story' }} #{{ $fCard->sort_order }}</p>
                    <p class="text-slate-600 line-clamp-2 mb-2">{{ $fCard->text }}</p>
                    <p class="text-rose-600 text-[11px] font-medium truncate mb-2">❌ {{ $fCard->ig_error ?: ($fCard->yt_error ?: $fCard->fb_error) }}</p>
                </div>
                <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                    <a href="{{ route('admin.parts.cards', $fCard->part_id) }}" class="text-violet-600 hover:underline font-semibold">View Card</a>
                    @if ($fCard->ig_status === 'failed')
                        <form method="POST" action="{{ route('admin.instagram.card.post', $fCard) }}" class="inline">
                            @csrf
                            <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white px-2 py-1 rounded text-[11px] font-semibold">Retry IG</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- Stats Overview --}}
<div class="grid sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <div class="text-3xl">📚</div>
        <p class="text-3xl font-bold mt-2 text-slate-800">{{ $stats['stories'] }}</p>
        <p class="text-slate-500 text-sm">Total Stories &amp; Quizzes</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <div class="text-3xl">📖</div>
        <p class="text-3xl font-bold mt-2 text-slate-800">{{ $stats['parts'] }}</p>
        <p class="text-slate-500 text-sm">Total Parts</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <div class="text-3xl">🖼️</div>
        <p class="text-3xl font-bold mt-2 text-slate-800">{{ $stats['cards'] }}</p>
        <p class="text-slate-500 text-sm">Total Cards Generated</p>
    </div>
</div>

{{-- Quick Studio Shortcuts --}}
<div class="grid sm:grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <a href="{{ route('admin.quiz.index') }}" class="flex items-center gap-3 p-3.5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white rounded-xl shadow-sm transition">
        <span class="text-2xl">🎯</span>
        <div>
            <p class="font-bold text-sm">Quiz Studio</p>
            <p class="text-[11px] text-amber-100">MCQ &amp; Timer Reels</p>
        </div>
    </a>
    <a href="{{ route('admin.studio.index') }}" class="flex items-center gap-3 p-3.5 bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white rounded-xl shadow-sm transition">
        <span class="text-2xl">✍️</span>
        <div>
            <p class="font-bold text-sm">Shayari Studio</p>
            <p class="text-[11px] text-rose-100">Quotes &amp; Status</p>
        </div>
    </a>
    <a href="{{ route('admin.stories.create') }}" class="flex items-center gap-3 p-3.5 bg-gradient-to-r from-violet-500 to-violet-600 hover:from-violet-600 hover:to-violet-700 text-white rounded-xl shadow-sm transition">
        <span class="text-2xl">📖</span>
        <div>
            <p class="font-bold text-sm">Story Studio</p>
            <p class="text-[11px] text-violet-100">Visual Multi-Part AI</p>
        </div>
    </a>
    <a href="{{ route('admin.puzzle.index') }}" class="flex items-center gap-3 p-3.5 bg-gradient-to-r from-orange-500 to-amber-600 hover:from-orange-600 hover:to-amber-700 text-white rounded-xl shadow-sm transition">
        <span class="text-2xl">🔍</span>
        <div>
            <p class="font-bold text-sm">Puzzle Studio</p>
            <p class="text-[11px] text-amber-100">Find The Odd One</p>
        </div>
    </a>
    <a href="{{ route('admin.mind_reader.index') }}" class="flex items-center gap-3 p-3.5 bg-gradient-to-r from-purple-600 to-indigo-700 hover:from-purple-700 hover:to-indigo-800 text-white rounded-xl shadow-sm transition">
        <span class="text-2xl">🔮</span>
        <div>
            <p class="font-bold text-sm">Mind Reader</p>
            <p class="text-[11px] text-purple-100">Magic Math Tricks</p>
        </div>
    </a>
    <a href="{{ route('admin.this_or_that.index') }}" class="flex items-center gap-3 p-3.5 bg-gradient-to-r from-teal-600 to-cyan-600 hover:from-teal-700 hover:to-cyan-700 text-white rounded-xl shadow-sm transition">
        <span class="text-2xl">⚖️</span>
        <div>
            <p class="font-bold text-sm">This or That</p>
            <p class="text-[11px] text-teal-100">Choose 1 Debate</p>
        </div>
    </a>
    <a href="{{ route('admin.name_secret.index') }}" class="flex items-center gap-3 p-3.5 bg-gradient-to-r from-pink-600 to-rose-600 hover:from-pink-700 hover:to-rose-700 text-white rounded-xl shadow-sm transition">
        <span class="text-2xl">🔤</span>
        <div>
            <p class="font-bold text-sm">Name Secrets</p>
            <p class="text-[11px] text-pink-100">Personality Traits</p>
        </div>
    </a>
    <a href="{{ route('admin.yoga.index') }}" class="flex items-center gap-3 p-3.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white rounded-xl shadow-sm transition">
        <span class="text-2xl">🧘</span>
        <div>
            <p class="font-bold text-sm">Yoga Studio</p>
            <p class="text-[11px] text-emerald-100">Kids &amp; Fitness</p>
        </div>
    </a>
</div>

{{-- Recent Content --}}
<div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
    <div class="flex items-center justify-between mb-3">
        <h2 class="font-semibold text-slate-800">Recent Content</h2>
        <a href="{{ route('admin.stories.index') }}" class="text-xs text-violet-600 hover:underline font-semibold">View All &rarr;</a>
    </div>
    @if ($recent->isEmpty())
        <p class="text-slate-500 text-sm py-6 text-center">No stories yet. Click above shortcuts to start generating.</p>
    @else
        <div class="divide-y divide-slate-100">
            @foreach ($recent as $story)
                <a href="{{ route('admin.stories.show', $story) }}" class="flex items-center justify-between py-3 hover:bg-slate-50 -mx-2 px-2 rounded-lg transition">
                    <div>
                        <p class="font-semibold text-slate-800 text-sm">{{ $story->title }}</p>
                        <p class="text-xs text-slate-500">{{ $story->parts_count }} parts • {{ ucfirst($story->type ?? 'story') }} • {{ ucfirst($story->language ?? 'gujarati') }}</p>
                    </div>
                    <span class="text-xs px-2.5 py-1 rounded-full font-semibold {{ $story->status === 'published' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ ucfirst($story->status) }}
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
