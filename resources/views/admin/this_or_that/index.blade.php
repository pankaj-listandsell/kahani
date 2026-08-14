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
                    <option value="gujarati" selected>ગુજરાતી (Gujarati)</option>
                    <option value="hindi">हिंदी (Hindi)</option>
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
</div>

@push('scripts')
<script>
(function() {
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const GEN_URL = @json(route('admin.this_or_that.generate'));
    const SAVE_URL = @json(route('admin.this_or_that.save'));

    let items = [];
    const el = id => document.getElementById(id);

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
            el('progressBar').style.width = '80%';
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
                    <span>Poll #${idx + 1}: ${item.title}</span>
                </div>
                <div class="p-3 flex-1 flex flex-col items-center"></div>
                <div class="p-3 bg-slate-50 border-t border-slate-100 text-xs text-slate-500">
                    <p class="font-medium text-slate-700 truncate">${item.option_a} VS ${item.option_b}</p>
                </div>
            `;
            cardBox.querySelector('.flex-1').appendChild(canvas);
            grid.appendChild(cardBox);
        });
    }

    function drawThisOrThat(c, item) {
        const ctx = c.getContext('2d');
        const W = 1080, H = 1920;

        // Background Dark
        ctx.fillStyle = '#0a0d14';
        ctx.fillRect(0, 0, W, H);

        // Header Title
        ctx.fillStyle = '#fbbf24';
        ctx.font = 'bold 58px "Noto Sans Devanagari", "Noto Sans Gujarati", sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(item.title || 'તમે શું પસંદ કરશો? 🤔', W / 2, 180);

        ctx.fillStyle = 'rgba(255,255,255,0.7)';
        ctx.font = 'bold 36px sans-serif';
        ctx.fillText('આ કે તે? તમારો સાચો જવાબ કમેન્ટ કરો!', W / 2, 250);

        // OPTION A BOX (Top Half)
        const boxW = W - 160, boxH = 580;
        const boxX = 80, boxY_A = 340;

        const gradA = ctx.createLinearGradient(boxX, boxY_A, boxX + boxW, boxY_A + boxH);
        gradA.addColorStop(0, '#e11d48');
        gradA.addColorStop(1, '#9333ea');
        ctx.fillStyle = gradA;
        ctx.roundRect(boxX, boxY_A, boxW, boxH, 32);
        ctx.fill();
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 4;
        ctx.stroke();

        ctx.fillStyle = 'rgba(255,255,255,0.2)';
        ctx.roundRect(boxX + 40, boxY_A + 40, 180, 70, 18);
        ctx.fill();
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 44px sans-serif';
        ctx.fillText('OPTION A', boxX + 130, boxY_A + 88);

        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 54px "Noto Sans Devanagari", "Noto Sans Gujarati", sans-serif';
        wrapText(ctx, item.option_a, W / 2, boxY_A + 280, boxW - 80, 75);

        // OPTION B BOX (Bottom Half)
        const boxY_B = 1040;
        const gradB = ctx.createLinearGradient(boxX, boxY_B, boxX + boxW, boxY_B + boxH);
        gradB.addColorStop(0, '#059669');
        gradB.addColorStop(1, '#0284c7');
        ctx.fillStyle = gradB;
        ctx.roundRect(boxX, boxY_B, boxW, boxH, 32);
        ctx.fill();
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 4;
        ctx.stroke();

        ctx.fillStyle = 'rgba(255,255,255,0.2)';
        ctx.roundRect(boxX + 40, boxY_B + 40, 180, 70, 18);
        ctx.fill();
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 44px sans-serif';
        ctx.fillText('OPTION B', boxX + 130, boxY_B + 88);

        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 54px "Noto Sans Devanagari", "Noto Sans Gujarati", sans-serif';
        wrapText(ctx, item.option_b, W / 2, boxY_B + 280, boxW - 80, 75);

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
        ctx.font = 'extrabold 82px sans-serif';
        ctx.fillText('VS', W / 2, 1008);

        // BOTTOM CTA
        ctx.fillStyle = '#fbbf24';
        ctx.font = 'bold 44px "Noto Sans Devanagari", "Noto Sans Gujarati", sans-serif';
        ctx.fillText('👇 તમારી પસંદગી કમેન્ટ બોક્સમાં લખો! 👇', W / 2, 1720);

        ctx.fillStyle = 'rgba(255,255,255,0.7)';
        ctx.font = 'bold 36px sans-serif';
        ctx.fillText('LIKE • SHARE • FOLLOW FOR MORE', W / 2, 1790);
    }

    function wrapText(ctx, text, x, y, maxWidth, lineHeight) {
        const words = text.split(' ');
        let line = '';
        const lines = [];
        for (let n = 0; n < words.length; n++) {
            const testLine = line + words[n] + ' ';
            const metrics = ctx.measureText(testLine);
            if (metrics.width > maxWidth && n > 0) {
                lines.push(line);
                line = words[n] + ' ';
            } else {
                line = testLine;
            }
        }
        lines.push(line);

        const startY = y - ((lines.length - 1) * lineHeight) / 2;
        lines.forEach((l, i) => {
            ctx.fillText(l.trim(), x, startY + i * lineHeight);
        });
    }

    // Save All Polls
    el('saveBtn').addEventListener('click', async () => {
        if (!items.length) return;
        const btn = el('saveBtn');
        btn.disabled = true;
        el('progressBox').classList.remove('hidden');
        el('progressBar').style.width = '30%';

        const off = document.createElement('canvas'); off.width = 1080; off.height = 1920;
        const cardsToSave = [];

        items.forEach((item, idx) => {
            drawThisOrThat(off, item);
            const fullVoiceText = item.title + '. Option A: ' + item.option_a + '. Option B: ' + item.option_b + '. Tamari pasand comment karo!';
            cardsToSave.push({
                order: idx + 1,
                text: fullVoiceText,
                caption: item.caption,
                hashtags: item.hashtags,
                image: off.toDataURL('image/webp', 0.92),
            });
        });

        el('progressBar').style.width = '75%';

        try {
            const r = await fetch(SAVE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ language: el('language').value, cards: cardsToSave })
            });
            const d = await r.json();
            if (!d.ok) throw new Error(d.error || 'Failed to save');

            el('progressBar').style.width = '100%';
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
