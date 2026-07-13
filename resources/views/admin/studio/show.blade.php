@extends('layouts.admin')
@section('title', $story->title)

@section('content')
<div class="max-w-5xl">
    <a href="{{ route('admin.studio.index') }}" class="text-sm text-slate-500 hover:text-violet-700">← Shayari &amp; Jokes</a>

    <div class="flex items-start justify-between gap-4 mt-2 mb-6 flex-wrap">
        <div>
            @php($icon = ['shayari'=>'🖊️','joke'=>'😂','quote'=>'🌟','status'=>'🔥','fact'=>'🤯'][$story->type] ?? '✨')
            <h2 class="text-xl font-bold">{{ $icon }} {{ $story->title }}</h2>
            <p class="text-slate-500 mt-1 text-sm">{{ $cards->count() }} cards · {{ ucfirst($story->status) }}</p>
        </div>
        <form method="POST" action="{{ route('admin.studio.destroy', $story) }}"
              onsubmit="return confirm('Poori collection ({{ $cards->count() }} cards) delete karein?')">
            @csrf @method('DELETE')
            <button class="px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 text-sm">🗑 Delete Collection</button>
        </form>
    </div>

    {{-- Reel & auto-post settings (is collection ke liye) --}}
    <div class="mb-6">
        @include('admin.partials._reel_settings')
    </div>

    @if ($cards->isEmpty())
        <div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-500">
            Is collection me koi card nahi.
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            @foreach ($cards as $card)
                <div class="bg-white rounded-xl border border-slate-200 p-2">
                    {{-- Image (reel banne par yahi video se replace ho jaata hai) --}}
                    <div class="media-slot">
                        <a href="{{ asset('storage/' . $card->image_path) }}" target="_blank">
                            <img src="{{ asset('storage/' . $card->image_path) }}"
                                 class="w-full rounded-lg border border-slate-100" alt="Card">
                        </a>
                    </div>

                    {{-- Post status (IG / YouTube / Facebook) --}}
                    <div class="flex items-center justify-center gap-2 mt-2 text-xs">
                        <span class="{{ $card->isPosted() ? '' : 'opacity-25 grayscale' }}" title="Instagram">📸</span>
                        <span class="{{ $card->isYtPosted() ? '' : 'opacity-25 grayscale' }}" title="YouTube">▶️</span>
                        <span class="{{ $card->isFbPosted() ? '' : 'opacity-25 grayscale' }}" title="Facebook">📘</span>
                    </div>

                    {{-- Generate Reel (voice ke saath) + preview --}}
                    <button type="button"
                            class="gen-reel mt-2 w-full text-[11px] bg-violet-600 hover:bg-violet-700 text-white rounded px-2 py-1.5"
                            data-url="{{ route('admin.cards.reel', $card) }}">
                        ▶ Generate Reel
                    </button>

                    <a href="{{ asset('storage/' . $card->image_path) }}" download
                       class="block text-center text-[11px] text-violet-600 hover:underline mt-1">⬇ Image</a>
                </div>
            @endforeach
        </div>

        <p class="text-xs text-slate-400 mt-4">
            Ye cards auto-post me apne-aap ek-ek karke Instagram / YouTube / Facebook par jaate hain
            (jahan connect + auto-post ON ho). Icon highlight = post ho chuka.
        </p>
    @endif
</div>

@push('scripts')
<script>
(function () {
    const CSRF = document.querySelector('meta[name=csrf-token]').content;

    document.querySelectorAll('.gen-reel').forEach(btn => {
        btn.addEventListener('click', async () => {
            const cell = btn.closest('div');
            const lbl = btn.textContent;
            btn.disabled = true;
            btn.textContent = '⏳ Reel ban rahi hai…';

            try {
                const r = await fetch(btn.dataset.url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                });
                const d = await r.json();
                if (d.ok) {
                    // Image ki jagah video (voice ke saath) dikha do
                    const slot = cell.querySelector('.media-slot');
                    slot.innerHTML = '<video controls playsinline class="w-full rounded-lg border border-slate-100"'
                        + ' src="' + d.url + '?t=' + Date.now() + '"></video>';
                    slot.querySelector('video').play().catch(() => {});
                    if (d.warning) slot.insertAdjacentHTML('beforeend',
                        '<p class="text-[10px] text-amber-600 mt-1 leading-tight">⚠ ' + d.warning + '</p>');
                    btn.textContent = '🔄 Dobara banao';
                } else {
                    btn.textContent = '⚠ ' + (d.error || 'Fail');
                    setTimeout(() => { btn.textContent = lbl; }, 3000);
                }
            } catch (e) {
                btn.textContent = '⚠ Error';
                setTimeout(() => { btn.textContent = lbl; }, 3000);
            }
            btn.disabled = false;
        });
    });
})();
</script>
@endpush
@endsection
