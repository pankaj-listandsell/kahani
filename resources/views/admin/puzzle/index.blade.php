@extends('layouts.admin')
@section('title', '🔍 Puzzle Studio — Find The Odd One')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-amber-600 via-orange-600 to-rose-600 rounded-2xl p-6 text-white shadow-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="inline-block px-2.5 py-1 bg-white/20 rounded-full text-xs font-semibold uppercase tracking-wider mb-2">🔥 200%+ Retention Format</span>
                <h1 class="text-2xl font-bold">🔍 Puzzle Studio — Find The Odd Character</h1>
                <p class="text-amber-100 text-sm mt-1">Generate viral grid puzzles (Find hidden letter/emoji) with countdown timer and glowing answer reveal.</p>
            </div>
            <div class="flex items-center gap-3">
                <button id="saveBtn" disabled class="bg-white text-orange-700 font-bold px-5 py-2.5 rounded-xl shadow hover:bg-amber-50 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-2">
                    <span>💾</span> Save All Puzzles
                </button>
            </div>
        </div>
    </div>

    {{-- Controls --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <div class="grid sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🌐 Language</label>
                <select id="language" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-medium">
                    <option value="gujarati" selected>ગુજરાતી (Gujarati)</option>
                    <option value="hindi">हिंदी (Hindi)</option>
                    <option value="hinglish">Hinglish</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🔢 Puzzle Count</label>
                <select id="count" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-medium">
                    <option value="3">3 Puzzles</option>
                    <option value="5" selected>5 Puzzles</option>
                    <option value="10">10 Puzzles</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🎨 Theme Style</label>
                <select id="theme" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-medium">
                    <option value="dark_gold" selected>✨ Dark &amp; Neon Gold</option>
                    <option value="midnight_blue">🌌 Midnight Blue &amp; Cyan</option>
                    <option value="emerald_matrix">🌲 Emerald Forest &amp; Lime</option>
                    <option value="crimson_fire">🔥 Crimson Red &amp; Yellow</option>
                </select>
            </div>
            <div class="flex items-end">
                <button id="genBtn" class="w-full bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white font-bold py-2 px-4 rounded-lg shadow transition flex items-center justify-center gap-2">
                    <span id="genIcon">⚡</span>
                    <span id="genText">Generate Puzzles</span>
                </button>
            </div>
        </div>

        {{-- Progress Bar --}}
        <div id="progressBox" class="hidden mt-4 pt-4 border-t border-slate-100">
            <div class="flex items-center justify-between text-xs font-semibold text-slate-600 mb-1">
                <span id="progressText">Generating...</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div id="progressBar" class="bg-gradient-to-r from-amber-500 to-orange-600 h-2 transition-all duration-300" style="width: 0%"></div>
            </div>
        </div>
    </div>

    {{-- Previews Grid --}}
    <div id="emptyState" class="bg-white rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center text-slate-400">
        <span class="text-4xl block mb-2">🔍</span>
        <p class="font-bold text-slate-600">No puzzles generated yet</p>
        <p class="text-xs mt-1">Select language &amp; click "Generate Puzzles" to create viral visual grid challenges.</p>
    </div>

    <div id="previewGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 hidden"></div>
</div>

@push('scripts')
<script>
(function() {
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const GEN_URL = @json(route('admin.puzzle.generate'));
    const SAVE_URL = @json(route('admin.puzzle.save'));

    let items = [];

    const THEMES = {
        dark_gold: { bg: ['#0f0d08', '#1f1807'], text: '#ffffff', oddBg: '#ffb703', oddGlow: 'rgba(255,183,3,0.8)', border: '#f59e0b', hookBg: '#d97706' },
        midnight_blue: { bg: ['#050d1a', '#0a192f'], text: '#ffffff', oddBg: '#00f0ff', oddGlow: 'rgba(0,240,255,0.8)', border: '#38bdf8', hookBg: '#0284c7' },
        emerald_matrix: { bg: ['#04140c', '#092618'], text: '#ffffff', oddBg: '#10b981', oddGlow: 'rgba(16,185,129,0.8)', border: '#34d399', hookBg: '#059669' },
        crimson_fire: { bg: ['#170505', '#2b0a0a'], text: '#ffffff', oddBg: '#f59e0b', oddGlow: 'rgba(245,158,11,0.8)', border: '#ef4444', hookBg: '#dc2626' }
    };

    const el = id => document.getElementById(id);

    el('genBtn').addEventListener('click', async () => {
        const btn = el('genBtn');
        btn.disabled = true;
        el('genText').textContent = 'AI Generating…';
        el('progressBox').classList.remove('hidden');
        el('progressBar').style.width = '40%';
        el('progressText').textContent = 'Consulting Gemini AI…';

        try {
            const r = await fetch(GEN_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ count: el('count').value, language: el('language').value })
            });
            const d = await r.json();
            if (!d.ok) throw new Error(d.error || 'Failed to generate');

            items = d.items;
            el('progressBar').style.width = '80%';
            el('progressText').textContent = 'Rendering canvases…';

            renderAllPreviews();

            el('progressBar').style.width = '100%';
            el('progressText').textContent = 'Done!';
            el('saveBtn').disabled = false;
            setTimeout(() => el('progressBox').classList.add('hidden'), 500);
        } catch (e) {
            alert('Error: ' + e.message);
            el('progressBox').classList.add('hidden');
        } finally {
            btn.disabled = false;
            el('genText').textContent = 'Generate Puzzles';
        }
    });

    el('theme').addEventListener('change', () => { if (items.length) renderAllPreviews(); });

    function renderAllPreviews() {
        el('emptyState').classList.add('hidden');
        const grid = el('previewGrid');
        grid.classList.remove('hidden');
        grid.innerHTML = '';

        items.forEach((item, idx) => {
            const cardBox = document.createElement('div');
            cardBox.className = 'bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm flex flex-col';

            const canvasQ = document.createElement('canvas');
            canvasQ.width = 1080; canvasQ.height = 1920;
            canvasQ.className = 'w-full aspect-[9/16] object-cover bg-slate-900';

            const canvasA = document.createElement('canvas');
            canvasA.width = 1080; canvasA.height = 1920;

            const curTheme = THEMES[el('theme').value] || THEMES.dark_gold;
            drawPuzzle(canvasQ, item, curTheme, false);
            drawPuzzle(canvasA, item, curTheme, true);

            cardBox.innerHTML = `
                <div class="p-3 bg-slate-50 border-b border-slate-100 flex items-center justify-between text-xs font-bold text-slate-700">
                    <span>Puzzle #${idx + 1}: Find "${item.odd_char}"</span>
                    <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-800">Row ${item.odd_row}, Col ${item.odd_col}</span>
                </div>
                <div class="p-3 flex-1 flex flex-col items-center"></div>
                <div class="p-3 bg-slate-50 border-t border-slate-100 text-xs text-slate-500">
                    <p class="font-medium text-slate-700 truncate">${item.hook}</p>
                </div>
            `;
            cardBox.querySelector('.flex-1').appendChild(canvasQ);
            grid.appendChild(cardBox);
        });
    }

    function drawPuzzle(c, item, t, isAnswer) {
        const ctx = c.getContext('2d');
        const W = 1080, H = 1920;

        // Gradient Background
        const grad = ctx.createLinearGradient(0, 0, 0, H);
        grad.addColorStop(0, t.bg[0]);
        grad.addColorStop(1, t.bg[1]);
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, W, H);

        // Header Box
        ctx.fillStyle = t.hookBg;
        ctx.roundRect(80, 160, W - 160, 130, 24);
        ctx.fill();

        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 52px "Noto Sans Devanagari", "Noto Sans Gujarati", sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(item.hook, W / 2, 225);

        // Subtitle instructions
        ctx.fillStyle = t.oddBg;
        ctx.font = 'bold 40px sans-serif';
        ctx.fillText(isAnswer ? '✅ આ રહ્યો સાચો જવાબ!' : '⏱️ ૫ સેકન્ડમાં શોધીને કમેન્ટ કરો!', W / 2, 340);

        // Puzzle Grid Container
        const startX = 100, startY = 430;
        const gridW = W - 200, gridH = 1180;
        const rows = item.grid_rows || 7, cols = item.grid_cols || 8;
        const cellW = gridW / cols, cellH = gridH / rows;

        // Background box for grid
        ctx.fillStyle = 'rgba(255,255,255,0.03)';
        ctx.roundRect(startX - 20, startY - 20, gridW + 40, gridH + 40, 24);
        ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,0.1)';
        ctx.lineWidth = 2;
        ctx.stroke();

        ctx.font = 'bold 64px "Noto Sans Devanagari", "Noto Sans Gujarati", sans-serif';

        for (let r = 1; r <= rows; r++) {
            for (let col = 1; col <= cols; col++) {
                const isOdd = (r === item.odd_row && col === item.odd_col);
                const char = isOdd ? item.odd_char : item.base_char;
                const cx = startX + (col - 1) * cellW + cellW / 2;
                const cy = startY + (r - 1) * cellH + cellH / 2;

                if (isAnswer && isOdd) {
                    // Highlight Glowing Target Circle
                    ctx.save();
                    ctx.shadowColor = t.oddGlow;
                    ctx.shadowBlur = 35;
                    ctx.fillStyle = t.oddBg;
                    ctx.beginPath();
                    ctx.arc(cx, cy, 54, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.restore();

                    ctx.fillStyle = '#000000';
                } else {
                    ctx.fillStyle = '#ffffff';
                }

                ctx.fillText(char, cx, cy);
            }
        }

        // Bottom CTA
        ctx.fillStyle = 'rgba(255,255,255,0.85)';
        ctx.font = 'bold 38px sans-serif';
        ctx.fillText('LIKE • SHARE • FOLLOW FOR MORE', W / 2, 1780);
    }

    // Save All Puzzles
    el('saveBtn').addEventListener('click', async () => {
        if (!items.length) return;
        const btn = el('saveBtn');
        btn.disabled = true;
        el('progressBox').classList.remove('hidden');
        el('progressBar').style.width = '30%';
        el('progressText').textContent = 'Rendering WebP cards…';

        const curTheme = THEMES[el('theme').value] || THEMES.dark_gold;
        const offQ = document.createElement('canvas'); offQ.width = 1080; offQ.height = 1920;
        const offA = document.createElement('canvas'); offA.width = 1080; offA.height = 1920;

        const cardsToSave = [];
        items.forEach((item, idx) => {
            drawPuzzle(offQ, item, curTheme, false);
            drawPuzzle(offA, item, curTheme, true);
            cardsToSave.push({
                order: idx + 1,
                text: item.hook,
                answer: item.answer_text,
                caption: item.caption,
                hashtags: item.hashtags,
                image: offQ.toDataURL('image/webp', 0.92),
                answer_image: offA.toDataURL('image/webp', 0.92),
            });
        });

        el('progressBar').style.width = '75%';
        el('progressText').textContent = 'Saving to database…';

        try {
            const r = await fetch(SAVE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ category: 'Puzzle', language: el('language').value, cards: cardsToSave })
            });
            const d = await r.json();
            if (!d.ok) throw new Error(d.error || 'Failed to save');

            el('progressBar').style.width = '100%';
            el('progressText').textContent = '✅ Saved! Redirecting…';
            setTimeout(() => window.location = d.redirect, 400);
        } catch (e) {
            alert('Save error: ' + e.message);
            btn.disabled = false;
            el('progressBox').classList.add('hidden');
        }
    });
})();
</script>
@endpush
@endsection
