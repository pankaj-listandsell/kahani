@extends('layouts.admin')
@section('title', '🌿 Nuskhe & Recipe Studio')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-emerald-600 via-green-600 to-lime-600 rounded-2xl p-6 text-white shadow-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="inline-block px-2.5 py-1 bg-white/20 rounded-full text-xs font-semibold uppercase tracking-wider mb-2">💾 Saves Booster</span>
                <h1 class="text-2xl font-bold">Gharelu Nuskhe &amp; Recipe Cards</h1>
                <p class="text-emerald-100 text-sm mt-1">Saamagri + reet + tip wale cards — log inhe save karke rakhte hain.</p>
            </div>
            <button id="saveBtn" disabled class="bg-white text-emerald-800 font-bold px-5 py-2.5 rounded-xl shadow hover:bg-emerald-50 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-2">
                <span>💾</span> Save All Cards
            </button>
        </div>
    </div>

    {{-- Controls --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-4">
        <div class="grid sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">📝 Type</label>
                <select id="mode" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none font-medium">
                    <option value="nuskha" selected>🌿 Gharelu Nuskha</option>
                    <option value="recipe">🍲 Recipe</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🏷️ Category</label>
                <select id="category" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none font-medium"></select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🌐 Language</label>
                <select id="language" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none font-medium">
                    <option value="gujarati" selected>ગુજરાતી (Gujarati)</option>
                    <option value="hindi">हिंदी (Hindi)</option>
                    <option value="hinglish">Hinglish</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🔢 Card Count</label>
                <select id="count" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none font-medium">
                    <option value="1">1 Card</option>
                    <option value="3" selected>3 Cards</option>
                    <option value="5">5 Cards</option>
                </select>
            </div>
        </div>
        <div class="grid sm:grid-cols-4 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">✍️ Topic (optional)</label>
                <input id="topic" type="text" maxlength="200" placeholder="e.g. ખાંસી માટે ઉકાળો / ખમણ ઢોકળા — khaali chhodo to AI khud chunega"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🏷 Page / Brand Name</label>
                <input id="brand" type="text" maxlength="30" placeholder="e.g. દિલની વાત"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none font-medium">
            </div>
            <div class="flex items-end">
                <button id="genBtn" class="w-full bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white font-bold py-2 px-4 rounded-lg shadow transition">
                    <span id="genText">Generate Cards</span>
                </button>
            </div>
        </div>

        <div id="progressBox" class="hidden pt-2">
            <div class="text-xs font-semibold text-slate-600 mb-1"><span id="progressText">AI likh raha hai… (20–60 sec)</span></div>
            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div id="progressBar" class="bg-gradient-to-r from-emerald-500 to-green-600 h-2 transition-all duration-300" style="width: 0%"></div>
            </div>
        </div>
    </div>

    <div id="emptyState" class="bg-white rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center text-slate-400">
        <span class="text-4xl block mb-2">🌿</span>
        <p class="font-bold text-slate-600">Abhi koi card nahi bana</p>
        <p class="text-xs mt-1">"Generate Cards" dabao — har card ke neeche ✏️ Edit se text sudhaar sakte ho.</p>
    </div>

    <div id="previewGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 hidden"></div>

    {{-- Saved --}}
    <div class="mt-10 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <form id="bulkForm" method="POST" action="{{ route('admin.stories.bulk_destroy') }}">
            @csrf
            <div class="flex items-center justify-between gap-4 mb-4 flex-wrap pb-3 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                    <span>💾</span> Saved Collections ({{ $collections->count() }})
                </h3>
                @if ($collections->isNotEmpty())
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-600 cursor-pointer select-none">
                            <input type="checkbox" id="selectAll" class="rounded border-slate-300 accent-emerald-600 w-4 h-4 cursor-pointer">
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
                <div class="flex items-center justify-between bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 mb-2.5 hover:border-emerald-400 hover:bg-emerald-50/20 transition gap-3">
                    <input type="checkbox" name="ids[]" value="{{ $c->id }}" class="bulk-item rounded border-slate-300 accent-emerald-600 w-4 h-4 cursor-pointer">
                    <a href="{{ route('admin.nuskha.show', $c) }}" class="flex-1 flex items-center justify-between gap-3 group">
                        <div>
                            <span class="font-bold text-slate-800 group-hover:text-emerald-700 transition">🌿 {{ $c->title }}</span>
                            <span class="text-xs text-slate-500 ml-2">({{ ucfirst($c->language ?? 'gujarati') }})</span>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-full">
                            {{ $c->parts_count }} cards · {{ ucfirst($c->status) }}
                        </span>
                    </a>
                    <button type="button" onclick="if(confirm('Kya aap sach me ye collection delete karna chahte hain?')) document.getElementById('single-del-{{ $c->id }}').submit();" class="text-xs font-semibold text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                        <span>🗑</span> Delete
                    </button>
                </div>
            @empty
                <p class="text-sm text-slate-500 text-center py-4">Abhi koi collection save nahi hai — upar se naya banayein! 🌿</p>
            @endforelse
        </form>

        @foreach ($collections as $c)
            <form id="single-del-{{ $c->id }}" method="POST" action="{{ route('admin.nuskha.destroy', $c) }}" class="hidden">
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
    const GEN_URL = @json(route('admin.nuskha.generate'));
    const SAVE_URL = @json(route('admin.nuskha.save'));

    const K = window.CanvasKit;
    const W = 1080, H = 1920;
    let items = [];
    let itemsLang = 'gujarati';
    let itemsMode = 'nuskha';
    let itemsCategory = '';
    const el = id => document.getElementById(id);

    const CATEGORIES = {
        nuskha: [
            ['Cold, cough & immunity', '🤧 Shardi-Khansi / Immunity'],
            ['Digestion, acidity & gas', '🫃 Pachan / Acidity / Gas'],
            ['Skin care & glow', '✨ Skin / Glow'],
            ['Hair care & dandruff', '💇 Vaal / Dandruff'],
            ['Kitchen & cleaning hacks', '🧽 Kitchen & Safai Tips'],
            ['Weight & fitness (diet tips)', '🏃 Vajan / Fitness'],
            ['Sleep, stress & headache', '😴 Oongh / Stress / Mathu dukhvu'],
            ['Dadi-Nani ke purane nuskhe', '👵 Dadi-Nani na Nuskha'],
        ],
        recipe: [
            ['Gujarati farsan & nasta', '🥟 Gujarati Farsan / Nasto'],
            ['Daily lunch & dinner (sabzi, dal, roti)', '🍛 Roj nu Jaman'],
            ['Quick breakfast', '🍳 Jaldi Nasto / Breakfast'],
            ['Sweets & mithai', '🍬 Mithai'],
            ['Healthy & diet recipes', '🥗 Healthy / Diet'],
            ['Kids tiffin recipes', '🍱 Bachcha na Tiffin'],
            ['Festival special recipes', '🪔 Tahevar Special'],
            ['Street food at home', '🌮 Street Food Ghare'],
        ],
    };
    function fillCategories() {
        el('category').innerHTML = CATEGORIES[el('mode').value]
            .map(([v, l]) => `<option value="${K.esc(v)}">${K.esc(l)}</option>`).join('');
    }
    el('mode').addEventListener('change', fillCategories);
    fillCategories();

    const I18N = {
        gujarati: {
            nuskha: { badge: '🌿 ઘરેલુ નુસખો', items: 'સામગ્રી:', steps: 'કેવી રીતે કરવું:', tip: '⚠️ ધ્યાન રાખો:' },
            recipe: { badge: '🍲 આજની રેસીપી', items: 'સામગ્રી:', steps: 'બનાવવાની રીત:', tip: '💡 ખાસ ટિપ:' },
            save: '💾 સેવ કરો  •  📤 શેર કરો', digits: '૦૧૨૩૪૫૬૭૮૯', brand: 'દિલની વાત',
        },
        hindi: {
            nuskha: { badge: '🌿 घरेलू नुस्खा', items: 'सामग्री:', steps: 'कैसे करें:', tip: '⚠️ ध्यान रखें:' },
            recipe: { badge: '🍲 आज की रेसिपी', items: 'सामग्री:', steps: 'बनाने की विधि:', tip: '💡 खास टिप:' },
            save: '💾 सेव करें  •  📤 शेयर करें', digits: '०१२३४५६७८९', brand: 'दिल की बात',
        },
        hinglish: {
            nuskha: { badge: '🌿 Gharelu Nuskha', items: 'Saamagri:', steps: 'Kaise karein:', tip: '⚠️ Dhyan rakhein:' },
            recipe: { badge: '🍲 Aaj Ki Recipe', items: 'Saamagri:', steps: 'Banane ki vidhi:', tip: '💡 Khaas tip:' },
            save: '💾 Save karo  •  📤 Share karo', digits: '0123456789', brand: 'Dil Ki Baat',
        },
    };
    const tr = () => I18N[itemsLang] || I18N.gujarati;
    const num = n => String(n).replace(/\d/g, d => tr().digits[d]);
    const brandName = () => el('brand').value.trim() || tr().brand;
    const list = v => (Array.isArray(v) ? v : String(v ?? '').split('\n')).map(K.str).map(s => s.trim()).filter(Boolean);

    // Brand naam yaad rakho (Salah studio wali hi key — sab studios me ek hi naam)
    try { el('brand').value = localStorage.getItem('salahBrand') || ''; } catch (e) {}
    el('brand').addEventListener('input', () => {
        try { localStorage.setItem('salahBrand', el('brand').value); } catch (e) {}
        if (items.length) renderAllPreviews();
    });

    el('genBtn').addEventListener('click', async () => {
        const btn = el('genBtn');
        btn.disabled = true;
        el('genText').textContent = 'Generating…';
        el('progressBox').classList.remove('hidden');
        el('progressText').textContent = 'AI likh raha hai… (20–60 sec)';
        el('progressBar').style.width = '40%';

        try {
            const r = await fetch(GEN_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({
                    count: el('count').value, language: el('language').value, mode: el('mode').value,
                    category: el('category').value, topic: el('topic').value,
                })
            });
            const d = await r.json();
            if (!d.ok) throw new Error(K.saveError(d));

            items = Array.isArray(d.items) ? d.items : [];
            if (!items.length) throw new Error('AI ne koi card nahi diya — dobara try karo.');
            itemsLang = el('language').value;
            itemsMode = el('mode').value;
            itemsCategory = el('category').selectedOptions[0]?.textContent.replace(/^\S+\s/, '') || '';
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
            el('genText').textContent = 'Generate Cards';
        }
    });

    // ---------- Previews + edit ----------
    const FIELDS = [
        ['title', 'Title', 2], ['intro', 'Intro', 2], ['items', 'Saamagri (har line ek)', 5],
        ['steps', 'Reet (har line ek step)', 6], ['tip', 'Tip / Saavdhani', 2],
    ];

    function renderAllPreviews() {
        el('emptyState').classList.add('hidden');
        const grid = el('previewGrid');
        grid.classList.remove('hidden');
        grid.innerHTML = '';

        items.forEach((item, idx) => {
            const box = document.createElement('div');
            box.className = 'bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm flex flex-col';
            box.innerHTML = `
                <div class="p-3 bg-emerald-50 border-b border-emerald-100 flex items-center justify-between gap-2 text-xs font-bold text-emerald-900">
                    <span class="truncate">#${idx + 1}: ${K.esc(item.title)}</span>
                    <button type="button" class="remove shrink-0 text-red-600 hover:underline">✕</button>
                </div>
                <div class="p-3 canvas-slot"></div>
                <details class="border-t border-slate-100 text-xs">
                    <summary class="px-3 py-2 cursor-pointer font-semibold text-slate-600 hover:bg-slate-50">✏️ Edit text</summary>
                    <div class="p-3 space-y-2 fields"></div>
                </details>`;

            const canvas = document.createElement('canvas');
            canvas.width = W; canvas.height = H;
            canvas.className = 'w-full aspect-[9/16] rounded-lg';
            box.querySelector('.canvas-slot').appendChild(canvas);
            drawNuskha(canvas, item);

            const fields = box.querySelector('.fields');
            FIELDS.forEach(([key, label, rows]) => {
                const isList = key === 'items' || key === 'steps';
                const wrap = document.createElement('label');
                wrap.className = 'block';
                wrap.innerHTML = `<span class="font-semibold text-slate-500">${label}</span>
                    <textarea rows="${rows}" class="mt-0.5 w-full rounded border border-slate-300 px-2 py-1 text-xs"></textarea>`;
                const ta = wrap.querySelector('textarea');
                ta.value = isList ? list(item[key]).join('\n') : K.str(item[key]);
                ta.addEventListener('input', () => {
                    item[key] = isList ? list(ta.value) : ta.value;
                    drawNuskha(canvas, item);
                });
                fields.appendChild(wrap);
            });

            box.querySelector('.remove').addEventListener('click', () => {
                items.splice(idx, 1);
                el('saveBtn').disabled = !items.length;
                renderAllPreviews();
            });
            grid.appendChild(box);
        });
    }

    // ---------- Card drawing ----------
    function drawBackground(ctx) {
        const g = ctx.createLinearGradient(0, 0, 0, H);
        g.addColorStop(0, '#f4fbe9');
        g.addColorStop(1, '#dcefc8');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);

        // Kono me halke patte
        ctx.save();
        ctx.globalAlpha = 0.18;
        ctx.font = '220px sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText('🌿', 70, 1830);
        ctx.fillText('🍃', 1010, 80);
        ctx.restore();
    }

    function drawNuskha(c, item) {
        const ctx = c.getContext('2d');
        const L = tr()[itemsMode] || tr().nuskha;
        K.reset(ctx);
        drawBackground(ctx);

        // Upar badge
        ctx.font = K.font(40);
        const bw = ctx.measureText(L.badge).width + 80;
        ctx.fillStyle = '#15803d';
        ctx.beginPath(); ctx.roundRect(W / 2 - bw / 2, 60, bw, 84, 42); ctx.fill();
        ctx.fillStyle = '#ffffff';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(L.badge, W / 2, 104);

        // Title — badge ke neeche, upar se shuru
        const t = K.fit(ctx, K.str(item.title), { maxW: W - 140, maxH: 260, size: 62, minSize: 40, lineHeight: 1.25 });
        const titleH = t.lines.length * t.lh;
        ctx.fillStyle = '#14532d';
        K.drawLines(ctx, t.lines, W / 2, 180 + titleH / 2, t.lh);
        const titleBottom = 180 + titleH;

        // Safed panel — intro, saamagri, reet, tip
        const px = 60, pw = W - 120, py = titleBottom + 30, ph = 1740 - py;
        ctx.save();
        ctx.shadowColor = 'rgba(20, 83, 45, 0.18)';
        ctx.shadowBlur = 30;
        ctx.fillStyle = '#ffffff';
        ctx.beginPath(); ctx.roundRect(px, py, pw, ph, 40); ctx.fill();
        ctx.restore();

        const green = '#15803d', amber = '#b45309';
        const paras = [];
        if (K.str(item.intro).trim()) paras.push([{ t: K.str(item.intro), b: false, c: '#475569' }]);
        const ing = list(item.items);
        if (ing.length) {
            paras.push([{ t: L.items, b: true, c: green }]);
            ing.forEach(s => paras.push([{ t: '•', b: true, c: green }, { t: s, b: false }]));
        }
        const steps = list(item.steps);
        if (steps.length) {
            paras.push([{ t: L.steps, b: true, c: green }]);
            steps.forEach((s, i) => paras.push([{ t: num(i + 1) + '.', b: true, c: green }, { t: s, b: false }]));
        }
        if (K.str(item.tip).trim()) paras.push([{ t: L.tip, b: true, c: amber }, { t: K.str(item.tip), b: false }]);

        ctx.fillStyle = '#1e293b';
        const layout = K.fitRich(ctx, paras, pw - 90, ph - 80, 46, 22, { paraGap: 0.32, lineHeight: 1.35 });
        K.drawRich(ctx, layout, px + 45, py + 40);

        // Footer
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillStyle = '#14532d';
        K.fitOneLine(ctx, tr().save, W - 160, 42, 28);
        ctx.fillText(tr().save, W / 2, 1800);
        ctx.fillStyle = 'rgba(20, 83, 45, 0.6)';
        K.fitOneLine(ctx, '— ' + brandName() + ' —', W - 200, 32, 22, '400');
        ctx.fillText('— ' + brandName() + ' —', W / 2, 1862);
    }

    /** Reel voiceover: title + intro + saamagri + reet (tip ke saath). */
    function voiceText(item) {
        const L = tr()[itemsMode] || tr().nuskha;
        return [
            K.str(item.title), K.str(item.intro),
            L.items + ' ' + list(item.items).join(', '),
            L.steps + ' ' + list(item.steps).join('. '),
            K.str(item.tip),
        ].map(s => s.trim()).filter(Boolean).join('. ');
    }

    // ---------- Save ----------
    el('saveBtn').addEventListener('click', async () => {
        if (!items.length) return;
        const btn = el('saveBtn');
        btn.disabled = true;
        el('progressBox').classList.remove('hidden');
        el('progressText').textContent = 'Saving…';
        el('progressBar').style.width = '30%';
        await K.ensureFonts();

        const off = document.createElement('canvas'); off.width = W; off.height = H;
        const cardsToSave = items.map((item, idx) => {
            drawNuskha(off, item);
            return {
                order: idx + 1,
                text: voiceText(item),
                caption: K.str(item.caption),
                hashtags: K.str(item.hashtags),
                image: off.toDataURL('image/webp', 0.92),
            };
        });

        el('progressBar').style.width = '75%';

        try {
            const r = await fetch(SAVE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ language: itemsLang, category: (itemsMode === 'recipe' ? 'Recipe' : 'Nuskha') + ': ' + itemsCategory, cards: cardsToSave })
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
    const selectAll = el('selectAll');
    const bulkBtn = el('bulkDeleteBtn');
    const countSpan = el('selectedCount');
    const form = el('bulkForm');

    if (selectAll && form) {
        const updateBulkState = () => {
            const all = form.querySelectorAll('.bulk-item');
            const checked = form.querySelectorAll('.bulk-item:checked');
            if (countSpan) countSpan.textContent = checked.length;
            if (bulkBtn) bulkBtn.disabled = checked.length === 0;
            selectAll.checked = all.length > 0 && checked.length === all.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
        };

        selectAll.addEventListener('change', () => {
            form.querySelectorAll('.bulk-item').forEach(cb => cb.checked = selectAll.checked);
            updateBulkState();
        });
        form.querySelectorAll('.bulk-item').forEach(cb => cb.addEventListener('change', updateBulkState));

        if (bulkBtn) {
            bulkBtn.addEventListener('click', () => {
                const count = form.querySelectorAll('.bulk-item:checked').length;
                if (count > 0 && confirm(`Kya aap sach me chuni hui ${count} collections delete karna chahte hain?`)) {
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
