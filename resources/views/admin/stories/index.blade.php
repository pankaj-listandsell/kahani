@extends('layouts.admin')
@section('title', 'Stories')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h2 class="text-xl font-bold">My Stories</h2>
    <a href="{{ route('admin.stories.create') }}"
       class="bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium rounded-lg px-4 py-2">
        + New Story
    </a>
</div>

@if ($stories->isEmpty())
    <div class="text-center text-slate-500 bg-white rounded-xl border border-slate-200 py-16">
        No stories yet. Click "New Story" above to begin.
    </div>
@else
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($stories as $story)
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm flex flex-col">
                @if ($story->cover_image)
                    <img src="{{ asset('storage/' . $story->cover_image) }}" class="h-40 w-full object-cover" alt="">
                @else
                    <div class="h-40 w-full bg-amber-100 flex items-center justify-center text-4xl">📚</div>
                @endif
                <div class="p-4 flex-1 flex flex-col">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-semibold text-lg">{{ $story->title }}</h3>
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $story->status === 'published' ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600' }}">
                            {{ ucfirst($story->status) }}
                        </span>
                    </div>
                    <p class="text-sm text-slate-500 mt-1 flex-1">{{ Str::limit($story->description, 80) }}</p>
                    <p class="text-xs text-slate-400 mt-2">{{ $story->parts_count }} parts</p>
                    <div class="flex gap-2 mt-3 text-sm">
                        <a href="{{ route('admin.stories.show', $story) }}" class="flex-1 text-center bg-rose-600 hover:bg-rose-700 text-white rounded-lg py-1.5">Manage</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
