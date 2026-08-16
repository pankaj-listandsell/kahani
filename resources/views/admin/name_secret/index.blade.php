@extends('layouts.admin')
@section('title', '🔤 Name & Personality Secrets Studio')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-rose-600 via-pink-600 to-amber-600 rounded-2xl p-6 text-white shadow-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="inline-block px-2.5 py-1 bg-white/20 rounded-full text-xs font-semibold uppercase tracking-wider mb-2">🏷️ Friend Tagging &amp; Share Magnet</span>
                <h1 class="text-2xl font-bold">Name &amp; Personality Secrets Studio</h1>
                <p class="text-rose-100 text-sm mt-1">Generate deep psychological name secrets and zodiac traits by first letter (A-Z) that make people tag friends.</p>
            </div>
            <div class="flex items-center gap-3">
                <button id="saveBtn" disabled class="bg-white text-rose-800 font-bold px-5 py-2.5 rounded-xl shadow hover:bg-rose-50 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-2">
                    <span>💾</span> Save All Secrets
                </button>
            </div>
        </div>
    </div>

    {{-- Controls --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <div class="grid sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🌐 Language</label>
                <select id="language" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-rose-500 focus:outline-none font-medium">
                    <option value="gujarati" selected>ગુજરાતી (Gujarati)</option>
                    <option value="hindi">हिंदी (Hindi)</option>
                    <option value="hinglish">Hinglish</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🔤 Letters to Generate (comma separated)</label>
                <input type="text" id="letters" value="A, S, P, R, K, M, N" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-rose-500 focus:outline-none font-bold uppercase tracking-widest" placeholder="A, S, P, R, K">
            </div>
            <div class="flex items-end">
                <button id="genBtn" class="w-full bg-gradient-to-r from-rose-500 to-pink-600 hover:from-rose-600 hover:to-pink-700 text-white font-bold py-2 px-4 rounded-lg shadow transition flex items-center justify-center gap-2">
                    <span id="genText">Generate Secrets</span>
                </button>
            </div>
        </div>

        {{-- Progress Bar --}}
        <div id="progressBox" class="hidden mt-4 pt-4 border-t border-slate-100">
            <div class="flex items-center justify-between text-xs font-semibold text-slate-600 mb-1">
                <span id="progressText">Generating...</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div id="progressBar" class="bg-gradient-to-r from-rose-500 to-pink-600 h-2 transition-all duration-300" style="width: 0%"></div>
            </div>
        </div>
    </div>

    {{-- Previews Grid --}}
    <div id="emptyState" class="bg-white rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center text-slate-400">
        <span class="text-4xl block mb-2">🔤</span>
        <p class="font-bold text-slate-600">No Name Secrets generated yet</p>
        <p class="text-xs mt-1">Enter letters above &amp; click "Generate Secrets" to create viral personality trait cards.</p>
    </div>

    <div id="previewGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 hidden"></div>

    {{-- Saved Name Secrets Collections --}}
    <div class="mt-10 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <form id="bulkForm" method="POST" action="{{ route('admin.stories.bulk_destroy') }}">
            @csrf
            <div class="flex items-center justify-between gap-4 mb-4 flex-wrap pb-3 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                    <span>💾</span> Saved Name Secrets ({{ $collections->count() }})
                </h3>
                @if ($collections->isNotEmpty())
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-600 cursor-pointer select-none">
                            <input type="checkbox" id="selectAll" class="rounded border-slate-300 accent-pink-600 w-4 h-4 cursor-pointer">
                            <span>Select All</span>
                        </label>
                        <button type="button" id="bulkDeleteBtn" disabled
                                class="text-xs font-bold text-white bg-red-600 hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed px-3.5 py-1.5 rounded-lg shadow-sm transition flex items-center gap-1.5">
                            <span>🗑</span> Delete Selected (<span id="selectedCount">0</span>)
                        </button>
                    </div>
                @endif
            </div>

            @forelse ($collections as $c)
                <div class="flex items-center justify-between bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 mb-2.5 hover:border-pink-400 hover:bg-pink-50/20 transition gap-3">
                    <input type="checkbox" name="ids[]" value="{{ $c->id }}" class="bulk-item rounded border-slate-300 accent-pink-600 w-4 h-4 cursor-pointer">
                    <a href="{{ route('admin.name_secret.show', $c) }}" class="flex-1 flex items-center justify-between gap-3 group">
                        <div>
                            <span class="font-bold text-slate-800 group-hover:text-pink-700 transition">🔤 {{ $c->title }}</span>
                            <span class="text-xs text-slate-500 ml-2">({{ ucfirst($c->language ?? 'gujarati') }})</span>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 bg-pink-100 text-pink-800 rounded-full">
                            {{ $c->parts_count }} cards · {{ ucfirst($c->status) }}
                        </span>
                    </a>
                    <button type="button" onclick="if(confirm('Kya aap sach me ye name secrets collection delete karna chahte hain?')) document.getElementById('single-del-{{ $c->id }}').submit();" class="text-xs font-semibold text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                        <span>🗑</span> Delete
                    </button>
                </div>
            @empty
                <p class="text-sm text-slate-500 text-center py-4">Abhi koi name secret save nahi hai — upar se naya banayein! 🔤</p>
            @endforelse
        </form>

        {{-- Hidden Single Delete Forms --}}
        @foreach ($collections as $c)
            <form id="single-del-{{ $c->id }}" method="POST" action="{{ route('admin.name_secret.destroy', $c) }}" class="hidden">
                @csrf @method('DELETE')
            </form>
        @endforeach
    </div>
</div>

@push('scripts')
<script>
(function() {
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const GEN_URL = @json(route('admin.name_secret.generate'));
    const SAVE_URL = @json(route('admin.name_secret.save'));

    let items = [];
    const el = id => document.getElementById(id);

    el('genBtn').addEventListener('click', async () => {
        const btn = el('genBtn');
        btn.disabled = true;
        el('genText').textContent = 'Generating…';
        el('progressBox').classList.remove('hidden');
        el('progressBar').style.width = '40%';

        try {
            const r = await fetch(GEN_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ letters: el('letters').value, language: el('language').value })
            });
            const d = await r.json();
            if (!d.ok) throw new Error(d.error || 'Failed to generate');

            items = d.items;
            el('progressBar').style.width = '80%';
            renderAllPreviews();

            el('progressBar').style.width = '100%';
            el('saveBtn').disabled = false;
            setTimeout(() => el('progressBox').classList.add('hidden'), 500);
        } catch (e) {
            alert('Error: ' + e.message);
            el('progressBox').classList.add('hidden');
        } finally {
            btn.disabled = false;
            el('genText').textContent = 'Generate Secrets';
        }
    });

    function renderAllPreviews() {
        el('emptyState').classList.add('hidden');
        const grid = el('previewGrid');
        grid.classList.remove('hidden');
        grid.innerHTML = '';

        items.forEach((item, idx) => {
            const cardBox = document.createElement('div');
            cardBox.className = 'bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm flex flex-col';

            const canvas = document.createElement('canvas');
            canvas.width = 1080; canvas.height = 1920;
            canvas.className = 'w-full aspect-[9/16] object-cover bg-slate-900';

            drawNameSecret(canvas, item);

            cardBox.innerHTML = `
                <div class="p-3 bg-rose-50 border-b border-rose-100 flex items-center justify-between text-xs font-bold text-rose-900">
                    <span>Letter '${item.letter}' Personality</span>
                    <span class="px-2 py-0.5 rounded bg-rose-200 text-rose-900 font-extrabold">Best: ${item.best_match || 'All'}</span>
                </div>
                <div class="p-3 flex-1 flex flex-col items-center"></div>
                <div class="p-3 bg-slate-50 border-t border-slate-100 text-xs text-slate-500">
                    <p class="font-medium text-slate-700 truncate">${item.tag_cta}</p>
                </div>
            `;
            cardBox.querySelector('.flex-1').appendChild(canvas);
            grid.appendChild(cardBox);
        });
    }

    function drawNameSecret(c, item) {
        const ctx = c.getContext('2d');
        const W = 1080, H = 1920;

        // Dark Luxury Red/Violet Gradient Background
        const grad = ctx.createLinearGradient(0, 0, 0, H);
        grad.addColorStop(0, '#1c050e');
        grad.addColorStop(1, '#3b0d1e');
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, W, H);

        // Header Title
        ctx.fillStyle = '#fbbf24';
        ctx.font = 'bold 54px "Noto Sans Devanagari", "Noto Sans Gujarati", sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(item.title || `'${item.letter}' અક્ષર વાળા લોકોનું રહસ્ય 👑`, W / 2, 160);

        ctx.fillStyle = 'rgba(255,255,255,0.7)';
        ctx.font = 'bold 36px sans-serif';
        ctx.fillText('સ્વભાવ, ગુણ અને રહસ્ય વિશે જાણો', W / 2, 230);

        // MASSIVE GLOWING LETTER BADGE
        ctx.save();
        ctx.shadowColor = '#f59e0b';
        ctx.shadowBlur = 50;
        ctx.fillStyle = '#f59e0b';
        ctx.beginPath();
        ctx.arc(W / 2, 450, 150, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 8;
        ctx.stroke();
        ctx.restore();

        ctx.fillStyle = '#000000';
        ctx.font = 'extrabold 180px sans-serif';
        ctx.textBaseline = 'middle';
        ctx.fillText(item.letter, W / 2, 460);

        // 3 LUXURY TRAIT CARDS
        const traits = [
            { icon: '💎', text: item.trait_1 },
            { icon: '🔥', text: item.trait_2 },
            { icon: '❤️', text: item.trait_3 }
        ];

        const startY = 660;
        const boxH = 260;
        const boxW = W - 180;

        traits.forEach((t, idx) => {
            const y = startY + idx * (boxH + 35);

            ctx.fillStyle = 'rgba(255,255,255,0.06)';
            ctx.roundRect(90, y, boxW, boxH, 24);
            ctx.fill();
            ctx.strokeStyle = 'rgba(245,158,11,0.4)';
            ctx.lineWidth = 2.5;
            ctx.stroke();

            // Icon circle
            ctx.fillStyle = 'rgba(245,158,11,0.2)';
            ctx.beginPath();
            ctx.arc(180, y + boxH / 2, 50, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = '#ffffff';
            ctx.font = '48px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(t.icon, 180, y + boxH / 2 + 5);

            // Trait text
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 42px "Noto Sans Devanagari", "Noto Sans Gujarati", sans-serif';
            ctx.textAlign = 'left';
            wrapText(ctx, t.text, 270, y + 85, boxW - 210, 58);
        });

        // BEST MATCH BADGE
        ctx.fillStyle = 'rgba(251,191,36,0.15)';
        ctx.roundRect(140, 1580, W - 280, 100, 20);
        ctx.fill();
        ctx.strokeStyle = '#fbbf24';
        ctx.lineWidth = 2;
        ctx.stroke();

        ctx.fillStyle = '#fbbf24';
        ctx.font = 'bold 42px "Noto Sans Devanagari", "Noto Sans Gujarati", sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(`✨ બેસ્ટ જોડી / મિત્ર: ${item.best_match || 'A, S, P, K'} ✨`, W / 2, 1630);

        // TAG CTA
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 44px "Noto Sans Devanagari", "Noto Sans Gujarati", sans-serif';
        ctx.fillText(item.tag_cta || 'આ અક્ષર વાળા ખાસ દોસ્તને ટેગ કરો! 🤝', W / 2, 1750);
    }

    function wrapText(ctx, text, x, y, maxWidth, lineHeight) {
        const words = text.split(' ');
        let line = '';
        for (let n = 0; n < words.length; n++) {
            const testLine = line + words[n] + ' ';
            const metrics = ctx.measureText(testLine);
            if (metrics.width > maxWidth && n > 0) {
                ctx.fillText(line, x, y);
                line = words[n] + ' ';
                y += lineHeight;
            } else {
                line = testLine;
            }
        }
        ctx.fillText(line, x, y);
    }

    // Save All Secrets
    el('saveBtn').addEventListener('click', async () => {
        if (!items.length) return;
        const btn = el('saveBtn');
        btn.disabled = true;
        el('progressBox').classList.remove('hidden');
        el('progressBar').style.width = '30%';

        const off = document.createElement('canvas'); off.width = 1080; off.height = 1920;
        const cardsToSave = [];

        items.forEach((item, idx) => {
            drawNameSecret(off, item);
            const fullVoiceText = item.title + '. ' + item.trait_1 + ' ' + item.trait_2 + ' ' + item.trait_3 + ' ' + item.tag_cta;
            cardsToSave.push({
                order: idx + 1,
                text: fullVoiceText,
                caption: item.caption,
                hashtags: item.hashtags,
                image: off.toDataURL('image/webp', 0.92),
            });
        });

        el('progressBar').style.width = '75%';

        try {
            const r = await fetch(SAVE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ language: el('language').value, cards: cardsToSave })
            });
            const d = await r.json();
            if (!d.ok) throw new Error(d.error || 'Failed to save');

            el('progressBar').style.width = '100%';
            setTimeout(() => window.location = d.redirect, 400);
        } catch (e) {
            alert('Save error: ' + e.message);
            btn.disabled = false;
            el('progressBox').classList.add('hidden');
        }
    });

    // ---------- Bulk Delete ----------
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
                if (count > 0 && confirm(`Kya aap sach me chune hue ${count} secrets ko delete karna chahte hain?`)) {
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
@endsection
