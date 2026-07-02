@extends('layouts.admin')
@section('title', 'Edit Part')

@section('content')
<div class="max-w-2xl">
    <a href="{{ route('admin.stories.show', $part->story) }}" class="text-sm text-slate-500 hover:text-rose-700">← Back</a>
    <h2 class="text-xl font-bold mt-2 mb-1">Edit Part {{ $part->sort_order }}</h2>
    <p class="text-slate-500 mb-6">Story: <span class="font-medium">{{ $part->story->title }}</span></p>

    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 text-sm text-indigo-800 mb-4 flex items-center justify-between gap-3">
        <span>🖼️ To create/edit this part's text cards (images):</span>
        <a href="{{ route('admin.parts.cards', $part) }}" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-3 py-1.5 shrink-0">Open Card Editor</a>
    </div>

    <form method="POST" action="{{ route('admin.parts.update', $part) }}"
          class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Part No. *</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $part->sort_order) }}" min="1" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none">
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-medium mb-1">Part Title (optional)</label>
                <input type="text" name="title" value="{{ old('title', $part->title) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Story Text (Hindi) *</label>
            <textarea name="body" rows="12" required
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 kahani-text focus:ring-2 focus:ring-rose-400 focus:outline-none">{{ old('body', $part->body) }}</textarea>
        </div>

        <p class="text-xs text-slate-500">Note: changing the text does not auto-update existing cards — reopen the Card Editor and regenerate.</p>

        <button class="bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg px-5 py-2.5">
            Save Changes
        </button>
    </form>
</div>
@endsection
