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
                    <option value="hindi">हिंदी Hindi</option>
                    <option value="gujarati">ગુજરાતી Gujarati</option>
                    <option value="hinglish">Hindi-English (Roman)</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Kitne?</label>
                <input type="number" id="count" value="5" min="1" max="30"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Theme 🎨</label>
                <select id="theme" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></select>
            </div>
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">🖼 Card Design</label>
                <select id="style" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="poster">🏆 Daily GK Quiz</option>
                    <option value="clean">📝 Classic (theme)</option>
                </select>
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

// Quiz bg — clean gradient + soft top glow (theme ka bhaari deco NAHI, taaki
// quiz saaf professional lage)
function bgAndDeco(ctx, t){
    const g = ctx.createLinearGradient(0, 0, W, H);
    g.addColorStop(0, t.bg[0]); g.addColorStop(1, t.bg[1]);
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    const gl = ctx.createRadialGradient(W/2, H*0.20, 0, W/2, H*0.20, W);
    gl.addColorStop(0, 'rgba(255,255,255,0.09)'); gl.addColorStop(1, 'rgba(255,255,255,0)');
    ctx.fillStyle = gl; ctx.fillRect(0, 0, W, H);
}
function handleAt(ctx, t, handle){ const hh=(handle||'').trim(); if(!hh)return; ctx.textAlign='center'; ctx.fillStyle=t.accent; ctx.globalAlpha=0.9; ctx.font=`600 34px ${sans}`; ctx.fillText(hh, W/2, H-95); ctx.globalAlpha=1; }

// ---------- Question card (exam-serious, clean) ----------
function renderQuestion(canvas, item, themeKey, handle, category) {
    const t = THEMES[themeKey] || THEMES.night;
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
    const t = THEMES[themeKey] || THEMES.night;
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
    const off = document.createElement('canvas');
    items.forEach((item, i) => {
        if (POSTERS[style]) renderLogoPoster(off, item, handle, category, language, POSTERS[style]);
        else if (style === 'poster') renderQuestionPoster(off, item, handle, category, language);
        else renderQuestion(off, item, theme, handle, category);
        const wrap = document.createElement('div'); wrap.className='text-center';
        const small = document.createElement('canvas'); small.width=270; small.height=480;
        small.className='w-full rounded-lg border border-slate-200 shadow-sm';
        small.getContext('2d').drawImage(off,0,0,270,480);
        wrap.appendChild(small);
        const cap = document.createElement('div'); cap.className='text-[11px] text-slate-500 mt-0.5'; cap.textContent='Q'+(i+1);
        wrap.appendChild(cap);
        grid.appendChild(wrap);
    });
    el('itemCount').textContent = items.length;
    el('cardCount').textContent = items.length;
    el('previewWrap').classList.remove('hidden');
}

// ---------- Generate ----------
el('genBtn').addEventListener('click', async () => {
    const btn = el('genBtn'), msg = el('msg');
    const payload = { category: el('category').value.trim(), count: parseInt(el('count').value,10)||5, language: el('language').value };
    btn.disabled=true; const lbl=btn.textContent; btn.textContent='⏳ Ban raha hai…'; msg.textContent='AI quiz bana raha hai…';
    el('previewWrap').classList.add('hidden');
    try {
        const r = await fetch(GEN_URL, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'}, body: JSON.stringify(payload) });
        const d = await r.json();
        if (d.ok && d.items && d.items.length) { items=d.items; await Promise.all([ensureFonts(), ensureLogo()]); renderPreviews(); msg.textContent=`✓ ${items.length} quiz ready — theme badal ke dekho, phir Save karo.`; }
        else msg.textContent = '⚠ ' + (d.error || 'Kuch nahi bana.');
    } catch(e){ msg.textContent='⚠ Error aaya, dobara try karo.'; }
    btn.disabled=false; btn.textContent=lbl;
});
['theme','handle','style','category','language'].forEach(id => el(id).addEventListener('change', () => { if(items.length) renderPreviews(); }));

// ---------- Save (2 cards per quiz, sequence me) ----------
el('saveBtn').addEventListener('click', async () => {
    if(!items.length) return;
    const btn=el('saveBtn'); btn.disabled=true; btn.classList.add('opacity-60'); el('progress').classList.remove('hidden');
    await Promise.all([ensureFonts(), ensureLogo()]);
    const theme=el('theme').value, handle=el('handle').value, category=el('category').value.trim(), language=el('language').value, style=el('style').value;
    const off=document.createElement('canvas');
    let collection=null, redirect=null, order=0;
    const total = items.length;
    try {
        for (let i=0;i<items.length;i++) {
            const item = items[i];
            const optsText = (item.options||[]).map((o,k)=>String.fromCharCode(65+k)+') '+o).join('\n');
            const ansIdx = (item.answer||'A').charCodeAt(0)-65;
            const ansOpt = (item.options && item.options[ansIdx]) ? item.options[ansIdx] : '';
            // Sirf Question card. Answer + reason caption me jaayega (image par nahi).
            const answerBlock = '✅ Sahi jawab: ' + (item.answer||'A') + ') ' + ansOpt + (item.reason ? '\n💡 ' + item.reason : '');
            if (POSTERS[style]) renderLogoPoster(off, item, handle, category, language, POSTERS[style]);
            else if (style === 'poster') renderQuestionPoster(off, item, handle, category, language);
            else renderQuestion(off, item, theme, handle, category);
            order++;
            await postCard({ collection, order, text: item.question + '\n\n' + optsText, answer: answerBlock, hashtags: item.hashtags||'', image: off.toDataURL('image/png'), category, language });
            el('bar').style.width = Math.round((order/total)*100)+'%';
            el('progressText').textContent = `${order} / ${total} cards saved…`;
        }
        el('progressText').textContent='✅ Saved! Redirecting…';
        setTimeout(()=>{ window.location = redirect; }, 700);
    } catch(e){ el('progressText').textContent='❌ Save fail: '+e.message; btn.disabled=false; btn.classList.remove('opacity-60'); }

    async function postCard(body) {
        const r = await fetch(SAVE_URL, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'}, body: JSON.stringify(body) });
        const d = await r.json();
        if(!d.ok) throw new Error(d.error || 'card fail');
        collection = d.collection; redirect = d.redirect;
        // agli calls me collection bhej sako isliye closure var update
        body.collection = collection;
    }
});
</script>
@endpush
@endsection
