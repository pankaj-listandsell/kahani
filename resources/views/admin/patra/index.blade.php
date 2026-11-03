@extends('layouts.admin')
@section('title', '💌 Prem Patra Studio')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-rose-500 via-pink-600 to-fuchsia-600 rounded-2xl p-6 text-white shadow-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="inline-block px-2.5 py-1 bg-white/20 rounded-full text-xs font-semibold uppercase tracking-wider mb-2">💌 Tag &amp; Share Booster</span>
                <h1 class="text-2xl font-bold">Prem Patra — Love Letter Cards</h1>
                <p class="text-rose-100 text-sm mt-1">Har letter alag love theme me — pehlo prem, pati-patni, judaai, fauji… har theme ka apna rang.</p>
            </div>
            <button id="saveBtn" disabled class="bg-white text-rose-700 font-bold px-5 py-2.5 rounded-xl shadow hover:bg-rose-50 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-2">
                <span>💾</span> Save All Cards
            </button>
        </div>
    </div>

    {{-- Controls --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-4">
        <div class="grid sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">💞 Love Theme</label>
                <select id="theme" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-rose-500 focus:outline-none font-medium"></select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🌐 Language</label>
                <select id="language" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-rose-500 focus:outline-none font-medium">
                    <option value="gujarati" selected>ગુજરાતી (Gujarati)</option>
                    <option value="hindi">हिंदी (Hindi)</option>
                    <option value="hinglish">Hinglish</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🔢 Letter Count</label>
                <select id="count" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-rose-500 focus:outline-none font-medium">
                    <option value="1">1 Letter</option>
                    <option value="3" selected>3 Letters</option>
                    <option value="5">5 Letters</option>
                    <option value="7">7 Letters</option>
                    <option value="12">12 Letters (sab themes)</option>
                </select>
            </div>
        </div>
        <div class="grid sm:grid-cols-4 gap-4">
            <div class="sm:col-span-3">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">✍️ Extra direction (optional)</label>
                <input id="topic" type="text" maxlength="200" placeholder="e.g. varsad ni yaad / gaam thi shaher gayelo premi — khaali chhodo to AI khud chunega"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-rose-500 focus:outline-none">
            </div>
            <div class="flex items-end">
                <button id="genBtn" class="w-full bg-gradient-to-r from-rose-500 to-pink-600 hover:from-rose-600 hover:to-pink-700 text-white font-bold py-2 px-4 rounded-lg shadow transition">
                    <span id="genText">Generate Letters</span>
                </button>
            </div>
        </div>

        <div id="progressBox" class="hidden pt-2">
            <div class="text-xs font-semibold text-slate-600 mb-1"><span id="progressText">AI likh raha hai… (20–60 sec)</span></div>
            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div id="progressBar" class="bg-gradient-to-r from-rose-500 to-pink-600 h-2 transition-all duration-300" style="width: 0%"></div>
            </div>
        </div>
    </div>

    <div id="emptyState" class="bg-white rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center text-slate-400">
        <span class="text-4xl block mb-2">💌</span>
        <p class="font-bold text-slate-600">Abhi koi letter nahi bana</p>
        <p class="text-xs mt-1">"Generate Letters" dabao — har card ke neeche ✏️ Edit se text ya theme badal sakte ho.</p>
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
                            <input type="checkbox" id="selectAll" class="rounded border-slate-300 accent-rose-600 w-4 h-4 cursor-pointer">
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
                <div class="flex items-center justify-between bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 mb-2.5 hover:border-rose-400 hover:bg-rose-50/20 transition gap-3">
                    <input type="checkbox" name="ids[]" value="{{ $c->id }}" class="bulk-item rounded border-slate-300 accent-rose-600 w-4 h-4 cursor-pointer">
                    <a href="{{ route('admin.patra.show', $c) }}" class="flex-1 flex items-center justify-between gap-3 group">
                        <div>
                            <span class="font-bold text-slate-800 group-hover:text-rose-700 transition">💌 {{ $c->title }}</span>
                            <span class="text-xs text-slate-500 ml-2">({{ ucfirst($c->language ?? 'gujarati') }})</span>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 bg-rose-100 text-rose-800 rounded-full">
                            {{ $c->parts_count }} cards · {{ ucfirst($c->status) }}
                        </span>
                    </a>
                    <button type="button" onclick="if(confirm('Kya aap sach me ye collection delete karna chahte hain?')) document.getElementById('single-del-{{ $c->id }}').submit();" class="text-xs font-semibold text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                        <span>🗑</span> Delete
                    </button>
                </div>
            @empty
                <p class="text-sm text-slate-500 text-center py-4">Abhi koi collection save nahi hai — upar se naya banayein! 💌</p>
            @endforelse
        </form>

        @foreach ($collections as $c)
            <form id="single-del-{{ $c->id }}" method="POST" action="{{ route('admin.patra.destroy', $c) }}" class="hidden">
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
    const GEN_URL = @json(route('admin.patra.generate'));
    const SAVE_URL = @json(route('admin.patra.save'));

    const K = window.CanvasKit;
    const W = 1080, H = 1920;
    const SERIF = '"Noto Serif Gujarati", "Noto Serif Devanagari", serif';
    const EMOJI = '"Segoe UI Emoji", "Apple Color Emoji", "Noto Color Emoji", sans-serif';
    const serif = (size, weight = '400') => `${weight} ${size}px ${SERIF}`;
    let items = [];
    let itemsLang = 'gujarati';
    let itemsThemeLabel = '';
    const el = id => document.getElementById(id);

    /**
     * Har love theme ka apna look. Key ViralStudioAiService::PATRA_THEMES se match honi chahiye.
     * bg = upar→neeche gradient, paper = kaagaz, ink = letter ka text, accent = naam/seal/border.
     */
    const THEMES = {
        pehlo_prem:    { bg: ['#fbcfe8', '#db2777'], paper: '#fff7fb', ink: '#4a1d3a', accent: '#db2777', stamp: '🌸', deco: ['🌸', '💗'], label: ['પહેલો પ્રેમ', 'पहला प्यार', 'Pehla Pyaar'] },
        pati_patni:    { bg: ['#fecaca', '#991b1b'], paper: '#fffaf0', ink: '#3f1d1d', accent: '#b91c1c', stamp: '💑', deco: ['❤️', '🌹'], label: ['પતિ-પત્ની', 'पति-पत्नी', 'Pati-Patni'] },
        long_distance: { bg: ['#bfdbfe', '#1e3a8a'], paper: '#f8fbff', ink: '#1e293b', accent: '#1d4ed8', stamp: '✈️', deco: ['✈️', '🌙'], label: ['દૂર રહીને પ્રેમ', 'दूरी वाला प्यार', 'Long Distance Pyaar'] },
        judaai:        { bg: ['#cbd5e1', '#1e293b'], paper: '#f8fafc', ink: '#1f2937', accent: '#475569', stamp: '💔', deco: ['🍂', '💔'], label: ['જુદાઈ', 'जुदाई', 'Judaai'] },
        ek_tarfi:      { bg: ['#ddd6fe', '#4c1d95'], paper: '#fbf8ff', ink: '#2e1065', accent: '#7c3aed', stamp: '🥀', deco: ['🥀', '✨'], label: ['એકતરફી પ્રેમ', 'एकतरफ़ा प्यार', 'Ektarfa Pyaar'] },
        maafi:         { bg: ['#fed7aa', '#c2410c'], paper: '#fffbf5', ink: '#431407', accent: '#c2410c', stamp: '🙏', deco: ['🕊️', '🤍'], label: ['માફીનો પત્ર', 'माफ़ी का ख़त', 'Maafi ka Khat'] },
        proposal:      { bg: ['#fecdd3', '#9f1239'], paper: '#fff8f8', ink: '#4c0519', accent: '#e11d48', stamp: '💍', deco: ['💍', '🌹'], label: ['દિલની કબૂલાત', 'दिल का इज़हार', 'Dil ka Izhaar'] },
        anniversary:   { bg: ['#fde68a', '#b45309'], paper: '#fffdf5', ink: '#422006', accent: '#b45309', stamp: '🥂', deco: ['✨', '💛'], label: ['લગ્નની વર્ષગાંઠ', 'सालगिरह', 'Saalgirah'] },
        budhapa:       { bg: ['#e7d8c3', '#78350f'], paper: '#fbf3e4', ink: '#3b2410', accent: '#92400e', stamp: '☕', deco: ['☕', '🍂'], label: ['જીવનભરનો સાથ', 'उम्रभर का साथ', 'Umarbhar ka Saath'] },
        fauji:         { bg: ['#d9e4c5', '#365314'], paper: '#fbfdf6', ink: '#1a2e05', accent: '#4d7c0f', stamp: '🎖️', deco: ['🎖️', '⭐'], label: ['ફૌજીનો પ્રેમ', 'फ़ौजी का प्यार', 'Fauji ka Pyaar'] },
        yaad:          { bg: ['#99f6e4', '#115e59'], paper: '#f5fffd', ink: '#042f2e', accent: '#0f766e', stamp: '🌧️', deco: ['🌧️', '☕'], label: ['તારી યાદ', 'तेरी याद', 'Teri Yaad'] },
        sagai:         { bg: ['#f5d0fe', '#86198f'], paper: '#fff8fe', ink: '#4a044e', accent: '#c026d3', stamp: '💐', deco: ['💐', '✨'], label: ['નવો સંબંધ', 'नया रिश्ता', 'Naya Rishta'] },
    };
    const LANG_IDX = { gujarati: 0, hindi: 1, hinglish: 2 };
    const themeOf = item => THEMES[item.theme] || THEMES.pehlo_prem;
    const themeLabel = (key, lang) => (THEMES[key] || THEMES.pehlo_prem).label[LANG_IDX[lang] ?? 0];

    el('theme').innerHTML = '<option value="mix" selected>🎲 Mix — har letter alag theme</option>'
        + Object.entries(THEMES).map(([k, t]) => `<option value="${k}">${t.stamp} ${K.esc(t.label[0])} (${K.esc(t.label[2])})</option>`).join('');

    const I18N = {
        gujarati: { badge: '💌 પ્રેમ પત્ર', cta: 'આ પત્ર કોને મોકલશો? ટેગ કરો 💌' },
        hindi:    { badge: '💌 प्रेम पत्र', cta: 'ये ख़त किसे भेजेंगे? टैग करें 💌' },
        hinglish: { badge: '💌 Prem Patra', cta: 'Ye khat kise bhejoge? Tag karo 💌' },
    };
    const tr = () => I18N[itemsLang] || I18N.gujarati;

    // Letter serif font me — Google Fonts sample text ke bina sirf latin subset laata hai
    let serifReady = null;
    function ensureSerif() {
        if (!serifReady) {
            serifReady = (async () => {
                await K.ensureFonts();
                for (const f of ['400 48px "Noto Serif Gujarati"', '700 48px "Noto Serif Gujarati"',
                                 '400 48px "Noto Serif Devanagari"', '700 48px "Noto Serif Devanagari"']) {
                    try { await document.fonts.load(f, 'અઆક કખગ अआक'); } catch (e) {}
                }
            })();
        }
        return serifReady;
    }

    el('genBtn').addEventListener('click', async () => {
        const btn = el('genBtn');
        btn.disabled = true;
        el('genText').textContent = 'Generating…';
        el('progressBox').classList.remove('hidden');
        el('progressText').textContent = 'AI letters likh raha hai… (20–60 sec)';
        el('progressBar').style.width = '40%';

        try {
            const r = await fetch(GEN_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({
                    count: el('count').value, language: el('language').value,
                    theme: el('theme').value, topic: el('topic').value,
                })
            });
            const d = await r.json();
            if (!d.ok) throw new Error(K.saveError(d));

            items = Array.isArray(d.items) ? d.items : [];
            if (!items.length) throw new Error('AI ne koi letter nahi diya — dobara try karo.');
            itemsLang = el('language').value;
            itemsThemeLabel = el('theme').value === 'mix' ? 'Mix' : themeLabel(el('theme').value, 'hinglish');
            el('progressBar').style.width = '80%';
            await ensureSerif();
            renderAllPreviews();

            el('progressBar').style.width = '100%';
            el('saveBtn').disabled = false;
            setTimeout(() => el('progressBox').classList.add('hidden'), 500);
        } catch (e) {
            alert('Error: ' + e.message);
            el('progressBox').classList.add('hidden');
        } finally {
            btn.disabled = false;
            el('genText').textContent = 'Generate Letters';
        }
    });

    // ---------- Previews + edit ----------
    const FIELDS = [
        ['hook', 'Upar ki line (hook)', 2], ['salutation', 'Sambodhan', 1], ['body', 'Letter (paragraph ke beech khaali line)', 9],
        ['closing', 'Ant', 1], ['signature', 'Naam', 1],
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
                <div class="p-3 bg-rose-50 border-b border-rose-100 flex items-center justify-between gap-2 text-xs font-bold text-rose-900">
                    <span class="truncate head"></span>
                    <button type="button" class="remove shrink-0 text-red-600 hover:underline">✕</button>
                </div>
                <div class="p-3 canvas-slot"></div>
                <details class="border-t border-slate-100 text-xs">
                    <summary class="px-3 py-2 cursor-pointer font-semibold text-slate-600 hover:bg-slate-50">✏️ Edit text / theme</summary>
                    <div class="p-3 space-y-2 fields"></div>
                </details>`;

            const head = box.querySelector('.head');
            const setHead = () => head.textContent = `#${idx + 1}: ${themeOf(item).stamp} ${themeLabel(item.theme, 'hinglish')}`;
            setHead();

            const canvas = document.createElement('canvas');
            canvas.width = W; canvas.height = H;
            canvas.className = 'w-full aspect-[9/16] rounded-lg';
            box.querySelector('.canvas-slot').appendChild(canvas);
            drawPatra(canvas, item);

            const fields = box.querySelector('.fields');
            const themeWrap = document.createElement('label');
            themeWrap.className = 'block';
            themeWrap.innerHTML = `<span class="font-semibold text-slate-500">Theme (design)</span>
                <select class="mt-0.5 w-full rounded border border-slate-300 px-2 py-1 text-xs">
                    ${Object.entries(THEMES).map(([k, t]) => `<option value="${k}">${t.stamp} ${K.esc(t.label[2])}</option>`).join('')}
                </select>`;
            const sel = themeWrap.querySelector('select');
            sel.value = THEMES[item.theme] ? item.theme : 'pehlo_prem';
            sel.addEventListener('change', () => { item.theme = sel.value; setHead(); drawPatra(canvas, item); });
            fields.appendChild(themeWrap);

            FIELDS.forEach(([key, label, rows]) => {
                const wrap = document.createElement('label');
                wrap.className = 'block';
                wrap.innerHTML = `<span class="font-semibold text-slate-500">${label}</span>
                    <textarea rows="${rows}" class="mt-0.5 w-full rounded border border-slate-300 px-2 py-1 text-xs"></textarea>`;
                const ta = wrap.querySelector('textarea');
                ta.value = K.str(item[key]);
                ta.addEventListener('input', () => { item[key] = ta.value; drawPatra(canvas, item); });
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
    function rng(seed) {
        return () => (seed = (seed * 16807) % 2147483647) / 2147483647;
    }

    function seedOf(s) {
        let h = 7;
        for (const ch of String(s)) h = (h * 31 + ch.codePointAt(0)) % 2147483646;
        return h + 1;
    }

    function drawBackground(ctx, T, seed) {
        const g = ctx.createLinearGradient(0, 0, 0, H);
        g.addColorStop(0, T.bg[0]);
        g.addColorStop(1, T.bg[1]);
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);

        // Theme ke halke emoji — har letter ka alag bikhraav
        const r = rng(seed);
        ctx.save();
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        for (let i = 0; i < 16; i++) {
            ctx.globalAlpha = 0.12 + r() * 0.12;
            ctx.font = `${Math.round(50 + r() * 70)}px ${EMOJI}`;
            ctx.fillText(T.deco[i % T.deco.length], r() * W, r() * H);
        }
        ctx.restore();
    }

    /** Postage stamp — kaagaz ke upar-daaye kone me, thoda tircha. */
    function drawStamp(ctx, T, x, y) {
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate(-0.12);
        ctx.fillStyle = '#ffffff';
        ctx.shadowColor = 'rgba(0,0,0,0.15)'; ctx.shadowBlur = 10;
        ctx.fillRect(-70, -80, 140, 160);
        ctx.shadowBlur = 0;
        ctx.strokeStyle = T.accent; ctx.lineWidth = 3;
        ctx.setLineDash([8, 7]);
        ctx.strokeRect(-58, -68, 116, 136);
        ctx.setLineDash([]);
        ctx.font = `80px ${EMOJI}`;
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(T.stamp, 0, 4);
        ctx.restore();
    }

    /** Letter ka text: sambodhan + paragraphs + ant + naam — size chhota karte jao jab tak fit na ho. */
    function layoutLetter(ctx, item, maxW, maxH) {
        const paras = K.str(item.body).split(/\n\s*\n|\n/).map(s => s.trim()).filter(Boolean);
        for (let s = 50; ; s -= 2) {
            const lh = s * 1.6;
            ctx.font = serif(s, '400');
            const bodyLines = paras.map(p => K.wrapLines(ctx, p, maxW));
            ctx.font = serif(Math.round(s * 1.1), '700');
            const sal = K.wrapLines(ctx, K.str(item.salutation), maxW);
            const height = sal.length * lh + s * 0.6
                + bodyLines.reduce((a, l) => a + l.length * lh, 0) + (paras.length - 1) * s * 0.7
                + s * 0.9 + lh + s * 1.3;
            if (height <= maxH || s <= 28) return { s, lh, sal, bodyLines, height };
        }
    }

    function drawPatra(c, item) {
        const ctx = c.getContext('2d');
        const T = themeOf(item);
        K.reset(ctx);
        drawBackground(ctx, T, seedOf(item.signature + item.theme));

        // Upar badge: 💌 Prem Patra • theme
        const badge = tr().badge + '  •  ' + themeLabel(item.theme, itemsLang);
        ctx.font = K.font(38);
        const bw = Math.min(W - 120, ctx.measureText(badge).width + 80);
        ctx.fillStyle = 'rgba(255,255,255,0.92)';
        ctx.beginPath(); ctx.roundRect(W / 2 - bw / 2, 62, bw, 80, 40); ctx.fill();
        ctx.fillStyle = T.accent;
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        K.fitOneLine(ctx, badge, bw - 50, 38, 26);
        ctx.fillText(badge, W / 2, 104);

        // Hook line
        ctx.save();
        ctx.fillStyle = '#ffffff';
        ctx.shadowColor = 'rgba(0,0,0,0.35)'; ctx.shadowBlur = 16;
        const hook = K.fit(ctx, K.str(item.hook), { maxW: W - 140, maxH: 190, size: 62, minSize: 40, lineHeight: 1.25 });
        K.drawLines(ctx, hook.lines, W / 2, 262, hook.lh);
        ctx.restore();

        // Kaagaz
        const px = 70, py = 380, pw = W - 140, ph = 1290;
        ctx.save();
        ctx.shadowColor = 'rgba(0,0,0,0.28)'; ctx.shadowBlur = 40; ctx.shadowOffsetY = 12;
        ctx.fillStyle = T.paper;
        ctx.beginPath(); ctx.roundRect(px, py, pw, ph, 18); ctx.fill();
        ctx.restore();
        ctx.strokeStyle = T.accent; ctx.globalAlpha = 0.35; ctx.lineWidth = 3;
        ctx.strokeRect(px + 22, py + 22, pw - 44, ph - 44);
        ctx.lineWidth = 1.5;
        ctx.strokeRect(px + 32, py + 32, pw - 64, ph - 64);
        ctx.globalAlpha = 1;
        drawStamp(ctx, T, px + pw - 110, py + 120);

        // Letter
        const tx = px + 75, tw = pw - 150;
        const top = py + 110, avail = ph - 230;
        const L = layoutLetter(ctx, item, tw, avail);
        let y = top + Math.max(0, (avail - L.height) / 3) + L.lh / 2;
        ctx.textBaseline = 'middle';

        ctx.textAlign = 'left';
        ctx.fillStyle = T.accent;
        ctx.font = serif(Math.round(L.s * 1.1), '700');
        L.sal.forEach(line => { ctx.fillText(line, tx, y); y += L.lh; });
        y += L.s * 0.6;

        ctx.fillStyle = T.ink;
        ctx.font = serif(L.s, '400');
        L.bodyLines.forEach((lines, i) => {
            if (i) y += L.s * 0.7;
            lines.forEach(line => { ctx.fillText(line, tx, y); y += L.lh; });
        });

        y += L.s * 0.9;
        ctx.textAlign = 'right';
        ctx.fillStyle = T.ink;
        ctx.font = serif(L.s, '400');
        ctx.fillText(K.str(item.closing), tx + tw, y);
        y += L.s * 1.3;
        ctx.fillStyle = T.accent;
        ctx.font = serif(Math.round(L.s * 1.2), '700');
        ctx.fillText(K.str(item.signature), tx + tw, y);

        // Seal (laakh ki mohar) kaagaz ke neeche beech me
        const sy = py + ph;
        ctx.save();
        ctx.shadowColor = 'rgba(0,0,0,0.3)'; ctx.shadowBlur = 14;
        ctx.fillStyle = T.accent;
        ctx.beginPath(); ctx.arc(W / 2, sy, 62, 0, Math.PI * 2); ctx.fill();
        ctx.restore();
        ctx.strokeStyle = 'rgba(255,255,255,0.5)'; ctx.lineWidth = 3;
        ctx.beginPath(); ctx.arc(W / 2, sy, 48, 0, Math.PI * 2); ctx.stroke();
        ctx.textAlign = 'center';
        ctx.font = `56px ${EMOJI}`;
        ctx.fillText('💌', W / 2, sy + 4);

        // CTA
        ctx.save();
        ctx.fillStyle = '#ffffff';
        ctx.shadowColor = 'rgba(0,0,0,0.35)'; ctx.shadowBlur = 12;
        K.fitOneLine(ctx, tr().cta, W - 120, 48, 30);
        ctx.fillText(tr().cta, W / 2, 1790);
        ctx.restore();
    }

    /** Reel voiceover: hook + poora letter. */
    function voiceText(item) {
        return [
            K.str(item.hook), K.str(item.salutation), K.str(item.body),
            K.str(item.closing) + ' ' + K.str(item.signature),
        ].map(s => s.trim()).filter(Boolean).join('\n\n');
    }

    // ---------- Save ----------
    el('saveBtn').addEventListener('click', async () => {
        if (!items.length) return;
        const btn = el('saveBtn');
        btn.disabled = true;
        el('progressBox').classList.remove('hidden');
        el('progressText').textContent = 'Saving…';
        el('progressBar').style.width = '30%';
        await ensureSerif();

        const off = document.createElement('canvas'); off.width = W; off.height = H;
        const cardsToSave = items.map((item, idx) => {
            drawPatra(off, item);
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
                body: JSON.stringify({ language: itemsLang, category: 'Prem Patra: ' + itemsThemeLabel, cards: cardsToSave })
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
