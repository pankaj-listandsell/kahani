@extends('layouts.admin')
@section('title', 'Kids Yoga Studio')

@section('content')
<div class="max-w-5xl">
    <h2 class="text-xl font-bold flex items-center gap-2">🧘 Kids Yoga Studio</h2>
    <p class="text-slate-500 mb-6">Aasan chuno → AI bachchon ki bhasha me steps + fayde likhta hai → har aasan ki vector illustration ban ke sundar card ready. Fir auto-post inhe IG/YouTube/Facebook par daalta rehta hai.</p>

    {{-- ============ POSE PICKER ============ --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <label class="block text-sm font-medium">1️⃣ Aasan chuno <span class="text-slate-400">(safe list — bachchon ke liye)</span></label>
            <div class="flex gap-2 text-xs">
                <button type="button" id="pickRandom" class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-50">🎲 Random 6</button>
                <button type="button" id="pickAll" class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-50">Sab</button>
                <button type="button" id="pickNone" class="px-3 py-1.5 rounded-lg border border-slate-300 hover:bg-slate-50">Clear</button>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
            @foreach ($poses as $key => $p)
                <label class="pose-chip flex items-center gap-2 border border-slate-200 rounded-lg px-3 py-2 cursor-pointer hover:border-emerald-300 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                    <input type="checkbox" class="poseBox accent-emerald-600" value="{{ $key }}">
                    <span class="text-lg">{{ $p['emoji'] }}</span>
                    <span class="text-sm leading-tight">
                        {{ $p['hi'] }}
                        <span class="block text-[11px] text-slate-400">{{ $p['en'] }}</span>
                    </span>
                </label>
            @endforeach
        </div>

        <hr class="border-slate-100">

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">🌐 Language</label>
                <select id="language" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="hindi">हिंदी Hindi</option>
                    <option value="gujarati">ગુજરાતી Gujarati</option>
                    <option value="hinglish">Hindi-English (Roman)</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Collection ka naam <span class="text-slate-400">(optional)</span></label>
                <input type="text" id="category" placeholder="e.g. Morning Yoga"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Theme 🎨</label>
                <select id="theme" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Handle <span class="text-slate-400">(optional)</span></label>
                <input type="text" id="handle" placeholder="@yourpage"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <button id="genBtn" class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg px-6 py-2.5 text-sm">
            🧘 Generate Cards
        </button>
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
        <p class="text-xs text-slate-500 mb-3">
            Har card par: <b>🔄</b> nayi AI image · <b>🔍</b> Pixabay se free vector · <b>📤</b> apni image ·
            <b>✅</b> is aasan ke liye pakki karo (aage har baar yahi image lagegi, turant).
        </p>
        <div id="grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3"></div>
        <div id="progress" class="hidden mt-4">
            <div class="w-full bg-slate-200 rounded-full h-3"><div id="bar" class="bg-emerald-600 h-3 rounded-full transition-all" style="width:0%"></div></div>
            <p id="progressText" class="text-sm text-slate-600 mt-2"></p>
        </div>
    </div>

    {{-- ============ EXISTING COLLECTIONS ============ --}}
    <div class="mt-8 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
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
                <div class="flex items-center justify-between bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 mb-2.5 hover:border-emerald-300 hover:bg-emerald-50/20 transition gap-3">
                    <input type="checkbox" name="ids[]" value="{{ $c->id }}" class="bulk-item rounded border-slate-300 accent-emerald-600 w-4 h-4 cursor-pointer">
                    <a href="{{ route('admin.yoga.show', $c) }}" class="flex-1 flex items-center justify-between gap-3 group">
                        <span class="font-bold text-slate-800 group-hover:text-emerald-700 transition">🧘 {{ $c->title }}</span>
                        <span class="text-xs text-slate-500 font-medium">{{ $c->parts_count }} cards · {{ ucfirst($c->status) }}</span>
                    </a>
                    <button type="button" onclick="if(confirm('Kya aap sach me ye yoga collection delete karna chahte hain?')) document.getElementById('single-del-{{ $c->id }}').submit();" class="text-xs font-semibold text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                        <span>🗑</span> Delete
                    </button>
                </div>
            @empty
                <p class="text-sm text-slate-500 text-center py-4">Abhi koi collection nahi — upar se pehli banao. 🧘</p>
            @endforelse
        </form>

        {{-- Hidden Single Delete Forms --}}
        @foreach ($collections as $c)
            <form id="single-del-{{ $c->id }}" method="POST" action="{{ route('admin.yoga.destroy', $c) }}" class="hidden">
                @csrf @method('DELETE')
            </form>
        @endforeach
    </div>
</div>

{{-- ============ PIXABAY PICKER (modal) ============ --}}
<div id="pixModal" class="hidden fixed inset-0 z-40 bg-black/50 p-4 overflow-y-auto">
    <div class="bg-white rounded-xl max-w-3xl mx-auto mt-10 p-5">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h3 class="font-semibold">🔍 Pixabay se free illustration</h3>
            <button id="pixClose" class="text-slate-400 hover:text-slate-700 text-xl leading-none">&times;</button>
        </div>
        <div class="flex gap-2 mb-3">
            <input type="text" id="pixQuery" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                   placeholder="e.g. tree pose yoga kids cartoon">
            <button id="pixGo" class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg px-4 py-2 text-sm">Search</button>
        </div>
        <p id="pixMsg" class="text-sm text-slate-500 mb-3"></p>
        <div id="pixGrid" class="grid grid-cols-3 sm:grid-cols-4 gap-2"></div>
        <p class="text-[11px] text-slate-400 mt-4">
            Pixabay License — commercial use allowed, attribution zaroori nahi. Phir bhi image choose karte waqt
            dekh lein ki pose sahi hai aur usme koi text/logo na ho.
        </p>
    </div>
</div>

{{-- Upload ke liye chhupa hua file input --}}
<input type="file" id="uploadInput" accept="image/png,image/jpeg,image/webp" class="hidden">

@push('scripts')
<script>
const CSRF     = document.querySelector('meta[name=csrf-token]').content;
const GEN_URL  = @json(route('admin.yoga.generate'));
const IMG_URL  = @json(route('admin.yoga.image'));
const SEARCH_URL  = @json(route('admin.yoga.search'));
const PICK_URL    = @json(route('admin.yoga.pick'));
const UPLOAD_URL  = @json(route('admin.yoga.upload'));
const APPROVE_URL = @json(route('admin.yoga.approve'));
const SAVE_URL = @json(route('admin.yoga.save'));
const el = id => document.getElementById(id);

// Pixabay key set hai ya nahi — generate ke jawab me pata chalta hai
let pixabayOn = false;

const postJson = (url, body) => fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    body: JSON.stringify(body),
}).then(r => r.json());

// Card size (9:16 — reel / short / IG)
const W = 1080, H = 1920;

// Har card par jaane wali safety line (caption me server bhi jodta hai)
const DISCLAIMER = '⚠️ बड़ों की देखरेख में ही करें';

// ---------- Themes (bachchon ke liye bright + soft) ----------
const THEMES = {
    mint:     { name: '🌿 Mint',      bg: ['#d4fc79', '#96e6a1'], text: '#17402c', accent: '#0f7a4a' },
    sky:      { name: '☁️ Sky',       bg: ['#a1c4fd', '#c2e9fb'], text: '#14345a', accent: '#0b6fb8' },
    sunny:    { name: '☀️ Sunny',     bg: ['#fceabb', '#f8b500'], text: '#4a3200', accent: '#c9550c' },
    bubble:   { name: '🍬 Bubblegum', bg: ['#ffd3e0', '#ffe9c9'], text: '#6f2340', accent: '#d63075' },
    lavender: { name: '💜 Lavender',  bg: ['#e0c3fc', '#8ec5fc'], text: '#2e1f57', accent: '#6a3fc0' },
    peach:    { name: '🍑 Peach',     bg: ['#ffecd2', '#fcb69f'], text: '#6d3226', accent: '#d9603f' },
};

Object.entries(THEMES).forEach(([k, t]) => {
    const o = document.createElement('option');
    o.value = k; o.textContent = t.name;
    el('theme').appendChild(o);
});

// ---------- Pose picker ----------
const boxes = () => Array.from(document.querySelectorAll('.poseBox'));
const selectedPoses = () => boxes().filter(b => b.checked).map(b => b.value);

el('pickAll').addEventListener('click',  () => boxes().forEach(b => b.checked = true));
el('pickNone').addEventListener('click', () => boxes().forEach(b => b.checked = false));
el('pickRandom').addEventListener('click', () => {
    const all = boxes();
    all.forEach(b => b.checked = false);
    // Fisher-Yates se 6 random
    const idx = all.map((_, i) => i);
    for (let i = idx.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [idx[i], idx[j]] = [idx[j], idx[i]];
    }
    idx.slice(0, 6).forEach(i => all[i].checked = true);
});

// ---------- Canvas helpers ----------
function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    if (ctx.roundRect) { ctx.roundRect(x, y, w, h, r); return; }
    ctx.moveTo(x+r,y); ctx.arcTo(x+w,y,x+w,y+h,r); ctx.arcTo(x+w,y+h,x,y+h,r);
    ctx.arcTo(x,y+h,x,y,r); ctx.arcTo(x,y,x+w,y,r); ctx.closePath();
}

function wrap(ctx, text, maxW) {
    const out = [];
    String(text).split(/\n/).forEach(para => {
        if (para.trim() === '') return;
        let line = '';
        para.split(/\s+/).forEach(w => {
            const test = line ? line + ' ' + w : w;
            if (ctx.measureText(test).width > maxW && line) { out.push(line); line = w; }
            else line = test;
        });
        if (line) out.push(line);
    });
    return out.length ? out : [''];
}

// Font size ghatate jao jab tak text ek hi line me fit na ho jaaye
function fitOneLine(ctx, text, maxW, fontFam, weight, maxSize, minSize) {
    let size = maxSize;
    while (size > minSize) {
        ctx.font = `${weight} ${size}px ${fontFam}`;
        if (ctx.measureText(text).width <= maxW) break;
        size -= 2;
    }
    ctx.font = `${weight} ${size}px ${fontFam}`;
    return size;
}

// Image ko box ke andar poora dikhao (aspect ratio bina bigade)
function drawContain(ctx, img, x, y, w, h) {
    const iw = img.naturalWidth || img.width, ih = img.naturalHeight || img.height;
    if (!iw || !ih) return;
    const r = Math.min(w / iw, h / ih);
    ctx.drawImage(img, x + (w - iw * r) / 2, y + (h - ih * r) / 2, iw * r, ih * r);
}

function loadImage(url) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload  = () => resolve(img);
        img.onerror = () => reject(new Error('image load fail'));
        img.src = url; // hamesha apni site se — warna canvas taint ho jaata hai
    });
}

const EMOJI = '"Segoe UI Emoji","Noto Color Emoji","Apple Color Emoji"';
const SANS  = `"Noto Sans Devanagari","Noto Sans Gujarati",${EMOJI}`;
const SERIF = `"Noto Serif Devanagari","Noto Serif Gujarati",${EMOJI}`;

/**
 * Ek yoga card render karo (1080x1920).
 * Layout: header → illustration panel → naam → 3 steps → fayda → disclaimer → handle
 */
function renderCard(canvas, item, themeKey, handle) {
    const t = THEMES[themeKey] || THEMES.mint;
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');

    // Background
    const g = ctx.createLinearGradient(0, 0, W, H);
    g.addColorStop(0, t.bg[0]); g.addColorStop(1, t.bg[1]);
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';

    // ----- Header chip -----
    ctx.fillStyle = 'rgba(255,255,255,0.55)';
    roundRect(ctx, W/2 - 210, 68, 420, 82, 41); ctx.fill();
    ctx.fillStyle = t.accent;
    ctx.font = `700 46px ${SANS}`;
    ctx.fillText('🧘 Kids Yoga', W/2, 88);

    // ----- Illustration panel -----
    const px = 95, py = 190, pw = W - px * 2, ph = 830;
    ctx.fillStyle = 'rgba(255,255,255,0.82)';
    roundRect(ctx, px, py, pw, ph, 56); ctx.fill();

    if (item.img) {
        drawContain(ctx, item.img, px + 34, py + 34, pw - 68, ph - 68);
    } else {
        // Image abhi aayi nahi — placeholder emoji
        ctx.fillStyle = t.accent;
        ctx.font = `400 260px ${EMOJI}`;
        ctx.fillText(item.emoji || '🧘', W/2, py + ph/2 - 150);
    }

    // ----- Aasan ka naam -----
    let y = py + ph + 46;
    ctx.fillStyle = t.text;
    const nameSize = fitOneLine(ctx, item.name, W - 160, SERIF, '800', 96, 52);
    ctx.fillText(item.name, W/2, y);
    y += nameSize + 14;

    ctx.fillStyle = t.accent;
    ctx.font = `600 42px ${SANS}`;
    ctx.fillText(item.name_en, W/2, y);
    y += 78;

    // ----- 3 steps (numbered, left aligned) -----
    const sx = 130, sw = W - sx * 2;
    (item.steps || []).slice(0, 3).forEach((step, i) => {
        const size = fitOneLine(ctx, step, sw - 96, SANS, '600', 52, 32);

        // Number badge
        ctx.fillStyle = t.accent;
        ctx.beginPath(); ctx.arc(sx + 30, y + size/2 + 4, 32, 0, 7); ctx.fill();
        ctx.fillStyle = '#ffffff';
        ctx.font = `700 36px ${SANS}`;
        ctx.textAlign = 'center';
        ctx.fillText(String(i + 1), sx + 30, y + 4);

        // Step text
        ctx.fillStyle = t.text;
        ctx.font = `600 ${size}px ${SANS}`;
        ctx.textAlign = 'left';
        ctx.fillText(step, sx + 82, y);

        y += Math.max(size, 64) + 30;
        ctx.textAlign = 'center';
    });

    // ----- Fayda -----
    if (item.benefit) {
        y += 16;
        ctx.font = `700 50px ${SANS}`;
        const lines = wrap(ctx, item.benefit, W - 200);
        ctx.fillStyle = t.accent;
        lines.slice(0, 2).forEach(line => { ctx.fillText(line, W/2, y); y += 66; });
    }

    // ----- Disclaimer + handle (hamesha neeche fix) -----
    ctx.fillStyle = t.text;
    ctx.globalAlpha = 0.75;
    ctx.font = `600 34px ${SANS}`;
    ctx.fillText(DISCLAIMER, W/2, H - 150);
    ctx.globalAlpha = 1;

    const hh = (handle || '').trim();
    if (hh) {
        ctx.fillStyle = t.accent;
        ctx.font = `700 36px ${SANS}`;
        ctx.fillText(hh, W/2, H - 92);
    }
}

// ---------- State ----------
let items = [];

async function ensureFonts() {
    for (const f of [
        '800 96px "Noto Serif Devanagari"', '600 52px "Noto Sans Devanagari"', '700 46px "Noto Sans Devanagari"',
        '800 96px "Noto Serif Gujarati"',   '600 52px "Noto Sans Gujarati"',   '700 46px "Noto Sans Gujarati"',
    ]) { try { await document.fonts.load(f); } catch (e) {} }
    await document.fonts.ready;
}

/** Ek card ka chhota preview (+ re-generate button) grid me lagao. */
function renderPreview(i) {
    const cell = document.getElementById('cell-' + i);
    if (!cell) return;
    const off = document.createElement('canvas');
    renderCard(off, items[i], el('theme').value, el('handle').value);
    const small = cell.querySelector('canvas');
    small.getContext('2d').clearRect(0, 0, small.width, small.height);
    small.getContext('2d').drawImage(off, 0, 0, small.width, small.height);
}

/** Ek cell ke buttons ka state (approved highlight) refresh karo. */
function syncCellButtons(i) {
    const cell = document.getElementById('cell-' + i);
    if (!cell) return;
    const btn = cell.querySelector('.approve');
    const ok  = !!items[i].isApproved;
    btn.classList.toggle('bg-emerald-600', ok);
    btn.classList.toggle('text-white', ok);
    btn.classList.toggle('bg-white/90', !ok);
    btn.title = ok ? 'Approved — dobara dabao to hata do' : 'Is aasan ke liye pakka karo';
    cell.querySelector('.badge').classList.toggle('hidden', !ok);
}

function renderPreviews() {
    const grid = el('grid');
    grid.innerHTML = '';
    items.forEach((item, i) => {
        const cell = document.createElement('div');
        cell.id = 'cell-' + i;
        cell.className = 'relative';
        cell.innerHTML = `
            <canvas width="270" height="480" class="w-full rounded-lg border border-slate-200 shadow-sm bg-white"></canvas>
            <span class="badge hidden absolute top-2 left-2 bg-emerald-600 text-white text-[10px] rounded px-1.5 py-0.5">✅ pakka</span>
            <div class="absolute top-2 right-2 flex flex-col gap-1">
                <button data-i="${i}" class="regen   bg-white/90 hover:bg-white border border-slate-300 rounded-lg w-9 h-9 text-sm shadow" title="Nayi AI image">🔄</button>
                <button data-i="${i}" class="pix     bg-white/90 hover:bg-white border border-slate-300 rounded-lg w-9 h-9 text-sm shadow ${pixabayOn ? '' : 'hidden'}" title="Pixabay se dhundo">🔍</button>
                <button data-i="${i}" class="upload  bg-white/90 hover:bg-white border border-slate-300 rounded-lg w-9 h-9 text-sm shadow" title="Apni image lagao">📤</button>
                <button data-i="${i}" class="approve bg-white/90 hover:bg-white border border-slate-300 rounded-lg w-9 h-9 text-sm shadow">✅</button>
            </div>`;
        grid.appendChild(cell);
        renderPreview(i);
        syncCellButtons(i);
    });
    el('itemCount').textContent = items.length;
    el('previewWrap').classList.remove('hidden');
}

/** Card ki image badlo (url + storage path) aur preview refresh karo. */
async function setImage(i, url, path, approved) {
    items[i].img        = await loadImage(url);
    items[i].path       = path || null;
    items[i].isApproved = !!approved;
    renderPreview(i);
    syncCellButtons(i);
}

/** AI se nayi illustration. */
async function fetchImage(i) {
    const d = await postJson(IMG_URL, { pose: items[i].key });
    if (!d.ok || !d.url) throw new Error(d.error || 'image fail');

    await setImage(i, d.url, d.path, false);
    items[i].usedFallback = !!d.fallback;
    return d;
}

/**
 * Har card ki image lao. Jo aasan pehle se approve hai uski image seedhi lag
 * jaati hai — AI call hi nahi hoti (isliye 2nd baar se sab turant banta hai).
 */
async function fetchAllImages() {
    let fallbacks = 0, approved = 0;
    el('progress').classList.remove('hidden');

    for (let i = 0; i < items.length; i++) {
        if (items[i].approved) {
            el('progressText').textContent = `✅ ${items[i].name} — pakki image lag gayi (${i + 1}/${items.length})`;
            try { await setImage(i, items[i].approved, null, true); approved++; } catch (e) { /* neeche AI try hoga */ }
        }

        if (!items[i].img) {
            el('progressText').textContent = `🎨 Illustration ${i + 1} / ${items.length} ban rahi hai… (thoda time lagta hai)`;
            try {
                const d = await fetchImage(i);
                if (d.fallback) fallbacks++;
            } catch (e) {
                fallbacks++;
            }
        }
        el('bar').style.width = Math.round(((i + 1) / items.length) * 100) + '%';
    }

    const bits = [];
    if (approved)  bits.push(`${approved} pakki image lagi`);
    if (fallbacks) bits.push(`${fallbacks} par AI image nahi bani (🔄 / 🔍 se badlo)`);
    el('progressText').textContent = bits.length
        ? '✓ ' + bits.join(' · ')
        : '✓ Saari illustrations ready — pasand aayein to ✅ dabao, phir Save karo.';
}

// ---------- Generate ----------
el('genBtn').addEventListener('click', async () => {
    const poses = selectedPoses();
    const btn = el('genBtn'), msg = el('msg');

    if (!poses.length) { msg.textContent = '⚠ Pehle kam se kam ek aasan chuno.'; return; }

    btn.disabled = true; const lbl = btn.textContent; btn.textContent = '⏳ Ban raha hai…';
    msg.textContent = 'AI steps aur fayde likh raha hai…';
    el('previewWrap').classList.add('hidden');

    try {
        const r = await fetch(GEN_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ poses, language: el('language').value }),
        });
        const d = await r.json();

        if (d.ok && d.items && d.items.length) {
            items = d.items;
            pixabayOn = !!d.pixabay;
            await ensureFonts();
            renderPreviews();
            msg.textContent = `✓ ${items.length} aasan ready — ab illustrations lag rahi hain…`;
            await fetchAllImages();
        } else {
            msg.textContent = '⚠ ' + (d.error || 'Kuch nahi bana. Dobara try karo.');
        }
    } catch (e) {
        msg.textContent = '⚠ Error aaya, dobara try karo.';
    }
    btn.disabled = false; btn.textContent = lbl;
});

// ---------- Per-card buttons ----------
el('grid').addEventListener('click', async (e) => {
    const btn = e.target.closest('.regen, .approve, .upload, .pix');
    if (!btn) return;
    const i = parseInt(btn.dataset.i, 10);

    // 🔄 nayi AI image
    if (btn.classList.contains('regen')) {
        btn.disabled = true; btn.textContent = '⏳';
        try { await fetchImage(i); } catch (err) { /* purani image rehne do */ }
        btn.disabled = false; btn.textContent = '🔄';
        return;
    }

    // ✅ is aasan ke liye pakka karo / hata do
    if (btn.classList.contains('approve')) {
        const on = !items[i].isApproved;
        if (on && !items[i].path) {
            el('progressText').textContent = '⚠ Ye simple fallback image hai — pehle 🔄 ya 🔍 se asli image lagao.';
            return;
        }
        btn.disabled = true;
        const d = await postJson(APPROVE_URL, { pose: items[i].key, path: on ? items[i].path : null });
        if (d.ok) { items[i].isApproved = on; syncCellButtons(i); }
        btn.disabled = false;
        return;
    }

    // 📤 apni image
    if (btn.classList.contains('upload')) {
        uploadFor = i;
        el('uploadInput').value = '';
        el('uploadInput').click();
        return;
    }

    // 🔍 Pixabay
    if (btn.classList.contains('pix')) {
        openPixabay(i);
    }
});

// ---------- Upload ----------
let uploadFor = null;

el('uploadInput').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file || uploadFor === null) return;
    const i = uploadFor;

    el('progressText').textContent = '📤 Image upload ho rahi hai…';
    el('progress').classList.remove('hidden');

    const fd = new FormData();
    fd.append('pose', items[i].key);
    fd.append('image', file);

    try {
        const r = await fetch(UPLOAD_URL, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: fd,
        });
        const d = await r.json();
        if (!d.ok) throw new Error(d.error || 'upload fail');
        await setImage(i, d.url, d.path, false);
        el('progressText').textContent = '✓ Image lag gayi — pasand aaye to ✅ dabao.';
    } catch (err) {
        el('progressText').textContent = '⚠ Upload fail: ' + err.message;
    }
});

// ---------- Pixabay picker ----------
let pixFor = null;

function openPixabay(i) {
    pixFor = i;
    el('pixQuery').value = items[i].name_en + ' yoga kids cartoon';
    el('pixGrid').innerHTML = '';
    el('pixMsg').textContent = '';
    el('pixModal').classList.remove('hidden');
    runPixSearch();
}

async function runPixSearch() {
    if (pixFor === null) return;
    el('pixMsg').textContent = '🔍 Dhoondh rahe hain…';
    el('pixGrid').innerHTML = '';

    const d = await postJson(SEARCH_URL, { pose: items[pixFor].key, query: el('pixQuery').value.trim() });

    if (!d.ok) { el('pixMsg').textContent = '⚠ ' + (d.error || 'Search fail'); return; }
    if (!d.results.length) { el('pixMsg').textContent = 'Kuch nahi mila — dusre shabd try karo.'; return; }

    el('pixMsg').textContent = `${d.results.length} results — jo pasand ho us par click karo.`;
    el('pixGrid').innerHTML = d.results.map(r =>
        `<button class="pixPick border border-slate-200 rounded-lg overflow-hidden hover:border-emerald-500"
                 data-url="${r.full}" title="${r.tags}">
            <img src="${r.preview}" class="w-full h-24 object-contain bg-white" alt="">
         </button>`
    ).join('');
}

el('pixGo').addEventListener('click', runPixSearch);
el('pixQuery').addEventListener('keydown', e => { if (e.key === 'Enter') runPixSearch(); });
el('pixClose').addEventListener('click', () => el('pixModal').classList.add('hidden'));
el('pixModal').addEventListener('click', e => { if (e.target === el('pixModal')) el('pixModal').classList.add('hidden'); });

el('pixGrid').addEventListener('click', async (e) => {
    const btn = e.target.closest('.pixPick');
    if (!btn || pixFor === null) return;

    el('pixMsg').textContent = '⏳ Image laa rahe hain…';
    // Server par download hoti hai — cross-origin image canvas ko taint kar deti
    const d = await postJson(PICK_URL, { pose: items[pixFor].key, url: btn.dataset.url });

    if (!d.ok) { el('pixMsg').textContent = '⚠ ' + (d.error || 'Fail'); return; }

    await setImage(pixFor, d.url, d.path, false);
    el('pixModal').classList.add('hidden');
    el('progressText').textContent = '✓ Pixabay image lag gayi — pasand aaye to ✅ dabao.';
    el('progress').classList.remove('hidden');
});

// Theme / handle badle → preview refresh
['theme', 'handle'].forEach(id => el(id).addEventListener('change', () => {
    if (items.length) items.forEach((_, i) => renderPreview(i));
}));

// ---------- Save ----------
el('saveBtn').addEventListener('click', async () => {
    if (!items.length) return;
    const btn = el('saveBtn'); btn.disabled = true; btn.classList.add('opacity-60');
    el('progress').classList.remove('hidden');

    await ensureFonts();
    const theme = el('theme').value, handle = el('handle').value;
    const category = el('category').value.trim(), language = el('language').value;
    const off = document.createElement('canvas');

    let collection = null, redirect = null;
    try {
        for (let i = 0; i < items.length; i++) {
            renderCard(off, items[i], theme, handle);

            // Voice/caption ka text — naam, steps aur fayda (emoji TTS khud hata deta hai)
            const it = items[i];
            const text = [
                it.name + ' — ' + it.name_en,
                ...(it.steps || []).map((s, n) => (n + 1) + '. ' + s),
                it.benefit || '',
            ].filter(Boolean).join('\n');

            const r = await fetch(SAVE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({
                    category, language, collection, order: i + 1, text,
                    hashtags: it.hashtags || '', image: off.toDataURL('image/png'),
                }),
            });
            const d = await r.json();
            if (!d.ok) throw new Error(d.error || ('card ' + (i + 1) + ' fail'));
            collection = d.collection;
            redirect = d.redirect;

            el('bar').style.width = Math.round(((i + 1) / items.length) * 100) + '%';
            el('progressText').textContent = `${i + 1} / ${items.length} cards saved…`;
        }
        el('progressText').textContent = '✅ Saved! Redirecting…';
        setTimeout(() => { window.location = redirect; }, 700);
    } catch (e) {
        el('progressText').textContent = '❌ Save fail: ' + e.message;
        btn.disabled = false; btn.classList.remove('opacity-60');
    }
});

// ---------- Bulk Delete ----------
(function() {
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
                if (count > 0 && confirm(`Kya aap sach me chune hue ${count} yoga collections ko delete karna chahte hain?`)) {
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
