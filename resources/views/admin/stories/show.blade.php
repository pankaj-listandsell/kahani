@extends('layouts.admin')
@section('title', $story->title)

@section('content')
<div class="flex items-start justify-between gap-4 mb-6">
    <div>
        <a href="{{ route('admin.stories.index') }}" class="text-sm text-slate-500 hover:text-rose-700">← All Stories</a>
        <h2 class="text-xl font-bold mt-2">{{ $story->title }}</h2>
        <p class="text-slate-500 mt-1">{{ $story->description }}</p>
    </div>
    <div class="flex flex-col gap-2 text-sm shrink-0">
        <a href="{{ route('admin.stories.edit', $story) }}" class="text-center px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50">Edit Story</a>
        <form method="POST" action="{{ route('admin.stories.destroy', $story) }}" onsubmit="return confirm('This will delete the whole story and all its parts. Are you sure?')">
            @csrf @method('DELETE')
            <button class="w-full px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50">Delete Story</button>
        </form>
    </div>
</div>

{{-- AI Cover Image (Pollinations) — 9:16, reel ka cover banega --}}
<div class="bg-white rounded-xl border border-slate-200 p-5 mb-6">
    <h3 class="font-semibold mb-1">🖼️ Cover Image (AI)</h3>
    <p class="text-sm text-slate-500 mb-4">
        Story se related 9:16 image banao. Yahi image Instagram <b>reel ka cover</b> (thumbnail) banegi.
    </p>
    <div class="flex flex-col sm:flex-row gap-5">
        <div class="shrink-0">
            @if ($story->cover_image)
                <img src="{{ asset('storage/' . $story->cover_image) }}"
                     class="w-40 rounded-lg border border-slate-200 object-cover" style="aspect-ratio:9/16;" alt="Cover">
            @else
                <div class="w-40 rounded-lg border-2 border-dashed border-slate-300 flex items-center justify-center text-slate-400 text-sm"
                     style="aspect-ratio:9/16;">Koi cover nahi</div>
            @endif
        </div>
        <div class="flex-1 space-y-4">
            {{-- Option A: AI se banao --}}
            <form method="POST" action="{{ route('admin.stories.cover.generate', $story) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-sm font-medium mb-1">🎨 AI se banao — image describe karein (English me behtar result)</label>
                    <textarea name="cover_prompt" rows="3" required
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none"
                              placeholder="e.g. magical dark forest at night, glowing fireflies, cinematic, vertical poster">{{ old('cover_prompt', $story->title) }}</textarea>
                    <p class="text-xs text-slate-500 mt-1">Purani cover replace ho jaayegi. Image banne me kuch second lagte hain.</p>
                </div>
                <button class="bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg px-5 py-2.5 text-sm">
                    🎨 Generate Cover Image
                </button>
            </form>

            {{-- ya --}}
            <div class="flex items-center gap-3 text-xs text-slate-400">
                <span class="flex-1 h-px bg-slate-200"></span> YA <span class="flex-1 h-px bg-slate-200"></span>
            </div>

            {{-- Option B: khud se upload karo --}}
            <form method="POST" action="{{ route('admin.stories.cover.upload', $story) }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-sm font-medium mb-1">📤 Apni image upload karo (best: 9:16, e.g. 1080×1920)</label>
                    <input type="file" name="cover_file" accept="image/*" required
                           class="w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-100 file:px-4 file:py-2 file:text-amber-800">
                    <p class="text-xs text-slate-500 mt-1">JPG/PNG chalega. Reel cover ke liye JPG behtar hai. Max 5 MB.</p>
                </div>
                <button class="bg-slate-800 hover:bg-slate-900 text-white font-medium rounded-lg px-5 py-2.5 text-sm">
                    📤 Upload Cover Image
                </button>
            </form>
        </div>
    </div>
</div>

@php($part = $story->parts->first())

{{-- Story ke cards (single part) --}}
<div class="bg-white rounded-xl border border-slate-200 p-5">
    <div class="flex items-center justify-between gap-3 mb-3 flex-wrap">
        <h3 class="text-lg font-semibold">🖼️ Cards</h3>
        @if ($part)
            <a href="{{ route('admin.parts.cards', $part) }}" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg px-4 py-2">
                🖼️ Make / Edit Cards
            </a>
        @endif
    </div>

    @if (! $part)
        <div class="text-center text-slate-500 py-8">
            Story text nahi mila. <a href="{{ route('admin.stories.edit', $story) }}" class="text-rose-600 underline">Edit Story</a> me text daalo.
        </div>
    @else
        {{-- Story text preview --}}
        <p class="kahani-text text-slate-700 text-sm">{{ Str::limit($part->body, 300) }}</p>

        {{-- Saved cards preview --}}
        @if ($part->cards->isNotEmpty())
            <div class="mt-4">
                <p class="text-xs text-slate-500 mb-1">{{ $part->cards->count() }} cards ready:</p>
                <div class="flex gap-3 overflow-x-auto pb-1">
                    @foreach ($part->cards as $card)
                        <div class="shrink-0 text-center">
                            <a href="{{ asset('storage/' . $card->image_path) }}" target="_blank">
                                <img src="{{ asset('storage/' . $card->image_path) }}" class="h-24 rounded-lg border border-slate-200" alt="Card {{ $card->sort_order }}">
                            </a>
                            <a href="{{ asset('storage/' . $card->image_path) }}" download class="block text-[11px] text-rose-600 hover:underline mt-1">⬇ Download</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <p class="text-xs text-amber-600 mt-4">⚠️ Abhi tak koi card nahi bana — "Make / Edit Cards" dabao.</p>
        @endif
    @endif
</div>
@endsection
