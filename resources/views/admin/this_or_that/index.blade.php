@extends('layouts.admin')
@section('title', '⚖️ This or That Studio — Choose 1 Challenge')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 rounded-2xl p-6 text-white shadow-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="inline-block px-2.5 py-1 bg-white/20 rounded-full text-xs font-semibold uppercase tracking-wider mb-2">⚖️ Comments &amp; Debate Booster</span>
                <h1 class="text-2xl font-bold">This or That Studio — Choose 1 Poll Challenge</h1>
                <p class="text-emerald-100 text-sm mt-1">Split-screen A vs B comparison cards that trigger intense debates and comments on Reels &amp; Shorts.</p>
            </div>
            <div class="flex items-center gap-3">
                <button id="saveBtn" disabled class="bg-white text-emerald-800 font-bold px-5 py-2.5 rounded-xl shadow hover:bg-emerald-50 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-2">
                    <span>💾</span> Save All Polls
                </button>
            </div>
        </div>
    </div>

    {{-- Controls --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <div class="grid sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🌐 Language</label>
                <select id="language" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none font-medium">
                    <option value="gujarati">ગુજરાતી (Gujarati)</option>
                    <option value="hindi" selected>हिंदी (Hindi)</option>
                    <option value="hinglish">Hinglish</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🏷️ Category</label>
                <select id="category" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none font-medium">
                    <option value="Career & Business" selected>💼 Career &amp; Business</option>
                    <option value="Money & Wealth">💰 Money &amp; Luxury</option>
                    <option value="Lifestyle & Travel">🌴 Travel &amp; Lifestyle</option>
                    <option value="Relationships">❤️ Love &amp; Friendship</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🔢 Poll Count</label>
                <select id="count" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none font-medium">
                    <option value="3">3 Polls</option>
                    <option value="5" selected>5 Polls</option>
                    <option value="10">10 Polls</option>
                </select>
            </div>
            <div class="flex items-end">
                <button id="genBtn" class="w-full bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold py-2 px-4 rounded-lg shadow transition flex items-center justify-center gap-2">
                    <span id="genText">Generate Polls</span>
                </button>
            </div>
        </div>

        {{-- Progress Bar --}}
        <div id="progressBox" class="hidden mt-4 pt-4 border-t border-slate-100">
            <div class="flex items-center justify-between text-xs font-semibold text-slate-600 mb-1">
                <span id="progressText">Generating...</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div id="progressBar" class="bg-gradient-to-r from-emerald-500 to-teal-600 h-2 transition-all duration-300" style="width: 0%"></div>
            </div>
        </div>
    </div>

    {{-- Previews Grid --}}
    <div id="emptyState" class="bg-white rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center text-slate-400">
        <span class="text-4xl block mb-2">⚖️</span>
        <p class="font-bold text-slate-600">No This or That polls generated yet</p>
        <p class="text-xs mt-1">Click "Generate Polls" to create viral A vs B comparison cards.</p>
    </div>

    <div id="previewGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 hidden"></div>

    {{-- Saved This or That Collections --}}
    <div class="mt-10 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <form id="bulkForm" method="POST" action="{{ route('admin.stories.bulk_destroy') }}">
            @csrf
            <div class="flex items-center justify-between gap-4 mb-4 flex-wrap pb-3 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                    <span>💾</span> Saved This or That Polls ({{ $collections->count() }})
                </h3>
                @if ($collections->isNotEmpty())
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-600 cursor-pointer select-none">
                            <input type="checkbox" id="selectAll" class="rounded border-slate-300 accent-teal-600 w-4 h-4 cursor-pointer">
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
                <div class="flex items-center justify-between bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 mb-2.5 hover:border-teal-400 hover:bg-teal-50/20 transition gap-3">
                    <input type="checkbox" name="ids[]" value="{{ $c->id }}" class="bulk-item rounded border-slate-300 accent-teal-600 w-4 h-4 cursor-pointer">
                    <a href="{{ route('admin.this_or_that.show', $c) }}" class="flex-1 flex items-center justify-between gap-3 group">
                        <div>
                            <span class="font-bold text-slate-800 group-hover:text-teal-700 transition">⚖️ {{ $c->title }}</span>
                            <span class="text-xs text-slate-500 ml-2">({{ ucfirst($c->language ?? 'gujarati') }})</span>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 bg-teal-100 text-teal-800 rounded-full">
                            {{ $c->parts_count }} cards · {{ ucfirst($c->status) }}
                        </span>
                    </a>
                    <button type="button" onclick="if(confirm('Kya aap sach me ye poll collection delete karna chahte hain?')) document.getElementById('single-del-{{ $c->id }}').submit();" class="text-xs font-semibold text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                        <span>🗑</span> Delete
                    </button>
                </div>
            @empty
                <p class="text-sm text-slate-500 text-center py-4">Abhi koi poll save nahi hai — upar se naya banayein! ⚖️</p>
            @endforelse
        </form>

        {{-- Hidden Single Delete Forms --}}
        @foreach ($collections as $c)
            <form id="single-del-{{ $c->id }}" method="POST" action="{{ route('admin.this_or_that.destroy', $c) }}" class="hidden">
                @csrf @method('DELETE')
            </form>
        @endforeach
    </div>
</div>

@push('scripts')
@include('admin.partials._canvas_kit')
<script>
(function() {
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const GEN_URL = @json(route('admin.this_or_that.generate'));
    const SAVE_URL = @json(route('admin.this_or_that.save'));

    const K = window.CanvasKit;
    let items = [];
    let itemsLang = 'hindi'; // jis language me items generate hue — canvas text bhi usi me
    const el = id => document.getElementById(id);

    const I18N = {
        gujarati: {
            title: 'તમે શું પસંદ કરશો? 🤔', sub: 'આ કે તે? તમારો સાચો જવાબ કમેન્ટ કરો!',
            cta: '👇 તમારી પસંદગી કમેન્ટ બોક્સમાં લખો! 👇', voiceCta: 'તમારી પસંદ કમેન્ટ કરો!',
        },
        hindi: {
            title: 'आप क्या चुनेंगे? 🤔', sub: 'ये या वो? अपना जवाब कमेंट करें!',
            cta: '👇 अपनी पसंद कमेंट बॉक्स में लिखें! 👇', voiceCta: 'अपनी पसंद कमेंट करें!',
        },
        hinglish: {
            title: 'Aap kya chunoge? 🤔', sub: 'Ye ya woh? Apna jawab comment karo!',
            cta: '👇 Apni pasand comment box me likho! 👇', voiceCta: 'Apni pasand comment karo!',
        },
    };
    const tr = () => I18N[itemsLang] || I18N.hindi;

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
                body: JSON.stringify({ count: el('count').value, language: el('language').value, category: el('category').value })
            });
            const d = await r.json();
            if (!d.ok) throw new Error(d.error || 'Failed to generate');

            items = d.items;
            itemsLang = el('language').value;
            el('progressBar').style.width = '80%';
            await K.ensureFonts();
            renderAllPreviews();

            el('progressBar').style.width = '100%';
            el('saveBtn').disabled = false;
            setTimeout(() => el('progressBox').classList.add('hidden'), 500);
        } catch (e) {
            alert('Error: ' + e.message);
            el('progressBox').classList.add('hidden');
        } finally {
            btn.disabled = false;
            el('genText').textContent = 'Generate Polls';
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

            drawThisOrThat(canvas, item);

            cardBox.innerHTML = `
                <div class="p-3 bg-emerald-50 border-b border-emerald-100 flex items-center justify-between text-xs font-bold text-emerald-900">
                    <span>Poll #${idx + 1}: ${K.esc(item.title)}</span>
                </div>
                <div class="p-3 flex-1 flex flex-col items-center"></div>
                <div class="p-3 bg-slate-50 border-t border-slate-100 text-xs text-slate-500">
                    <p class="font-medium text-slate-700 truncate">${K.esc(item.option_a)} VS ${K.esc(item.option_b)}</p>
                </div>
            `;
            cardBox.querySelector('.flex-1').appendChild(canvas);
            grid.appendChild(cardBox);
        });
    }

    function drawThisOrThat(c, item) {
        const ctx = c.getContext('2d');
        const W = 1080, H = 1920;
        const L = tr();
        K.reset(ctx);

        const optA = String(item.option_a || item.optionA || item.option_1 || item.a || '');
        const optB = String(item.option_b || item.optionB || item.option_2 || item.b || '');

        // Background Dark
        ctx.fillStyle = '#0a0d14';
        ctx.fillRect(0, 0, W, H);

        // Header Title
        const title = item.title || L.title;
        ctx.fillStyle = '#fbbf24';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'alphabetic';
        K.fitOneLine(ctx, title, W - 100, 58, 34);
        ctx.fillText(title, W / 2, 180);

        ctx.fillStyle = 'rgba(255,255,255,0.7)';
        K.fitOneLine(ctx, L.sub, W - 120, 36, 26);
        ctx.fillText(L.sub, W / 2, 250);

        // OPTION A BOX (Top Half)
        const boxW = W - 160, boxH = 580;
        const boxX = 80, boxY_A = 340;

        ctx.beginPath();
        const gradA = ctx.createLinearGradient(boxX, boxY_A, boxX + boxW, boxY_A + boxH);
        gradA.addColorStop(0, '#e11d48');
        gradA.addColorStop(1, '#9333ea');
        ctx.fillStyle = gradA;
        ctx.roundRect(boxX, boxY_A, boxW, boxH, 32);
        ctx.fill();
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 4;
        ctx.stroke();

        // Option A Badge
        ctx.beginPath();
        ctx.fillStyle = 'rgba(255,255,255,0.2)';
        ctx.roundRect(boxX + 40, boxY_A + 40, 220, 70, 18);
        ctx.fill();

        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 40px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('OPTION A', boxX + 150, boxY_A + 75);

        // Option A Text — badge ke neeche wale hisse me fit
        ctx.fillStyle = '#ffffff';
        K.fitDraw(ctx, optA, W / 2, boxY_A + 340, { maxW: boxW - 80, maxH: 400, size: 54, minSize: 32, lineHeight: 1.4 });

        // OPTION B BOX (Bottom Half)
        const boxY_B = 1040;

        ctx.beginPath();
        const gradB = ctx.createLinearGradient(boxX, boxY_B, boxX + boxW, boxY_B + boxH);
        gradB.addColorStop(0, '#059669');
        gradB.addColorStop(1, '#0284c7');
        ctx.fillStyle = gradB;
        ctx.roundRect(boxX, boxY_B, boxW, boxH, 32);
        ctx.fill();
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 4;
        ctx.stroke();

        // Option B Badge
        ctx.beginPath();
        ctx.fillStyle = 'rgba(255,255,255,0.2)';
        ctx.roundRect(boxX + 40, boxY_B + 40, 220, 70, 18);
        ctx.fill();

        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 40px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('OPTION B', boxX + 150, boxY_B + 75);

        // Option B Text — badge ke neeche wale hisse me fit
        ctx.fillStyle = '#ffffff';
        K.fitDraw(ctx, optB, W / 2, boxY_B + 340, { maxW: boxW - 80, maxH: 400, size: 54, minSize: 32, lineHeight: 1.4 });

        // GLOWING "VS" CIRCLE IN CENTER
        ctx.save();
        ctx.shadowColor = '#fbbf24';
        ctx.shadowBlur = 40;
        ctx.fillStyle = '#fbbf24';
        ctx.beginPath();
        ctx.arc(W / 2, 980, 105, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 6;
        ctx.stroke();
        ctx.restore();

        ctx.fillStyle = '#000000';
        ctx.font = '900 82px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('VS', W / 2, 982);

        // BOTTOM CTA
        ctx.fillStyle = '#fbbf24';
        ctx.textBaseline = 'alphabetic';
        K.fitOneLine(ctx, L.cta, W - 100, 44, 30);
        ctx.fillText(L.cta, W / 2, 1720);

        ctx.fillStyle = 'rgba(255,255,255,0.7)';
        ctx.font = 'bold 36px sans-serif';
        ctx.fillText('LIKE • SHARE • FOLLOW FOR MORE', W / 2, 1790);
    }

    // Save All Polls
    el('saveBtn').addEventListener('click', async () => {
        if (!items.length) return;
        const btn = el('saveBtn');
        btn.disabled = true;
        el('progressBox').classList.remove('hidden');
        el('progressBar').style.width = '30%';
        await K.ensureFonts();

        const off = document.createElement('canvas'); off.width = 1080; off.height = 1920;
        const cardsToSave = [];

        items.forEach((item, idx) => {
            drawThisOrThat(off, item);
            const fullVoiceText = (item.title || tr().title) + '. Option A: ' + item.option_a + '. Option B: ' + item.option_b + '. ' + tr().voiceCta;
            cardsToSave.push({
                order: idx + 1,
                text: fullVoiceText,
                caption: K.str(item.caption),
                hashtags: K.str(item.hashtags),
                image: off.toDataURL('image/webp', 0.92),
            });
        });

        el('progressBar').style.width = '75%';

        try {
            const r = await fetch(SAVE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ language: itemsLang, cards: cardsToSave })
            });
            const d = await r.json();
            if (!d.ok) throw new Error(K.saveError(d));

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
                if (count > 0 && confirm(`Kya aap sach me chune hue ${count} polls ko delete karna chahte hain?`)) {
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
