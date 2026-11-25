@extends('layouts.admin')
@section('title', $story->title)

@section('content')
<div class="max-w-5xl">
    <a href="{{ route('admin.rashifal.index') }}" class="text-sm text-slate-500 hover:text-sky-700">← 🔯 Aaj nu Rashifal</a>

    <div class="flex items-start justify-between gap-4 mt-2 mb-6 flex-wrap">
        <div>
            <h2 class="text-xl font-bold">🔯 {{ $story->title }}</h2>
            <p class="text-slate-500 mt-1 text-sm">{{ $cards->count() }} rashi cards · {{ ucfirst($story->status) }}</p>
        </div>
        <form method="POST" action="{{ route('admin.rashifal.destroy', $story) }}"
              onsubmit="return confirm('Ye rashifal delete karein?')">
            @csrf @method('DELETE')
            <button class="px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 text-sm">🗑 Delete</button>
        </form>
    </div>

    @if (in_array($story->type, ['rashifal_short', 'rashifal_week'], true))
        @php($isWeek = $story->type === 'rashifal_week')
        {{-- 📺 YouTube Shorts / 🎬 Saptahik video — har part = ek video (sirf YouTube) --}}
        <div class="space-y-4 mb-6">
            @foreach ($story->parts->sortBy('sort_order') as $part)
                @php($first = $part->cards->sortBy('sort_order')->first())
                <div class="bg-white rounded-xl border border-red-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div class="flex items-center gap-3">
                            @if ($isWeek && $part->image_path)
                                <a href="{{ asset('storage/' . $part->image_path) }}" target="_blank" title="Thumbnail">
                                    <img src="{{ asset('storage/' . $part->image_path) }}" class="h-16 aspect-video object-cover rounded border border-slate-200" alt="Thumbnail">
                                </a>
                            @endif
                            <div>
                            <h3 class="font-bold">{{ \App\Models\Story::rashiShortLabel($part->body) }} — {{ $isWeek ? 'YouTube Video' : 'YouTube Short' }}</h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                {{ $part->cards->count() }} frames •
                                @if ($first?->yt_status === 'posted')
                                    ✅ Upload ho gaya{{ $first->yt_video_id ? ' (' . $first->yt_video_id . ')' : '' }}
                                @elseif ($first?->yt_status === 'failed')
                                    ❌ Fail: {{ \Illuminate\Support\Str::limit($first->yt_error, 120) }}
                                @elseif ($isWeek)
                                    ⏳ Auto-post {{ \Illuminate\Support\Carbon::parse($story->category)->format('d M') }} (Somvar) se pehle time slot me upload karega
                                @else
                                    ⏳ Auto-post {{ \Illuminate\Support\Carbon::parse($story->category)->format('d M') }} ko upload karega
                                @endif
                            </p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="short-preview text-xs font-semibold bg-slate-100 hover:bg-slate-200 border border-slate-300 rounded-lg px-3 py-1.5"
                                    data-url="{{ route('admin.rashifal.short.preview', $part) }}" data-slot="short-video-{{ $part->id }}" data-wide="{{ $isWeek ? 1 : 0 }}">▶ Video dekho</button>
                            @if ($first?->yt_status !== 'posted')
                                <form method="POST" action="{{ route('admin.rashifal.short.upload', $part) }}"
                                      onsubmit="if(!confirm('Ye Short abhi YouTube par upload karein?')) return false; this.querySelector('button').disabled=true; this.querySelector('button').textContent='⏳ Upload ho raha hai…'">
                                    @csrf
                                    <button class="text-xs font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg px-3 py-1.5">⬆ Abhi YouTube par upload karo</button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <div id="short-video-{{ $part->id }}" class="mt-3"></div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Reel & auto-post settings --}}
    <div class="mb-6">
        @include('admin.partials._reel_settings')
    </div>

    @if ($cards->isEmpty())
        <div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-500">Koi card nahi.</div>
    @else
        <form id="bulkCardsForm" method="POST" action="{{ route('admin.cards.bulk_destroy') }}">
            @csrf
            <div class="flex items-center justify-between gap-3 mb-4 bg-white border border-slate-200 rounded-xl p-3 shadow-sm flex-wrap">
                <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer select-none">
                    <input type="checkbox" id="selectAllCards" class="rounded border-slate-300 accent-indigo-600 w-4 h-4 cursor-pointer">
                    <span>Select All Cards ({{ $cards->count() }})</span>
                </label>
                <button type="button" id="bulkDeleteCardsBtn" disabled
                        class="text-xs font-bold text-white bg-red-600 hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed px-3.5 py-1.5 rounded-lg shadow-sm transition flex items-center gap-1.5">
                    <span>🗑</span> Delete Selected Cards (<span id="selectedCardsCount">0</span>)
                </button>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                @foreach ($cards as $i => $card)
                    <div class="bg-white rounded-xl border border-slate-200 p-2 shadow-sm relative">
                        <div class="flex items-center justify-between mb-1">
                            <label class="flex items-center gap-1 cursor-pointer">
                                <input type="checkbox" name="ids[]" value="{{ $card->id }}" class="bulk-card rounded border-slate-300 accent-indigo-600 w-4 h-4 cursor-pointer">
                                <span class="text-[11px] font-bold text-indigo-600">🔯 Card {{ $i + 1 }}</span>
                            </label>
                        </div>
                        <div class="media-slot">
                            <a href="{{ asset('storage/' . $card->image_path) }}" target="_blank">
                                <img src="{{ asset('storage/' . $card->image_path) }}" class="w-full rounded-lg border border-slate-100" alt="Card">
                            </a>
                        </div>
                        <div class="flex items-center justify-center gap-2 mt-2 text-xs">
                            <span class="{{ $card->isPosted() ? '' : 'opacity-25 grayscale' }}" title="Instagram">📸</span>
                            <span class="{{ $card->isYtPosted() ? '' : 'opacity-25 grayscale' }}" title="YouTube">▶️</span>
                            <span class="{{ $card->isFbPosted() ? '' : 'opacity-25 grayscale' }}" title="Facebook">📘</span>
                        </div>
                        <button type="button"
                                class="gen-reel mt-2 w-full text-[11px] bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded px-2 py-1.5"
                                data-url="{{ route('admin.cards.reel', $card) }}">▶ Generate Reel</button>
                        <a href="{{ asset('storage/' . $card->image_path) }}" download
                           class="block text-center text-[11px] text-indigo-600 hover:underline mt-1 font-medium">⬇ Image</a>
                        <button type="button" onclick="if(confirm('Ye card delete karein?')) document.getElementById('card-del-{{ $card->id }}').submit();"
                                class="w-full text-[11px] text-red-600 hover:bg-red-50 rounded px-2 py-1 mt-1 font-semibold">
                            🗑 Remove
                        </button>
                    </div>
                @endforeach
            </div>
        </form>

        {{-- Hidden Individual Card Delete Forms --}}
        @foreach ($cards as $card)
            <form id="card-del-{{ $card->id }}" method="POST" action="{{ route('admin.cards.destroy', $card) }}" class="hidden">
                @csrf @method('DELETE')
            </form>
        @endforeach

        <p class="text-xs text-slate-400 mt-4">Har card ek rashi ka bhavishya hai — auto-post inhe sirf isi din post karta hai. Auto-post inhe drip post karta hai.</p>
    @endif
</div>

@push('scripts')
<script>
(function () {
    const CSRF = document.querySelector('meta[name=csrf-token]').content;

    // 📺 Short ka video banake dikhao (upload nahi)
    document.querySelectorAll('.short-preview').forEach(btn => {
        btn.addEventListener('click', async () => {
            const lbl = btn.textContent, slot = document.getElementById(btn.dataset.slot);
            const wide = btn.dataset.wide === '1';
            btn.disabled = true; btn.textContent = wide ? '⏳ Video ban raha hai… (3-8 min, page band mat karo)' : '⏳ Video ban raha hai… (1-2 min)';
            try {
                const r = await fetch(btn.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } });
                const d = await r.json();
                if (!d.ok) throw new Error(d.error || 'Fail');
                slot.innerHTML = '<video controls playsinline class="w-full ' + (wide ? 'max-w-3xl' : 'max-w-xs') + ' rounded-lg border border-slate-200" src="' + d.url + '"></video>';
                slot.querySelector('video').play().catch(() => {});
                btn.textContent = '🔄 Dobara banao';
            } catch (e) { btn.textContent = '⚠ ' + e.message; setTimeout(() => { btn.textContent = lbl; }, 4000); }
            btn.disabled = false;
        });
    });

    document.querySelectorAll('.gen-reel').forEach(btn => {
        btn.addEventListener('click', async () => {
            const cell = btn.closest('div'); const lbl = btn.textContent;
            btn.disabled = true; btn.textContent = '⏳ Reel ban rahi hai…';
            try {
                const r = await fetch(btn.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } });
                const d = await r.json();
                if (d.ok) {
                    const slot = cell.querySelector('.media-slot');
                    slot.innerHTML = '<video controls playsinline class="w-full rounded-lg border border-slate-100" src="' + d.url + '?t=' + Date.now() + '"></video>';
                    slot.querySelector('video').play().catch(() => {});
                    btn.textContent = '🔄 Dobara banao';
                } else { btn.textContent = '⚠ ' + (d.error || 'Fail'); setTimeout(() => { btn.textContent = lbl; }, 3000); }
            } catch (e) { btn.textContent = '⚠ Error'; setTimeout(() => { btn.textContent = lbl; }, 3000); }
            btn.disabled = false;
        });
    });

    // ---------- Bulk Delete Cards ----------
    const selectAllCards = document.getElementById('selectAllCards');
    const bulkDeleteCardsBtn = document.getElementById('bulkDeleteCardsBtn');
    const selectedCardsCount = document.getElementById('selectedCardsCount');
    const bulkCardsForm = document.getElementById('bulkCardsForm');

    if (selectAllCards && bulkCardsForm) {
        const updateCardsState = () => {
            const items = bulkCardsForm.querySelectorAll('.bulk-card');
            const checked = bulkCardsForm.querySelectorAll('.bulk-card:checked');
            if (selectedCardsCount) selectedCardsCount.textContent = checked.length;
            if (bulkDeleteCardsBtn) bulkDeleteCardsBtn.disabled = checked.length === 0;
            if (selectAllCards) {
                selectAllCards.checked = items.length > 0 && checked.length === items.length;
                selectAllCards.indeterminate = checked.length > 0 && checked.length < items.length;
            }
        };

        selectAllCards.addEventListener('change', () => {
            bulkCardsForm.querySelectorAll('.bulk-card').forEach(cb => cb.checked = selectAllCards.checked);
            updateCardsState();
        });

        bulkCardsForm.querySelectorAll('.bulk-card').forEach(cb => {
            cb.addEventListener('change', updateCardsState);
        });

        if (bulkDeleteCardsBtn) {
            bulkDeleteCardsBtn.addEventListener('click', () => {
                const count = bulkCardsForm.querySelectorAll('.bulk-card:checked').length;
                if (count > 0 && confirm(`Kya aap sach me chune hue ${count} cards ko delete karna chahte hain?`)) {
                    bulkDeleteCardsBtn.disabled = true;
                    bulkDeleteCardsBtn.textContent = '⏳ Deleting…';
                    bulkCardsForm.submit();
                }
            });
        }
    }
})();
</script>
@endpush
@endsection
