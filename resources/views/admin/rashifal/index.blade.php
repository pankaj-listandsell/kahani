@extends('layouts.admin')
@section('title', '🔯 Aaj nu Rashifal — 12 Rashi Cards')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-indigo-700 via-purple-700 to-fuchsia-700 rounded-2xl p-6 text-white shadow-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="inline-block px-2.5 py-1 bg-white/20 rounded-full text-xs font-semibold uppercase tracking-wider mb-2">🔯 Daily Fixed Content</span>
                <h1 class="text-2xl font-bold">Aaj nu Rashifal — 12 Rashi Cards</h1>
                <p class="text-indigo-100 text-sm mt-1">Ek din ke 12 cards (Mesh → Meen). Auto-post inhe <b>sirf usi din</b>, kram me, baaki cards se pehle post karta hai.</p>
            </div>
            <button id="saveBtn" disabled class="bg-white text-indigo-800 font-bold px-5 py-2.5 rounded-xl shadow hover:bg-indigo-50 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-2">
                <span>💾</span> Save 12 Cards
            </button>
        </div>
    </div>

    {{-- Controls --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-4">
        <div class="grid sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">📅 Date</label>
                <input id="date" type="date" value="{{ now()->addDay()->toDateString() }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none font-medium">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🌐 Language</label>
                <select id="language" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none font-medium">
                    <option value="gujarati" selected>ગુજરાતી (Gujarati)</option>
                    <option value="hindi">हिंदी (Hindi)</option>
                    <option value="hinglish">Hinglish</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🏷 Page / Brand Name</label>
                <input id="brand" type="text" maxlength="30" placeholder="e.g. દિલની વાત"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none font-medium">
            </div>
            <div class="flex items-end">
                <button id="genBtn" class="w-full bg-gradient-to-r from-indigo-600 to-purple-700 hover:from-indigo-700 hover:to-purple-800 text-white font-bold py-2 px-4 rounded-lg shadow transition">
                    <span id="genText">Generate Rashifal</span>
                </button>
            </div>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-1 border-t border-slate-100">
            <button id="weekBtn" class="shrink-0 bg-gradient-to-r from-fuchsia-600 to-purple-700 hover:from-fuchsia-700 hover:to-purple-800 text-white font-bold py-2 px-4 rounded-lg shadow transition disabled:opacity-50">
                📅 Agle 7 din banao
            </button>
            <p class="text-xs text-slate-500">Upar ki date se 7 din ka rashifal ek click me ban ke <b>seedha save</b> ho jayega (pehle se save din skip). Har din usi din auto-post hoga. Page band mat karna — 3-7 minute lag sakte hain.</p>
        </div>

        <div id="progressBox" class="hidden pt-2">
            <div class="text-xs font-semibold text-slate-600 mb-1"><span id="progressText">AI likh raha hai… (20–60 sec)</span></div>
            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div id="progressBar" class="bg-gradient-to-r from-indigo-500 to-purple-600 h-2 transition-all duration-300" style="width: 0%"></div>
            </div>
        </div>
    </div>

    <div id="emptyState" class="bg-white rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center text-slate-400">
        <span class="text-4xl block mb-2">🔯</span>
        <p class="font-bold text-slate-600">Abhi koi rashifal nahi bana</p>
        <p class="text-xs mt-1">Date chuno aur "Generate Rashifal" dabao — har card ke neeche ✏️ Edit se text sudhaar sakte ho.</p>
    </div>

    <div id="previewGrid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 hidden"></div>

    {{-- Saved --}}
    <div class="mt-10 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <form id="bulkForm" method="POST" action="{{ route('admin.stories.bulk_destroy') }}">
            @csrf
            <div class="flex items-center justify-between gap-4 mb-4 flex-wrap pb-3 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                    <span>💾</span> Saved Rashifal ({{ $collections->count() }})
                </h3>
                @if ($collections->isNotEmpty())
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-600 cursor-pointer select-none">
                            <input type="checkbox" id="selectAll" class="rounded border-slate-300 accent-indigo-600 w-4 h-4 cursor-pointer">
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
                @php
                    $isToday = $c->category === now()->toDateString();
                    $isPast = $c->category < now()->toDateString();
                @endphp
                <div class="flex items-center justify-between bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 mb-2.5 hover:border-indigo-400 hover:bg-indigo-50/20 transition gap-3 {{ $isPast ? 'opacity-60' : '' }}">
                    <input type="checkbox" name="ids[]" value="{{ $c->id }}" class="bulk-item rounded border-slate-300 accent-indigo-600 w-4 h-4 cursor-pointer">
                    <a href="{{ route('admin.rashifal.show', $c) }}" class="flex-1 flex items-center justify-between gap-3 group">
                        <div>
                            <span class="font-bold text-slate-800 group-hover:text-indigo-700 transition">🔯 {{ $c->title }}</span>
                            <span class="text-xs text-slate-500 ml-2">({{ ucfirst($c->language ?? 'gujarati') }})</span>
                            @if ($isToday)
                                <span class="text-[10px] font-bold px-2 py-0.5 bg-green-100 text-green-800 rounded-full ml-1">AAJ</span>
                            @elseif ($isPast)
                                <span class="text-[10px] font-bold px-2 py-0.5 bg-slate-200 text-slate-600 rounded-full ml-1">Purana</span>
                            @endif
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 bg-indigo-100 text-indigo-800 rounded-full">{{ ucfirst($c->status) }}</span>
                    </a>
                    <button type="button" onclick="if(confirm('Ye rashifal delete karein?')) document.getElementById('single-del-{{ $c->id }}').submit();" class="text-xs font-semibold text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                        <span>🗑</span> Delete
                    </button>
                </div>
            @empty
                <p class="text-sm text-slate-500 text-center py-4">Abhi koi rashifal save nahi hai — upar se naya banayein! 🔯</p>
            @endforelse
        </form>

        @foreach ($collections as $c)
            <form id="single-del-{{ $c->id }}" method="POST" action="{{ route('admin.rashifal.destroy', $c) }}" class="hidden">
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
    const GEN_URL = @json(route('admin.rashifal.generate'));
    const SAVE_URL = @json(route('admin.rashifal.save'));

    const K = window.CanvasKit;
    const W = 1080, H = 1920;
    let items = [];
    let itemsLang = 'gujarati';
    let itemsDate = '';
    let dateExists = false;
    const el = id => document.getElementById(id);

    const KEYS = ['mesh', 'vrishabh', 'mithun', 'kark', 'simha', 'kanya', 'tula', 'vrishchik', 'dhanu', 'makar', 'kumbh', 'meen'];
    const SYMBOLS = ['♈', '♉', '♊', '♋', '♌', '♍', '♎', '♏', '♐', '♑', '♒', '♓'];
    const I18N = {
        gujarati: {
            names: ['મેષ', 'વૃષભ', 'મિથુન', 'કર્ક', 'સિંહ', 'કન્યા', 'તુલા', 'વૃશ્ચિક', 'ધન', 'મકર', 'કુંભ', 'મીન'],
            letters: ['અ, લ, ઈ', 'બ, વ, ઉ', 'ક, છ, ઘ', 'ડ, હ', 'મ, ટ', 'પ, ઠ, ણ', 'ર, ત', 'ન, ય', 'ભ, ધ, ફ, ઢ', 'ખ, જ', 'ગ, શ, સ, ષ', 'દ, ચ, ઝ, થ'],
            head: 'આજનું રાશિફળ', love: 'પ્રેમ:', career: 'કામકાજ:', health: 'સ્વાસ્થ્ય:',
            num: 'શુભ અંક', color: 'શુભ રંગ', cta: 'તમારી રાશિ કઈ છે? કોમેન્ટમાં લખો 👇',
            locale: 'gu-IN', digits: '૦૧૨૩૪૫૬૭૮૯', brand: 'દિલની વાત',
        },
        hindi: {
            names: ['मेष', 'वृषभ', 'मिथुन', 'कर्क', 'सिंह', 'कन्या', 'तुला', 'वृश्चिक', 'धनु', 'मकर', 'कुंभ', 'मीन'],
            letters: null,
            head: 'आज का राशिफल', love: 'प्रेम:', career: 'करियर:', health: 'स्वास्थ्य:',
            num: 'शुभ अंक', color: 'शुभ रंग', cta: 'आपकी राशि कौन सी है? कमेंट करें 👇',
            locale: 'hi-IN', digits: '०१२३४५६७८९', brand: 'दिल की बात',
        },
        hinglish: {
            names: ['Mesh', 'Vrishabh', 'Mithun', 'Kark', 'Simha', 'Kanya', 'Tula', 'Vrishchik', 'Dhanu', 'Makar', 'Kumbh', 'Meen'],
            letters: ['Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo', 'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'],
            head: 'Aaj Ka Rashifal', love: 'Love:', career: 'Career:', health: 'Health:',
            num: 'Lucky Number', color: 'Lucky Color', cta: 'Aapki rashi kaunsi hai? Comment karo 👇',
            locale: 'en-IN', digits: '0123456789', brand: 'Dil Ki Baat',
        },
    };
    const tr = () => I18N[itemsLang] || I18N.gujarati;
    const num = n => String(n ?? '').replace(/\d/g, d => tr().digits[d]);
    const brandName = () => el('brand').value.trim() || tr().brand;
    const dateLabel = () => {
        try {
            return num(new Date(itemsDate + 'T00:00:00').toLocaleDateString(tr().locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
        } catch (e) { return itemsDate; }
    };

    // Brand naam yaad rakho (Salah studio wali hi key — sab studios me ek hi naam)
    try { el('brand').value = localStorage.getItem('salahBrand') || ''; } catch (e) {}
    el('brand').addEventListener('input', () => {
        try { localStorage.setItem('salahBrand', el('brand').value); } catch (e) {}
        if (items.length) renderAllPreviews();
    });

    /** AI ke items ko Mesh→Meen kram me lagao (rashi key se, warna jis kram me aaye). */
    function orderItems(raw) {
        const out = new Array(12).fill(null);
        const rest = [];
        raw.forEach(it => {
            const k = String(it.rashi || '').toLowerCase().replace(/[^a-z]/g, '').replace('dhan', 'dhanu').replace('dhanuu', 'dhanu').replace('singh', 'simha');
            const i = KEYS.indexOf(k);
            if (i >= 0 && !out[i]) out[i] = it; else rest.push(it);
        });
        for (let i = 0; i < 12; i++) if (!out[i]) out[i] = rest.shift() || null;
        return out.map((it, i) => it ? Object.assign(it, { _idx: i }) : null).filter(Boolean);
    }

    el('genBtn').addEventListener('click', async () => {
        const btn = el('genBtn');
        if (!el('date').value) { alert('Date chuno'); return; }
        btn.disabled = true;
        el('genText').textContent = 'Generating…';
        el('progressBox').classList.remove('hidden');
        el('progressText').textContent = 'AI likh raha hai… (20–60 sec)';
        el('progressBar').style.width = '40%';

        try {
            const r = await fetch(GEN_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ date: el('date').value, language: el('language').value })
            });
            const d = await r.json();
            if (!d.ok) throw new Error(K.saveError(d));

            items = orderItems(Array.isArray(d.items) ? d.items : []);
            if (!items.length) throw new Error('AI ne koi rashi nahi di — dobara try karo.');
            itemsLang = el('language').value;
            itemsDate = el('date').value;
            dateExists = !!d.exists;
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
            el('genText').textContent = 'Generate Rashifal';
        }
    });

    // ---------- Previews + edit ----------
    const FIELDS = [
        ['prediction', 'Bhavishya', 4], ['love', 'Prem', 2], ['career', 'Kaam', 2], ['health', 'Swasthya', 2],
        ['lucky_number', 'Shubh ank', 1], ['lucky_color', 'Shubh rang', 1],
    ];

    function renderAllPreviews() {
        el('emptyState').classList.add('hidden');
        const grid = el('previewGrid');
        grid.classList.remove('hidden');
        grid.innerHTML = '';

        items.forEach(item => {
            const box = document.createElement('div');
            box.className = 'bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm flex flex-col';
            box.innerHTML = `
                <div class="p-2 bg-indigo-50 border-b border-indigo-100 text-xs font-bold text-indigo-900">${SYMBOLS[item._idx]} ${K.esc(tr().names[item._idx])}</div>
                <div class="p-2 canvas-slot"></div>
                <details class="border-t border-slate-100 text-xs">
                    <summary class="px-3 py-2 cursor-pointer font-semibold text-slate-600 hover:bg-slate-50">✏️ Edit</summary>
                    <div class="p-3 space-y-2 fields"></div>
                </details>`;

            const canvas = document.createElement('canvas');
            canvas.width = W; canvas.height = H;
            canvas.className = 'w-full aspect-[9/16] rounded-lg';
            box.querySelector('.canvas-slot').appendChild(canvas);
            drawRashi(canvas, item);

            const fields = box.querySelector('.fields');
            FIELDS.forEach(([key, label, rows]) => {
                const wrap = document.createElement('label');
                wrap.className = 'block';
                wrap.innerHTML = `<span class="font-semibold text-slate-500">${label}</span>
                    <textarea rows="${rows}" class="mt-0.5 w-full rounded border border-slate-300 px-2 py-1 text-xs"></textarea>`;
                const ta = wrap.querySelector('textarea');
                ta.value = K.str(item[key]);
                ta.addEventListener('input', () => { item[key] = ta.value; drawRashi(canvas, item); });
                fields.appendChild(wrap);
            });
            grid.appendChild(box);
        });
    }

    // ---------- Card drawing ----------
    function rng(seed) {
        return () => (seed = (seed * 16807) % 2147483647) / 2147483647;
    }

    function drawSky(ctx, idx) {
        const g = ctx.createLinearGradient(0, 0, 0, H);
        g.addColorStop(0, '#0b1033');
        g.addColorStop(0.55, '#2a1260');
        g.addColorStop(1, '#4a1450');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);

        // Taare — har rashi ka alag pattern
        const r = rng(7 + idx * 101);
        for (let i = 0; i < 260; i++) {
            const s = r() * 2.6 + 0.4;
            ctx.fillStyle = `rgba(255,255,255,${0.25 + r() * 0.6})`;
            ctx.beginPath(); ctx.arc(r() * W, r() * H, s, 0, Math.PI * 2); ctx.fill();
        }

        // Rashi chakra ki halki rings
        ctx.save();
        ctx.strokeStyle = 'rgba(250, 204, 21, 0.18)';
        ctx.lineWidth = 3;
        [250, 300].forEach(rad => { ctx.beginPath(); ctx.arc(W / 2, 470, rad, 0, Math.PI * 2); ctx.stroke(); });
        ctx.font = K.font(34, '400');
        ctx.fillStyle = 'rgba(250, 204, 21, 0.35)';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        SYMBOLS.forEach((sym, i) => {
            const a = (i / 12) * Math.PI * 2 - Math.PI / 2;
            ctx.fillText(sym + '︎', W / 2 + Math.cos(a) * 275, 470 + Math.sin(a) * 275);
        });
        ctx.restore();
    }

    function drawRashi(c, item) {
        const ctx = c.getContext('2d');
        const L = tr();
        const i = item._idx;
        K.reset(ctx);
        drawSky(ctx, i);

        // Upar: heading + date
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillStyle = '#facc15';
        K.fitOneLine(ctx, '✨ ' + L.head + ' ✨', W - 120, 64, 40);
        ctx.fillText('✨ ' + L.head + ' ✨', W / 2, 95);
        ctx.fillStyle = 'rgba(255,255,255,0.85)';
        K.fitOneLine(ctx, dateLabel(), W - 160, 36, 26, '400');
        ctx.fillText(dateLabel(), W / 2, 160);

        // Beech me rashi ka chinh
        ctx.save();
        ctx.shadowColor = 'rgba(250, 204, 21, 0.7)';
        ctx.shadowBlur = 50;
        const cg = ctx.createRadialGradient(W / 2, 470, 20, W / 2, 470, 200);
        cg.addColorStop(0, '#fde68a');
        cg.addColorStop(1, '#d97706');
        ctx.fillStyle = cg;
        ctx.beginPath(); ctx.arc(W / 2, 470, 190, 0, Math.PI * 2); ctx.fill();
        ctx.restore();
        ctx.fillStyle = '#3b0764';
        ctx.font = `200px "Segoe UI Symbol", "Noto Sans Symbols 2", ${K.STACK}`;
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(SYMBOLS[i] + '︎', W / 2, 480);

        // Rashi naam + akshar
        ctx.fillStyle = '#ffffff';
        ctx.font = K.font(96);
        ctx.fillText(L.names[i], W / 2, 750);
        if (L.letters) {
            ctx.fillStyle = '#fcd34d';
            K.fitOneLine(ctx, '(' + L.letters[i] + ')', W - 200, 40, 28, '400');
            ctx.fillText('(' + L.letters[i] + ')', W / 2, 830);
        }

        // Bhavishya — kaanch jaisa panel
        const px = 70, pw = W - 140, py = 890, ph = 520;
        ctx.fillStyle = 'rgba(255,255,255,0.10)';
        ctx.strokeStyle = 'rgba(250, 204, 21, 0.45)';
        ctx.lineWidth = 3;
        ctx.beginPath(); ctx.roundRect(px, py, pw, ph, 36); ctx.fill(); ctx.stroke();

        const gold = '#fcd34d';
        const paras = [
            [{ t: K.str(item.prediction), b: false }],
            [{ t: '❤️ ' + L.love, b: true, c: gold }, { t: K.str(item.love), b: false }],
            [{ t: '💼 ' + L.career, b: true, c: gold }, { t: K.str(item.career), b: false }],
            [{ t: '🩺 ' + L.health, b: true, c: gold }, { t: K.str(item.health), b: false }],
        ].filter(p => p.some(s => !s.b && s.t.trim()));
        ctx.fillStyle = '#ffffff';
        const layout = K.fitRich(ctx, paras, pw - 80, ph - 70, 42, 24, { paraGap: 0.6 });
        K.drawRich(ctx, layout, px + 40, py + (ph - layout.height) / 2);

        // Shubh ank / rang
        const pills = [
            L.num + ': ' + num(item.lucky_number),
            L.color + ': ' + K.str(item.lucky_color),
        ];
        pills.forEach((t, k) => {
            const cx = k === 0 ? W * 0.28 : W * 0.72, cy = 1500, w = 420, h = 96;
            ctx.fillStyle = 'rgba(250, 204, 21, 0.95)';
            ctx.beginPath(); ctx.roundRect(cx - w / 2, cy - h / 2, w, h, 48); ctx.fill();
            ctx.fillStyle = '#3b0764';
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            K.fitOneLine(ctx, t, w - 40, 38, 24);
            ctx.fillText(t, cx, cy + 2);
        });

        // CTA + brand
        ctx.fillStyle = '#ffffff';
        K.fitOneLine(ctx, L.cta, W - 120, 46, 30);
        ctx.fillText(L.cta, W / 2, 1640);
        ctx.fillStyle = 'rgba(255,255,255,0.6)';
        K.fitOneLine(ctx, '— ' + brandName() + ' —', W - 200, 36, 24, '400');
        ctx.fillText('— ' + brandName() + ' —', W / 2, 1715);
    }

    /** Reel voiceover: rashi ka naam + bhavishya + prem/kaam/swasthya. */
    function voiceText(item) {
        const L = tr();
        return [
            L.head + ', ' + L.names[item._idx],
            K.str(item.prediction),
            L.love + ' ' + K.str(item.love),
            L.career + ' ' + K.str(item.career),
            L.health + ' ' + K.str(item.health),
        ].map(s => s.trim()).filter(Boolean).join('. ');
    }

    // ---------- Save ----------
    async function postJson(url, body) {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(body)
        });
        const d = await r.json();
        if (!d.ok) throw new Error(K.saveError(d));
        return d;
    }

    /** Abhi ke items (itemsDate / itemsLang) ke 12 cards bana ke save karo. */
    async function saveCurrent() {
        await K.ensureFonts();
        const off = document.createElement('canvas'); off.width = W; off.height = H;
        const cardsToSave = items.map(item => {
            drawRashi(off, item);
            return {
                order: item._idx + 1,
                text: voiceText(item),
                caption: K.str(item.caption) || (tr().head + ' — ' + tr().names[item._idx] + ' ' + SYMBOLS[item._idx]),
                hashtags: K.str(item.hashtags),
                image: off.toDataURL('image/webp', 0.92),
            };
        });
        return postJson(SAVE_URL, { date: itemsDate, language: itemsLang, cards: cardsToSave });
    }

    el('saveBtn').addEventListener('click', async () => {
        if (!items.length) return;
        if (dateExists && !confirm('Is date ka rashifal pehle se save hai. Phir bhi naya save karein? (dono post honge)')) return;
        const btn = el('saveBtn');
        btn.disabled = true;
        el('progressBox').classList.remove('hidden');
        el('progressText').textContent = 'Saving…';
        el('progressBar').style.width = '50%';

        try {
            const d = await saveCurrent();
            el('progressBar').style.width = '100%';
            setTimeout(() => window.location = d.redirect, 400);
        } catch (e) {
            alert('Save error: ' + e.message);
            btn.disabled = false;
            el('progressBox').classList.add('hidden');
        }
    });

    // ---------- Agle 7 din: generate + seedha save, ek ke baad ek ----------
    el('weekBtn').addEventListener('click', async () => {
        const start = el('date').value;
        if (!start) { alert('Date chuno'); return; }
        const lang = el('language').value;
        const days = [...Array(7)].map((_, i) => {
            const d = new Date(start + 'T00:00:00');
            d.setDate(d.getDate() + i);
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        });
        if (!confirm(`${days[0]} se ${days[6]} tak 7 din ka rashifal banake save karein?`)) return;

        const btns = ['weekBtn', 'genBtn', 'saveBtn'].map(el);
        btns.forEach(b => b.disabled = true);
        el('progressBox').classList.remove('hidden');
        const done = [], skipped = [], failed = [];

        for (const [i, day] of days.entries()) {
            el('progressText').textContent = `Din ${i + 1}/7 (${day}) — AI likh raha hai…`;
            el('progressBar').style.width = Math.round((i / 7) * 100) + '%';
            try {
                const d = await postJson(GEN_URL, { date: day, language: lang, skip_existing: true });
                if (d.skipped) { skipped.push(day); continue; }

                items = orderItems(Array.isArray(d.items) ? d.items : []);
                if (items.length < 12) throw new Error(`AI ne sirf ${items.length} rashi di`);
                itemsLang = lang;
                itemsDate = day;
                el('progressText').textContent = `Din ${i + 1}/7 (${day}) — save ho raha hai…`;
                await saveCurrent();
                done.push(day);
            } catch (e) {
                failed.push(day + ': ' + e.message);
            }
        }

        el('progressBar').style.width = '100%';
        alert(
            `✅ Save hua: ${done.length} din` +
            (skipped.length ? `\n⏭ Pehle se tha (skip): ${skipped.join(', ')}` : '') +
            (failed.length ? `\n⚠ Fail (dobara try karo):\n${failed.join('\n')}` : '')
        );
        window.location.reload();
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
                if (count > 0 && confirm(`Kya aap sach me chune hue ${count} rashifal delete karna chahte hain?`)) {
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
