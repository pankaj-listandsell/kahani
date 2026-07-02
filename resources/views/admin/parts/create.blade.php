@extends('layouts.admin')
@section('title', 'Add Part')

@section('content')
<div class="max-w-2xl">
    <a href="{{ route('admin.stories.show', $story) }}" class="text-sm text-slate-500 hover:text-rose-700">← Back</a>
    <h2 class="text-xl font-bold mt-2 mb-1">Add Part</h2>
    <p class="text-slate-500 mb-6">Story: <span class="font-medium">{{ $story->title }}</span></p>

    <form method="POST" action="{{ route('admin.parts.store', $story) }}"
          class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
        @csrf

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Part No. *</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $nextOrder) }}" min="1" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none">
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-medium mb-1">Part Title (optional)</label>
                <input type="text" name="title" value="{{ old('title') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none"
                       placeholder="e.g. जंगल में पहला कदम">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Story Text (Hindi) *</label>
            <textarea name="body" rows="12" required
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 kahani-text focus:ring-2 focus:ring-rose-400 focus:outline-none"
                      placeholder="Paste the full story text for this part here...">{{ old('body') }}</textarea>
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800">
            💡 After saving, you'll go straight to the <b>card editor</b>, where this text is automatically split into multiple image cards.
        </div>

        <button class="bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg px-5 py-2.5">
            Save Part → Make Cards
        </button>
    </form>
</div>
@endsection
