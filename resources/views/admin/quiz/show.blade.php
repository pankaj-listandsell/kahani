@extends('layouts.admin')
@section('title', $story->title)

@section('content')
<div class="max-w-5xl">
    <a href="{{ route('admin.quiz.index') }}" class="text-sm text-slate-500 hover:text-violet-700">← Quiz</a>

    <div class="flex items-start justify-between gap-4 mt-2 mb-6 flex-wrap">
        <div>
            <h2 class="text-xl font-bold">🎯 {{ $story->title }}</h2>
            <p class="text-slate-500 mt-1 text-sm">{{ $cards->count() }} question cards · {{ ucfirst($story->status) }}</p>
        </div>
        <form method="POST" action="{{ route('admin.quiz.destroy', $story) }}"
              onsubmit="return confirm('Poori quiz collection delete karein?')">
            @csrf @method('DELETE')
            <button class="px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 text-sm">🗑 Delete</button>
        </form>
    </div>

    {{-- Reel & auto-post settings --}}
    <div class="mb-6">
        @include('admin.partials._reel_settings')
    </div>

    @if ($hasTimer)
        {{-- Timer reel: question + countdown → answer, sab ek hi video me --}}
        <div class="mb-6 bg-white rounded-xl border border-slate-200 p-4 flex items-center gap-3 flex-wrap">
            <span class="text-sm font-medium">⏱ Countdown timer</span>
            <select id="quizTimer" data-url="{{ route('admin.quiz.timer') }}"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach (\App\Services\QuizReelService::TIMER_CHOICES as $s)
                    <option value="{{ $s }}" @selected($timer === $s)>{{ $s }} second</option>
                @endforeach
            </select>
            <span id="timerSaved" class="text-green-600 text-xs font-medium"></span>
            <p class="text-xs text-slate-500">
                Reel me sawaal + countdown, phir answer — sab ek hi video me. Badalne ke baad
                "Generate Reel" dobara dabao.
            </p>
        </div>
    @endif

    @if ($cards->isEmpty())
        <div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-500">Koi card nahi.</div>
    @else
        <form id="bulkCardsForm" method="POST" action="{{ route('admin.cards.bulk_destroy') }}">
            @csrf
            <div class="flex items-center justify-between gap-3 mb-4 bg-white border border-slate-200 rounded-xl p-3 shadow-sm flex-wrap">
                <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer select-none">
                    <input type="checkbox" id="selectAllCards" class="rounded border-slate-300 accent-violet-600 w-4 h-4 cursor-pointer">
                    <span>Select All Cards ({{ $cards->count() }})</span>
                </label>
                <button type="button" id="bulkDeleteCardsBtn" disabled
                        class="text-xs font-bold text-white bg-red-600 hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed px-3.5 py-1.5 rounded-lg shadow-sm transition flex items-center gap-1.5">
                    <span>🗑</span> Delete Selected Cards (<span id="selectedCardsCount">0</span>)
                </button>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                @foreach ($cards as $i => $card)
                    <div class="bg-white rounded-xl border border-slate-200 p-2 relative">
                        <div class="flex items-center justify-between mb-1">
                            <label class="flex items-center gap-1 cursor-pointer">
                                <input type="checkbox" name="ids[]" value="{{ $card->id }}" class="bulk-card rounded border-slate-300 accent-violet-600 w-4 h-4 cursor-pointer">
                                <span class="text-[11px] font-medium text-violet-600">❓ Q{{ $i + 1 }}</span>
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
                                class="gen-reel mt-2 w-full text-[11px] bg-violet-600 hover:bg-violet-700 text-white rounded px-2 py-1.5"
                                data-url="{{ route('admin.cards.reel', $card) }}">▶ Generate Reel</button>
                        <a href="{{ asset('storage/' . $card->image_path) }}" download
                           class="block text-center text-[11px] text-violet-600 hover:underline mt-1">⬇ Image</a>
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

        <p class="text-xs text-slate-400 mt-4">Har card ek quiz question hai (answer + reason caption me). Auto-post inhe post karta hai. Icon highlight = post ho chuka.</p>
    @endif
</div>

@push('scripts')
<script>
(function () {
    const CSRF = document.querySelector('meta[name=csrf-token]').content;

    // ---------- Countdown timer seconds ----------
    const timerSel = document.getElementById('quizTimer');
    if (timerSel) {
        timerSel.addEventListener('change', async () => {
            const saved = document.getElementById('timerSaved');
            saved.textContent = '…';
            try {
                const r = await fetch(timerSel.dataset.url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ seconds: parseInt(timerSel.value, 10) }),
                });
                saved.textContent = (await r.json()).ok ? '✓ saved' : '⚠ fail';
            } catch (e) { saved.textContent = '⚠ error'; }
            setTimeout(() => { saved.textContent = ''; }, 2500);
        });
    }

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
