@extends('layouts.admin')
@section('title', 'Quiz Studio')

@section('content')
<div class="max-w-5xl">
    <h2 class="text-xl font-bold flex items-center gap-2">🎯 Quiz Studio</h2>
    <p class="text-slate-500 mb-6">Topic likho (jaise Constable, GK, History) → AI MCQ banata hai → har quiz ka <b>ek Question card</b> (4 options). <b>Answer + reason caption me</b> jaata hai (card par nahi). Fir auto-post inhe post karta hai.</p>

    {{-- CONTROLS --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 space-y-4">
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Topic / Exam</label>
                <input type="text" id="category" list="catList" placeholder="e.g. Constable GK"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <datalist id="catList">
                    {{-- Exam-focused --}}
                    <option value="Constable GK"><option value="SSC GK"><option value="Railway (RRB) GK">
                    <option value="Police Exam GK"><option value="Army / Defence GK"><option value="Banking Awareness">
                    {{-- General Knowledge --}}
                    <option value="General Knowledge (GK)"><option value="Current Affairs"><option value="Static GK">
                    <option value="Important Days"><option value="Awards & Honours"><option value="Books & Authors">
                    <option value="Sports GK"><option value="Famous Personalities">
                    {{-- Subjects --}}
                    <option value="Indian History"><option value="World History"><option value="Indian Geography">
                    <option value="World Geography"><option value="Indian Polity / Constitution"><option value="Economics">
                    <option value="General Science"><option value="Physics"><option value="Chemistry"><option value="Biology">
                    <option value="Computer Knowledge"><option value="Environment & Ecology"><option value="Space & Technology">
                    {{-- Aptitude --}}
                    <option value="Reasoning"><option value="Maths / Quantitative Aptitude"><option value="English Grammar">
                    {{-- Culture / misc --}}
                    <option value="Indian Culture"><option value="Indian Festivals"><option value="Rajasthan GK">
                    <option value="Madhya Pradesh GK"><option value="UP GK"><option value="Bihar GK">
                </datalist>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">🌐 Language</label>
                <select id="language" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="gujarati" selected>ગુજરાતી Gujarati</option>
                    <option value="hindi">हिंदी Hindi</option>
                    <option value="hinglish">Hindi-English (Roman)</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Kitne cards?</label>
                <input type="number" id="count" value="5" min="1" max="30"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <p id="countHint" class="text-[11px] text-slate-400 mt-1"></p>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Theme 🎨</label>
                <select id="theme" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></select>
            </div>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">🖼 Card Design</label>
                <select id="style" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="simple">✨ Simple (header + footer)</option>
                    <option value="list">📋 Q&amp;A List (kai sawaal ek card me)</option>
                    <option value="poster">🏆 Daily GK Quiz</option>
                    <option value="sahitya">🪶 Sahitya Parchment (2×2 grid)</option>
                    <option value="clean">📝 Classic (theme)</option>
                </select>
                <label id="listOptsWrap" class="hidden items-center gap-2 mt-2 text-xs text-slate-600 cursor-pointer">
                    <input type="checkbox" id="listOptions" class="accent-violet-600">
                    Options bhi dikhao <span class="text-slate-400">(kam sawaal fit honge)</span>
                </label>
            </div>
            <div id="headerWrap">
                <label class="block text-sm font-medium mb-1">📌 Header text</label>
                <input type="text" id="headerText" value="Daily GK Quiz" placeholder="Daily GK Quiz"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Watermark / Handle <span class="text-slate-400">(optional)</span></label>
                <input type="text" id="handle" placeholder="@yourpage"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="flex items-end">
                <button id="genBtn" class="w-full bg-violet-600 hover:bg-violet-700 text-white font-medium rounded-lg px-5 py-2.5 text-sm">🎯 Generate Quiz</button>
            </div>
        </div>
        <p id="msg" class="text-sm text-slate-500"></p>
    </div>

    {{-- PREVIEW --}}
    <div id="previewWrap" class="hidden mt-6">
        <div class="flex items-center justify-between mb-3 gap-3 flex-wrap">
            <h3 class="font-semibold"><span id="itemCount">0</span> question cards <span id="cardCount" class="hidden"></span></h3>
            <button id="saveBtn" class="bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg px-5 py-2.5 text-sm">✅ Save All Cards</button>
        </div>
        <div id="grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3"></div>
        <div id="progress" class="hidden mt-4">
            <div class="w-full bg-slate-200 rounded-full h-3"><div id="bar" class="bg-rose-600 h-3 rounded-full transition-all" style="width:0%"></div></div>
            <p id="progressText" class="text-sm text-slate-600 mt-2"></p>
        </div>
    </div>

    {{-- COLLECTIONS --}}
    <div class="mt-8">
        <h3 class="font-semibold mb-3">Saved quizzes</h3>
        @forelse ($collections as $c)
            <a href="{{ route('admin.quiz.show', $c) }}"
               class="flex items-center justify-between bg-white border border-slate-200 rounded-lg px-4 py-3 mb-2 hover:border-violet-300">
                <span class="font-medium">🎯 {{ $c->title }}</span>
                <span class="text-xs text-slate-400">{{ $c->parts_count }} cards · {{ ucfirst($c->status) }}</span>
            </a>
        @empty
            <p class="text-sm text-slate-500">Abhi koi quiz nahi — upar se pehla banao. 🎯</p>
        @endforelse
    </div>
</div>

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name=csrf-token]').content;
const GEN_URL  = @json(route('admin.quiz.generate'));
const SAVE_URL = @json(route('admin.quiz.save'));
const el = id => document.getElementById(id);
const W = 1080, H = 1920;

// Quiz logo (public/img/quiz-logo.png) — GK Quiz poster designs me use hota hai.
// File na ho to poster me ek simple drawn badge fallback aa jaata hai.
const QUIZ_LOGO = new Image();
let logoReady = false;
QUIZ_LOGO.onload = () => { logoReady = true; };
QUIZ_LOGO.src = @json(asset('img/quiz-logo.png'));
function ensureLogo(){ return new Promise(res => { if (QUIZ_LOGO.complete) { logoReady = QUIZ_LOGO.naturalWidth > 0; return res(); } QUIZ_LOGO.onload = () => { logoReady = true; res(); }; QUIZ_LOGO.onerror = () => res(); }); }

// ---------- Themes (Studio jaisa) ----------
const THEMES = {
    night:   { name: '🌙 Night Sky',   bg: ['#0b1224', '#1e293b'], text: '#f8fafc', accent: '#fbbf24', serif: true,  deco: 'stars'  },
    paper:   { name: '📜 Paper',       bg: ['#f6ecd4', '#e6d3ab'], text: '#3a2c19', accent: '#9a5b23', serif: true,  deco: 'border' },
    urdu:    { name: '🕌 Classic',     bg: ['#3a0d12', '#6d181c'], text: '#f6e7c8', accent: '#e7c15b', serif: true,  deco: 'quotes' },
    minimal: { name: '⚡ Minimal',     bg: ['#0f172a', '#0f172a'], text: '#ffffff', accent: '#38bdf8', serif: false, deco: 'line'   },
    sunset:  { name: '🌇 Sunset',      bg: ['#ff512f', '#dd2476'], text: '#fff7ed', accent: '#ffe08a', serif: true,  deco: 'glow'   },
    ocean:   { name: '🌊 Ocean',       bg: ['#2193b0', '#6dd5ed'], text: '#ffffff', accent: '#e0fbfc', serif: true,  deco: 'line'   },
    royal:   { name: '👑 Royal',       bg: ['#41295a', '#2f0743'], text: '#f3e8ff', accent: '#f0c65a', serif: true,  deco: 'quotes' },
    forest:  { name: '🌿 Forest',      bg: ['#0f2027', '#203a43'], text: '#eafff0', accent: '#a7e8bd', serif: true,  deco: 'corner' },
    neon:    { name: '💫 Neon',        bg: ['#0d0d0d', '#1a1a2e'], text: '#ffffff', accent: '#00f5d4', serif: false, deco: 'glow'   },
    gold:    { name: '✨ Black Gold',  bg: ['#0a0a0a', '#1c1c1c'], text: '#f7e7b4', accent: '#d4af37', serif: true,  deco: 'frame'  },
    parch:   { name: '🪶 Parchment',   bg: ['#f7ead0', '#e9d3a8'], text: '#2b1a0e', accent: '#c2410c', serif: true,  deco: 'border' },
};
Object.entries(THEMES).forEach(([k, t]) => { const o = document.createElement('option'); o.value = k; o.textContent = t.name; el('theme').appendChild(o); });
el('theme').value = 'night';

const EMOJI = '"Segoe UI Emoji","Noto Color Emoji","Apple Color Emoji"';
const serif = `"Noto Serif Devanagari","Noto Serif Gujarati",${EMOJI}`, sans = `"Noto Sans Devanagari","Noto Sans Gujarati",${EMOJI}`;

function hexRgb(h){ const n = parseInt(h.slice(1),16); return [(n>>16)&255,(n>>8)&255,n&255]; }
function lum(h){ const [r,g,b] = hexRgb(h); return 0.299*r + 0.587*g + 0.114*b; }
function roundRect(ctx,x,y,w,h,r){ ctx.beginPath(); if(ctx.roundRect){ctx.roundRect(x,y,w,h,r);return;} ctx.moveTo(x+r,y);ctx.arcTo(x+w,y,x+w,y+h,r);ctx.arcTo(x+w,y+h,x,y+h,r);ctx.arcTo(x,y+h,x,y,r);ctx.arcTo(x,y,x+w,y,r);ctx.closePath(); }
function wrap(ctx,text,maxW){ const out=[]; text.split(/\n/).forEach(p=>{ if(p.trim()===''){return;} const words=p.split(/\s+/); let line=''; words.forEach(w=>{ const t=line?line+' '+w:w; if(ctx.measureText(t).width>maxW&&line){out.push(line);line=w;}else line=t; }); if(line)out.push(line); }); return out.length?out:['']; }
function fitLines(ctx,text,maxW,maxH,fam,weight,maxSize){ let size=maxSize; while(size>24){ ctx.font=`${weight} ${size}px ${fam}`; const lines=wrap(ctx,text,maxW); const lh=size*1.4; if(lines.length*lh<=maxH) return {size,lines,lh}; size-=3; } ctx.font=`${weight} ${size}px ${fam}`; return {size,lines:wrap(ctx,text,maxW),lh:size*1.4}; }
function fitOne(ctx,text,maxW,fam,weight,maxSize){ let size=maxSize; ctx.font=`${weight} ${size}px ${fam}`; while(size>28&&ctx.measureText(text).width>maxW){ size-=2; ctx.font=`${weight} ${size}px ${fam}`; } return size; }

function drawDeco(ctx,t){
    ctx.save();
    if(t.deco==='stars'){ ctx.fillStyle='rgba(255,255,255,0.7)'; for(let i=0;i<50;i++){const x=((i*137)%W),y=((i*251)%(H*0.9));ctx.beginPath();ctx.arc(x,y,(i%3===0)?2.4:1.2,0,7);ctx.fill();} }
    else if(t.deco==='border'){ ctx.strokeStyle=t.accent;ctx.lineWidth=4; roundRect(ctx,55,55,W-110,H-110,26);ctx.stroke(); }
    else if(t.deco==='corner'){ ctx.fillStyle=t.accent;ctx.globalAlpha=0.22;[[110,150],[W-110,150],[110,H-160],[W-110,H-160]].forEach(([x,y])=>{ctx.beginPath();ctx.arc(x,y,55,0,7);ctx.fill();});ctx.globalAlpha=1; }
    else if(t.deco==='quotes'){ ctx.fillStyle=t.accent;ctx.globalAlpha=0.28;ctx.font='900 300px Georgia,serif';ctx.textAlign='left';ctx.textBaseline='top';ctx.fillText('“',60,90);ctx.globalAlpha=1; }
    else if(t.deco==='line'){ ctx.strokeStyle=t.accent;ctx.lineWidth=6;ctx.beginPath();ctx.moveTo(W/2-70,180);ctx.lineTo(W/2+70,180);ctx.stroke(); }
    else if(t.deco==='glow'){ const g=ctx.createRadialGradient(W/2,H*0.3,0,W/2,H*0.3,W*0.9);g.addColorStop(0,'rgba(255,255,255,0.15)');g.addColorStop(1,'rgba(255,255,255,0)');ctx.fillStyle=g;ctx.fillRect(0,0,W,H); }
    else if(t.deco==='frame'){ ctx.strokeStyle=t.accent;ctx.lineWidth=5;const m=80,len=100;[[m,m,1,1],[W-m,m,-1,1],[m,H-m,1,-1],[W-m,H-m,-1,-1]].forEach(([x,y,dx,dy])=>{ctx.beginPath();ctx.moveTo(x,y+dy*len);ctx.lineTo(x,y);ctx.lineTo(x+dx*len,y);ctx.stroke();}); }
    ctx.restore();
}

// Quiz bg — clean gradient + soft top glow
function bgAndDeco(ctx, t){
    const g = ctx.createLinearGradient(0, 0, W, H);
    g.addColorStop(0, t.bg[0]); g.addColorStop(1, t.bg[1]);
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    const gl = ctx.createRadialGradient(W/2, H*0.20, 0, W/2, H*0.20, W);
    gl.addColorStop(0, 'rgba(255,255,255,0.09)'); gl.addColorStop(1, 'rgba(255,255,255,0)');
    ctx.fillStyle = gl; ctx.fillRect(0, 0, W, H);
}

function quizTheme(themeKey){
    return THEMES[themeKey] || THEMES.night;
}
function handleAt(ctx, t, handle){ const hh=(handle||'').trim(); if(!hh)return; ctx.textAlign='center'; ctx.fillStyle=t.accent; ctx.globalAlpha=0.9; ctx.font=`600 34px ${sans}`; ctx.fillText(hh, W/2, H-95); ctx.globalAlpha=1; }

// ---------- Question card (exam-serious, clean) ----------
function renderQuestion(canvas, item, themeKey, handle, category) {
    const t = quizTheme(themeKey);
    canvas.width=W; canvas.height=H;
    const ctx = canvas.getContext('2d');
    const fam = t.serif ? serif : sans;
    const dark = lum(t.text) > 140;
    const panel = dark ? 'rgba(255,255,255,0.09)' : 'rgba(0,0,0,0.045)';
    const panelBd = dark ? 'rgba(255,255,255,0.22)' : 'rgba(0,0,0,0.12)';
    bgAndDeco(ctx, t);

    const pad = 90, maxW = W - pad*2;
    ctx.textAlign='left'; ctx.textBaseline='top';

    // Header label + topic (right) + divider
    let y = 100;
    ctx.fillStyle=t.accent; ctx.font=`800 44px ${sans}`;
    ctx.fillText('📝 QUIZ', pad, y);
    const topic = (category || item.category || '').trim();
    if (topic) {
        ctx.font=`700 34px ${sans}`;
        const tw = ctx.measureText(topic.toUpperCase()).width;
        const px = 26, ph = 52, bx = W - pad - tw - px*2, by = y - 3;
        roundRect(ctx, bx, by, tw + px*2, ph, ph/2);
        ctx.fillStyle = t.accent; ctx.globalAlpha = 0.16; ctx.fill(); ctx.globalAlpha = 1;
        roundRect(ctx, bx, by, tw + px*2, ph, ph/2); ctx.lineWidth = 2; ctx.strokeStyle = t.accent; ctx.stroke();
        ctx.fillStyle = t.accent; ctx.textBaseline = 'middle';
        ctx.fillText(topic.toUpperCase(), bx + px, by + ph/2 + 1);
        ctx.textBaseline = 'top';
    }
    y += 74;
    ctx.strokeStyle=panelBd; ctx.lineWidth=3;
    ctx.beginPath(); ctx.moveTo(pad, y); ctx.lineTo(W-pad, y); ctx.stroke();
    y += 120; // header aur question ke beech zyada space (question thoda neeche)

    // Question (left-aligned) — bold/mota
    const q = fitLines(ctx, item.question, maxW, H*0.26, fam, '900', 64);
    ctx.fillStyle=t.text; ctx.font=`900 ${q.size}px ${fam}`;
    q.lines.forEach(l => { ctx.fillText(l, pad, y); y += q.lh; });

    // Options — bache hue space me evenly bhar do
    const opts = (item.options||[]).slice(0,4);
    const n = opts.length || 4;
    const bottom = H - 220;               // prompt + handle ke upar tak
    const optTop = y + 60;
    const gap = 30;
    const boxH = Math.min(215, (bottom - optTop - (n-1)*gap) / n);
    let oy = optTop;
    opts.forEach((opt, i) => {
        ctx.save();
        ctx.shadowColor='rgba(0,0,0,0.26)'; ctx.shadowBlur=20; ctx.shadowOffsetY=7;
        roundRect(ctx, pad, oy, maxW, boxH, 24); ctx.fillStyle=panel; ctx.fill();
        ctx.restore();
        roundRect(ctx, pad, oy, maxW, boxH, 24); ctx.lineWidth=2; ctx.strokeStyle=panelBd; ctx.stroke();
        // letter badge
        const bs = Math.min(96, boxH-44), bx=pad+24, by=oy+(boxH-bs)/2;
        roundRect(ctx, bx, by, bs, bs, 16); ctx.fillStyle=t.accent; ctx.fill();
        ctx.fillStyle=dark?'#0b1224':'#ffffff'; ctx.textAlign='center'; ctx.textBaseline='middle';
        ctx.font=`800 ${Math.round(bs*0.5)}px ${sans}`; ctx.fillText(String.fromCharCode(65+i), bx+bs/2, by+bs/2+2);
        // option text
        const tx = bx+bs+34, tw = maxW-(bx+bs+34-pad)-36;
        const os = fitOne(ctx, opt, tw, fam, '700', 48);
        ctx.fillStyle=t.text; ctx.textAlign='left'; ctx.textBaseline='middle';
        ctx.font=`700 ${os}px ${fam}`; ctx.fillText(opt, tx, oy+boxH/2+2);
        oy += boxH + gap;
    });

    // Chhota prompt (emoji ke saath)
    ctx.textAlign='center'; ctx.textBaseline='middle';
    ctx.fillStyle=t.accent; ctx.font=`700 44px ${fam}`;
    ctx.fillText('🤔 सही जवाब सोचिए 👇', W/2, H - 170);

    handleAt(ctx, t, handle);
}

// ---------- Answer card (exam-serious, clean) ----------
function renderAnswer(canvas, item, themeKey, handle) {
    const t = quizTheme(themeKey);
    canvas.width=W; canvas.height=H;
    const ctx = canvas.getContext('2d');
    const fam = t.serif ? serif : sans;
    const dark = lum(t.text) > 140;
    const panelBd = dark ? 'rgba(255,255,255,0.22)' : 'rgba(0,0,0,0.12)';
    bgAndDeco(ctx, t);

    const pad = 90, maxW = W - pad*2;
    const ansIdx = (item.answer||'A').charCodeAt(0) - 65;
    const ansOpt = (item.options && item.options[ansIdx]) ? item.options[ansIdx] : '';
    const ansText = (item.answer||'A') + ')  ' + ansOpt;

    ctx.textAlign='left'; ctx.textBaseline='top';

    // Header label + divider
    let y = 100;
    ctx.fillStyle='#22c55e'; ctx.font=`800 44px ${sans}`;
    ctx.fillText('✅ ANSWER', pad, y);
    y += 74;
    ctx.strokeStyle='rgba(34,197,94,0.55)'; ctx.lineWidth=3;
    ctx.beginPath(); ctx.moveTo(pad, y); ctx.lineTo(W-pad, y); ctx.stroke();
    y += 54;

    // Question (context — muted)
    const q = fitLines(ctx, item.question, maxW, H*0.20, fam, '600', 46);
    ctx.fillStyle=t.text; ctx.globalAlpha=0.70; ctx.font=`600 ${q.size}px ${fam}`;
    q.lines.forEach(l => { ctx.fillText(l, pad, y); y += q.lh; });
    ctx.globalAlpha=1;
    y += 55;

    // Sahi jawab box (green)
    const a = fitLines(ctx, ansText, maxW - 70, H*0.20, fam, '700', 62);
    const boxH = 54 + a.lines.length*a.lh + 40;
    ctx.save(); ctx.shadowColor='rgba(0,0,0,0.24)'; ctx.shadowBlur=18; ctx.shadowOffsetY=6;
    roundRect(ctx, pad, y, maxW, boxH, 24); ctx.fillStyle='rgba(34,197,94,0.16)'; ctx.fill(); ctx.restore();
    roundRect(ctx, pad, y, maxW, boxH, 24); ctx.lineWidth=3; ctx.strokeStyle='#22c55e'; ctx.stroke();
    let ay = y + 30;
    ctx.fillStyle='#22c55e'; ctx.font=`700 32px ${sans}`; ctx.fillText('SAHI JAWAB', pad+34, ay);
    ay += 52;
    ctx.fillStyle=t.text; ctx.font=`700 ${a.size}px ${fam}`;
    a.lines.forEach(l => { ctx.fillText(l, pad+34, ay); ay += a.lh; });
    y += boxH + 55;

    // Reason
    if ((item.reason||'').trim() !== '') {
        const r = fitLines(ctx, '💡 ' + item.reason, maxW, (H-160) - y, fam, '500', 46);
        ctx.fillStyle=t.text; ctx.globalAlpha=0.9; ctx.font=`500 ${r.size}px ${fam}`;
        r.lines.forEach(l => { ctx.fillText(l, pad, y); y += r.lh; });
        ctx.globalAlpha=1;
    }

    handleAt(ctx, t, handle);
}

// ---------- "Simple" design ----------
// Saaf-suthra layout: upar sirf header text (jaise "Daily GK Quiz"), beech me
// question + options, aur neeche ek patli line wala simple footer. Koi emoji
// bhari sajawat nahi. Rang chune hue THEME se aate hain.

/**
 * Center-aligned letter-spaced text — header ko premium look deta hai.
 *
 * Devanagari/Gujarati par tracking NAHI lagti: har character alag draw karne se
 * matra aur conjunct tooot jaate hain ("વૈદિક" → "વ ૈ દ િ ક"). Aise text ko
 * seedha normal fillText se likhte hain.
 */
function trackedText(ctx, text, cx, y, spacing) {
    const align = ctx.textAlign;

    if (/[^\x00-\x7F]/.test(text)) {
        ctx.textAlign = 'center';
        ctx.fillText(text, cx, y);
        ctx.textAlign = align;
        return;
    }

    ctx.textAlign = 'left';
    const chars = [...text];
    const total = chars.reduce((s, ch) => s + ctx.measureText(ch).width, 0) + spacing * (chars.length - 1);
    let x = cx - total / 2;
    chars.forEach(ch => { ctx.fillText(ch, x, y); x += ctx.measureText(ch).width + spacing; });
    ctx.textAlign = align;
}

/** Simple design ka header — sirf text + patli accent line. Y (line ke neeche) return. */
function simpleHeader(ctx, t, headerText) {
    const title = (headerText || '').trim() || 'Daily GK Quiz';

    ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
    const size = fitOne(ctx, title, W - 260, sans, '800', 62);
    ctx.font = `800 ${size}px ${sans}`;
    ctx.fillStyle = t.text;
    trackedText(ctx, title.toUpperCase(), W / 2, 128, Math.round(size * 0.09));

    // Patli accent line — header ko baaki card se alag karti hai
    ctx.strokeStyle = t.accent; ctx.lineWidth = 5; ctx.lineCap = 'round';
    ctx.beginPath(); ctx.moveTo(W / 2 - 90, 188); ctx.lineTo(W / 2 + 90, 188); ctx.stroke();

    return 188;
}

/** Simple design ka footer — patli line, chhota CTA, handle. Footer ka top Y return. */
function simpleFooter(ctx, t, handle, language, cta) {
    const top = 1650;

    ctx.strokeStyle = t.text; ctx.globalAlpha = 0.18; ctx.lineWidth = 3;
    ctx.beginPath(); ctx.moveTo(90, top); ctx.lineTo(W - 90, top); ctx.stroke();
    ctx.globalAlpha = 1;

    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';

    if (cta) {
        const CTA = {
            hindi:    'अपना जवाब कमेंट करें — जवाब कैप्शन में',
            gujarati: 'તમારો જવાબ કોમેન્ટ કરો — જવાબ કૅપ્શનમાં',
            hinglish: 'Apna answer comment karein — answer caption me',
        };
        const txt = CTA[language] || CTA.hindi;
        const s = fitOne(ctx, txt, W - 200, sans, '600', 40);
        ctx.fillStyle = t.text; ctx.globalAlpha = 0.8; ctx.font = `600 ${s}px ${sans}`;
        ctx.fillText(txt, W / 2, top + 76);
        ctx.globalAlpha = 1;
    }

    const hh = (handle || '').trim();
    if (hh) {
        ctx.fillStyle = t.accent; ctx.font = `700 36px ${sans}`;
        ctx.fillText(hh, W / 2, cta ? top + 152 : top + 90);
    }

    return top;
}

function renderSimple(canvas, item, themeKey, handle, category, language, headerText) {
    const t = quizTheme(themeKey);
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');
    const dark = lum(t.text) > 140; // light text = dark background

    const g = ctx.createLinearGradient(0, 0, 0, H);
    g.addColorStop(0, t.bg[0]); g.addColorStop(1, t.bg[1]);
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);

    let y = simpleHeader(ctx, t, headerText) + 62;

    // Topic — sirf tab jab user ne diya ho
    const topic = (category || item.category || '').trim();
    if (topic !== '') {
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillStyle = t.accent; ctx.globalAlpha = 0.9; ctx.font = `700 34px ${sans}`;
        trackedText(ctx, topic.toUpperCase(), W / 2, y, 3);
        ctx.globalAlpha = 1;
        y += 66;
    }

    // ---- Question ----
    const pad = 84, maxW = W - pad * 2;
    ctx.textAlign = 'left'; ctx.textBaseline = 'top';
    const q = fitLines(ctx, item.question, maxW - 40, 430, sans, '700', 62);
    ctx.fillStyle = t.text; ctx.font = `700 ${q.size}px ${sans}`;
    let qy = y + 20;
    q.lines.forEach(l => { ctx.fillText(l, pad, qy); qy += q.lh; });

    // ---- Options ----
    const opts = (item.options || []).slice(0, 4);
    const n = opts.length || 4;
    const top = qy + 70, bottom = 1590, gap = 26;
    const boxH = Math.min(160, (bottom - top - (n - 1) * gap) / n);
    const blockH = n * boxH + (n - 1) * gap;
    let oy = top + Math.max(0, (bottom - top - blockH) / 2);

    opts.forEach((opt, i) => {
        roundRect(ctx, pad, oy, maxW, boxH, 18);
        ctx.fillStyle = dark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.05)';
        ctx.fill();
        ctx.lineWidth = 2.5;
        ctx.strokeStyle = dark ? 'rgba(255,255,255,0.22)' : 'rgba(0,0,0,0.16)';
        roundRect(ctx, pad, oy, maxW, boxH, 18); ctx.stroke();

        // A / B / C / D
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillStyle = t.accent; ctx.font = `800 ${Math.round(boxH * 0.42)}px ${sans}`;
        ctx.fillText(String.fromCharCode(65 + i), pad + 58, oy + boxH / 2 + 2);

        // Option text
        const tx = pad + 116;
        const os = fitOne(ctx, opt, maxW - (tx - pad) - 40, sans, '600', 46);
        ctx.fillStyle = t.text; ctx.textAlign = 'left'; ctx.font = `600 ${os}px ${sans}`;
        ctx.fillText(opt, tx, oy + boxH / 2 + 2);

        oy += boxH + gap;
    });

    simpleFooter(ctx, t, handle, language, true);
}

/** Simple design ka answer card — reel me countdown ke baad yahi dikhta hai. */
function renderSimpleAnswer(canvas, item, themeKey, handle, category, language, headerText) {
    const t = quizTheme(themeKey);
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');
    const dark = lum(t.text) > 140;
    const GREEN = '#22c55e';

    const g = ctx.createLinearGradient(0, 0, 0, H);
    g.addColorStop(0, t.bg[0]); g.addColorStop(1, t.bg[1]);
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);

    let y = simpleHeader(ctx, t, headerText) + 70;

    const pad = 84, maxW = W - pad * 2;
    const ansIdx = (item.answer || 'A').charCodeAt(0) - 65;
    const ansOpt = (item.options && item.options[ansIdx]) ? item.options[ansIdx] : '';

    // Question — halka (context ke liye)
    ctx.textAlign = 'left'; ctx.textBaseline = 'top';
    const q = fitLines(ctx, item.question, maxW, 300, sans, '600', 48);
    ctx.fillStyle = t.text; ctx.globalAlpha = 0.65; ctx.font = `600 ${q.size}px ${sans}`;
    q.lines.forEach(l => { ctx.fillText(l, pad, y); y += q.lh; });
    ctx.globalAlpha = 1;

    // Answer block ko question ke neeche bache hue space me center karo
    const reasonLines = (item.reason || '').trim() !== ''
        ? fitLines(ctx, item.reason, maxW, 400, sans, '500', 44) : null;
    const aPre = fitLines(ctx, (item.answer || 'A') + ')  ' + ansOpt, maxW - 80, 300, sans, '800', 66);
    const blockH = (156 + aPre.lines.length * aPre.lh)
        + (reasonLines ? 56 + reasonLines.lines.length * reasonLines.lh : 0);
    y = Math.max(y + 66, y + (1590 - y - blockH) / 2);

    // ---- Sahi jawab (green box) ----
    const a = fitLines(ctx, (item.answer || 'A') + ')  ' + ansOpt, maxW - 80, 300, sans, '800', 66);
    const boxH = 156 + a.lines.length * a.lh;
    roundRect(ctx, pad, y, maxW, boxH, 22);
    ctx.fillStyle = dark ? 'rgba(34,197,94,0.16)' : 'rgba(34,197,94,0.14)';
    ctx.fill();
    ctx.lineWidth = 3; ctx.strokeStyle = GREEN; roundRect(ctx, pad, y, maxW, boxH, 22); ctx.stroke();

    ctx.fillStyle = GREEN; ctx.font = `700 32px ${sans}`;
    trackedText2(ctx, 'SAHI JAWAB', pad + 40, y + 40, 3);

    ctx.fillStyle = t.text; ctx.font = `800 ${a.size}px ${sans}`;
    let ay = y + 112;
    a.lines.forEach(l => { ctx.fillText(l, pad + 40, ay); ay += a.lh; });
    y += boxH + 56;

    // ---- Reason ----
    if ((item.reason || '').trim() !== '') {
        const r = fitLines(ctx, item.reason, maxW, 1600 - y, sans, '500', 44);
        ctx.fillStyle = t.text; ctx.globalAlpha = 0.85; ctx.font = `500 ${r.size}px ${sans}`;
        r.lines.forEach(l => { ctx.fillText(l, pad, y); y += r.lh; });
        ctx.globalAlpha = 1;
    }

    simpleFooter(ctx, t, handle, language, false);
}

/** Left-aligned letter spacing (trackedText center-aligned hai). */
function trackedText2(ctx, text, x, y, spacing) {
    ctx.textAlign = 'left'; ctx.textBaseline = 'top';
    // Indic text par tracking matra/conjunct tod deti hai — waise hi likho
    if (/[^\x00-\x7F]/.test(text)) { ctx.fillText(text, x, y); return; }
    [...text].forEach(ch => { ctx.fillText(ch, x, y); x += ctx.measureText(ch).width + spacing; });
}

// ---------- "Q&A List" design ----------
// Ek hi card me kai sawaal + unke jawab (revision/study post jaisa). Kitne
// sawaal aayenge ye fix nahi — font ghata-badha kar jitne padhne layak fit
// hote hain utne. Bache hue sawaal agle card par chale jaate hain.

const LIST_ZONE_TOP = 300;     // header/topic ke neeche se content shuru
const LIST_ZONE_BOTTOM = 1630; // footer line se thoda upar
const LIST_FONT_MAX = 44;
const LIST_FONT_MIN = 24;      // isse chhota = reel me padha nahi jaata
const LIST_FONT_COMFORT = 34;  // "kitne sawaal mangwane hain" isi size par tay hota hai

/**
 * Diye gaye sawaalon ka layout ek font size par naapo.
 * Render aur measurement dono yahi function use karte hain — warna preview aur
 * asli card ka layout alag ho jaata hai.
 */
function listLayout(ctx, quizItems, fs, showOptions, maxW) {
    const qSize = fs, aSize = Math.round(fs * 0.92), oSize = Math.round(fs * 0.82);
    const blocks = [];
    let total = 0;

    quizItems.forEach((item, i) => {
        ctx.font = `700 ${qSize}px ${sans}`;
        const qLines = wrap(ctx, (i + 1) + '. ' + item.question, maxW - 20);

        let oLines = [];
        if (showOptions) {
            ctx.font = `500 ${oSize}px ${sans}`;
            const opts = (item.options || []).slice(0, 4)
                .map((o, k) => String.fromCharCode(65 + k) + ') ' + o).join('   ');
            oLines = opts.trim() === '' ? [] : wrap(ctx, opts, maxW - 40);
        }

        const ansIdx = (item.answer || 'A').charCodeAt(0) - 65;
        const ansOpt = (item.options && item.options[ansIdx]) ? item.options[ansIdx] : '';
        ctx.font = `800 ${aSize}px ${sans}`;
        const aLines = wrap(ctx, '✅ ' + (item.answer || 'A') + ') ' + ansOpt, maxW - 40);

        const h = qLines.length * qSize * 1.34
            + (oLines.length ? 8 + oLines.length * oSize * 1.34 : 0)
            + 10 + aLines.length * aSize * 1.34;

        blocks.push({ qLines, oLines, aLines, h, qSize, aSize, oSize });
        total += h + Math.round(fs * 0.62); // do sawaalon ke beech gap
    });

    return { blocks, total };
}

/**
 * Sawaalon ko cards me baanto — har card me utne hi jitne padhne layak font par
 * fit ho jaayein. Return: [{items, fs}, ...]
 */
/**
 * Comfort font par ek card me kitne sawaal aaram se aate hain.
 * Isi se tay hota hai ki AI se kitne sawaal mangwane hain — warna zyada mangwa
 * kar cards minimum font (24) par thuns jaate hain.
 */
function itemsPerCard(quizItems, showOptions) {
    const ctx = document.createElement('canvas').getContext('2d');
    const maxW = W - 84 * 2;
    const zone = LIST_ZONE_BOTTOM - LIST_ZONE_TOP;

    let n = 0;
    for (let k = 1; k <= quizItems.length; k++) {
        if (listLayout(ctx, quizItems.slice(0, k), LIST_FONT_COMFORT, showOptions, maxW).total > zone) break;
        n = k;
    }

    return Math.max(1, n);
}

/** Ek array ko `parts` barabar hisson me baanto (bache hue items shuru ke hisson me). */
function splitEvenly(arr, parts) {
    const out = [];
    const base = Math.floor(arr.length / parts);
    let rem = arr.length % parts, i = 0;

    for (let p = 0; p < parts; p++) {
        const n = base + (rem > 0 ? 1 : 0);
        if (rem > 0) rem--;
        if (n > 0) out.push(arr.slice(i, i + n));
        i += n;
    }

    return out;
}

function packQuizPages(quizItems, showOptions, targetPages) {
    const ctx = document.createElement('canvas').getContext('2d');
    const maxW = W - 84 * 2;
    const zone = LIST_ZONE_BOTTOM - LIST_ZONE_TOP;

    // In sawaalon ke liye sabse bada font jo poore card me fit ho jaaye
    const bestFont = (group) => {
        for (let s = LIST_FONT_MAX; s > LIST_FONT_MIN; s -= 2) {
            if (listLayout(ctx, group, s, showOptions, maxW).total <= zone) return s;
        }
        return LIST_FONT_MIN;
    };
    const fits = (group) => listLayout(ctx, group, LIST_FONT_MIN, showOptions, maxW).total <= zone;

    // User ne cards ki ginti maangi hai (Q&A List) — bas utne hisson me baant do
    if (targetPages > 0 && quizItems.length) {
        const groups = splitEvenly(quizItems, targetPages);
        if (groups.every(fits)) {
            return groups.map(group => ({ items: group, fs: bestFont(group) }));
        }
        // Na fit ho to neeche greedy chalega (cards zyada ban jaayenge)
    }

    // 1) Greedy — har card me jitne minimum font par fit ho sakein
    const greedy = [];
    let pending = quizItems.slice();
    while (pending.length) {
        let take = pending.length;
        while (take > 1 && !fits(pending.slice(0, take))) take--;
        greedy.push(pending.slice(0, take));
        pending = pending.slice(take);
    }

    // 2) Barabar baanto. Greedy akela chhod de to aakhri card par 1-2 sawaal
    //    reh jaate hain (16 sawaal → 15 + 1) aur pehla card font 24 par ghut
    //    jaata hai. Utne hi cards me barabar baantne se font bada rehta hai.
    if (greedy.length > 1) {
        const per = Math.ceil(quizItems.length / greedy.length);
        const balanced = [];
        for (let i = 0; i < quizItems.length; i += per) balanced.push(quizItems.slice(i, i + per));

        if (balanced.length === greedy.length && balanced.every(fits)) {
            return balanced.map(group => ({ items: group, fs: bestFont(group) }));
        }
    }

    return greedy.map(group => ({ items: group, fs: bestFont(group) }));
}

function renderList(canvas, page, themeKey, handle, category, language, headerText, showOptions, pageNo, totalPages) {
    const t = quizTheme(themeKey);
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');
    const dark = lum(t.text) > 140;
    const GREEN = dark ? '#4ade80' : '#15803d';

    const g = ctx.createLinearGradient(0, 0, 0, H);
    g.addColorStop(0, t.bg[0]); g.addColorStop(1, t.bg[1]);
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);

    simpleHeader(ctx, t, headerText);

    // Topic + page number (kai cards hon to "1/3")
    const topic = (category || '').trim();
    const label = [topic.toUpperCase(), totalPages > 1 ? `${pageNo}/${totalPages}` : ''].filter(Boolean).join('  •  ');
    if (label !== '') {
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillStyle = t.accent; ctx.globalAlpha = 0.9; ctx.font = `700 34px ${sans}`;
        trackedText(ctx, label, W / 2, 250, 3);
        ctx.globalAlpha = 1;
    }

    const pad = 84, maxW = W - pad * 2;
    const { blocks } = listLayout(ctx, page.items, page.fs, showOptions, maxW);

    ctx.textAlign = 'left'; ctx.textBaseline = 'top';
    let y = LIST_ZONE_TOP;

    blocks.forEach(b => {
        // Sawaal
        ctx.fillStyle = t.text; ctx.font = `700 ${b.qSize}px ${sans}`;
        b.qLines.forEach(l => { ctx.fillText(l, pad, y); y += b.qSize * 1.34; });

        // Options (optional)
        if (b.oLines.length) {
            y += 8;
            ctx.fillStyle = t.text; ctx.globalAlpha = 0.62; ctx.font = `500 ${b.oSize}px ${sans}`;
            b.oLines.forEach(l => { ctx.fillText(l, pad + 30, y); y += b.oSize * 1.34; });
            ctx.globalAlpha = 1;
        }

        // Jawab
        y += 10;
        ctx.fillStyle = GREEN; ctx.font = `800 ${b.aSize}px ${sans}`;
        b.aLines.forEach(l => { ctx.fillText(l, pad + 30, y); y += b.aSize * 1.34; });

        y += Math.round(page.fs * 0.62);
    });

    simpleFooter(ctx, t, handle, language, false);
}

/** Ek list-card ka text (voice + caption ke liye). */
function listText(page) {
    return page.items.map((item, i) => {
        const ansIdx = (item.answer || 'A').charCodeAt(0) - 65;
        const ansOpt = (item.options && item.options[ansIdx]) ? item.options[ansIdx] : '';
        return (i + 1) + '. ' + item.question + '\n✅ ' + (item.answer || 'A') + ') ' + ansOpt;
    }).join('\n\n');
}

/** Page ke saare items ke hashtags mila do (dedup, max 30 — Instagram limit). */
function listHashtags(page) {
    const seen = new Set(), out = [];
    page.items.forEach(item => {
        (item.hashtags || '').split(/\s+/).forEach(tag => {
            const k = tag.toLowerCase();
            if (tag.startsWith('#') && !seen.has(k) && out.length < 30) { seen.add(k); out.push(tag); }
        });
    });
    return out.join(' ');
}

// ---------- "Daily Quiz" poster style (navy + yellow, illustrated look) ----------
function pdot(ctx, x, y, r, color){ ctx.fillStyle = color; ctx.beginPath(); ctx.arc(x, y, r, 0, 7); ctx.fill(); }

function renderQuestionPoster(canvas, item, handle, category, language) {
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');
    const NAVY = '#152a54', NAVY2 = '#1d3a72', YELLOW = '#f9c21a', BG = '#eef1f6';

    // Background
    ctx.fillStyle = BG; ctx.fillRect(0, 0, W, H);

    // ---- Top navy band (wavy bottom) ----
    ctx.fillStyle = NAVY;
    ctx.beginPath();
    ctx.moveTo(0, 0); ctx.lineTo(W, 0); ctx.lineTo(W, 380);
    ctx.quadraticCurveTo(W * 0.72, 460, W * 0.5, 410);
    ctx.quadraticCurveTo(W * 0.24, 360, 0, 440);
    ctx.closePath(); ctx.fill();

    // dot grid (top-left)
    for (let r = 0; r < 4; r++) for (let c = 0; c < 5; c++) pdot(ctx, 52 + c * 26, 48 + r * 26, 4, 'rgba(249,194,26,0.85)');

    // side emojis
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.font = `92px ${EMOJI}`; ctx.fillText('📣', 150, 205);
    ctx.font = `96px ${EMOJI}`; ctx.fillText('💡', 905, 175);

    // "DAILY" pill
    ctx.font = `800 52px ${sans}`;
    const dW = ctx.measureText('DAILY').width;
    roundRect(ctx, W / 2 - dW / 2 - 28, 66, dW + 56, 68, 16); ctx.fillStyle = YELLOW; ctx.fill();
    ctx.fillStyle = NAVY; ctx.fillText('DAILY', W / 2, 101);

    // "QUIZ" speech bubble (auto-fit width)
    const bigTxt = 'QUIZ';
    const bigSize = fitOne(ctx, bigTxt, W - 210, sans, '900', 156);
    ctx.font = `900 ${bigSize}px ${sans}`;
    const qW = ctx.measureText(bigTxt).width;
    const bx = W / 2 - qW / 2 - 44, bw = qW + 88, by = 158, bh = 188;
    roundRect(ctx, bx, by, bw, bh, 30); ctx.fillStyle = NAVY2; ctx.fill();
    ctx.lineWidth = 6; ctx.strokeStyle = YELLOW; roundRect(ctx, bx, by, bw, bh, 30); ctx.stroke();
    ctx.fillStyle = NAVY2; ctx.beginPath(); // tail
    ctx.moveTo(bx + bw * 0.42, by + bh - 6); ctx.lineTo(bx + bw * 0.58, by + bh - 6); ctx.lineTo(bx + bw * 0.46, by + bh + 42); ctx.closePath(); ctx.fill();
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillStyle = '#ffffff'; ctx.fillText(bigTxt, W / 2, by + bh / 2 + 8);

    // "Test Your Knowledge"
    ctx.fillStyle = NAVY; ctx.font = `800 46px ${sans}`;
    ctx.fillText('Test Your Knowledge', W / 2, 500);
    const tkW = ctx.measureText('Test Your Knowledge').width;
    ctx.strokeStyle = YELLOW; ctx.lineWidth = 6; ctx.lineCap = 'round';
    [-1, 1].forEach(s => { const x0 = W / 2 + s * (tkW / 2 + 26); for (let k = 0; k < 3; k++) { ctx.beginPath(); ctx.moveTo(x0 + s * k * 16, 486); ctx.lineTo(x0 + s * (k * 16 + 10), 514); ctx.stroke(); } });

    // Topic badge — jo topic/exam select kiya wahi (fallback: QUESTION)
    const topic = ((category || item.category || '').trim().toUpperCase()) || 'QUESTION';
    const tpSize = fitOne(ctx, topic, W - 260, sans, '800', 40);
    ctx.font = `800 ${tpSize}px ${sans}`;
    const quW = ctx.measureText(topic).width;
    roundRect(ctx, W / 2 - quW / 2 - 34, 556, quW + 68, 66, 33); ctx.fillStyle = NAVY; ctx.fill();
    ctx.fillStyle = YELLOW; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(topic, W / 2, 590);

    // ---- Question card ----
    const cardX = 54, cardW = W - 108, cardY = 672;
    ctx.textAlign = 'left'; ctx.textBaseline = 'top';
    const qfit = fitLines(ctx, item.question, cardW - 240, 340, sans, '800', 58);
    const cardH = Math.max(230, 120 + qfit.lines.length * qfit.lh);
    ctx.save(); ctx.shadowColor = 'rgba(20,40,80,0.16)'; ctx.shadowBlur = 30; ctx.shadowOffsetY = 12;
    roundRect(ctx, cardX, cardY, cardW, cardH, 34); ctx.fillStyle = '#ffffff'; ctx.fill(); ctx.restore();
    // faded "?" watermark
    ctx.fillStyle = 'rgba(21,42,84,0.06)'; ctx.font = `900 300px ${sans}`; ctx.textAlign = 'right'; ctx.textBaseline = 'alphabetic';
    ctx.fillText('?', cardX + cardW - 40, cardY + cardH - 34);
    // Q. badge
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    pdot(ctx, cardX + 96, cardY + 92, 56, NAVY);
    ctx.fillStyle = YELLOW; ctx.font = `900 44px ${sans}`; ctx.fillText('Q.', cardX + 98, cardY + 94);
    // question text
    ctx.textAlign = 'left'; ctx.textBaseline = 'top'; ctx.fillStyle = NAVY; ctx.font = `800 ${qfit.size}px ${sans}`;
    let qy = cardY + 48; const qx = cardX + 180;
    qfit.lines.forEach(l => { ctx.fillText(l, qx, qy); qy += qfit.lh; });

    // ---- Options (answer NOT revealed) ----
    const opts = (item.options || []).slice(0, 4); const n = opts.length || 4;
    const optTop = cardY + cardH + 42, zoneBottom = 1520, gap = 26;
    const boxH = Math.min(150, (zoneBottom - optTop - (n - 1) * gap) / n);
    let oy = optTop;
    opts.forEach((opt, i) => {
        ctx.save(); ctx.shadowColor = 'rgba(20,40,80,0.12)'; ctx.shadowBlur = 16; ctx.shadowOffsetY = 6;
        roundRect(ctx, cardX, oy, cardW, boxH, boxH / 2); ctx.fillStyle = '#ffffff'; ctx.fill(); ctx.restore();
        roundRect(ctx, cardX, oy, cardW, boxH, boxH / 2); ctx.lineWidth = 3; ctx.strokeStyle = NAVY; ctx.stroke();
        const bs = boxH - 28, lbx = cardX + 16, lby = oy + 14;
        pdot(ctx, lbx + bs / 2, lby + bs / 2, bs / 2, NAVY);
        ctx.fillStyle = '#ffffff'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.font = `800 ${Math.round(bs * 0.44)}px ${sans}`;
        ctx.fillText(String.fromCharCode(65 + i), lbx + bs / 2, lby + bs / 2 + 2);
        const tx = lbx + bs + 34, tmaxW = cardW - (tx - cardX) - 44;
        const os = fitOne(ctx, opt, tmaxW, sans, '700', 46);
        ctx.fillStyle = NAVY; ctx.textAlign = 'left'; ctx.textBaseline = 'middle'; ctx.font = `700 ${os}px ${sans}`;
        ctx.fillText(opt, tx, oy + boxH / 2 + 2);
        oy += boxH + gap;
    });

    // ---- Bottom navy band (wavy top) ----
    ctx.fillStyle = NAVY;
    ctx.beginPath();
    ctx.moveTo(0, 1706); ctx.quadraticCurveTo(W * 0.26, 1662, W * 0.5, 1704);
    ctx.quadraticCurveTo(W * 0.76, 1744, W, 1690);
    ctx.lineTo(W, H); ctx.lineTo(0, H); ctx.closePath(); ctx.fill();

    // ---- Ribbon: apna jawab comment karo, jawab caption me (selected language) ----
    const CTA = {
        hindi:    'अपना जवाब कमेंट करें 👇 जवाब कैप्शन में',
        gujarati: 'તમારો જવાબ કોમેન્ટ કરો 👇 જવાબ કૅપ્શનમાં',
        hinglish: 'Apna answer comment karein 👇 answer caption me',
    };
    const rtxt = CTA[language] || CTA.hindi;
    const rx = cardX, ry = 1548, rW = cardW, rh = 104;
    roundRect(ctx, rx, ry, rW, rh, 20); ctx.fillStyle = NAVY; ctx.fill();
    ctx.lineWidth = 4; ctx.strokeStyle = YELLOW; roundRect(ctx, rx, ry, rW, rh, 20); ctx.stroke();
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    const rSize = fitOne(ctx, rtxt, rW - 70, sans, '700', 44);
    ctx.fillStyle = '#ffffff'; ctx.font = `700 ${rSize}px ${sans}`;
    ctx.fillText(rtxt, rx + rW / 2, ry + rh / 2 + 2);

    // ---- Footer feature strip ----
    const feats = [['📚', 'सरकारी परीक्षा', 'की तैयारी करें'], ['🎯', 'अपना ज्ञान', 'बढ़ाएं'], ['🏆', 'रोज़ाना क्विज़', 'खेलें'], ['🏅', 'सफलता की ओर', 'एक कदम']];
    const fw = W / 4, fy = 1812;
    feats.forEach((f, i) => {
        const cx = fw * i + 30;
        ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
        ctx.font = `46px ${EMOJI}`; ctx.fillText(f[0], cx, fy + 26);
        ctx.fillStyle = '#ffffff'; ctx.font = `600 25px ${sans}`;
        ctx.fillText(f[1], cx + 58, fy + 8); ctx.fillText(f[2], cx + 58, fy + 44);
    });

    // optional handle (bottom)
    const hh = (handle || '').trim();
    if (hh) { ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillStyle = YELLOW; ctx.globalAlpha = 0.9; ctx.font = `600 26px ${sans}`; ctx.fillText(hh, W / 2, 1900); ctx.globalAlpha = 1; }
}

// ---------- GK Quiz logo posters (aapke logo ke saath, alag-alag colors) ----------
// Logo image ka center-square crop draw karta hai (badge circle isolate ho jaata
// hai). File na mile to ek drawn badge fallback.
// Text ko circle ke arc par likho (top arc ke liye centerAngle = -PI/2)
function arcText(ctx, text, cx, cy, radius, centerAngle, fontPx, color){
    ctx.save();
    ctx.fillStyle = color; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.font = `800 ${Math.round(fontPx)}px ${sans}`;
    const chars = [...text];
    const widths = chars.map(ch => ctx.measureText(ch).width + fontPx * 0.06);
    const totalAng = widths.reduce((a, b) => a + b, 0) / radius;
    let ang = centerAngle - totalAng / 2;
    chars.forEach((ch, i) => {
        ang += (widths[i] / 2) / radius;
        ctx.save();
        ctx.translate(cx + Math.cos(ang) * radius, cy + Math.sin(ang) * radius);
        ctx.rotate(ang + Math.PI / 2);
        ctx.fillText(ch, 0, 0);
        ctx.restore();
        ang += (widths[i] / 2) / radius;
    });
    ctx.restore();
}

// GK QUIZ DAILY badge — agar quiz-logo.png ho to wo, warna code se draw (KBC-style)
function drawLogo(ctx, cx, top, box){
    if (logoReady && QUIZ_LOGO.naturalWidth > 0){
        const s = Math.min(QUIZ_LOGO.naturalWidth, QUIZ_LOGO.naturalHeight);
        const sx = (QUIZ_LOGO.naturalWidth - s) / 2, sy = (QUIZ_LOGO.naturalHeight - s) / 2;
        ctx.drawImage(QUIZ_LOGO, sx, sy, s, s, cx - box / 2, top, box, box);
        return box;
    }

    // ---- Drawn badge (koi image file ki zaroorat nahi) ----
    const R = box / 2, cy = top + R;
    // soft glow
    ctx.save();
    const gl = ctx.createRadialGradient(cx, cy, R * 0.6, cx, cy, R * 1.15);
    gl.addColorStop(0, 'rgba(249,194,26,0.28)'); gl.addColorStop(1, 'rgba(249,194,26,0)');
    ctx.fillStyle = gl; ctx.beginPath(); ctx.arc(cx, cy, R * 1.15, 0, 7); ctx.fill();
    ctx.restore();
    // navy disc
    pdot(ctx, cx, cy, R, '#12315e');
    // gold double ring
    ctx.lineWidth = box * 0.032; ctx.strokeStyle = '#f9c21a';
    ctx.beginPath(); ctx.arc(cx, cy, R - box * 0.045, 0, 7); ctx.stroke();
    ctx.lineWidth = box * 0.010; ctx.strokeStyle = 'rgba(249,194,26,0.55)';
    ctx.beginPath(); ctx.arc(cx, cy, R - box * 0.115, 0, 7); ctx.stroke();

    // centre lightbulb (idea)
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.font = `${Math.round(box * 0.36)}px ${EMOJI}`;
    ctx.fillText('💡', cx, cy - box * 0.03);

    // check (sahi) + question (sawal) badges
    ctx.font = `${Math.round(box * 0.135)}px ${EMOJI}`;
    ctx.fillText('✅', cx - box * 0.205, cy + box * 0.205);
    ctx.fillText('❓', cx + box * 0.205, cy + box * 0.205);

    // curved top "GK QUIZ"
    arcText(ctx, 'GK QUIZ', cx, cy, R - box * 0.135, -Math.PI / 2, box * 0.125, '#ffffff');
    // bottom "DAILY"
    ctx.font = `800 ${Math.round(box * 0.088)}px ${sans}`; ctx.fillStyle = '#ffffff';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.save();
    // subtle wide letter-spacing feel via manual draw
    ctx.fillText('D A I L Y', cx, cy + R * 0.72);
    ctx.restore();
    return box;
}

// ---------- "Sahitya" parchment poster ----------
// Purane kaagaz jaisa cream background, upar suraj-kirno wala badge + logo,
// beech me bada sajawati title aur sawaal, aur options 4 vertical box ke bajaye
// 2×2 grid me (har ek par gol A/B/C/D badge). Culture/literature quiz ke liye.
//
// Is design me photo background NAHI lagta — parchment ka poora look photo ke
// upar bikhar jaata hai. Isliye item.bg yahan jaan-boojh kar ignore hota hai.
const SAH = {
    bg1:    '#f7ead0',   // parchment — upar
    bg2:    '#e9d3a8',   // parchment — neeche
    title1: '#7a3b12',   // title gradient
    title2: '#c2410c',
    body:   '#1f2937',   // sawaal ka text
    opt:    '#8e1b1b',   // option ka maroon text
    badge:  '#123a63',   // A/B/C/D ka gol badge
    accent: '#d2691e',   // option border / flourish
    pill:   '#fffaf0',   // option ke andar ka rang
    ok:     '#15803d',   // sahi jawab
    okBg:   '#e7f8ec',
};

/** Purana kaagaz — halki tirchhi lakeeren + kinaron par gehra vignette. */
function sahBackground(ctx) {
    const g = ctx.createLinearGradient(0, 0, W, H);
    g.addColorStop(0, SAH.bg1); g.addColorStop(0.5, '#f2e0bd'); g.addColorStop(1, SAH.bg2);
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);

    // Likhawat jaisi texture. Random nahi — fixed formula, taaki question aur
    // answer card ka background bilkul ek jaisa bane (reel me jhilmilata nahi).
    ctx.save();
    ctx.strokeStyle = 'rgba(120,85,40,0.07)'; ctx.lineWidth = 2;
    for (let i = 0; i < 110; i++) {
        const y = (i * 197) % H, x = (i * 313) % W, len = 120 + (i * 37) % 280;
        ctx.beginPath(); ctx.moveTo(x, y);
        ctx.quadraticCurveTo(x + len / 2, y - 14, x + len, y);
        ctx.stroke();
    }
    ctx.restore();

    const v = ctx.createRadialGradient(W / 2, H / 2, H * 0.30, W / 2, H / 2, H * 0.74);
    v.addColorStop(0, 'rgba(140,100,50,0)'); v.addColorStop(1, 'rgba(140,100,50,0.30)');
    ctx.fillStyle = v; ctx.fillRect(0, 0, W, H);
}

/** Top-left badge (jaise "Soulful Saturday") — peechhe suraj ki kirnein. */
function sahBadge(ctx, text) {
    const words = (text || '').trim().split(/\s+/).filter(Boolean);
    if (!words.length) return;
    const half = Math.ceil(words.length / 2);
    const l1 = words.length > 1 ? words.slice(0, half).join(' ') : words[0];
    const l2 = words.length > 1 ? words.slice(half).join(' ') : '';

    const cx = 182, cy = 138;
    ctx.save();
    ctx.strokeStyle = 'rgba(226,113,29,0.5)'; ctx.lineWidth = 8; ctx.lineCap = 'round';
    for (let i = 0; i < 12; i++) {
        const a = (i * Math.PI) / 6;
        ctx.beginPath();
        ctx.moveTo(cx + Math.cos(a) * 112, cy + Math.sin(a) * 100);
        ctx.lineTo(cx + Math.cos(a) * 152, cy + Math.sin(a) * 136);
        ctx.stroke();
    }
    ctx.restore();

    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    const fs = Math.min(fitOne(ctx, l1, 250, sans, '800', 38), l2 ? fitOne(ctx, l2, 250, sans, '800', 38) : 38);
    ctx.font = `800 ${fs}px ${sans}`;
    const bw = Math.max(ctx.measureText(l1).width, l2 ? ctx.measureText(l2).width : 0) + 68;
    const bh = l2 ? 122 : 80;

    const g = ctx.createLinearGradient(cx - bw / 2, cy - bh / 2, cx + bw / 2, cy + bh / 2);
    g.addColorStop(0, '#f5a623'); g.addColorStop(1, '#e2711d');
    roundRect(ctx, cx - bw / 2, cy - bh / 2, bw, bh, 26); ctx.fillStyle = g; ctx.fill();
    ctx.lineWidth = 5; ctx.strokeStyle = '#fff3d6';
    roundRect(ctx, cx - bw / 2, cy - bh / 2, bw, bh, 26); ctx.stroke();

    ctx.fillStyle = '#ffffff';
    if (l2) { ctx.fillText(l1, cx, cy - 28); ctx.fillText(l2, cx, cy + 28); }
    else ctx.fillText(l1, cx, cy);
}

/** Top-right logo — sirf tab jab img/quiz-logo.png maujood ho. */
function sahLogo(ctx) {
    if (!logoReady || !QUIZ_LOGO.naturalWidth) return;
    const box = 150;
    const s = Math.min(box / QUIZ_LOGO.naturalWidth, box / QUIZ_LOGO.naturalHeight);
    const w = QUIZ_LOGO.naturalWidth * s, h = QUIZ_LOGO.naturalHeight * s;
    ctx.drawImage(QUIZ_LOGO, W - 60 - w, 62, w, h);
}

/** Bada sajawati title (topic) — 2 line tak, neeche sunehri flourish. Bottom Y return. */
function sahTitle(ctx, topic, top) {
    const txt = (topic || '').trim();
    if (txt === '') return top;

    const fit = fitLines(ctx, txt, W - 320, 300, serif, '800', 112);
    const lines = fit.lines.slice(0, 2);
    ctx.textAlign = 'center'; ctx.textBaseline = 'top';
    ctx.font = `800 ${fit.size}px ${serif}`;

    let y = top;
    lines.forEach(l => {
        const g = ctx.createLinearGradient(0, y, 0, y + fit.size);
        g.addColorStop(0, SAH.title1); g.addColorStop(1, SAH.title2);
        ctx.save();
        ctx.shadowColor = 'rgba(255,250,240,0.9)'; ctx.shadowOffsetY = 3; ctx.shadowBlur = 2;
        ctx.fillStyle = g; ctx.fillText(l, W / 2, y);
        ctx.restore();
        y += fit.lh;
    });

    // Quill — title ke dayein kinare par
    ctx.font = `78px ${EMOJI}`; ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
    ctx.fillText('🪶', W - 176, top + fit.size * 0.55);

    // Flourish: patli line + beech me heera
    y += 18;
    ctx.strokeStyle = SAH.accent; ctx.lineWidth = 4; ctx.lineCap = 'round';
    ctx.beginPath(); ctx.moveTo(W / 2 - 190, y); ctx.lineTo(W / 2 - 26, y); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(W / 2 + 26, y); ctx.lineTo(W / 2 + 190, y); ctx.stroke();
    ctx.fillStyle = SAH.accent;
    ctx.beginPath(); ctx.moveTo(W / 2, y - 13); ctx.lineTo(W / 2 + 13, y); ctx.lineTo(W / 2, y + 13); ctx.lineTo(W / 2 - 13, y); ctx.closePath(); ctx.fill();

    return y + 22;
}

/**
 * Options 2×2 grid me. `revealIdx` de do to wo option hara ho jaata hai
 * (answer card), warna sab ek jaise (question card).
 * Aadhi chaudai me lamba Gujarati/Hindi option ek line me nahi aata, isliye
 * har pill ke andar do line tak wrap hota hai.
 */
function sahOptions(ctx, opts, top, bottom, revealIdx) {
    const list = (opts || []).slice(0, 4);
    if (!list.length) return top;
    const rows = Math.ceil(list.length / 2);
    const pad = 58, colGap = 26, rowGap = 26;
    const cw = (W - pad * 2 - colGap) / 2;
    const ch = Math.min(168, (bottom - top - (rows - 1) * rowGap) / rows);

    list.forEach((opt, i) => {
        const x = pad + (i % 2) * (cw + colGap);
        const y = top + ((i / 2) | 0) * (ch + rowGap);
        const hit = revealIdx === i;

        ctx.save();
        ctx.shadowColor = 'rgba(90,60,20,0.22)'; ctx.shadowBlur = 14; ctx.shadowOffsetY = 6;
        roundRect(ctx, x, y, cw, ch, ch / 2);
        ctx.fillStyle = hit ? SAH.okBg : SAH.pill; ctx.fill();
        ctx.restore();
        roundRect(ctx, x, y, cw, ch, ch / 2);
        ctx.lineWidth = 4; ctx.strokeStyle = hit ? SAH.ok : SAH.accent; ctx.stroke();

        const bs = ch - 28, bcx = x + 14 + bs / 2, bcy = y + 14 + bs / 2;
        pdot(ctx, bcx, bcy, bs / 2, hit ? SAH.ok : SAH.badge);
        ctx.fillStyle = '#ffffff'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.font = `800 ${Math.round(bs * 0.5)}px ${sans}`;
        ctx.fillText(String.fromCharCode(65 + i), bcx, bcy + 2);

        const tx = x + 14 + bs + 20, tw = cw - (tx - x) - 24;
        const f = fitLines(ctx, opt, tw, ch - 30, sans, '700', 40);
        ctx.fillStyle = hit ? '#14532d' : SAH.opt; ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
        ctx.font = `700 ${f.size}px ${sans}`;
        let ty = y + ch / 2 - ((f.lines.length - 1) * f.lh) / 2;
        f.lines.forEach(l => { ctx.fillText(l, tx, ty + 2); ty += f.lh; });
    });

    return top + rows * ch + (rows - 1) * rowGap;
}

/**
 * Neeche kitaabein + phool, aur dayein taraf tirchha cream note.
 * `noteText` khaali chhodo to sirf ek patli lakeer + handle aata hai — answer
 * card par wajah wala box neeche tak aata hai, wahan poori sajawat nahi samati.
 */
function sahFooter(ctx, handle, language, noteText) {
    if (!noteText) {
        ctx.strokeStyle = SAH.accent; ctx.globalAlpha = 0.45; ctx.lineWidth = 3; ctx.lineCap = 'round';
        ctx.beginPath(); ctx.moveTo(W / 2 - 140, H - 118); ctx.lineTo(W / 2 + 140, H - 118); ctx.stroke();
        ctx.globalAlpha = 1;
        const h0 = (handle || '').trim();
        if (h0) {
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillStyle = SAH.title1; ctx.globalAlpha = 0.75; ctx.font = `700 34px ${sans}`;
            ctx.fillText(h0, W / 2, H - 62);
            ctx.globalAlpha = 1;
        }
        return;
    }

    ctx.textAlign = 'left'; ctx.textBaseline = 'alphabetic';
    ctx.font = `188px ${EMOJI}`; ctx.fillText('📚', 56, H - 132);
    ctx.font = `92px ${EMOJI}`;  ctx.fillText('🌼', 236, H - 128);
    ctx.font = `66px ${EMOJI}`;  ctx.fillText('🌸', 318, H - 210);

    const nw = 400, nh = 218, nx = W - nw - 70, ny = H - nh - 132;
    ctx.save();
    ctx.translate(nx + nw / 2, ny + nh / 2); ctx.rotate(-0.045); ctx.translate(-(nx + nw / 2), -(ny + nh / 2));
    ctx.shadowColor = 'rgba(90,60,20,0.28)'; ctx.shadowBlur = 18; ctx.shadowOffsetY = 9;
    roundRect(ctx, nx, ny, nw, nh, 14); ctx.fillStyle = '#fffaf0'; ctx.fill();
    ctx.shadowColor = 'transparent'; ctx.shadowBlur = 0; ctx.shadowOffsetY = 0;
    ctx.lineWidth = 2.5; ctx.strokeStyle = 'rgba(180,130,60,0.45)';
    roundRect(ctx, nx, ny, nw, nh, 14); ctx.stroke();

    const f = fitLines(ctx, noteText, nw - 56, nh - 56, serif, '700', 40);
    ctx.fillStyle = SAH.title1; ctx.textAlign = 'center'; ctx.textBaseline = 'top';
    ctx.font = `700 ${f.size}px ${serif}`;
    let ty = ny + (nh - f.lines.length * f.lh) / 2;
    f.lines.forEach(l => { ctx.fillText(l, nx + nw / 2, ty); ty += f.lh; });
    ctx.restore();

    const hh = (handle || '').trim();
    if (hh) {
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillStyle = SAH.title1; ctx.globalAlpha = 0.75; ctx.font = `700 34px ${sans}`;
        ctx.fillText(hh, W / 2, H - 48);
        ctx.globalAlpha = 1;
    }
}

const SAH_CTA = {
    hindi:    'अपना जवाब\nकमेंट करें 👇',
    gujarati: 'તમારો જવાબ\nકોમેન્ટ કરો 👇',
    hinglish: 'Apna answer\ncomment karein 👇',
};

function renderSahitya(canvas, item, handle, category, language, headerText) {
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');

    sahBackground(ctx);
    sahBadge(ctx, headerText);
    sahLogo(ctx);

    const topic = (category || item.category || '').trim();
    const titleBottom = sahTitle(ctx, topic, 268);

    // Options ka block neeche fix hai — sawaal beech ki bachi jagah me center hota hai
    const optTop = 1140, optBottom = 1470;

    const qTop = titleBottom + 46;
    const q = fitLines(ctx, item.question, W - 150, optTop - qTop - 40, sans, '700', 62);
    ctx.textAlign = 'center'; ctx.textBaseline = 'top';
    ctx.fillStyle = SAH.body; ctx.font = `700 ${q.size}px ${sans}`;
    let qy = qTop + Math.max(0, (optTop - qTop - 40 - q.lines.length * q.lh) / 2);
    q.lines.forEach(l => { ctx.fillText(l, W / 2, qy); qy += q.lh; });

    sahOptions(ctx, item.options, optTop, optBottom, -1);
    sahFooter(ctx, handle, language, SAH_CTA[language] || SAH_CTA.hindi);
}

/** Sahitya ka answer card — wahi layout, sahi option hara, neeche wajah. */
function renderSahityaAnswer(canvas, item, handle, category, language, headerText) {
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');

    sahBackground(ctx);
    sahBadge(ctx, headerText);
    sahLogo(ctx);

    const topic = (category || item.category || '').trim();
    const titleBottom = sahTitle(ctx, topic, 268);

    const optTop = 1140, optBottom = 1470;
    const ansIdx = (item.answer || 'A').charCodeAt(0) - 65;

    // Sawaal — halka, sirf context ke liye (jagah jawab aur wajah ko chahiye)
    const qTop = titleBottom + 40;
    const q = fitLines(ctx, item.question, W - 150, 300, sans, '600', 50);
    ctx.textAlign = 'center'; ctx.textBaseline = 'top';
    ctx.fillStyle = SAH.body; ctx.globalAlpha = 0.7; ctx.font = `600 ${q.size}px ${sans}`;
    let qy = qTop;
    q.lines.forEach(l => { ctx.fillText(l, W / 2, qy); qy += q.lh; });
    ctx.globalAlpha = 1;

    // "SAHI JAWAB" ribbon
    const ry = Math.min(qy + 60, optTop - 140);
    ctx.font = `800 42px ${sans}`;
    const label = { hindi: 'सही जवाब', gujarati: 'સાચો જવાબ', hinglish: 'SAHI JAWAB' }[language] || 'सही जवाब';
    const lw = ctx.measureText(label).width + 90;
    roundRect(ctx, W / 2 - lw / 2, ry, lw, 82, 41); ctx.fillStyle = SAH.ok; ctx.fill();
    ctx.fillStyle = '#ffffff'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(label, W / 2, ry + 43);

    sahOptions(ctx, item.options, optTop, optBottom, ansIdx);

    // Wajah — options ke neeche, footer se pehle
    const reason = (item.reason || '').trim();
    if (reason !== '') {
        const bx = 58, bw = W - 116, by = optBottom + 34;
        const f = fitLines(ctx, reason, bw - 64, 220, sans, '500', 40);
        const bh = 56 + f.lines.length * f.lh;
        roundRect(ctx, bx, by, bw, bh, 20); ctx.fillStyle = 'rgba(255,250,240,0.85)'; ctx.fill();
        ctx.lineWidth = 3; ctx.strokeStyle = SAH.accent;
        roundRect(ctx, bx, by, bw, bh, 20); ctx.stroke();
        ctx.fillStyle = SAH.body; ctx.textAlign = 'left'; ctx.textBaseline = 'top';
        ctx.font = `500 ${f.size}px ${sans}`;
        let ty = by + 28;
        f.lines.forEach(l => { ctx.fillText(l, bx + 32, ty); ty += f.lh; });
    }

    // Answer card par note/kitaabein nahi — wajah wala box wahi jagah leta hai
    sahFooter(ctx, handle, language, null);
}

const POSTERS = {
    gkNavy:   { name: '🔵 GK Quiz — Navy (logo)',   bg: ['#0c1f3f', '#173a72'], accent: '#f9c21a', ink: '#0c1f3f', band: '#081428' },
    gkGreen:  { name: '🟢 GK Quiz — Green (logo)',  bg: ['#06281f', '#0e5240'], accent: '#ffd24a', ink: '#06281f', band: '#041c16' },
    gkMaroon: { name: '🔴 GK Quiz — Maroon (logo)', bg: ['#3a0d16', '#711a24'], accent: '#ffd24a', ink: '#3a0d16', band: '#280910' },
    gkWhite:  { name: '⚪ GK Quiz — White (logo)',  bg: ['#eef1f6', '#d8e0ee'], accent: '#1d4ed8', ink: '#152a54', band: '#152a54', light: true },
};

function renderLogoPoster(canvas, item, handle, category, language, P) {
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');
    const ACC = P.accent, INK = P.ink, BAND = P.band;
    const badgeTxt = lum(ACC) > 150 ? INK : '#ffffff';
    const qMark = lum(ACC) > 150 ? ACC : '#ffd24a';

    // Background gradient
    const g = ctx.createLinearGradient(0, 0, 0, H);
    g.addColorStop(0, P.bg[0]); g.addColorStop(1, P.bg[1]);
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);

    // Logo (top center)
    const logoBox = 360;
    drawLogo(ctx, W / 2, 44, logoBox);
    let y = 44 + logoBox + 6;

    // Topic badge — selected topic/exam
    const topic = ((category || item.category || '').trim().toUpperCase()) || 'GK QUIZ';
    const tpSize = fitOne(ctx, topic, W - 260, sans, '800', 40);
    ctx.font = `800 ${tpSize}px ${sans}`;
    const bW = ctx.measureText(topic).width;
    roundRect(ctx, W / 2 - bW / 2 - 34, y, bW + 68, 66, 33); ctx.fillStyle = ACC; ctx.fill();
    ctx.fillStyle = badgeTxt; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(topic, W / 2, y + 34);
    y += 66 + 34;

    // Question card (white)
    const cardX = 54, cardW = W - 108, cardY = y;
    ctx.textAlign = 'left'; ctx.textBaseline = 'top';
    const qfit = fitLines(ctx, item.question, cardW - 240, 340, sans, '800', 58);
    const cardH = Math.max(230, 120 + qfit.lines.length * qfit.lh);
    ctx.save(); ctx.shadowColor = 'rgba(0,0,0,0.24)'; ctx.shadowBlur = 30; ctx.shadowOffsetY = 12;
    roundRect(ctx, cardX, cardY, cardW, cardH, 34); ctx.fillStyle = '#ffffff'; ctx.fill(); ctx.restore();
    ctx.fillStyle = 'rgba(21,42,84,0.06)'; ctx.font = `900 300px ${sans}`; ctx.textAlign = 'right'; ctx.textBaseline = 'alphabetic';
    ctx.fillText('?', cardX + cardW - 40, cardY + cardH - 34);
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    pdot(ctx, cardX + 96, cardY + 92, 56, INK);
    ctx.fillStyle = qMark; ctx.font = `900 44px ${sans}`; ctx.fillText('Q.', cardX + 98, cardY + 94);
    ctx.textAlign = 'left'; ctx.textBaseline = 'top'; ctx.fillStyle = INK; ctx.font = `800 ${qfit.size}px ${sans}`;
    let qy = cardY + 48; const qx = cardX + 180;
    qfit.lines.forEach(l => { ctx.fillText(l, qx, qy); qy += qfit.lh; });

    // Options (answer NOT revealed)
    const opts = (item.options || []).slice(0, 4); const n = opts.length || 4;
    const optTop = cardY + cardH + 42, zoneBottom = 1520, gap = 26;
    const boxH = Math.min(150, (zoneBottom - optTop - (n - 1) * gap) / n);
    let oy = optTop;
    opts.forEach((opt, i) => {
        ctx.save(); ctx.shadowColor = 'rgba(0,0,0,0.18)'; ctx.shadowBlur = 16; ctx.shadowOffsetY = 6;
        roundRect(ctx, cardX, oy, cardW, boxH, boxH / 2); ctx.fillStyle = '#ffffff'; ctx.fill(); ctx.restore();
        roundRect(ctx, cardX, oy, cardW, boxH, boxH / 2); ctx.lineWidth = 3; ctx.strokeStyle = INK; ctx.stroke();
        const bs = boxH - 28, lbx = cardX + 16, lby = oy + 14;
        pdot(ctx, lbx + bs / 2, lby + bs / 2, bs / 2, INK);
        ctx.fillStyle = '#ffffff'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.font = `800 ${Math.round(bs * 0.44)}px ${sans}`;
        ctx.fillText(String.fromCharCode(65 + i), lbx + bs / 2, lby + bs / 2 + 2);
        const tx = lbx + bs + 34, tmaxW = cardW - (tx - cardX) - 44;
        const os = fitOne(ctx, opt, tmaxW, sans, '700', 46);
        ctx.fillStyle = INK; ctx.textAlign = 'left'; ctx.textBaseline = 'middle'; ctx.font = `700 ${os}px ${sans}`;
        ctx.fillText(opt, tx, oy + boxH / 2 + 2);
        oy += boxH + gap;
    });

    // Bottom band (wavy top)
    ctx.fillStyle = BAND;
    ctx.beginPath();
    ctx.moveTo(0, 1706); ctx.quadraticCurveTo(W * 0.26, 1662, W * 0.5, 1704);
    ctx.quadraticCurveTo(W * 0.76, 1744, W, 1690);
    ctx.lineTo(W, H); ctx.lineTo(0, H); ctx.closePath(); ctx.fill();

    // Ribbon (selected language)
    const CTA = {
        hindi:    'अपना जवाब कमेंट करें 👇 जवाब कैप्शन में',
        gujarati: 'તમારો જવાબ કોમેન્ટ કરો 👇 જવાબ કૅપ્શનમાં',
        hinglish: 'Apna answer comment karein 👇 answer caption me',
    };
    const rtxt = CTA[language] || CTA.hindi;
    const rx = cardX, ry = 1548, rW = cardW, rh = 104;
    roundRect(ctx, rx, ry, rW, rh, 20); ctx.fillStyle = BAND; ctx.fill();
    ctx.lineWidth = 4; ctx.strokeStyle = ACC; roundRect(ctx, rx, ry, rW, rh, 20); ctx.stroke();
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    const rSize = fitOne(ctx, rtxt, rW - 70, sans, '700', 44);
    ctx.fillStyle = '#ffffff'; ctx.font = `700 ${rSize}px ${sans}`; ctx.fillText(rtxt, rx + rW / 2, ry + rh / 2 + 2);

    // Footer strip
    const feats = [['📚', 'सरकारी परीक्षा', 'की तैयारी करें'], ['🎯', 'अपना ज्ञान', 'बढ़ाएं'], ['🏆', 'रोज़ाना क्विज़', 'खेलें'], ['🏅', 'सफलता की ओर', 'एक कदम']];
    const fw = W / 4, fy = 1812;
    feats.forEach((f, i) => {
        const cx = fw * i + 30;
        ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
        ctx.font = `46px ${EMOJI}`; ctx.fillText(f[0], cx, fy + 26);
        ctx.fillStyle = '#ffffff'; ctx.font = `600 25px ${sans}`;
        ctx.fillText(f[1], cx + 58, fy + 8); ctx.fillText(f[2], cx + 58, fy + 44);
    });

    const hh = (handle || '').trim();
    if (hh) { ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillStyle = ACC; ctx.globalAlpha = 0.9; ctx.font = `600 26px ${sans}`; ctx.fillText(hh, W / 2, 1900); ctx.globalAlpha = 1; }
}

// ---------- State ----------
let items = [];
async function ensureFonts(){ for(const f of ['700 68px "Noto Serif Devanagari"','700 68px "Noto Sans Devanagari"','700 68px "Noto Serif Gujarati"','700 68px "Noto Sans Gujarati"']){ try{await document.fonts.load(f);}catch(e){} } await document.fonts.ready; }

function renderPreviews() {
    const grid = el('grid'); grid.innerHTML='';
    const theme = el('theme').value, handle = el('handle').value, category = el('category').value.trim(), style = el('style').value, language = el('language').value;
    const headerText = el('headerText').value;
    const off = document.createElement('canvas');

    const addCell = (label) => {
        const wrap = document.createElement('div'); wrap.className='text-center';
        const small = document.createElement('canvas'); small.width=270; small.height=480;
        small.className='w-full rounded-lg border border-slate-200 shadow-sm';
        small.getContext('2d').drawImage(off,0,0,270,480);
        wrap.appendChild(small);
        const cap = document.createElement('div'); cap.className='text-[11px] text-slate-500 mt-0.5'; cap.textContent=label;
        wrap.appendChild(cap);
        grid.appendChild(wrap);
    };

    // Q&A List: kai sawaal ek card me — cards ki ginti apne aap nikalti hai
    if (style === 'list') {
        const showOpts = el('listOptions').checked;
        const pages = packQuizPages(items, showOpts);
        pages.forEach((page, i) => {
            renderList(off, page, theme, handle, category, language, headerText, showOpts, i + 1, pages.length);
            addCell(`Card ${i + 1} — ${page.items.length} sawaal`);
        });
        el('itemCount').textContent = items.length;
        el('cardCount').textContent = pages.length;
        el('previewWrap').classList.remove('hidden');
        return;
    }

    items.forEach((item, i) => {
        if (style === 'simple') renderSimple(off, item, theme, handle, category, language, headerText);
        else if (style === 'sahitya') renderSahitya(off, item, handle, category, language, headerText);
        else if (POSTERS[style]) renderLogoPoster(off, item, handle, category, language, POSTERS[style]);
        else if (style === 'poster') renderQuestionPoster(off, item, handle, category, language);
        else renderQuestion(off, item, theme, handle, category);
        addCell('Q' + (i + 1));
    });
    el('itemCount').textContent = items.length;
    el('cardCount').textContent = items.length;
    el('previewWrap').classList.remove('hidden');
}

// ---------- Generate ----------
// Pichhle generate ka hisaab — is topic par kitne sawaal pehle se ban chuke hain
// aur is baar kitne repeat nikaale gaye.
let genStats = { asked: 0, dropped: 0 };

/** AI se ek batch sawaal maango. `exclude` me pehle mile sawaal — dobara na aayein. */
async function fetchQuizBatch(base, count, exclude) {
    const r = await fetch(GEN_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ ...base, count, exclude: exclude.slice(-40) }),
    });
    const d = await r.json();
    if (!d.ok || !d.items) throw new Error(d.error || 'Kuch nahi bana.');

    genStats.asked = d.asked || 0;
    genStats.dropped += d.dropped || 0;

    return d.items;
}

/** "is topic par pehle se X sawaal, Y repeat hataye" — message ke liye. */
function repeatNote() {
    const bits = [];
    if (genStats.asked) bits.push(`is topic par pehle se ${genStats.asked} sawaal ban chuke hain`);
    if (genStats.dropped) bits.push(`${genStats.dropped} repeat hata diye`);
    return bits.length ? ' (' + bits.join(', ') + ')' : '';
}

/**
 * Q&A List ke liye utne sawaal jama karo jitne se `targetCards` card ban jaayein.
 *
 * Ek card me kitne sawaal aayenge ye pehle se pata nahi (sawaal ki lambai par
 * depend karta hai), isliye batch-batch me maangte hain aur har baar pack karke
 * dekhte hain ki kitne card ban rahe hain. Ek hi AI call me 70 MCQ maangna
 * bharosemand nahi — model beech me kaat deta hai.
 */
async function collectForCards(base, targetCards, showOpts, onProgress) {
    const collected = [], seen = new Set();

    // Pehla andaaza; pehle batch ke baad asli naap se badal jaata hai
    let goal = targetCards * (showOpts ? 7 : 10);

    for (let round = 0; round < 8; round++) {
        const need = goal - collected.length;
        if (need <= 0) break;

        onProgress(`AI sawaal bana raha hai… ${collected.length}/${goal}`);

        const batch = await fetchQuizBatch(base, Math.min(25, Math.max(5, need)), collected.map(i => i.question));

        let added = 0;
        batch.forEach(it => {
            const key = (it.question || '').trim().toLowerCase();
            if (key && !seen.has(key)) { seen.add(key); collected.push(it); added++; }
        });

        // AI naye sawaal nahi de pa raha — aur try karne ka fayda nahi
        if (added === 0) break;

        // Ab asli sawaalon se naapo ki ek card me kitne aate hain
        if (round === 0) goal = targetCards * itemsPerCard(collected, showOpts);
    }

    // Zaroorat se zyada aa gaye to extra chhod do, phir theek utne cards me baanto
    const use = collected.slice(0, goal);
    const pages = packQuizPages(use, showOpts, targetCards);

    return { items: pages.flatMap(p => p.items), pages, short: pages.length < targetCards };
}

el('genBtn').addEventListener('click', async () => {
    const btn = el('genBtn'), msg = el('msg');
    const wanted = parseInt(el('count').value, 10) || 5;
    const base = { category: el('category').value.trim(), language: el('language').value };
    const isList = el('style').value === 'list';

    btn.disabled=true; const lbl=btn.textContent; btn.textContent='⏳ Ban raha hai…'; msg.textContent='AI quiz bana raha hai…';
    el('previewWrap').classList.add('hidden');
    genStats = { asked: 0, dropped: 0 };

    try {
        if (isList) {
            // "Kitne cards?" = card ki ginti. Har card me jitne sawaal fit ho jaayein.
            const res = await collectForCards(base, wanted, el('listOptions').checked, t => { msg.textContent = t; });
            items = res.items;
            await Promise.all([ensureFonts(), ensureLogo()]);
            renderPreviews();
            msg.textContent = res.short
                ? `⚠ Sirf ${res.pages.length} card ban paaye (${items.length} sawaal) — AI is topic par aur naye sawaal nahi de paaya${repeatNote()}.`
                : `✓ ${res.pages.length} cards ready — kul ${items.length} sawaal${repeatNote()}. Theme badal ke dekho, phir Save karo.`;
        } else {
            // Baaki designs me 1 sawaal = 1 card
            items = await fetchQuizBatch(base, Math.min(30, wanted), []);
            await Promise.all([ensureFonts(), ensureLogo()]);
            renderPreviews();
            msg.textContent = `✓ ${items.length} cards ready${repeatNote()} — theme badal ke dekho, phir Save karo.`;
        }
    } catch(e){ msg.textContent = '⚠ ' + (e.message || 'Error aaya, dobara try karo.'); }
    btn.disabled=false; btn.textContent=lbl;
});
// Header text Simple + List + Sahitya me lagta hai (Sahitya me wo upar-left ka
// badge banta hai); "Options bhi dikhao" sirf List me
function syncStyleFields() {
    const s = el('style').value;
    el('headerWrap').style.display = (s === 'simple' || s === 'list' || s === 'sahitya') ? '' : 'none';
    el('listOptsWrap').classList.toggle('hidden', s !== 'list');
    el('listOptsWrap').classList.toggle('flex', s === 'list');
    el('countHint').textContent = s === 'list'
        ? 'Har card me jitne sawaal fit honge utne aayenge'
        : '1 sawaal = 1 card';
}
el('style').addEventListener('change', syncStyleFields);
syncStyleFields();

['theme','handle','style','category','language','headerText','listOptions'].forEach(id => el(id).addEventListener('change', () => { if(items.length) renderPreviews(); }));

function getCanvasData(c) {
    try {
        const u = c.toDataURL('image/webp', 0.92);
        if (u && u.startsWith('data:image/webp')) return u;
    } catch(e){}
    return c.toDataURL('image/png');
}

// ---------- Save (Batch fast save) ----------
el('saveBtn').addEventListener('click', async () => {
    if(!items.length) return;
    const btn=el('saveBtn'); btn.disabled=true; btn.classList.add('opacity-60'); el('progress').classList.remove('hidden');
    el('bar').style.width = '35%';
    el('progressText').textContent = 'Rendering cards…';

    await Promise.all([ensureFonts(), ensureLogo()]);
    const theme=el('theme').value, handle=el('handle').value, category=el('category').value.trim(), language=el('language').value, style=el('style').value, headerText=el('headerText').value;
    const off=document.createElement('canvas');
    const ansOff=document.createElement('canvas');
    const cardsToSave = [];

    try {
        if (style === 'list') {
            const showOpts = el('listOptions').checked;
            const pages = packQuizPages(items, showOpts);
            for (let p = 0; p < pages.length; p++) {
                renderList(off, pages[p], theme, handle, category, language, headerText, showOpts, p + 1, pages.length);
                cardsToSave.push({
                    order: p + 1,
                    text: listText(pages[p]),
                    caption: (pages[p].items[0] || {}).caption || '',
                    questions: pages[p].items.map(i => i.question),
                    hashtags: listHashtags(pages[p]),
                    image: getCanvasData(off),
                });
            }
        } else {
            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                const optsText = (item.options||[]).map((o,k)=>String.fromCharCode(65+k)+') '+o).join('\n');
                const ansIdx = (item.answer||'A').charCodeAt(0)-65;
                const ansOpt = (item.options && item.options[ansIdx]) ? item.options[ansIdx] : '';
                const answerBlock = '✅ Sahi jawab: ' + (item.answer||'A') + ') ' + ansOpt + (item.reason ? '\n💡 ' + item.reason : '');

                if (style === 'simple') renderSimple(off, item, theme, handle, category, language, headerText);
                else if (style === 'sahitya') renderSahitya(off, item, handle, category, language, headerText);
                else if (POSTERS[style]) renderLogoPoster(off, item, handle, category, language, POSTERS[style]);
                else if (style === 'poster') renderQuestionPoster(off, item, handle, category, language);
                else renderQuestion(off, item, theme, handle, category);

                if (style === 'simple') renderSimpleAnswer(ansOff, item, theme, handle, category, language, headerText);
                else if (style === 'sahitya') renderSahityaAnswer(ansOff, item, handle, category, language, headerText);
                else renderAnswer(ansOff, item, theme, handle);

                cardsToSave.push({
                    order: i + 1,
                    text: item.question + '\n\n' + optsText,
                    answer: answerBlock,
                    caption: item.caption || '',
                    questions: [item.question],
                    hashtags: item.hashtags || '',
                    image: getCanvasData(off),
                    answer_image: getCanvasData(ansOff),
                });
            }
        }

        el('bar').style.width = '75%';
        el('progressText').textContent = 'Saving all cards…';

        const r = await fetch(SAVE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                category,
                language,
                cards: cardsToSave,
            })
        });

        const d = await r.json();
        if (!d.ok) throw new Error(d.error || 'Card save failed');

        el('bar').style.width = '100%';
        el('progressText').textContent = '✅ Saved! Redirecting…';
        setTimeout(() => { window.location = d.redirect; }, 300);
    } catch(e) {
        el('progressText').textContent = '❌ Save fail: ' + e.message;
        btn.disabled = false;
        btn.classList.remove('opacity-60');
    }
});
</script>
@endpush
@endsection
