@extends('layouts.admin')
@section('title', '🔮 Mind Reader Studio — Magic Math & Psychology')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-purple-700 via-indigo-700 to-violet-800 rounded-2xl p-6 text-white shadow-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="inline-block px-2.5 py-1 bg-white/20 rounded-full text-xs font-semibold uppercase tracking-wider mb-2">🔮 100% Interaction Magic Game</span>
                <h1 class="text-2xl font-bold">Mind Reader Studio — Magic Psychology Reels</h1>
                <p class="text-purple-100 text-sm mt-1">Interactive step-by-step math and psychological tricks that read the viewer's mind and boost follows.</p>
            </div>
            <div class="flex items-center gap-3">
                <button id="saveBtn" disabled class="bg-white text-purple-800 font-bold px-5 py-2.5 rounded-xl shadow hover:bg-purple-50 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-2">
                    <span>💾</span> Save All Tricks
                </button>
            </div>
        </div>
    </div>

    {{-- Controls --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <div class="grid sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🌐 Language</label>
                <select id="language" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:outline-none font-medium">
                    <option value="gujarati" selected>ગુજરાતી (Gujarati)</option>
                    <option value="hindi">हिंदी (Hindi)</option>
                    <option value="hinglish">Hinglish</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🔢 Tricks Count</label>
                <select id="count" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:outline-none font-medium">
                    <option value="3" selected>3 Magic Tricks</option>
                    <option value="5">5 Magic Tricks</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🎨 Visual Theme</label>
                <select id="theme" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:outline-none font-medium">
                    <option value="neon_purple" selected>🔮 Mystic Purple &amp; Neon</option>
                    <option value="cyber_blue">⚡ Cyber Blue &amp; Gold</option>
                    <option value="dark_magician">🎩 Dark Magician Gold</option>
                </select>
            </div>
            <div class="flex items-end">
                <button id="genBtn" class="w-full bg-gradient-to-r from-purple-600 to-indigo-700 hover:from-purple-700 hover:to-indigo-800 text-white font-bold py-2 px-4 rounded-lg shadow transition flex items-center justify-center gap-2">
                    <span id="genText">Generate Tricks</span>
                </button>
            </div>
        </div>

        {{-- Progress Bar --}}
        <div id="progressBox" class="hidden mt-4 pt-4 border-t border-slate-100">
            <div class="flex items-center justify-between text-xs font-semibold text-slate-600 mb-1">
                <span id="progressText">Generating...</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div id="progressBar" class="bg-gradient-to-r from-purple-500 to-indigo-600 h-2 transition-all duration-300" style="width: 0%"></div>
            </div>
        </div>
    </div>

    {{-- Previews Grid --}}
    <div id="emptyState" class="bg-white rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center text-slate-400">
        <span class="text-4xl block mb-2">🔮</span>
        <p class="font-bold text-slate-600">No Mind Reader tricks generated yet</p>
        <p class="text-xs mt-1">Click "Generate Tricks" to create interactive viral psychology games.</p>
    </div>

    <div id="previewGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 hidden"></div>
</div>

@push('scripts')
<script>
(function() {
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const GEN_URL = @json(route('admin.mind_reader.generate'));
    const SAVE_URL = @json(route('admin.mind_reader.save'));

    let items = [];

    const THEMES = {
        neon_purple: { bg: ['#0f051d', '#230a3f'], accent: '#c084fc', gold: '#fbbf24', border: '#a855f7', box: 'rgba(168,85,247,0.12)' },
        cyber_blue: { bg: ['#031024', '#08254f'], accent: '#38bdf8', gold: '#facc15', border: '#0284c7', box: 'rgba(56,189,248,0.12)' },
        dark_magician: { bg: ['#120808', '#2a1111'], accent: '#f59e0b', gold: '#fbbf24', border: '#d97706', box: 'rgba(245,158,11,0.12)' }
    };

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
                body: JSON.stringify({ count: el('count').value, language: el('language').value })
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
            el('genText').textContent = 'Generate Tricks';
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

            const curTheme = THEMES[el('theme').value] || THEMES.neon_purple;
            drawMindReader(canvasQ, item, curTheme, false);
            drawMindReader(canvasA, item, curTheme, true);

            cardBox.innerHTML = `
                <div class="p-3 bg-purple-50 border-b border-purple-100 flex items-center justify-between text-xs font-bold text-purple-900">
                    <span>Trick #${idx + 1}: ${item.title}</span>
                    <span class="px-2 py-0.5 rounded bg-purple-200 text-purple-900 font-extrabold">${item.final_answer}</span>
                </div>
                <div class="p-3 flex-1 flex flex-col items-center"></div>
                <div class="p-3 bg-slate-50 border-t border-slate-100 text-xs text-slate-500">
                    <p class="font-medium text-slate-700 truncate">${item.call_to_action}</p>
                </div>
            `;
            cardBox.querySelector('.flex-1').appendChild(canvasQ);
            grid.appendChild(cardBox);
        });
    }

    function drawMindReader(c, item, t, isAnswer) {
        const ctx = c.getContext('2d');
        const W = 1080, H = 1920;

        const grad = ctx.createLinearGradient(0, 0, 0, H);
        grad.addColorStop(0, t.bg[0]);
        grad.addColorStop(1, t.bg[1]);
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, W, H);

        // Header Title
        ctx.fillStyle = t.gold;
        ctx.font = 'bold 56px "Noto Sans Devanagari", "Noto Sans Gujarati", sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(item.title, W / 2, 220);

        ctx.fillStyle = 'rgba(255,255,255,0.7)';
        ctx.font = 'bold 36px sans-serif';
        ctx.fillText(isAnswer ? '🎯 સાચો જવાબ નીચે જુઓ!' : '🧠 ધ્યાનથી ગણો અને અંતે જવાબ જુઓ!', W / 2, 290);

        if (!isAnswer) {
            // 3 Steps
            const steps = [
                { num: '૧', text: item.step1 },
                { num: '૨', text: item.step2 },
                { num: '૩', text: item.step3 }
            ];

            const startY = 420;
            const stepH = 340;

            steps.forEach((s, idx) => {
                const y = startY + idx * (stepH + 40);

                ctx.fillStyle = t.box;
                ctx.roundRect(100, y, W - 200, stepH, 28);
                ctx.fill();
                ctx.strokeStyle = t.border;
                ctx.lineWidth = 3;
                ctx.stroke();

                // Step Circle Badge
                ctx.fillStyle = t.accent;
                ctx.beginPath();
                ctx.arc(200, y + stepH / 2, 60, 0, Math.PI * 2);
                ctx.fill();

                ctx.fillStyle = '#000000';
                ctx.font = 'bold 54px sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(s.num, 200, y + stepH / 2);

                // Step text (word wrap)
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 44px "Noto Sans Devanagari", "Noto Sans Gujarati", sans-serif';
                ctx.textAlign = 'left';
                wrapText(ctx, s.text, 300, y + 100, W - 440, 64);
            });

            // Countdown box
            ctx.fillStyle = t.gold;
            ctx.font = 'bold 46px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('⏱️ જવાબ જોવા માટે ૫ સેકન્ડ રાહ જુઓ...', W / 2, 1720);
        } else {
            // Final Answer Reveal Card
            ctx.save();
            ctx.shadowColor = t.gold;
            ctx.shadowBlur = 40;
            ctx.fillStyle = 'rgba(251,191,36,0.15)';
            ctx.roundRect(120, 520, W - 240, 750, 36);
            ctx.fill();
            ctx.strokeStyle = t.gold;
            ctx.lineWidth = 4;
            ctx.stroke();
            ctx.restore();

            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 50px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('તમારો છેલ્લો જવાબ છે:', W / 2, 680);

            // Huge Result Number / Text
            ctx.fillStyle = t.gold;
            ctx.font = 'extrabold 90px "Noto Sans Devanagari", "Noto Sans Gujarati", sans-serif';
            ctx.fillText(item.final_answer, W / 2, 880);

            // CTA
            ctx.fillStyle = t.accent;
            ctx.font = 'bold 46px "Noto Sans Devanagari", "Noto Sans Gujarati", sans-serif';
            wrapText(ctx, item.call_to_action, 180, 1050, W - 360, 65);

            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 40px sans-serif';
            ctx.fillText('LIKE • SHARE • SUBSCRIBE FOR MORE', W / 2, 1680);
        }
    }

    function wrapText(ctx, text, x, y, maxWidth, lineHeight) {
        const words = text.split(' ');
        let line = '';
        for (let n = 0; n < words.length; n++) {
            const testLine = line + words[n] + ' ';
            const metrics = ctx.measureText(testLine);
            if (metrics.width > maxWidth && n > 0) {
                ctx.fillText(line, x, y);
                line = words[n] + ' ';
                y += lineHeight;
            } else {
                line = testLine;
            }
        }
        ctx.fillText(line, x, y);
    }

    // Save All Tricks
    el('saveBtn').addEventListener('click', async () => {
        if (!items.length) return;
        const btn = el('saveBtn');
        btn.disabled = true;
        el('progressBox').classList.remove('hidden');
        el('progressBar').style.width = '30%';

        const curTheme = THEMES[el('theme').value] || THEMES.neon_purple;
        const offQ = document.createElement('canvas'); offQ.width = 1080; offQ.height = 1920;
        const offA = document.createElement('canvas'); offA.width = 1080; offA.height = 1920;

        const cardsToSave = [];
        items.forEach((item, idx) => {
            drawMindReader(offQ, item, curTheme, false);
            drawMindReader(offA, item, curTheme, true);
            const fullVoiceText = item.title + '. ' + item.step1 + ' ' + item.step2 + ' ' + item.step3;
            cardsToSave.push({
                order: idx + 1,
                text: fullVoiceText,
                answer: item.final_answer + '. ' + item.call_to_action,
                caption: item.caption,
                hashtags: item.hashtags,
                image: offQ.toDataURL('image/webp', 0.92),
                answer_image: offA.toDataURL('image/webp', 0.92),
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
