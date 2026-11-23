@extends('layouts.admin')
@section('title', '🚫 Shabd List')

@section('content')
<div class="max-w-4xl space-y-6">
    <a href="{{ route('admin.stories.index') }}" class="text-sm text-slate-500 hover:text-rose-700">← 📚 Stories</a>

    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-xl font-bold">🚫 Ashleel Shabd List</h2>
        <p class="text-sm text-slate-500 mt-1">
            Story <b>paste / import / AI se bane</b> — save hote samay in shabd ki jagah saaf shabd aa jaata hai.
            Sirf <b>poora shabd</b> badalta hai (jaise "ગાંડ" badlega, "ગાંડો" nahi) — isliye doosre roop (ચોદ્યો, લોડાને …) alag line me jodo.
        </p>
        <p class="text-xs mt-2 {{ $aiOn ? 'text-green-700' : 'text-slate-500' }}">
            {{ $aiOn ? '🤖 AI filter chalu hai — list ke baad AI baaki ashleel shabd khud dhoondh ke badalta hai aur neeche "AI e shodhela shabd" me jod deta hai.' : '🤖 AI filter band hai (.env WORD_FILTER_AI=false) — sirf list chalegi.' }}
        </p>
        @unless ($canEdit)
            <p class="text-xs text-amber-700 mt-2">👀 List sab users ke liye ek hi hai — sirf admin badal sakta hai.</p>
        @endunless
    </div>

    @foreach ([
        ['words', '📝 Tamari list', 'Ye hamesha AI wali list se upar maani jaati hai.', $words],
        ['learned', '🤖 AI e shodhela shabd', 'AI ne story me dhoondh ke yahan jode. Galat lage to hatao, sahi ho to "⬆ Mari list" dabao.', $learned],
    ] as [$key, $title, $hint, $map])
        <form method="POST" action="{{ route('admin.stories.words.save') }}" class="bg-white rounded-xl border border-slate-200 p-5 word-list">
            @csrf
            <input type="hidden" name="list" value="{{ $key }}">
            <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
                <div>
                    <h3 class="font-bold">{{ $title }} <span class="text-xs font-normal text-slate-500">({{ count($map) }})</span></h3>
                    <p class="text-xs text-slate-500">{{ $hint }}</p>
                </div>
                @if ($canEdit)
                    <div class="flex gap-2">
                        @if ($key === 'words')
                            @if (Route::has('admin.stories.words.defaults'))
                                <button formaction="{{ route('admin.stories.words.defaults') }}"
                                        onclick="return confirm('Taiyar moti list (Gujarati + Hindi + Hinglish) tamari list ma umerie? Tamara pehla na shabd jem che tem j rahese.')"
                                        class="text-sm border border-amber-300 text-amber-700 rounded-lg px-3 py-1.5 hover:bg-amber-50">⬇ Moti list umero</button>
                            @endif
                            <button type="button" class="add-row text-sm border border-slate-300 rounded-lg px-3 py-1.5 hover:bg-slate-50">+ Navo shabd</button>
                        @endif
                        <button class="text-sm bg-rose-600 hover:bg-rose-700 text-white font-semibold rounded-lg px-4 py-1.5">💾 Save</button>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-[1fr_auto_1fr_auto] gap-2 items-center text-sm rows">
                <div class="text-xs font-bold text-slate-500 uppercase">Kharab shabd</div>
                <div></div>
                <div class="text-xs font-bold text-slate-500 uppercase">Eni jagya e</div>
                <div></div>
                @forelse ($map as $bad => $good)
                    <input name="bad[]" value="{{ $bad }}" {{ $canEdit ? '' : 'readonly' }} class="rounded-lg border border-slate-300 px-3 py-1.5">
                    <span class="text-slate-400">→</span>
                    <input name="good[]" value="{{ $good }}" {{ $canEdit ? '' : 'readonly' }} class="rounded-lg border border-slate-300 px-3 py-1.5">
                    <div class="flex gap-1">
                        @if ($canEdit)
                            @if ($key === 'learned')
                                <button formaction="{{ route('admin.stories.words.promote') }}" name="word" value="{{ $bad }}"
                                        class="text-xs border border-green-300 text-green-700 rounded px-2 py-1 hover:bg-green-50" title="Tamari list ma lai jao">⬆ Mari list</button>
                            @endif
                            <button type="button" class="del-row text-xs text-red-600 border border-red-200 rounded px-2 py-1 hover:bg-red-50" title="Hatao (pachhi Save dabao)">🗑</button>
                        @endif
                    </div>
                @empty
                    <p class="col-span-4 text-sm text-slate-400 py-2 empty-note">{{ $key === 'learned' ? 'Hju AI e koi shabd nathi shodhyo.' : 'List khaali che.' }}</p>
                @endforelse
            </div>
        </form>
    @endforeach

    {{-- 🧪 Test --}}
    <form method="POST" action="{{ route('admin.stories.words.test') }}" class="bg-white rounded-xl border border-slate-200 p-5 space-y-3">
        @csrf
        <h3 class="font-bold">🧪 Test karo</h3>
        <textarea name="text" rows="5" required placeholder="Story no koi bhaag ahiya paste karo…"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm kahani-text">{{ old('text') }}</textarea>
        <div class="flex items-center gap-4 flex-wrap">
            @if ($aiOn)
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="ai" value="1" class="rounded border-slate-300" {{ old('ai') ? 'checked' : '' }}>
                    🤖 AI thi pan shodho <span class="text-xs text-slate-500">(navo shabd male to AI list ma jodashe)</span>
                </label>
            @endif
            <button class="text-sm bg-slate-800 hover:bg-slate-900 text-white font-semibold rounded-lg px-4 py-1.5"
                    onclick="this.textContent='⏳ Tapasi rahyu che…'">Test</button>
        </div>
        @if (session('word_test'))
            <div class="rounded-lg bg-green-50 border border-green-200 p-3">
                <p class="text-xs font-bold text-green-800 mb-1">Result:</p>
                <p class="text-sm whitespace-pre-line kahani-text">{{ session('word_test') }}</p>
            </div>
        @endif
    </form>
</div>

@push('scripts')
<script>
document.querySelectorAll('.word-list').forEach(form => {
    const rows = form.querySelector('.rows');
    // 🗑 — us row ke 4 cells hatao (Save dabane par list se nikal jaayega)
    rows.addEventListener('click', e => {
        const btn = e.target.closest('.del-row');
        if (!btn) return;
        const cell = btn.parentElement;
        [cell.previousElementSibling, cell.previousElementSibling.previousElementSibling, cell.previousElementSibling.previousElementSibling.previousElementSibling]
            .forEach(n => n.remove());
        cell.remove();
    });
    form.querySelector('.add-row')?.addEventListener('click', () => {
        form.querySelector('.empty-note')?.remove();
        const cls = 'rounded-lg border border-slate-300 px-3 py-1.5';
        rows.insertAdjacentHTML('beforeend',
            `<input name="bad[]" class="${cls}" placeholder="kharab shabd">` +
            `<span class="text-slate-400">→</span>` +
            `<input name="good[]" class="${cls}" placeholder="saaf shabd">` +
            `<div class="flex gap-1"><button type="button" class="del-row text-xs text-red-600 border border-red-200 rounded px-2 py-1 hover:bg-red-50">🗑</button></div>`);
        rows.querySelectorAll('input[name="bad[]"]').forEach((i, k, all) => k === all.length - 1 && i.focus());
    });
});
</script>
@endpush
@endsection
