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
    <form id="bulkForm" method="POST" action="{{ route('admin.stories.bulk_destroy') }}">
        @csrf
        <div class="flex items-center justify-between gap-4 mb-4 bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex-wrap">
            <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer select-none">
                <input type="checkbox" id="selectAll" class="rounded border-slate-300 accent-rose-600 w-4 h-4 cursor-pointer">
                <span>Select All Stories ({{ $stories->count() }})</span>
            </label>
            <button type="button" id="bulkDeleteBtn" disabled
                    class="text-xs font-bold text-white bg-red-600 hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed px-4 py-2 rounded-lg shadow-sm transition flex items-center gap-1.5">
                <span>🗑</span> Delete Selected (<span id="selectedCount">0</span>)
            </button>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($stories as $story)
                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm flex flex-col relative">
                    <label class="absolute top-3 left-3 z-10 bg-white/90 backdrop-blur rounded-lg p-1.5 shadow-sm border border-slate-200 cursor-pointer">
                        <input type="checkbox" name="ids[]" value="{{ $story->id }}" class="bulk-item rounded border-slate-300 accent-rose-600 w-4 h-4 cursor-pointer">
                    </label>

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
                            <a href="{{ route('admin.stories.show', $story) }}" class="flex-1 text-center bg-rose-600 hover:bg-rose-700 text-white rounded-lg py-1.5 font-medium">Manage</a>
                            <button type="button" onclick="if(confirm('Kya aap sach me ye story delete karna chahte hain?')) document.getElementById('single-del-{{ $story->id }}').submit();" class="px-3 py-1.5 border border-red-200 text-red-600 hover:bg-red-50 rounded-lg text-xs font-semibold flex items-center gap-1" title="Delete">
                                <span>🗑</span> Delete
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </form>

    {{-- Hidden Single Delete Forms --}}
    @foreach ($stories as $story)
        <form id="single-del-{{ $story->id }}" method="POST" action="{{ route('admin.stories.destroy', $story) }}" class="hidden">
            @csrf @method('DELETE')
        </form>
    @endforeach
@endif
@endsection

@push('scripts')
<script>
(function () {
    const toggle = document.getElementById('importToggle');
    const box = document.getElementById('importBox');
    if (toggle && box) {
        toggle.addEventListener('click', () => box.classList.toggle('hidden'));
    }
    const importForm = box ? box.querySelector('form') : null;
    const importBtn = document.getElementById('importSubmit');
    if (importForm && importBtn) {
        importForm.addEventListener('submit', () => {
            importBtn.disabled = true;
            importBtn.textContent = '⏳ Import ho rahi hai… (thoda ruko)';
            importBtn.classList.add('opacity-60');
        });
    }

    // Bulk Delete
    const selectAll = document.getElementById('selectAll');
    const bulkBtn = document.getElementById('bulkDeleteBtn');
    const countSpan = document.getElementById('selectedCount');
    const form = document.getElementById('bulkForm');

    if (selectAll && form) {
        const updateBulkState = () => {
            const items = form.querySelectorAll('.bulk-item');
            const checked = form.querySelectorAll('.bulk-item:checked');
            if (countSpan) countSpan.textContent = checked.length;
            if (bulkBtn) bulkBtn.disabled = checked.length === 0;
            if (selectAll) {
                selectAll.checked = items.length > 0 && checked.length === items.length;
                selectAll.indeterminate = checked.length > 0 && checked.length < items.length;
            }
        };

        selectAll.addEventListener('change', () => {
            form.querySelectorAll('.bulk-item').forEach(cb => cb.checked = selectAll.checked);
            updateBulkState();
        });

        form.querySelectorAll('.bulk-item').forEach(cb => {
            cb.addEventListener('change', updateBulkState);
        });

        if (bulkBtn) {
            bulkBtn.addEventListener('click', () => {
                const count = form.querySelectorAll('.bulk-item:checked').length;
                if (count > 0 && confirm(`Kya aap sach me chune hue ${count} stories ko delete karna chahte hain?`)) {
                    bulkBtn.disabled = true;
                    bulkBtn.textContent = '⏳ Deleting…';
                    form.submit();
                }
            });
        }
    }
})();
</script>
@endpush
