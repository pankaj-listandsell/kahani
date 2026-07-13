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

    {{-- ============ EXISTING COLLECTIONS ============ --}}
    <div class="mt-8">
        <h3 class="font-semibold mb-3">Saved collections</h3>
        @forelse ($collections as $c)
            <a href="{{ route('admin.studio.show', $c) }}"
               class="flex items-center justify-between bg-white border border-slate-200 rounded-lg px-4 py-3 mb-2 hover:border-violet-300">
                <span class="font-medium">
                    @php($icon = ['shayari'=>'🖊️','joke'=>'😂','quote'=>'🌟','status'=>'🔥','fact'=>'🤯'][$c->type] ?? '✨')
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
const el = id => document.getElementById(id);

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
};

// Theme dropdown fill
Object.entries(THEMES).forEach(([k, t]) => {
    const o = document.createElement('option');
    o.value = k; o.textContent = t.name;
    el('theme').appendChild(o);
});

// Category datalist sync
function syncCats() {
    const list = CATS[el('type').value] || [];
    el('catList').innerHTML = list.map(c => `<option value="${c}">`).join('');
    // Type-appropriate default theme
    const defTheme = { joke: 'pop', status: 'neon', fact: 'midnight' };
    if (defTheme[el('type').value]) el('theme').value = defTheme[el('type').value];
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

// Ek card render karo (full 1080x1920)
function renderCard(canvas, item, themeKey, handle) {
    const t = THEMES[themeKey] || THEMES.night;
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');
    // Emoji + Gujarati font fallback (Devanagari/Gujarati/Roman + color emoji)
    const EMOJI = '"Segoe UI Emoji","Noto Color Emoji","Apple Color Emoji"';
    const serif = `"Noto Serif Devanagari","Noto Serif Gujarati",${EMOJI}`, sans = `"Noto Sans Devanagari","Noto Sans Gujarati",${EMOJI}`;
    const fam = t.serif ? serif : sans;

    // Background gradient
    const g = ctx.createLinearGradient(0, 0, W, H);
    g.addColorStop(0, t.bg[0]); g.addColorStop(1, t.bg[1]);
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);

    drawDeco(ctx, t);

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

    // Main text (subtle shadow for readability)
    ctx.shadowColor = 'rgba(0,0,0,0.25)'; ctx.shadowBlur = 12; ctx.shadowOffsetY = 3;
    ctx.fillStyle = t.text;
    ctx.font = `${t.serif ? '600' : '700'} ${main.size}px ${fam}`;
    main.lines.forEach(line => { ctx.fillText(line, W/2, y); y += main.lh; });

    // Punchline (accent, bigger)
    if (punch) {
        y += 70;
        ctx.fillStyle = t.accent;
        ctx.font = `700 ${punch.size}px ${fam}`;
        punch.lines.forEach(line => { ctx.fillText(line, W/2, y); y += punch.lh; });
    }
    ctx.shadowColor = 'transparent'; ctx.shadowBlur = 0; ctx.shadowOffsetY = 0;

    // ----- Handle / watermark -----
    const hh = (handle || '').trim();
    if (hh) {
        ctx.fillStyle = t.accent;
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

function renderPreviews() {
    const grid = el('grid');
    grid.innerHTML = '';
    const theme = el('theme').value;
    const handle = el('handle').value;
    const off = document.createElement('canvas');

    items.forEach((item, i) => {
        renderCard(off, item, theme, handle);
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
['theme', 'handle'].forEach(id => el(id).addEventListener('change', () => { if (items.length) renderPreviews(); }));
el('handle').addEventListener('input', () => { /* debounce-lite: change par hi re-render */ });

// ---------- Save ----------
el('saveBtn').addEventListener('click', async () => {
    if (!items.length) return;
    const btn = el('saveBtn'); btn.disabled = true; btn.classList.add('opacity-60');
    el('progress').classList.remove('hidden');

    await ensureFonts();
    const theme = el('theme').value, handle = el('handle').value;
    const type = el('type').value, category = el('category').value.trim();
    const language = el('language').value;
    const off = document.createElement('canvas');

    // Per-card save — pehli card nayi collection banati hai, baaki usi me add
    let collection = null, redirect = null;
    try {
        for (let i = 0; i < items.length; i++) {
            renderCard(off, items[i], theme, handle);
            // Joke ka text = setup + punchline (caption/voice ke liye)
            const text = items[i].punchline ? (items[i].text + '\n\n' + items[i].punchline) : items[i].text;

            const r = await fetch(SAVE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ type, category, language, collection, order: i + 1, text, hashtags: items[i].hashtags || '', image: off.toDataURL('image/png') }),
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
