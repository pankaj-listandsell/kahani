@extends('layouts.admin')
@section('title', 'Shayari & Jokes Studio')

@section('content')
<div class="max-w-5xl">
    <h2 class="text-xl font-bold flex items-center gap-2">✨ Shayari &amp; Jokes Studio</h2>
    <p class="text-slate-500 mb-6">Topic likho → AI khoobsurat Shayari / Jokes / Suvichar banata hai → ek click me sundar cards ban ke save. Fir auto-post inhe apne-aap IG/YouTube/Facebook par daalta rehta hai.</p>

    {{-- ============ CONTROLS ============ --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Type</label>
                <select id="type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="shayari">🖊️ Shayari</option>
                    <option value="joke">😂 Jokes</option>
                    <option value="quote">🌟 Suvichar / Quotes</option>
                    <option value="status">🔥 Status</option>
                    <option value="fact">🤯 Facts</option>
                    <option value="ukhana">🧩 Ukhana / Paheli</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">🌐 Language</label>
                <select id="language" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="hindi">हिंदी Hindi</option>
                    <option value="gujarati">ગુજરાતી Gujarati</option>
                    <option value="hinglish">Hindi-English (Roman)</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Topic / Mood</label>
                <input type="text" id="category" list="catList" placeholder="e.g. Love"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <datalist id="catList"></datalist>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Kitne?</label>
                <input type="number" id="count" value="10" min="1" max="30"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Theme 🎨</label>
                <select id="theme" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></select>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Watermark / Handle <span class="text-slate-400">(optional)</span></label>
                <input type="text" id="handle" placeholder="@yourpage"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="flex items-end">
                <button id="genBtn" class="w-full bg-violet-600 hover:bg-violet-700 text-white font-medium rounded-lg px-5 py-2.5 text-sm">
                    ✨ Generate
                </button>
            </div>
        </div>

        {{-- Background photo (Pixabay) --}}
        <div class="flex items-center gap-3 flex-wrap border-t border-slate-100 pt-4">
            <span class="text-sm font-medium">🖼️ Background photo</span>
            <button type="button" id="bgBtn" class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-50 text-xs">
                🔍 Pixabay se chuno
            </button>
            <button type="button" id="bgClear" class="hidden px-3 py-1.5 rounded-lg border border-red-300 text-red-600 hover:bg-red-50 text-xs">
                ✕ Hatao
            </button>
            <img id="bgThumb" class="hidden h-12 w-12 object-cover rounded-lg border border-slate-200" alt="">
            <span class="text-xs text-slate-400">Na chuno to theme ka gradient lagega</span>
        </div>

        <p id="msg" class="text-sm text-slate-500"></p>
    </div>

    {{-- ============ PREVIEW GRID ============ --}}
    <div id="previewWrap" class="hidden mt-6">
        <div class="flex items-center justify-between mb-3 gap-3 flex-wrap">
            <h3 class="font-semibold"><span id="itemCount">0</span> cards ready</h3>
            <button id="saveBtn" class="bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg px-5 py-2.5 text-sm">
                ✅ Save All Cards
            </button>
        </div>
        <div id="grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3"></div>
        <div id="progress" class="hidden mt-4">
            <div class="w-full bg-slate-200 rounded-full h-3"><div id="bar" class="bg-rose-600 h-3 rounded-full transition-all" style="width:0%"></div></div>
            <p id="progressText" class="text-sm text-slate-600 mt-2"></p>
        </div>
    </div>

    {{-- ============ PIXABAY BACKGROUND PICKER ============ --}}
    <div id="bgModal" class="hidden fixed inset-0 z-40 bg-black/50 p-4 overflow-y-auto">
        <div class="bg-white rounded-xl max-w-3xl mx-auto mt-10 p-5">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h3 class="font-semibold">🖼️ Background photo (Pixabay)</h3>
                <button id="bgClose" class="text-slate-400 hover:text-slate-700 text-xl leading-none">&times;</button>
            </div>
            <div class="flex gap-2 mb-2">
                <input type="text" id="bgQuery" list="bgList" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                       placeholder="e.g. rain night, sunset, tea, mountains">
                <datalist id="bgList"></datalist>
                <button id="bgGo" class="bg-violet-600 hover:bg-violet-700 text-white rounded-lg px-4 py-2 text-sm">Search</button>
            </div>
            <p class="text-[11px] text-slate-400 mb-1">
                💡 Pixabay ANGREZI me hi theek dhoondhta hai — neeche se chuno ya English me likho:
            </p>
            <div id="bgChips" class="flex flex-wrap gap-1.5 mb-3"></div>
            <p id="bgMsg" class="text-sm text-slate-500 mb-3"></p>
            <div id="bgGrid" class="grid grid-cols-3 sm:grid-cols-4 gap-2"></div>
            <p class="text-[11px] text-slate-400 mt-4">
                Pixabay License — commercial use allowed, attribution zaroori nahi.
                Sirf khadi (vertical) photos dikhti hain kyunki cards 9:16 hote hain.
            </p>
        </div>
    </div>

    {{-- ============ EXISTING COLLECTIONS ============ --}}
    <div class="mt-8">
        <h3 class="font-semibold mb-3">Saved collections</h3>
        @forelse ($collections as $c)
            <a href="{{ route('admin.studio.show', $c) }}"
               class="flex items-center justify-between bg-white border border-slate-200 rounded-lg px-4 py-3 mb-2 hover:border-violet-300">
                <span class="font-medium">
                    @php($icon = ['shayari'=>'🖊️','joke'=>'😂','quote'=>'🌟','status'=>'🔥','fact'=>'🤯','ukhana'=>'🧩'][$c->type] ?? '✨')
                    {{ $icon }} {{ $c->title }}
                </span>
                <span class="text-xs text-slate-400">{{ $c->parts_count }} cards · {{ ucfirst($c->status) }}</span>
            </a>
        @empty
            <p class="text-sm text-slate-500">Abhi koi collection nahi — upar se pehli banao. ✨</p>
        @endforelse
    </div>
</div>

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name=csrf-token]').content;
const GEN_URL  = @json(route('admin.studio.generate'));
const SAVE_URL = @json(route('admin.studio.save'));
const BG_SEARCH_URL = @json(route('admin.studio.bg.search'));
const BG_PICK_URL   = @json(route('admin.studio.bg.pick'));
const el = id => document.getElementById(id);

const postJson = (url, body) => fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    body: JSON.stringify(body),
}).then(r => r.json());

function loadImage(url) {
    return new Promise((res, rej) => {
        const img = new Image();
        img.onload = () => res(img);
        img.onerror = () => rej(new Error('image load fail'));
        img.src = url; // hamesha apni site se — warna canvas taint ho jaata hai
    });
}

// Card size (9:16, reel/short/IG)
const W = 1080, H = 1920;

// ---------- Category suggestions per type ----------
const CATS = {
    shayari: [
        'Love', 'Romantic', 'Ishq', 'Mohabbat', 'Sad', 'Dard', 'Bewafa', 'Judai',
        'Dosti', 'Yaadein', 'Zindagi', 'Tanhai', 'Intezaar', 'Aankhein', 'Chaand',
        'Barish', 'Ishqiya', 'Attitude', 'Motivational', 'Dil', 'Pyaar', 'Gam',
        'Khushi', 'Family', 'Maa', 'Papa', 'Behan-Bhai', 'Good Morning', 'Good Night',
        'Birthday', 'Festival', 'Desh Bhakti', 'Shukrana',
    ],
    joke:    [
        'Funny', 'Pati-Patni', 'Santa-Banta', 'School', 'Teacher-Student', 'Office',
        'Boss-Employee', 'Bachpan', 'Doctor', 'Neta', 'Girlfriend-Boyfriend',
        'Sharabi', 'Kanjoos', 'Padosi', 'Exam', 'WhatsApp', 'Ghar-Grihasti',
        'Chai', 'Petrol', 'Mehngai', 'Bakait',
    ],
    quote:   [
        'Motivational', 'Success', 'Good Morning', 'Good Night', 'Life', 'Attitude',
        'Positive', 'Hard Work', 'Self Confidence', 'Time', 'Discipline', 'Dreams',
        'Study', 'Struggle', 'Karma', 'Gratitude', 'Leadership', 'Spiritual',
        'Bhagavad Gita', 'Chanakya Niti', 'Health', 'Money', 'Friendship', 'Family',
    ],
    status:  [
        'Attitude', 'Love', 'Sad', 'Motivational', 'Funny', 'Friendship', 'Life',
        'Alone', 'Royal', 'Swag', 'Desh Bhakti', 'Good Vibes', 'Success', 'Breakup',
        'Cool', 'Savage', 'Girls', 'Boys',
    ],
    fact:    [
        'Science', 'Space', 'Human Body', 'Animals', 'History', 'India', 'World',
        'Technology', 'Nature', 'Ocean', 'Brain', 'Food', 'Money', 'Sports',
        'Psychology', 'Amazing', 'Weird', 'Health',
    ],
    ukhana:  [
        'Shareer / Body', 'Ghar ki cheezein', 'Rasoi / Kitchen', 'Jaanwar', 'Pakshi',
        'Phal / Fruits', 'Sabzi', 'Prakriti / Nature', 'Aakash / Sky', 'Paani',
        'School', 'Khilone', 'Kapde', 'Tyohaar', 'Ped-Paudhe', 'Keede-Makode',
        'Rang', 'Aawaz', 'Mausam', 'Sawari / Vehicles',
    ],
};

// ---------- Themes ----------
// bg: [c1, c2] gradient (same = solid) · deco: extra decoration
const THEMES = {
    night:   { name: '🌙 Night Sky',   bg: ['#0b1224', '#1e293b'], text: '#f8fafc', accent: '#fbbf24', serif: true,  deco: 'stars'  },
    paper:   { name: '📜 Paper',       bg: ['#f6ecd4', '#e6d3ab'], text: '#3a2c19', accent: '#9a5b23', serif: true,  deco: 'border' },
    floral:  { name: '🌸 Floral',      bg: ['#fde7f1', '#e6d5ff'], text: '#5b2a4e', accent: '#db2777', serif: true,  deco: 'corner' },
    urdu:    { name: '🕌 Urdu Classic', bg: ['#3a0d12', '#6d181c'], text: '#f6e7c8', accent: '#e7c15b', serif: true,  deco: 'quotes' },
    minimal: { name: '⚡ Minimal',     bg: ['#0f172a', '#0f172a'], text: '#ffffff', accent: '#38bdf8', serif: false, deco: 'line'   },
    pop:     { name: '😂 Joke Pop',    bg: ['#fde68a', '#fca5a5'], text: '#1f2937', accent: '#dc2626', serif: false, deco: 'none'   },
    sunset:  { name: '🌇 Sunset',      bg: ['#ff512f', '#dd2476'], text: '#fff7ed', accent: '#ffe08a', serif: true,  deco: 'glow'   },
    ocean:   { name: '🌊 Ocean',       bg: ['#2193b0', '#6dd5ed'], text: '#ffffff', accent: '#e0fbfc', serif: true,  deco: 'line'   },
    royal:   { name: '👑 Royal',       bg: ['#41295a', '#2f0743'], text: '#f3e8ff', accent: '#f0c65a', serif: true,  deco: 'quotes' },
    rosegold:{ name: '🌹 Rose Gold',   bg: ['#f7cac9', '#f3e0dc'], text: '#7a3b47', accent: '#bd6b73', serif: true,  deco: 'corner' },
    forest:  { name: '🌿 Forest',      bg: ['#0f2027', '#203a43'], text: '#eafff0', accent: '#a7e8bd', serif: true,  deco: 'corner' },
    neon:    { name: '💫 Neon',        bg: ['#0d0d0d', '#1a1a2e'], text: '#ffffff', accent: '#00f5d4', serif: false, deco: 'glow'   },
    peach:   { name: '🍑 Peach',       bg: ['#ffecd2', '#fcb69f'], text: '#7c3a2d', accent: '#e07a5f', serif: true,  deco: 'dots'   },
    midnight:{ name: '🌌 Midnight',    bg: ['#232526', '#414345'], text: '#f5f5f5', accent: '#c0c0c0', serif: false, deco: 'stars'  },
    candy:   { name: '🍭 Candy',       bg: ['#a18cd1', '#fbc2eb'], text: '#4a2c5a', accent: '#d6336c', serif: true,  deco: 'dots'   },
    gold:    { name: '✨ Black Gold',  bg: ['#0a0a0a', '#1c1c1c'], text: '#f7e7b4', accent: '#d4af37', serif: true,  deco: 'frame'  },
    // Bal-sahitya style — garam mitti wale rang, bachchon ki kitaab jaisa
    kidsWarm:{ name: '🧩 Kids Warm',   bg: ['#ffe9c7', '#f6c68a'], text: '#4a2b12', accent: '#c2410c', serif: false, deco: 'dots'   },
    kidsSky: { name: '🎈 Kids Sky',    bg: ['#d9f0ff', '#a8dcf5'], text: '#12384f', accent: '#e0620d', serif: false, deco: 'dots'   },
};

// Theme dropdown fill
Object.entries(THEMES).forEach(([k, t]) => {
    const o = document.createElement('option');
    o.value = k; o.textContent = t.name;
    el('theme').appendChild(o);
});

// Category datalist sync
function syncCats() {
    const type = el('type').value;
    const list = CATS[type] || [];
    el('catList').innerHTML = list.map(c => `<option value="${c}">`).join('');
    // Type-appropriate default theme
    const defTheme = { joke: 'pop', status: 'neon', fact: 'midnight', ukhana: 'kidsWarm' };
    if (defTheme[type]) el('theme').value = defTheme[type];
}
el('type').addEventListener('change', syncCats);
syncCats();

// ---------- Canvas helpers ----------
function hexRgb(h) { const n = parseInt(h.slice(1), 16); return [(n>>16)&255, (n>>8)&255, n&255]; }

function wrap(ctx, text, maxW) {
    const out = [];
    text.split(/\n/).forEach(para => {
        if (para.trim() === '') { return; }
        const words = para.split(/\s+/);
        let line = '';
        words.forEach(w => {
            const test = line ? line + ' ' + w : w;
            if (ctx.measureText(test).width > maxW && line) { out.push(line); line = w; }
            else line = test;
        });
        if (line) out.push(line);
    });
    return out.length ? out : [''];
}

// Auto-fit: font size dhoondo jisse text width+height me fit ho jaaye
function fitLines(ctx, text, maxW, maxH, fontFam, weight, maxSize) {
    let size = maxSize;
    while (size > 22) {
        ctx.font = `${weight} ${size}px ${fontFam}`;
        const lines = wrap(ctx, text, maxW);
        const lh = size * 1.5;
        if (lines.length * lh <= maxH) return { size, lines, lh };
        size -= 3;
    }
    ctx.font = `${weight} ${size}px ${fontFam}`;
    return { size, lines: wrap(ctx, text, maxW), lh: size * 1.5 };
}

function drawDeco(ctx, theme) {
    ctx.save();
    if (theme.deco === 'stars') {
        // Deterministic "stars" (Math.random na use karके consistent)
        ctx.fillStyle = 'rgba(255,255,255,0.75)';
        for (let i = 0; i < 60; i++) {
            const x = ((i * 137) % W), y = ((i * 251) % (H * 0.9));
            const r = (i % 3 === 0) ? 2.5 : 1.3;
            ctx.beginPath(); ctx.arc(x, y, r, 0, 7); ctx.fill();
        }
    } else if (theme.deco === 'border') {
        ctx.strokeStyle = theme.accent; ctx.lineWidth = 4;
        roundRect(ctx, 60, 60, W - 120, H - 120, 28); ctx.stroke();
        ctx.lineWidth = 1.5;
        roundRect(ctx, 80, 80, W - 160, H - 160, 22); ctx.stroke();
    } else if (theme.deco === 'corner') {
        ctx.fillStyle = theme.accent; ctx.globalAlpha = 0.25;
        [[120,160],[W-120,160],[120,H-200],[W-120,H-200]].forEach(([x,y]) => {
            ctx.beginPath(); ctx.arc(x, y, 60, 0, 7); ctx.fill();
        });
        ctx.globalAlpha = 1;
    } else if (theme.deco === 'quotes') {
        ctx.fillStyle = theme.accent; ctx.globalAlpha = 0.35;
        ctx.font = '900 320px Georgia, serif';
        ctx.textAlign = 'left';  ctx.textBaseline = 'top';    ctx.fillText('“', 70, 120);
        ctx.textAlign = 'right'; ctx.textBaseline = 'bottom'; ctx.fillText('”', W - 70, H - 220);
        ctx.globalAlpha = 1;
    } else if (theme.deco === 'line') {
        ctx.strokeStyle = theme.accent; ctx.lineWidth = 6;
        ctx.beginPath(); ctx.moveTo(W/2 - 70, 220); ctx.lineTo(W/2 + 70, 220); ctx.stroke();
    } else if (theme.deco === 'glow') {
        // Center-top se soft light glow (dark themes par bahut sundar)
        const g = ctx.createRadialGradient(W/2, H*0.32, 0, W/2, H*0.32, W*0.9);
        g.addColorStop(0, 'rgba(255,255,255,0.16)');
        g.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    } else if (theme.deco === 'dots') {
        // Halka repeating dot pattern
        ctx.fillStyle = theme.accent; ctx.globalAlpha = 0.12;
        for (let y = 130; y < H - 130; y += 72) {
            for (let x = 120; x < W - 90; x += 72) { ctx.beginPath(); ctx.arc(x, y, 4, 0, 7); ctx.fill(); }
        }
        ctx.globalAlpha = 1;
    } else if (theme.deco === 'frame') {
        // Elegant corner brackets (L-shaped)
        ctx.strokeStyle = theme.accent; ctx.lineWidth = 5;
        const m = 90, len = 110;
        [[m, m, 1, 1], [W - m, m, -1, 1], [m, H - m, 1, -1], [W - m, H - m, -1, -1]].forEach(([x, y, dx, dy]) => {
            ctx.beginPath();
            ctx.moveTo(x, y + dy * len); ctx.lineTo(x, y); ctx.lineTo(x + dx * len, y);
            ctx.stroke();
        });
    }
    ctx.restore();
}

function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    if (ctx.roundRect) { ctx.roundRect(x, y, w, h, r); return; }
    ctx.moveTo(x+r,y); ctx.arcTo(x+w,y,x+w,y+h,r); ctx.arcTo(x+w,y+h,x,y+h,r);
    ctx.arcTo(x,y+h,x,y,r); ctx.arcTo(x,y,x+w,y,r); ctx.closePath();
}

// ---------- Ukhana / Paheli card ----------
// Bal-sahitya wali shaili: upar "ઓળખી બતાવો – N", beech me paheli, neeche
// "jawab comment me" wali CTA. Jawab card par NAHI aata — caption me jaata hai.
const UKHANA_TITLE = {
    gujarati: 'ઓળખી બતાવો',
    hindi:    'पहचानो तो जानें',
    hinglish: 'Pehchano to jaane',
};
const UKHANA_CTA = {
    gujarati: 'જવાબ કોમેન્ટમાં લખો 👇',
    hindi:    'जवाब कमेंट में लिखो 👇',
    hinglish: 'Jawab comment me likho 👇',
};

function renderUkhana(canvas, item, themeKey, handle, language) {
    const t = THEMES[themeKey] || THEMES.kidsWarm;
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');
    const EMOJI_F = '"Segoe UI Emoji","Noto Color Emoji","Apple Color Emoji"';
    const sans = `"Noto Sans Devanagari","Noto Sans Gujarati",${EMOJI_F}`;

    const hasPhoto = drawBg(ctx, t);
    if (!hasPhoto) {
        const g = ctx.createLinearGradient(0, 0, W, H);
        g.addColorStop(0, t.bg[0]); g.addColorStop(1, t.bg[1]);
        ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
        drawDeco(ctx, t);
    }
    const inkText = hasPhoto ? '#ffffff' : t.text;

    ctx.textAlign = 'center';

    // ---- Header: "ઓળખી બતાવો" ek pill me ----
    const title = UKHANA_TITLE[language] || UKHANA_TITLE.hindi;
    ctx.textBaseline = 'middle';
    let hs = 62;
    ctx.font = `800 ${hs}px ${sans}`;
    while (hs > 34 && ctx.measureText(title).width > W - 300) { hs -= 2; ctx.font = `800 ${hs}px ${sans}`; }
    const hw = ctx.measureText(title).width;
    roundRect(ctx, W/2 - hw/2 - 56, 150, hw + 112, hs + 56, (hs + 56) / 2);
    ctx.fillStyle = t.accent; ctx.fill();
    ctx.fillStyle = '#ffffff';
    ctx.fillText(title, W/2, 150 + (hs + 56) / 2);

    // ---- Paheli (beech me, bada) ----
    ctx.textBaseline = 'top';
    const pad = 120, maxW = W - pad * 2;
    const body = fitLines(ctx, item.text, maxW, H * 0.44, sans, '700', 84);
    let y = (H - body.lines.length * body.lh) / 2 - 40;
    ctx.shadowColor = hasPhoto ? 'rgba(0,0,0,0.75)' : 'rgba(0,0,0,0.14)';
    ctx.shadowBlur = hasPhoto ? 22 : 10;
    ctx.shadowOffsetY = 3;
    ctx.fillStyle = inkText;
    ctx.font = `700 ${body.size}px ${sans}`;
    body.lines.forEach(l => { ctx.fillText(l, W/2, y); y += body.lh; });
    ctx.shadowColor = 'transparent'; ctx.shadowBlur = 0; ctx.shadowOffsetY = 0;

    // ---- Bada "?" (paheli ke neeche) — sirf tab jab CTA se takraye nahi ----
    if (y + 150 < H - 400) {
        ctx.fillStyle = t.accent; ctx.globalAlpha = 0.3;
        ctx.font = `900 200px ${sans}`;
        ctx.textBaseline = 'middle';
        ctx.fillText('?', W/2, y + 150);
        ctx.globalAlpha = 1;
    }

    // ---- CTA ribbon ----
    const cta = UKHANA_CTA[language] || UKHANA_CTA.hindi;
    let cs = 50;
    ctx.font = `700 ${cs}px ${sans}`;
    while (cs > 30 && ctx.measureText(cta).width > W - 260) { cs -= 2; ctx.font = `700 ${cs}px ${sans}`; }
    const cw = ctx.measureText(cta).width;
    roundRect(ctx, W/2 - cw/2 - 48, H - 300, cw + 96, cs + 52, 22);
    ctx.fillStyle = t.accent; ctx.fill();
    ctx.fillStyle = '#ffffff';
    ctx.fillText(cta, W/2, H - 300 + (cs + 52) / 2);

    // ---- Handle ----
    const hh = (handle || '').trim();
    if (hh) {
        ctx.fillStyle = inkText; ctx.globalAlpha = 0.75;
        ctx.font = `600 36px ${sans}`;
        ctx.fillText(hh, W/2, H - 130);
        ctx.globalAlpha = 1;
    }
}

// ---------- Background photo (Pixabay) ----------
// null = koi photo nahi, theme ka gradient hi chalega.
let bgImage = null;

/**
 * Photo ko poore card par "cover" ki tarah bharo (aspect bina bigaade, extra
 * hissa kat jaata hai), phir upar dark scrim — warna photo par text padha
 * nahi jaata.
 */
function drawBg(ctx, t) {
    if (!bgImage) return false;

    const iw = bgImage.naturalWidth || bgImage.width, ih = bgImage.naturalHeight || bgImage.height;
    if (!iw || !ih) return false;

    // BLUR zaroori hai — sharp photo ki detail (pattern, bheed, shaakhein) text
    // ke peeche shor macha deti hai aur shayari padhi nahi jaati. Blur ke baad
    // photo sirf rang aur mahaul deti hai. Image thodi badi draw karte hain
    // warna blur se kinaare halke pad jaate hain.
    const r = Math.max(W / iw, H / ih) * 1.12;
    ctx.save();
    ctx.filter = 'blur(26px)';
    ctx.drawImage(bgImage, (W - iw * r) / 2, (H - ih * r) / 2, iw * r, ih * r);
    ctx.restore();
    ctx.filter = 'none';

    // Upar-neeche gehra, beech me thoda halka — text beech me hota hai
    const s = ctx.createLinearGradient(0, 0, 0, H);
    s.addColorStop(0,    'rgba(0,0,0,0.70)');
    s.addColorStop(0.45, 'rgba(0,0,0,0.55)');
    s.addColorStop(1,    'rgba(0,0,0,0.72)');
    ctx.fillStyle = s; ctx.fillRect(0, 0, W, H);

    return true;
}

// Ek card render karo (full 1080x1920)
function renderCard(canvas, item, themeKey, handle) {
    const t = THEMES[themeKey] || THEMES.night;
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');
    // Emoji + Gujarati font fallback (Devanagari/Gujarati/Roman + color emoji)
    const EMOJI = '"Segoe UI Emoji","Noto Color Emoji","Apple Color Emoji"';
    const serif = `"Noto Serif Devanagari","Noto Serif Gujarati",${EMOJI}`, sans = `"Noto Sans Devanagari","Noto Sans Gujarati",${EMOJI}`;
    const fam = t.serif ? serif : sans;

    // Background — Pixabay photo (agar chuni ho) warna theme ka gradient
    const hasPhoto = drawBg(ctx, t);
    if (!hasPhoto) {
        const g = ctx.createLinearGradient(0, 0, W, H);
        g.addColorStop(0, t.bg[0]); g.addColorStop(1, t.bg[1]);
        ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
        drawDeco(ctx, t);
    }

    // Photo ke upar theme ka rang chal nahi sakta — safed text hi padha jaata hai
    const inkText = hasPhoto ? '#ffffff' : t.text;
    const inkAcc  = hasPhoto ? '#ffd97d' : t.accent;

    const pad = 130;
    const maxW = W - pad * 2;
    const isJoke = !!item.punchline;

    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';

    // ----- Text block(s) -----
    const mainMaxH = isJoke ? H * 0.42 : H * 0.62;
    const main = fitLines(ctx, item.text, maxW, mainMaxH, fam, t.serif ? '600' : '700', 78);

    let punch = null;
    if (isJoke) {
        punch = fitLines(ctx, item.punchline, maxW, H * 0.24, fam, '700', 86);
    }

    // Vertical centering
    const mainH  = main.lines.length * main.lh;
    const punchH = punch ? (punch.lines.length * punch.lh + 70) : 0;
    let y = (H - (mainH + punchH)) / 2;

    // Main text — photo ke upar shadow gehri, taaki har photo par padha jaaye
    ctx.shadowColor = hasPhoto ? 'rgba(0,0,0,0.75)' : 'rgba(0,0,0,0.25)';
    ctx.shadowBlur = hasPhoto ? 22 : 12;
    ctx.shadowOffsetY = 3;
    ctx.fillStyle = inkText;
    ctx.font = `${t.serif ? '600' : '700'} ${main.size}px ${fam}`;
    main.lines.forEach(line => { ctx.fillText(line, W/2, y); y += main.lh; });

    // Punchline (accent, bigger)
    if (punch) {
        y += 70;
        ctx.fillStyle = inkAcc;
        ctx.font = `700 ${punch.size}px ${fam}`;
        punch.lines.forEach(line => { ctx.fillText(line, W/2, y); y += punch.lh; });
    }
    ctx.shadowColor = 'transparent'; ctx.shadowBlur = 0; ctx.shadowOffsetY = 0;

    // ----- Handle / watermark -----
    const hh = (handle || '').trim();
    if (hh) {
        ctx.fillStyle = inkAcc;
        ctx.globalAlpha = 0.9;
        ctx.font = `600 34px ${sans}`;
        ctx.fillText(hh, W/2, H - 120);
        ctx.globalAlpha = 1;
    }
}

// ---------- State ----------
let items = [];

async function ensureFonts() {
    for (const f of [
        '700 78px "Noto Serif Devanagari"', '600 78px "Noto Serif Devanagari"', '700 78px "Noto Sans Devanagari"',
        '700 78px "Noto Serif Gujarati"', '600 78px "Noto Serif Gujarati"', '700 78px "Noto Sans Gujarati"',
    ]) { try { await document.fonts.load(f); } catch (e) {} }
    await document.fonts.ready;
}

/** Type ke hisaab se sahi renderer chuno. */
function drawCard(off, item, i) {
    const theme = el('theme').value, handle = el('handle').value;
    if (el('type').value === 'ukhana') {
        renderUkhana(off, item, theme, handle, el('language').value);
    } else {
        renderCard(off, item, theme, handle);
    }
}

function renderPreviews() {
    const grid = el('grid');
    grid.innerHTML = '';
    const off = document.createElement('canvas');

    items.forEach((item, i) => {
        drawCard(off, item, i);
        const small = document.createElement('canvas');
        small.width = 270; small.height = 480;
        small.className = 'w-full rounded-lg border border-slate-200 shadow-sm';
        small.getContext('2d').drawImage(off, 0, 0, 270, 480);
        grid.appendChild(small);
    });
    el('itemCount').textContent = items.length;
    el('previewWrap').classList.remove('hidden');
}

// ---------- Generate ----------
el('genBtn').addEventListener('click', async () => {
    const btn = el('genBtn'), msg = el('msg');
    const payload = {
        type: el('type').value,
        category: el('category').value.trim(),
        count: parseInt(el('count').value, 10) || 10,
        language: el('language').value,
    };
    btn.disabled = true; const lbl = btn.textContent; btn.textContent = '⏳ Ban raha hai…';
    msg.textContent = 'AI likh raha hai, thoda ruko…';
    el('previewWrap').classList.add('hidden');

    try {
        const r = await fetch(GEN_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
        const d = await r.json();
        if (d.ok && d.items && d.items.length) {
            items = d.items;
            await ensureFonts();
            renderPreviews();
            msg.textContent = `✓ ${items.length} ${payload.type} ready — theme/handle badal ke live preview dekho, phir Save karo.`;
        } else {
            msg.textContent = '⚠ ' + (d.error || 'Kuch nahi bana. Dobara try karo.');
        }
    } catch (e) {
        msg.textContent = '⚠ Error aaya, dobara try karo.';
    }
    btn.disabled = false; btn.textContent = lbl;
});

// Theme / handle badle → live preview update
['theme', 'handle', 'language'].forEach(id => el(id).addEventListener('change', () => { if (items.length) renderPreviews(); }));

// ---------- Pixabay background picker ----------
// Type ke hisaab se ready-made ANGREZI search words. Pixabay Hindi/Gujarati
// samajhta nahi, isliye seedhe English suggestions dena hi sabse kaam ka hai.
const BG_SUGGEST = {
    shayari: ['rain window', 'night sky moon', 'lonely road', 'sunset silhouette', 'candle dark',
              'rose petals', 'foggy morning', 'rainy street', 'starry night', 'empty bench',
              'couple silhouette', 'tea rain', 'old letter', 'train window'],
    joke:    ['colorful abstract', 'confetti', 'yellow background', 'balloons', 'comic pattern',
              'funny cartoon', 'bright gradient'],
    quote:   ['mountain sunrise', 'runner sunrise', 'summit climb', 'open road', 'lighthouse',
              'eagle sky', 'meditation sunrise', 'books study', 'calm lake', 'forest path'],
    status:  ['city night lights', 'motorcycle road', 'neon lights', 'smoke abstract',
              'silhouette sunset', 'dark texture', 'car headlights'],
    fact:    ['galaxy space', 'earth from space', 'laboratory', 'deep ocean', 'ancient ruins',
              'brain concept', 'microscope', 'desert dunes'],
    ukhana:  ['colorful paper', 'crayons kids', 'bright pattern', 'chalkboard', 'paper texture',
              'rainbow background', 'notebook doodle'],
};

function renderBgChips() {
    const list = BG_SUGGEST[el('type').value] || BG_SUGGEST.shayari;
    el('bgChips').innerHTML = list.map(s =>
        `<button type="button" class="bgSug px-2.5 py-1 rounded-full border border-slate-300 text-xs hover:border-violet-500 hover:bg-violet-50">${s}</button>`
    ).join('');
    el('bgList').innerHTML = list.map(s => `<option value="${s}">`).join('');
}

el('bgChips').addEventListener('click', (e) => {
    const b = e.target.closest('.bgSug');
    if (!b) return;
    el('bgQuery').value = b.textContent;
    bgSearch();
});

el('bgBtn').addEventListener('click', () => {
    renderBgChips();
    el('bgModal').classList.remove('hidden');
    if (el('bgQuery').value.trim()) bgSearch();
});
el('bgClose').addEventListener('click', () => el('bgModal').classList.add('hidden'));
el('bgModal').addEventListener('click', e => { if (e.target === el('bgModal')) el('bgModal').classList.add('hidden'); });

el('bgClear').addEventListener('click', () => {
    bgImage = null;
    el('bgThumb').classList.add('hidden');
    el('bgClear').classList.add('hidden');
    if (items.length) renderPreviews();
});

async function bgSearch() {
    const q = el('bgQuery').value.trim();
    if (!q) { el('bgMsg').textContent = '⚠ Kuch likho — jaise "rain night" ya "sunset".'; return; }

    el('bgMsg').textContent = '🔍 Dhoondh rahe hain…';
    el('bgGrid').innerHTML = '';

    const d = await postJson(BG_SEARCH_URL, { query: q });
    if (!d.ok) { el('bgMsg').textContent = '⚠ ' + (d.error || 'Search fail'); return; }
    if (!d.results.length) { el('bgMsg').textContent = 'Kuch nahi mila — dusre shabd try karo.'; return; }

    el('bgMsg').textContent = `${d.results.length} photos — jo pasand ho us par click karo.`;
    el('bgGrid').innerHTML = d.results.map(r =>
        `<button class="bgPick border border-slate-200 rounded-lg overflow-hidden hover:border-violet-500"
                 data-url="${r.full}" title="${r.tags}">
            <img src="${r.preview}" class="w-full h-24 object-cover" alt="">
         </button>`
    ).join('');
}

el('bgGo').addEventListener('click', bgSearch);
el('bgQuery').addEventListener('keydown', e => { if (e.key === 'Enter') bgSearch(); });

el('bgGrid').addEventListener('click', async (e) => {
    const btn = e.target.closest('.bgPick');
    if (!btn) return;

    el('bgMsg').textContent = '⏳ Image laa rahe hain…';
    // Server par download — cross-origin image canvas ko taint kar deti hai
    const d = await postJson(BG_PICK_URL, { url: btn.dataset.url });
    if (!d.ok) { el('bgMsg').textContent = '⚠ ' + (d.error || 'Fail'); return; }

    try {
        bgImage = await loadImage(d.url);
    } catch (err) {
        el('bgMsg').textContent = '⚠ Image load nahi hui.'; return;
    }

    el('bgThumb').src = d.url;
    el('bgThumb').classList.remove('hidden');
    el('bgClear').classList.remove('hidden');
    el('bgModal').classList.add('hidden');
    if (items.length) renderPreviews();
});
el('handle').addEventListener('input', () => { /* debounce-lite: change par hi re-render */ });

// ---------- Save ----------
el('saveBtn').addEventListener('click', async () => {
    if (!items.length) return;
    const btn = el('saveBtn'); btn.disabled = true; btn.classList.add('opacity-60');
    el('progress').classList.remove('hidden');

    await ensureFonts();
    const type = el('type').value, category = el('category').value.trim();
    const language = el('language').value;
    const off = document.createElement('canvas');

    // Per-card save — pehli card nayi collection banati hai, baaki usi me add
    let collection = null, redirect = null;
    try {
        for (let i = 0; i < items.length; i++) {
            drawCard(off, items[i], i);
            // Joke ka text = setup + punchline (caption/voice ke liye)
            const text = items[i].punchline ? (items[i].text + '\n\n' + items[i].punchline) : items[i].text;

            const r = await fetch(SAVE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ type, category, language, collection, order: i + 1, text, answer: items[i].answer || '', hashtags: items[i].hashtags || '', image: off.toDataURL('image/png') }),
            });
            const d = await r.json();
            if (!d.ok) throw new Error(d.error || ('card ' + (i + 1) + ' fail'));
            collection = d.collection;
            redirect = d.redirect;

            const pct = Math.round(((i + 1) / items.length) * 100);
            el('bar').style.width = pct + '%';
            el('progressText').textContent = `${i + 1} / ${items.length} cards saved…`;
        }
        el('progressText').textContent = '✅ Saved! Redirecting…';
        setTimeout(() => { window.location = redirect; }, 700);
    } catch (e) {
        el('progressText').textContent = '❌ Save fail: ' + e.message;
        btn.disabled = false; btn.classList.remove('opacity-60');
    }
});
</script>
@endpush
@endsection
