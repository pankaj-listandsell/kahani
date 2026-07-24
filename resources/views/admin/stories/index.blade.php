@extends('layouts.admin')
@section('title', 'Stories')

@section('content')
<div class="flex items-center justify-between mb-6 gap-3 flex-wrap">
    <h2 class="text-xl font-bold">My Stories</h2>
    <div class="flex gap-2">
        <button type="button" id="importToggle"
                class="border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium rounded-lg px-4 py-2">
            🔗 Import from URL
        </button>
        <a href="{{ route('admin.stories.create') }}"
           class="bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium rounded-lg px-4 py-2">
            + New Story
        </a>
    </div>
</div>

{{-- Import from another website (URL) --}}
<div id="importBox" class="hidden bg-white rounded-xl border border-slate-200 p-5 mb-6">
    <form method="POST" action="{{ route('admin.stories.import') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Story ya Index/Listing URL</label>
            <input type="url" name="url" required placeholder="https://www.hindikibindi.com/content/vidyarthi/stories/index.php"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <p class="text-xs text-slate-400 mt-1">Abhi support: <b>hindikibindi.com</b>. <b>Index/listing URL</b> do → saari stories apne-aap import ho jaayengi; ek story URL do → sirf wahi. (Max se kam kar sakte ho.)</p>
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">🌐 Language</label>
                <select name="language" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="hindi">हिंदी Hindi</option>
                    <option value="gujarati">ગુજરાતી Gujarati</option>
                    <option value="hinglish">Hindi-English (Roman)</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Max (index ke liye)</label>
                <input type="number" name="limit" min="1" max="200" placeholder="e.g. 20"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <label class="flex items-end gap-2 pb-2 text-sm">
                <input type="checkbox" name="all" value="1" class="rounded border-slate-300">
                <span>Index ki <b>saari</b> stories import karo</span>
            </label>
        </div>
        <div class="flex items-center gap-3">
            <button type="submit" id="importSubmit"
                    class="bg-violet-600 hover:bg-violet-700 text-white font-medium rounded-lg px-5 py-2.5 text-sm">
                ⬇ Import
            </button>
            <span class="text-xs text-slate-400">Import <b>draft</b> me aati hai — review karke publish karein. Duplicate apne-aap skip.</span>
        </div>
    </form>
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

@push('scripts')
<script>
(function () {
    const toggle = document.getElementById('importToggle');
    const box = document.getElementById('importBox');
    if (toggle && box) {
        // Agar validation/flash ke baad box khula rakhna ho to bhi kaam kare
        toggle.addEventListener('click', () => box.classList.toggle('hidden'));
    }
    // Bulk import synchronous hai — submit par disable + "importing" dikha do
    const form = box ? box.querySelector('form') : null;
    const btn = document.getElementById('importSubmit');
    if (form && btn) {
        form.addEventListener('submit', () => {
            btn.disabled = true;
            btn.textContent = '⏳ Import ho rahi hai… (thoda ruko)';
            btn.classList.add('opacity-60');
        });
    }
})();
</script>
@endpush
