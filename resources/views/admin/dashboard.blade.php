@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
<div class="grid sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="text-3xl">📚</div>
        <p class="text-3xl font-bold mt-2">{{ $stats['stories'] }}</p>
        <p class="text-slate-500 text-sm">Total Stories</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="text-3xl">📖</div>
        <p class="text-3xl font-bold mt-2">{{ $stats['parts'] }}</p>
        <p class="text-slate-500 text-sm">Total Parts</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="text-3xl">🖼️</div>
        <p class="text-3xl font-bold mt-2">{{ $stats['cards'] }}</p>
        <p class="text-slate-500 text-sm">Total Text Cards</p>
    </div>
</div>

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.stories.create') }}" class="bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium rounded-lg px-4 py-2.5">+ New Story</a>
    <a href="{{ route('admin.stories.index') }}" class="bg-white border border-slate-300 text-sm rounded-lg px-4 py-2.5 hover:bg-slate-50">All Stories</a>
</div>

<div class="bg-white rounded-xl border border-slate-200 p-5">
    <h2 class="font-semibold mb-3">Recent Stories</h2>
    @if ($recent->isEmpty())
        <p class="text-slate-500 text-sm py-6 text-center">No stories yet. Click "+ New Story" to start.</p>
    @else
        <div class="divide-y divide-slate-100">
            @foreach ($recent as $story)
                <a href="{{ route('admin.stories.show', $story) }}" class="flex items-center justify-between py-3 hover:bg-slate-50 -mx-2 px-2 rounded-lg">
                    <div>
                        <p class="font-medium">{{ $story->title }}</p>
                        <p class="text-xs text-slate-500">{{ $story->parts_count }} parts</p>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $story->status === 'published' ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600' }}">
                        {{ ucfirst($story->status) }}
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
