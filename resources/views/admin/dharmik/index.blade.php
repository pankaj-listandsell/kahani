@extends('layouts.admin')
@section('title', '🙏 Dharmik Card Studio')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-yellow-500 via-amber-500 to-orange-600 rounded-2xl p-6 text-white shadow-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="inline-block px-2.5 py-1 bg-white/20 rounded-full text-xs font-semibold uppercase tracking-wider mb-2">🙏 Roj navo dharmik content</span>
                <h1 class="text-2xl font-bold">Dharmik Card Studio</h1>
                <p class="text-amber-50 text-sm mt-1">Mantra list · Niyam grid (2 column) · "Kem?" article. 24 category, 4:5 cards, Facebook feed mate best.</p>
            </div>
            <button id="saveBtn" disabled class="bg-white text-amber-800 font-bold px-5 py-2.5 rounded-xl shadow hover:bg-amber-50 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-2">
                <span>💾</span> Save All Cards
            </button>
        </div>
    </div>

    {{-- Controls --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-4">
        {{-- Ek click: dar card alag category + category pramane template --}}
        <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            <div class="sm:w-40">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🔢 Ketla Card</label>
                <select id="count" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-medium">
                    <option value="1">1 Card</option>
                    <option value="3">3 Cards</option>
                    <option value="5" selected>5 Cards</option>
                    <option value="8">8 Cards</option>
                </select>
            </div>
            <button id="genBtn" class="flex-1 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white font-bold py-3 px-4 rounded-lg shadow transition text-base">
                <span id="genText">✨ Aaj na Card Banavo</span>
            </button>
        </div>
        <p class="text-xs text-slate-500 -mt-2">{{ count($categories) }} category mathi dar card alag category nu bane (mantra, vastu, Chanakya, Gita, katha, mandir, swapna, ank jyotish…) — ek card aaj na vaar na dev ({{ \Illuminate\Support\Str::after($todayDev, '— ') }}) nu. Template category pramane jate pasand thay.</p>

        <details class="text-sm">
            <summary class="cursor-pointer font-semibold text-slate-600">⚙️ Vadhu options (category / template / topic / language / footer)</summary>
            <div class="grid sm:grid-cols-3 gap-4 mt-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">🏷️ Category</label>
                    <select id="category" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-medium">
                        <option value="mix" selected>🔀 Mix — dar card alag category</option>
                        <option value="auto">📅 Fakt aaj no vaar ({{ \Illuminate\Support\Str::after($todayDev, '— ') }})</option>
                        @foreach ($categories as $key => [$label])
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">🧩 Template</label>
                    <select id="template" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-medium">
                        <option value="mix" selected>🔀 Auto (category pramane)</option>
                        <option value="list">① Numbered list (mantra / upay)</option>
                        <option value="grid">▦ 2-column grid (niyam)</option>
                        <option value="article">📰 Article (sawal + javab)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">🌐 Language</label>
                    <select id="language" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-medium">
                        <option value="gujarati" selected>ગુજરાતી (Gujarati)</option>
                        <option value="hindi">हिंदी (Hindi)</option>
                        <option value="hinglish">Hinglish</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">✍️ Topic (optional)</label>
                    <input id="topic" type="text" maxlength="200" placeholder="e.g. ધનતેરસ ના ઉપાય — khaali chhodo to AI navo topic pasand karse"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">🎨 Footer patti</label>
                    <input id="footer" type="text" maxlength="80" placeholder="ધાર્મિક માહિતી અને રોજના રાશિફળ માટે Follow કરો."
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
            </div>
        </details>

        <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
            ⚠️ AI kyarek mantra ma akshar khota lakhi shake che — save karta pehla mantra/shlok ek vaar vanchi lo (✏️ Edit thi sudhari shako).
        </p>

        <div id="progressBox" class="hidden pt-2">
            <div class="text-xs font-semibold text-slate-600 mb-1"><span id="progressText">AI lakhi rahyu che… (20–60 sec)</span></div>
            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div id="progressBar" class="bg-gradient-to-r from-amber-500 to-orange-600 h-2 transition-all duration-300" style="width: 0%"></div>
            </div>
        </div>
    </div>

    <div id="emptyState" class="bg-white rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center text-slate-400">
        <span class="text-4xl block mb-2">🙏</span>
        <p class="font-bold text-slate-600">Haju koi card nathi banyu</p>
        <p class="text-xs mt-1">"✨ Aaj na Card Banavo" dabavo — dar card niche ✏️ Edit thi text sudhari shako.</p>
    </div>

    <div id="previewGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 hidden"></div>

    {{-- Saved collections --}}
    <div class="mt-10 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <form id="bulkForm" method="POST" action="{{ route('admin.stories.bulk_destroy') }}">
            @csrf
            <div class="flex items-center justify-between gap-4 mb-4 flex-wrap pb-3 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                    <span>💾</span> Saved Dharmik Collections ({{ $collections->count() }})
                </h3>
                @if ($collections->isNotEmpty())
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-600 cursor-pointer select-none">
                            <input type="checkbox" id="selectAll" class="rounded border-slate-300 accent-amber-600 w-4 h-4 cursor-pointer">
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
                <div class="flex items-center justify-between bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 mb-2.5 hover:border-amber-400 hover:bg-amber-50/20 transition gap-3">
                    <input type="checkbox" name="ids[]" value="{{ $c->id }}" class="bulk-item rounded border-slate-300 accent-amber-600 w-4 h-4 cursor-pointer">
                    <a href="{{ route('admin.dharmik.show', $c) }}" class="flex-1 flex items-center justify-between gap-3 group">
                        <div class="min-w-0">
                            <span class="font-bold text-slate-800 group-hover:text-amber-700 transition">🙏 {{ $c->title }}</span>
                            @if ($c->description)
                                <span class="block text-xs text-slate-500 truncate">{{ \Illuminate\Support\Str::limit(str_replace("\n", ' · ', $c->description), 120) }}</span>
                            @endif
                        </div>
                        <span class="shrink-0 text-xs font-semibold px-2.5 py-1 bg-amber-100 text-amber-800 rounded-full">
                            {{ $c->cards_count }} cards · {{ ucfirst($c->status) }}
                        </span>
                    </a>
                    <button type="button" onclick="if(confirm('Aa collection delete karvi che?')) document.getElementById('single-del-{{ $c->id }}').submit();" class="text-xs font-semibold text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                        <span>🗑</span> Delete
                    </button>
                </div>
            @empty
                <p class="text-sm text-slate-500 text-center py-4">Haju koi collection save nathi — upar thi navu banavo! 🙏</p>
            @endforelse
        </form>

        @foreach ($collections as $c)
            <form id="single-del-{{ $c->id }}" method="POST" action="{{ route('admin.dharmik.destroy', $c) }}" class="hidden">
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
    const GEN_URL = @json(route('admin.dharmik.generate'));
    const SAVE_URL = @json(route('admin.dharmik.save'));

    const K = window.CanvasKit;
    const W = 1080, H = 1350; // 4:5 — Facebook / Instagram feed
    const C = { yellow: '#FFD31A', ink: '#141414', blue: '#1e3a9e', text: '#1b2440', line: '#2b2b2b' };
    const FOOT_H = 74;

    let items = [];
    let itemsLang = 'gujarati';
    let itemsCategory = '';
    const el = id => document.getElementById(id);

    const DEFAULTS = {
        gujarati: { footer: 'ધાર્મિક માહિતી અને રોજના રાશિફળ માટે Follow કરો.' },
        hindi:    { footer: 'धार्मिक जानकारी और रोज़ाना राशिफल के लिए Follow करें।' },
        hinglish: { footer: 'Dharmik jankari aur daily rashifal ke liye Follow karein.' },
    };
    const def = () => DEFAULTS[itemsLang] || DEFAULTS.gujarati;
    const footerText = () => el('footer').value.trim() || def().footer;

    // Footer yaad rakho (sirf is browser me)
    ['footer'].forEach(id => {
        try { el(id).value = localStorage.getItem('dharmik_' + id) || ''; } catch (e) {}
        el(id).addEventListener('input', () => {
            try { localStorage.setItem('dharmik_' + id, el(id).value); } catch (e) {}
            if (items.length) items.forEach(it => it._redraw && it._redraw());
        });
    });

    // ---------- Generate ----------
    el('genBtn').addEventListener('click', async () => {
        const btn = el('genBtn');
        btn.disabled = true;
        el('genText').textContent = 'Generating…';
        el('progressText').textContent = 'AI lakhi rahyu che… (20–60 sec)';
        el('progressBox').classList.remove('hidden');
        el('progressBar').style.width = '40%';

        try {
            const r = await fetch(GEN_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({
                    count: el('count').value, language: el('language').value, template: el('template').value,
                    category: el('category').value, topic: el('topic').value,
                })
            });
            const d = await r.json();
            if (!d.ok) throw new Error(K.saveError(d));

            items = Array.isArray(d.items) ? d.items : [];
            if (!items.length) throw new Error('AI e koi card na aapyu — fari try karo.');
            itemsLang = el('language').value;
            const cat = el('category');
            itemsCategory = cat.options[cat.selectedIndex].text.replace(/^\S+\s/, '');
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
            el('genText').textContent = '✨ Aaj na Card Banavo';
        }
    });

    // ---------- Previews + edit ----------
    const LIST_FIELDS = [
        ['title', 'Title', 2], ['items', 'Points (ek line = ek point)', 8], ['note', 'Niche note', 3],
        ['cta', 'Comment CTA (blue line)', 2], ['caption', 'Caption', 3], ['hashtags', 'Hashtags', 2],
    ];
    const ARTICLE_FIELDS = [
        ['title', 'Title (sawal)', 2], ['intro', 'Intro (line upar)', 4], ['body', 'Javab (line niche)', 6],
        ['cta', 'Comment CTA (blue line)', 2],
        ['caption', 'Caption', 3], ['hashtags', 'Hashtags', 2],
    ];
    const TPL_LABEL = { list: '① List', grid: '▦ Grid', article: '📰 Article' };

    const listOf = v => (Array.isArray(v) ? v : K.str(v).split('\n')).map(s => K.str(s).trim()).filter(Boolean);

    function renderAllPreviews() {
        el('emptyState').classList.add('hidden');
        const grid = el('previewGrid');
        grid.classList.remove('hidden');
        grid.innerHTML = '';

        items.forEach((item, idx) => {
            const box = document.createElement('div');
            box.className = 'bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm flex flex-col';
            box.innerHTML = `
                <div class="p-3 bg-amber-50 border-b border-amber-100 flex items-center justify-between gap-2 text-xs font-bold text-amber-900">
                    <span class="truncate">#${idx + 1} · ${TPL_LABEL[item.template] || ''} · ${K.esc(item.title)}</span>
                    <button type="button" class="remove shrink-0 text-red-600 hover:underline">✕</button>
                </div>
                <div class="p-3 canvas-slot"></div>
                <details class="border-t border-slate-100 text-xs">
                    <summary class="px-3 py-2 cursor-pointer font-semibold text-slate-600 hover:bg-slate-50">✏️ Edit text</summary>
                    <div class="p-3 space-y-2 fields"></div>
                </details>`;

            const canvas = document.createElement('canvas');
            canvas.width = W; canvas.height = H;
            canvas.className = 'w-full aspect-[4/5] rounded-lg border border-slate-100';
            box.querySelector('.canvas-slot').appendChild(canvas);
            item._redraw = () => drawCard(canvas, item);
            item._redraw();

            const fields = box.querySelector('.fields');
            (item.template === 'article' ? ARTICLE_FIELDS : LIST_FIELDS).forEach(([key, label, rows]) => {
                const wrap = document.createElement('label');
                wrap.className = 'block';
                wrap.innerHTML = `<span class="font-semibold text-slate-500">${label}</span>
                    <textarea rows="${rows}" class="mt-0.5 w-full rounded border border-slate-300 px-2 py-1 text-xs"></textarea>`;
                const ta = wrap.querySelector('textarea');
                ta.value = key === 'items' ? listOf(item.items).join('\n') : K.str(item[key]);
                ta.addEventListener('input', () => {
                    item[key] = key === 'items' ? listOf(ta.value) : ta.value;
                    item._redraw();
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

    // ---------- Drawing helpers ----------
    function rng(seed) { return () => (seed = (seed * 16807) % 2147483647) / 2147483647; }

    function rr(ctx, x, y, w, h, r) {
        r = Math.min(r, w / 2, h / 2);
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    /** Halka sa churelo kaagaz (light grey paper). */
    function drawPaper(ctx) {
        const g = ctx.createRadialGradient(W / 2, H / 2, 150, W / 2, H / 2, H * 0.8);
        g.addColorStop(0, '#f6f5f1');
        g.addColorStop(1, '#e3e0d8');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);
        const r = rng(7);
        for (let i = 0; i < 2200; i++) {
            ctx.fillStyle = r() < 0.5 ? 'rgba(90,85,70,0.05)' : 'rgba(255,255,255,0.35)';
            ctx.fillRect(r() * W, r() * H, 1 + r() * 3, 1 + r() * 3);
        }
        // Kaagaz ni karchli (crease) lines
        ctx.save();
        ctx.lineWidth = 2;
        for (let i = 0; i < 14; i++) {
            const x = r() * W, y = r() * H, a = r() * Math.PI, l = 120 + r() * 260;
            ctx.strokeStyle = r() < 0.5 ? 'rgba(120,115,100,0.06)' : 'rgba(255,255,255,0.5)';
            ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x + Math.cos(a) * l, y + Math.sin(a) * l); ctx.stroke();
        }
        ctx.restore();
    }

    /** Pilu pill title — return: pill no bottom y. */
    function drawTitle(ctx, title) {
        const x = 26, y = 26, w = W - x - 26, h = 206;
        ctx.save();
        rr(ctx, x, y, w, h, h / 2);
        ctx.fillStyle = C.yellow; ctx.fill();
        ctx.strokeStyle = C.ink; ctx.lineWidth = 4; ctx.stroke();
        ctx.fillStyle = C.ink;
        ctx.textAlign = 'center';
        K.fitDraw(ctx, K.str(title).trim(), x + w / 2, y + h / 2 + 4, { maxW: w - 90, maxH: h - 36, size: 76, minSize: 34, lineHeight: 1.18 });
        ctx.restore();
        return y + h;
    }

    function drawFooter(ctx) {
        ctx.save();
        rr(ctx, 18, H - FOOT_H, W - 36, FOOT_H + 40, 38);
        ctx.fillStyle = C.yellow; ctx.fill();
        ctx.fillStyle = C.ink;
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        K.fitOneLine(ctx, footerText(), W - 120, 32, 18);
        ctx.fillText(footerText(), W / 2, H - FOOT_H / 2 + 2);
        ctx.restore();
    }

    /** Blue CTA line — return: CTA no top y (na hoy to footer no top). */
    function drawCta(ctx, cta) {
        const t = K.str(cta).trim();
        const bottom = H - FOOT_H - 18;
        if (!t) return bottom;
        ctx.save();
        ctx.fillStyle = C.blue;
        ctx.textAlign = 'center';
        const r = K.fit(ctx, t, { maxW: W - 120, maxH: 96, size: 36, minSize: 24, lineHeight: 1.25 });
        const hgt = r.lines.length * r.lh;
        K.drawLines(ctx, r.lines, W / 2, bottom - hgt / 2, r.lh);
        ctx.restore();
        return bottom - hgt - 14;
    }

    function numCircle(ctx, n, cx, cy, r) {
        ctx.save();
        ctx.fillStyle = C.ink;
        ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = '#ffffff';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.font = K.font(Math.round(r * (n > 9 ? 1.0 : 1.2)));
        ctx.fillText(String(n), cx, cy + 2);
        ctx.restore();
    }

    /** Ek cell ma text (left aligned, jarur pade to 2 line) */
    function cellText(ctx, text, x, cy, maxW, maxH, size) {
        ctx.save();
        ctx.fillStyle = C.ink;
        const r = K.fit(ctx, text, { maxW, maxH, size, minSize: 20, lineHeight: 1.18 });
        ctx.textAlign = 'left';
        K.drawLines(ctx, r.lines, x, cy, r.lh);
        ctx.restore();
    }

    /** Note paragraph ka layout (bold, ghera vadali rang). */
    function noteLayout(ctx, note, maxH) {
        const t = K.str(note).trim();
        if (!t) return null;
        return K.fitRich(ctx, [[{ t, b: true }]], W - 100, maxH, 32, 20, { lineHeight: 1.35 });
    }

    // ---------- Templates ----------
    function drawList(ctx, item, top, bottom) {
        const list = listOf(item.items).slice(0, 14);
        const note = noteLayout(ctx, item.note, 170);
        const noteH = note ? note.height + 26 : 0;
        const n = Math.max(1, list.length);
        const rowH = Math.min(118, (bottom - top - noteH) / n);
        const tableH = rowH * n;
        const y0 = top + Math.max(0, (bottom - top - noteH - tableH) / 2);
        const x0 = 30, x1 = W - 30, numW = 108;

        ctx.save();
        ctx.fillStyle = 'rgba(255,255,255,0.35)';
        ctx.fillRect(x0, y0, x1 - x0, tableH);
        ctx.strokeStyle = C.line; ctx.lineWidth = 3;
        ctx.strokeRect(x0, y0, x1 - x0, tableH);
        ctx.lineWidth = 2;
        for (let i = 1; i < n; i++) { ctx.beginPath(); ctx.moveTo(x0, y0 + i * rowH); ctx.lineTo(x1, y0 + i * rowH); ctx.stroke(); }
        ctx.beginPath(); ctx.moveTo(x0 + numW, y0); ctx.lineTo(x0 + numW, y0 + tableH); ctx.stroke();
        ctx.restore();

        const fs = Math.min(46, rowH * 0.48);
        list.forEach((t, i) => {
            const cy = y0 + i * rowH + rowH / 2;
            numCircle(ctx, i + 1, x0 + numW / 2, cy, Math.min(31, rowH * 0.33));
            cellText(ctx, t, x0 + numW + 22, cy + 2, x1 - x0 - numW - 40, rowH - 12, fs);
        });

        if (note) {
            ctx.fillStyle = C.text;
            K.drawRich(ctx, note, 50, y0 + tableH + 22);
        }
    }

    function drawGrid(ctx, item, top, bottom) {
        const list = listOf(item.items).slice(0, 24);
        const note = noteLayout(ctx, item.note, 110);
        const noteH = note ? note.height + 22 : 0;
        const rows = Math.max(1, Math.ceil(list.length / 2));
        const rowH = Math.min(126, (bottom - top - noteH) / rows);
        const tableH = rowH * rows;
        const y0 = top + Math.max(0, (bottom - top - noteH - tableH) / 2);
        const x0 = 24, x1 = W - 24, colW = (x1 - x0) / 2, numW = 62;

        ctx.save();
        ctx.fillStyle = 'rgba(255,255,255,0.35)';
        ctx.fillRect(x0, y0, x1 - x0, tableH);
        ctx.strokeStyle = C.line; ctx.lineWidth = 3;
        ctx.strokeRect(x0, y0, x1 - x0, tableH);
        ctx.lineWidth = 2;
        for (let i = 1; i < rows; i++) { ctx.beginPath(); ctx.moveTo(x0, y0 + i * rowH); ctx.lineTo(x1, y0 + i * rowH); ctx.stroke(); }
        [x0 + colW, x0 + numW, x0 + colW + numW].forEach((x, k) => {
            ctx.lineWidth = k === 0 ? 3 : 2;
            ctx.beginPath(); ctx.moveTo(x, y0); ctx.lineTo(x, y0 + tableH); ctx.stroke();
        });
        ctx.restore();

        const fs = Math.min(40, rowH * 0.4);
        list.forEach((t, i) => {
            const col = i < rows ? 0 : 1, row = i < rows ? i : i - rows;
            const cx = x0 + col * colW, cy = y0 + row * rowH + rowH / 2;
            numCircle(ctx, i + 1, cx + numW / 2, cy, Math.min(23, rowH * 0.3));
            cellText(ctx, t, cx + numW + 14, cy + 2, colW - numW - 24, rowH - 10, fs);
        });

        if (note) {
            ctx.fillStyle = C.text;
            K.drawRich(ctx, note, 50, y0 + tableH + 20);
        }
    }

    function drawArticle(ctx, item, top, bottom) {
        const paras = s => K.str(s).split(/\n\s*\n|\n/).map(p => p.trim()).filter(Boolean).map(p => [{ t: p, b: true }]);
        const intro = paras(item.intro), body = paras(item.body);
        const maxW = W - 90, divH = 70; // intro ane javab vachche shobha ni line

        // Intro + javab ek j font size ma fit karo (image nathi, etle motu lakhan)
        let s = 46, li, lb;
        for (;;) {
            li = K.layoutRich(ctx, intro, maxW, s, { lineHeight: 1.4 });
            lb = K.layoutRich(ctx, body, maxW, s, { lineHeight: 1.4 });
            if (li.height + lb.height + divH <= bottom - top || s <= 20) break;
            s -= 1;
        }

        const used = li.height + lb.height + divH;
        let y = top + Math.max(0, (bottom - top - used) / 2);
        ctx.fillStyle = C.ink;
        K.drawRich(ctx, li, 45, y);
        y += li.height;

        // Divider: — ◆ —
        const cy = y + divH / 2;
        ctx.save();
        ctx.strokeStyle = C.line; ctx.lineWidth = 3;
        ctx.beginPath(); ctx.moveTo(140, cy); ctx.lineTo(W / 2 - 34, cy); ctx.moveTo(W / 2 + 34, cy); ctx.lineTo(W - 140, cy); ctx.stroke();
        ctx.fillStyle = C.yellow;
        ctx.beginPath(); ctx.moveTo(W / 2, cy - 20); ctx.lineTo(W / 2 + 20, cy); ctx.lineTo(W / 2, cy + 20); ctx.lineTo(W / 2 - 20, cy); ctx.closePath();
        ctx.fill(); ctx.stroke();
        ctx.restore();
        y += divH;

        ctx.fillStyle = C.ink;
        K.drawRich(ctx, lb, 45, y);
    }

    function drawCard(c, item) {
        const ctx = c.getContext('2d');
        K.reset(ctx);
        drawPaper(ctx);
        const titleBottom = drawTitle(ctx, item.title);
        drawFooter(ctx);
        const ctaTop = drawCta(ctx, item.cta);

        const top = titleBottom + 30, bottom = ctaTop - 12;
        K.reset(ctx);
        if (item.template === 'grid') drawGrid(ctx, item, top, bottom);
        else if (item.template === 'article') drawArticle(ctx, item, top, bottom);
        else drawList(ctx, item, top, bottom);
    }

    /** Reel voiceover mate mukhya text. */
    function voiceText(item) {
        return [item.title, ...listOf(item.items), item.intro, item.body, item.note, item.cta]
            .map(K.str).map(s => s.trim()).filter(Boolean).join('. ');
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
            drawCard(off, item);
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
                body: JSON.stringify({
                    language: itemsLang, category: itemsCategory, cards: cardsToSave,
                    titles: items.map(it => K.str(it.title).trim()).filter(Boolean),
                })
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
                if (count > 0 && confirm(`Pasand kareli ${count} collections delete karvi che?`)) {
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
