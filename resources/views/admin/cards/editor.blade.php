@extends('layouts.admin')
@section('title', 'Text Cards')

@section('content')
<div>
    <a href="{{ route('admin.stories.show', $part->story) }}" class="text-sm text-slate-500 hover:text-rose-700">← Back</a>
    <h2 class="text-xl font-bold mt-2">🖼️ Text Card Editor</h2>
    <p class="text-slate-500 mb-6">
        Story: <span class="font-medium">{{ $part->story->title }}</span> ·
        Part {{ $part->sort_order }} @if($part->title) — {{ $part->title }} @endif
    </p>

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Settings --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
            <h3 class="font-semibold">Settings</h3>

            <div>
                <label class="block text-sm font-medium mb-1">Card Size</label>
                <select id="size" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <option value="1080x1920">Reel – Full screen 1080 × 1920 (9:16)</option>
                </select>
                <p class="text-xs text-slate-500 mt-1">Sabhi cards <b>9:16</b> (1080 × 1920) me bante hain — reel ki poori screen bhar jaati hai, koi bar nahi.</p>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Background Style</label>
                <select id="style" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <option value="storybook" selected>Storybook (cream + maroon title) — recommended</option>
                    <option value="gradient">Gradient (two colors)</option>
                    <option value="solid">Solid (one color)</option>
                </select>
            </div>

            <div id="colorGrid" class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Color 1</label>
                    <input type="color" id="color1" value="#7c3aed" class="w-full h-10 rounded-lg border border-slate-300">
                </div>
                <div id="color2Wrap">
                    <label class="block text-sm font-medium mb-1">Color 2</label>
                    <input type="color" id="color2" value="#db2777" class="w-full h-10 rounded-lg border border-slate-300">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Text Size</label>
                <select id="fontSize" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <option value="38">Small (more text / card)</option>
                    <option value="44" selected>Medium</option>
                    <option value="52">Large (less text / card)</option>
                </select>
            </div>

            <div id="quickColors">
                <label class="block text-sm font-medium mb-1">Quick colors</label>
                <div class="flex gap-2 flex-wrap">
                    @php($presets = [['#7c3aed','#db2777'],['#0f766e','#14b8a6'],['#b91c1c','#f59e0b'],['#1e3a8a','#3b82f6'],['#111827','#374151'],['#9d174d','#f43f5e']])
                    @foreach($presets as $p)
                        <button type="button" class="preset w-8 h-8 rounded-full border-2 border-white shadow"
                                data-c1="{{ $p[0] }}" data-c2="{{ $p[1] }}"
                                style="background:linear-gradient(135deg,{{ $p[0] }},{{ $p[1] }})"></button>
                    @endforeach
                </div>
            </div>

            {{-- Storybook colors + bold (only for the storybook style) --}}
            <div id="storybookControls" class="space-y-3 border-t border-slate-200 pt-4">
                <label class="block text-sm font-medium">🎨 Storybook Colors</label>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Background</label>
                        <input type="color" id="sbBg" value="#f3e7cf" class="w-full h-10 rounded-lg border border-slate-300">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Title Box</label>
                        <input type="color" id="sbBox" value="#6d181c" class="w-full h-10 rounded-lg border border-slate-300">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Title Text</label>
                        <input type="color" id="sbTitle" value="#f0c65a" class="w-full h-10 rounded-lg border border-slate-300">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Body Text</label>
                        <input type="color" id="sbBody" value="#2b211a" class="w-full h-10 rounded-lg border border-slate-300">
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm font-medium cursor-pointer">
                    <input type="checkbox" id="sbBold" class="rounded border-slate-300">
                    <b>Bold</b> body text
                </label>

                <div>
                    <label class="block text-xs text-slate-500 mb-1">Ready-made themes</label>
                    <div class="flex gap-2 flex-wrap">
                        @php($sbThemes = [
                            ['bg'=>'#f3e7cf','box'=>'#6d181c','title'=>'#f0c65a','body'=>'#2b211a'],
                            ['bg'=>'#eef3e2','box'=>'#234d2e','title'=>'#e7c15b','body'=>'#1f2a1c'],
                            ['bg'=>'#e6eef5','box'=>'#123a5e','title'=>'#ecd08a','body'=>'#16222e'],
                            ['bg'=>'#fbeef0','box'=>'#7a1733','title'=>'#f2c2a0','body'=>'#2a1720'],
                            ['bg'=>'#201b17','box'=>'#3a1518','title'=>'#e7b84f','body'=>'#f1e6d2'],
                        ])
                        @foreach($sbThemes as $t)
                            <button type="button" class="sbtheme w-8 h-8 rounded-full border-2 border-white shadow"
                                    data-bg="{{ $t['bg'] }}" data-box="{{ $t['box'] }}" data-title="{{ $t['title'] }}" data-body="{{ $t['body'] }}"
                                    style="background:{{ $t['box'] }}"></button>
                        @endforeach
                    </div>
                </div>
            </div>

            <button id="reflow" class="w-full bg-slate-800 hover:bg-slate-900 text-white rounded-lg py-2 text-sm">
                🔄 Update Preview
            </button>
        </div>

        {{-- Live preview --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold">Preview</h3>
                <div class="flex items-center gap-2 text-sm">
                    <button id="prev" class="px-3 py-1 border rounded-lg">‹</button>
                    <span id="pageLabel" class="text-slate-600">Card 1 / 1</span>
                    <button id="next" class="px-3 py-1 border rounded-lg">›</button>
                </div>
            </div>
            <div class="bg-slate-100 rounded-lg p-3 flex items-center justify-center">
                <canvas id="preview" class="max-w-full rounded-lg shadow" style="width:320px;"></canvas>
            </div>
        </div>
    </div>

    {{-- Generate --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 mt-6">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div>
                <h3 class="font-semibold">Save Cards</h3>
                <p class="text-sm text-slate-500">The full text will be split into <span id="totalCards" class="font-medium">?</span> cards. All images will be saved.</p>
            </div>
            <button id="generate" class="bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg px-6 py-3">
                ✅ Generate & Save All Cards
            </button>
        </div>
        <div id="progress" class="hidden mt-4">
            <div class="w-full bg-slate-200 rounded-full h-3">
                <div id="bar" class="bg-rose-600 h-3 rounded-full transition-all" style="width:0%"></div>
            </div>
            <p id="progressText" class="text-sm text-slate-600 mt-2"></p>
        </div>
    </div>

    {{-- Existing saved cards --}}
    @if($part->cards->isNotEmpty())
        <div class="mt-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold">Saved cards ({{ $part->cards->count() }})</h3>
                <form method="POST" action="{{ route('admin.parts.cards.clear', $part) }}" onsubmit="return confirm('Delete all cards?')">
                    @csrf @method('DELETE')
                    <button class="text-sm text-red-600 hover:underline">Delete all</button>
                </form>
            </div>
            <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
                @foreach($part->cards as $card)
                    <div class="relative group">
                        <img src="{{ asset('storage/' . $card->image_path) }}" class="rounded-lg border border-slate-200 w-full" alt="Card {{ $card->sort_order }}">
                        <span class="absolute top-1 left-1 bg-black/60 text-white text-[11px] rounded px-1.5 py-0.5">#{{ $card->sort_order }}</span>
                        <form method="POST" action="{{ route('admin.cards.destroy', $card) }}"
                              onsubmit="return confirm('Delete card #{{ $card->sort_order }}?')"
                              class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition">
                            @csrf @method('DELETE')
                            <button type="submit" title="Delete this card"
                                    class="bg-red-600 hover:bg-red-700 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs shadow">
                                ✕
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
const PART = {
    body: @json($part->body),
    title: @json($part->title),
    order: {{ $part->sort_order }},
    storyTitle: @json($part->story->title),
};
const SAVE_URL = @json(route('admin.parts.cards.store', $part));
const DONE_URL = @json(route('admin.stories.show', $part->story));
const CSRF = document.querySelector('meta[name=csrf-token]').content;

const el = id => document.getElementById(id);
let pages = [];
let currentPage = 0;

function hexToRgb(hex) {
    const n = parseInt(hex.slice(1), 16);
    return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 };
}
function luminance(hex) {
    const c = hexToRgb(hex);
    return 0.299 * c.r + 0.587 * c.g + 0.114 * c.b;
}

// ---------- Storybook style (cream background + maroon title box) ----------
const SB = {
    bg:        '#f3e7cf',   // warm cream paper
    box:       '#6d181c',   // deep maroon title box
    boxBorder: '#e7c15b',   // thin gold frame inside the box
    title:     '#f0c65a',   // gold title text
    sub:       '#fbf1dc',   // cream-white "भाग N"
    body:      '#2b211a',   // dark brown body text
};

function titleFont(s) { return `700 ${s.titleSize}px "Noto Sans Devanagari"`; }
function subFont(s)   { return `600 ${s.headerSize}px "Noto Sans Devanagari"`; }
// Body font — bold only when the storybook "Bold body text" option is on
function bodyFont(s) {
    const w = (s.style === 'storybook' && s.sbBold) ? '700 ' : '';
    return `${w}${s.bodySize}px "Noto Serif Devanagari"`;
}

// Layout the maroon title box for the storybook style.
// storyTitle is same on every card, so the box height is constant.
function storyHeader(ctx, s) {
    const boxX  = s.pad - 30;
    const boxW  = s.W - boxX * 2;
    const innerW = boxW - 80;
    const titlePad = 44;
    const titleLH  = Math.round(s.titleSize * 1.3);
    const subLH    = Math.round(s.headerSize * 1.25);

    ctx.font = titleFont(s);
    const titleLines = wrapText(ctx, PART.storyTitle, innerW);

    const boxH = titlePad * 2 + titleLines.length * titleLH + 8 + subLH;
    return { boxX, boxY: s.pad, boxW, boxH, titleLines, titlePad, titleLH, subLH };
}

function settings() {
    const [w, h] = el('size').value.split('x').map(Number);
    const bodySize = parseInt(el('fontSize').value, 10);
    return {
        W: w, H: h,
        style: el('style').value,
        c1: el('color1').value,
        c2: el('color2').value,
        // Storybook-specific colors + bold body text
        sbBg:    el('sbBg').value,
        sbBox:   el('sbBox').value,
        sbTitle: el('sbTitle').value,
        sbBody:  el('sbBody').value,
        sbBold:  el('sbBold').checked,
        bodySize,
        lineHeight: Math.round(bodySize * 1.7),
        titleSize: Math.round(bodySize * 1.35),
        headerSize: Math.round(bodySize * 1.2),    // story title (body se bada)
        badgeSize: Math.round(bodySize * 0.58),
        pad: 90,
    };
}

// Word-wrap into lines
function wrapText(ctx, text, maxWidth) {
    const lines = [];
    text.split(/\n/).forEach(para => {
        if (para.trim() === '') { lines.push(''); return; }
        const words = para.split(/\s+/);
        let line = '';
        words.forEach(word => {
            const test = line ? line + ' ' + word : word;
            if (ctx.measureText(test).width > maxWidth && line) {
                lines.push(line);
                line = word;
            } else {
                line = test;
            }
        });
        if (line) lines.push(line);
    });
    return lines;
}

// Split lines into cards (pages)
function computePages(ctx, s) {
    const contentW = s.W - s.pad * 2;
    // Footer/dots nahi hai — bas neeche thoda margin
    const bottomLimit = s.H - s.pad - 30;

    // Top header (har card). Storybook = maroon title box (tall), warna
    // "story title (भाग N)" ek line + divider.
    const headerBlock = s.style === 'storybook'
        ? storyHeader(ctx, s).boxH + 46
        : Math.round(s.headerSize * 1.25) + 26;

    ctx.font = bodyFont(s);
    const allLines = wrapText(ctx, PART.body, contentW);

    const firstCap = Math.floor((bottomLimit - (s.pad + headerBlock)) / s.lineHeight);
    const restCap  = firstCap;

    const result = [];
    let i = 0, first = true;
    while (i < allLines.length) {
        const cap = Math.max(1, first ? firstCap : restCap);
        let page = allLines.slice(i, i + cap);
        i += cap;
        while (page.length && page[page.length - 1] === '') page.pop();
        while (page.length && page[0] === '') page.shift();
        if (page.length) result.push(page);
        first = false;
    }
    if (result.length === 0) result.push(['']);
    return { pages: result, titleLines: [], titleBlock: 0 };
}

// Rounded-rectangle path (fallback agar ctx.roundRect na ho)
function roundRectPath(ctx, x, y, w, h, r) {
    ctx.beginPath();
    if (ctx.roundRect) { ctx.roundRect(x, y, w, h, r); return; }
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
}

// Ek line me fit karo, na ho to … laga do
function fitOneLine(ctx, text, maxWidth) {
    if (ctx.measureText(text).width <= maxWidth) return text;
    let t = text;
    while (t.length && ctx.measureText(t + '…').width > maxWidth) t = t.slice(0, -1);
    return t.trim() + '…';
}

// Draw one card — Modern / Bold style
function renderPage(canvas, pageLines, pageIndex, total, titleLines, titleBlock, s) {
    canvas.width = s.W;
    canvas.height = s.H;
    const ctx = canvas.getContext('2d');
    const contentW = s.W - s.pad * 2;

    // ---------- Storybook style (reference design) ----------
    if (s.style === 'storybook') {
        // Paper background (customizable)
        ctx.fillStyle = s.sbBg;
        ctx.fillRect(0, 0, s.W, s.H);

        const h = storyHeader(ctx, s);

        // Title box
        roundRectPath(ctx, h.boxX, h.boxY, h.boxW, h.boxH, 30);
        ctx.fillStyle = s.sbBox;
        ctx.fill();

        // Thin frame inside the box (matches the title color)
        roundRectPath(ctx, h.boxX + 12, h.boxY + 12, h.boxW - 24, h.boxH - 24, 20);
        ctx.lineWidth = 2.5;
        ctx.strokeStyle = s.sbTitle;
        ctx.stroke();

        // Title (centered, wraps)
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        ctx.font = titleFont(s);
        ctx.fillStyle = s.sbTitle;
        let ty = h.boxY + h.titlePad;
        h.titleLines.forEach(line => { ctx.fillText(line, s.W / 2, ty); ty += h.titleLH; });

        // Subtitle "भाग N" (cream-white, centered)
        ctx.font = subFont(s);
        ctx.fillStyle = SB.sub;
        ctx.fillText(`भाग ${pageIndex + 1}`, s.W / 2, ty + 8);

        // Body text (left aligned, optional bold)
        ctx.textAlign = 'left';
        ctx.fillStyle = s.sbBody;
        ctx.font = bodyFont(s);
        let by = h.boxY + h.boxH + 46;
        pageLines.forEach(line => { ctx.fillText(line, s.pad, by); by += s.lineHeight; });
        return;
    }

    // 1) Background gradient / solid
    if (s.style === 'gradient') {
        const g = ctx.createLinearGradient(0, 0, s.W, s.H);
        g.addColorStop(0, s.c1);
        g.addColorStop(1, s.c2);
        ctx.fillStyle = g;
    } else {
        ctx.fillStyle = s.c1;
    }
    ctx.fillRect(0, 0, s.W, s.H);

    const lum = luminance(s.c1);
    const dark = lum < 140;
    const textColor = dark ? '#ffffff' : '#1f2937';
    const accent = dark ? 'rgba(255,255,255,0.9)' : '#db2777';

    // 2) Radial glow (top-center) — depth ke liye
    const glow = ctx.createRadialGradient(s.W * 0.5, s.H * 0.30, 0, s.W * 0.5, s.H * 0.30, s.W * 0.95);
    glow.addColorStop(0, dark ? 'rgba(255,255,255,0.16)' : 'rgba(255,255,255,0.40)');
    glow.addColorStop(1, 'rgba(255,255,255,0)');
    ctx.fillStyle = glow;
    ctx.fillRect(0, 0, s.W, s.H);

    // 3) Glass panel (readability)
    const px = s.pad - 34;
    const py = s.pad - 46;
    const pBottom = s.H - s.pad - 10;
    roundRectPath(ctx, px, py, s.W - px * 2, pBottom - py, 40);
    ctx.fillStyle = dark ? 'rgba(255,255,255,0.10)' : 'rgba(255,255,255,0.45)';
    ctx.fill();
    ctx.lineWidth = 2;
    ctx.strokeStyle = dark ? 'rgba(255,255,255,0.22)' : 'rgba(0,0,0,0.08)';
    ctx.stroke();

    ctx.textBaseline = 'top';

    const applyShadow = () => {
        ctx.shadowColor = dark ? 'rgba(0,0,0,0.35)' : 'rgba(0,0,0,0.12)';
        ctx.shadowBlur = 10; ctx.shadowOffsetX = 0; ctx.shadowOffsetY = 3;
    };
    const clearShadow = () => { ctx.shadowColor = 'transparent'; ctx.shadowBlur = 0; ctx.shadowOffsetY = 0; };

    // 4) TOP HEADER (har card) — "story title (भाग N)" ek hi line me
    let y = s.pad;

    ctx.font = `700 ${s.headerSize}px "Noto Sans Devanagari"`;
    const suffix = ` (भाग ${pageIndex + 1})`;
    const suffixW = ctx.measureText(suffix).width;
    const title = fitOneLine(ctx, PART.storyTitle, contentW - suffixW);

    // Title
    ctx.fillStyle = textColor;
    applyShadow();
    ctx.fillText(title, s.pad, y);
    clearShadow();

    // Usi line me "(भाग N)" — accent color
    ctx.fillStyle = accent;
    ctx.globalAlpha = dark ? 0.95 : 1;
    ctx.fillText(suffix, s.pad + ctx.measureText(title).width, y);
    ctx.globalAlpha = 1;

    y += Math.round(s.headerSize * 1.25);

    // Divider
    ctx.globalAlpha = 0.25;
    ctx.fillStyle = textColor;
    ctx.fillRect(s.pad, y + 6, contentW, 2);
    ctx.globalAlpha = 1;
    y += 26;

    // 5) Body text
    ctx.fillStyle = textColor;
    ctx.font = bodyFont(s);
    applyShadow();
    pageLines.forEach(line => { ctx.fillText(line, s.pad, y); y += s.lineHeight; });
    clearShadow();

}

let cache = null;

async function reflow() {
    const s = settings();
    const off = document.createElement('canvas');
    off.width = s.W; off.height = s.H;
    const ctx = off.getContext('2d');
    await document.fonts.load(`${s.bodySize}px "Noto Serif Devanagari"`);
    await document.fonts.load(`700 ${s.bodySize}px "Noto Serif Devanagari"`);
    await document.fonts.load(`700 ${s.titleSize}px "Noto Sans Devanagari"`);
    await document.fonts.load(`600 ${s.headerSize}px "Noto Sans Devanagari"`);
    await document.fonts.ready;

    const c = computePages(ctx, s);
    pages = c.pages;
    cache = c;
    currentPage = Math.min(currentPage, pages.length - 1);
    el('totalCards').textContent = pages.length;
    drawPreview();
}

function drawPreview() {
    const s = settings();
    renderPage(el('preview'), pages[currentPage], currentPage, pages.length, cache.titleLines, cache.titleBlock, s);
    el('pageLabel').textContent = `Card ${currentPage + 1} / ${pages.length}`;
}

async function postCard(dataUrl, order, reset) {
    const res = await fetch(SAVE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ image: dataUrl, order, reset }),
    });
    if (!res.ok) throw new Error('Card ' + order + ' failed to save (HTTP ' + res.status + ')');
    return res.json();
}

async function generateAll() {
    const s = settings();
    await reflow();
    const btn = el('generate');
    btn.disabled = true;
    btn.classList.add('opacity-60');
    el('progress').classList.remove('hidden');

    const off = document.createElement('canvas');
    try {
        for (let i = 0; i < pages.length; i++) {
            renderPage(off, pages[i], i, pages.length, cache.titleLines, cache.titleBlock, s);
            const dataUrl = off.toDataURL('image/png');
            await postCard(dataUrl, i + 1, i === 0);
            const pct = Math.round(((i + 1) / pages.length) * 100);
            el('bar').style.width = pct + '%';
            el('progressText').textContent = `${i + 1} / ${pages.length} cards saved...`;
        }
        el('progressText').textContent = '✅ All cards saved! Redirecting...';
        setTimeout(() => { window.location = DONE_URL; }, 800);
    } catch (e) {
        el('progressText').textContent = '❌ ' + e.message;
        btn.disabled = false;
        btn.classList.remove('opacity-60');
    }
}

el('reflow').addEventListener('click', reflow);
el('generate').addEventListener('click', generateAll);
el('prev').addEventListener('click', () => { if (currentPage > 0) { currentPage--; drawPreview(); } });
el('next').addEventListener('click', () => { if (currentPage < pages.length - 1) { currentPage++; drawPreview(); } });
['size', 'style', 'color1', 'color2', 'fontSize',
 'sbBg', 'sbBox', 'sbTitle', 'sbBody', 'sbBold'].forEach(id => el(id).addEventListener('change', reflow));

// Storybook ready-made themes
document.querySelectorAll('.sbtheme').forEach(b => b.addEventListener('click', () => {
    el('sbBg').value    = b.dataset.bg;
    el('sbBox').value   = b.dataset.box;
    el('sbTitle').value = b.dataset.title;
    el('sbBody').value  = b.dataset.body;
    reflow();
}));
function updateStyleUI() {
    const v = el('style').value;
    const usesColors = (v === 'gradient' || v === 'solid');
    el('colorGrid').style.display  = usesColors ? '' : 'none';
    el('quickColors').style.display = usesColors ? '' : 'none';
    el('color2Wrap').style.display = (v === 'gradient') ? '' : 'none';
    el('storybookControls').style.display = (v === 'storybook') ? '' : 'none';
}
el('style').addEventListener('change', updateStyleUI);
updateStyleUI();
document.querySelectorAll('.preset').forEach(b => b.addEventListener('click', () => {
    el('color1').value = b.dataset.c1;
    el('color2').value = b.dataset.c2;
    reflow();
}));

reflow();
</script>
@endpush
@endsection
