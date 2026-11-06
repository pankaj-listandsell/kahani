@extends('layouts.admin')
@section('title', '📅 Aaj nu Panchang')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-orange-700 via-red-700 to-rose-800 rounded-2xl p-6 text-white shadow-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="inline-block px-2.5 py-1 bg-white/20 rounded-full text-xs font-semibold uppercase tracking-wider mb-2">📅 Daily Fixed Content</span>
                <h1 class="text-2xl font-bold">Aaj nu Panchang — Panchang + Choghadiya</h1>
                <p class="text-orange-100 text-sm mt-1">Ek din ke 2 cards. Auto-post inhe <b>sirf usi din</b>, rashifal se bhi pehle post karta hai.</p>
            </div>
            <button id="saveBtn" disabled class="bg-white text-red-800 font-bold px-5 py-2.5 rounded-xl shadow hover:bg-orange-50 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-2">
                <span>💾</span> Save 2 Cards
            </button>
        </div>
    </div>

    {{-- Form --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-4">
        <div class="grid sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">📅 Date</label>
                <input id="date" type="date" value="{{ now()->addDay()->toDateString() }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">📍 Sheher (samay)</label>
                <select id="city" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($cities as $key => $c)
                        <option value="{{ $key }}">{{ $c[0] }} ({{ ucfirst($key) }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🌐 Language</label>
                <select id="language" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="gujarati" selected>ગુજરાતી (Gujarati)</option>
                    <option value="hindi">हिंदी (Hindi)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🪔 Tyohar (optional)</label>
                <input id="festival" list="festList" placeholder="jaise: દિવાળી" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <datalist id="festList">
                    <option value="ધનતેરસ"><option value="કાળી ચૌદસ"><option value="દિવાળી"><option value="નૂતન વર્ષ (બેસતું વર્ષ)">
                    <option value="ભાઈબીજ"><option value="લાભ પાંચમ"><option value="દેવ ઉઠી એકાદશી"><option value="દેવ દિવાળી">
                    <option value="धनतेरस"><option value="दिवाली"><option value="भाई दूज">
                </datalist>
            </div>
        </div>

        <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-900">
            📖 Neeche ke khaane <b>Gujarati calendar / panchang</b> dekh kar bharo (jo khaali chhodoge wo card par nahi aayega).
            <b>Sunrise, sunset, choghadiya, rahu kaal aur abhijit</b> panel khud ganit se nikalta hai — chune hue sheher ke hisaab se.
        </div>

        <div class="grid sm:grid-cols-4 gap-4">
            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Samvat</label>
                <input id="samvat" value="વિક્રમ સંવત ૨૦૮૨" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Maas (mahino)</label>
                <input id="maas" placeholder="jaise: આસો / કારતક" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Paksha</label>
                <select id="paksha" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">—</option><option value="sud">સુદ (Shukla)</option><option value="vad">વદ (Krishna)</option>
                </select></div>
            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Tithi</label>
                <input id="tithi" placeholder="jaise: અમાસ" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Nakshatra</label>
                <input id="nakshatra" placeholder="jaise: સ્વાતિ" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Yog</label>
                <input id="yog" placeholder="jaise: આયુષ્માન" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Karan</label>
                <input id="karan" placeholder="jaise: નાગ" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Chandra rashi</label>
                <input id="chandra" placeholder="jaise: તુલા" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">✨ Shubh muhurat <span class="font-normal text-slate-400">(har line ek — max 4. jaise: <code>લક્ષ્મી પૂજન = સાંજે 6:10 થી 8:05</code>)</span></label>
            <textarea id="muhurat" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="લક્ષ્મી પૂજન = સાંજે 6:10 થી 8:05&#10;ચોપડા પૂજન = સાંજે 7:00 થી 8:30"></textarea>
        </div>
        <div class="flex items-center gap-3">
            <button id="genBtn" class="bg-gradient-to-r from-orange-600 to-red-700 hover:from-orange-700 hover:to-red-800 text-white font-bold py-2 px-5 rounded-lg shadow">🔄 Card banao</button>
            <span id="msg" class="text-sm text-slate-500"></span>
        </div>
    </div>

    {{-- Preview --}}
    <div id="previewGrid" class="hidden grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4"></div>

    {{-- Saved --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <h3 class="font-bold text-slate-800 mb-3">💾 Saved Panchang ({{ $collections->count() }})</h3>
        @forelse ($collections as $c)
            <div class="flex items-center justify-between bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 mb-2 gap-3">
                <a href="{{ route('admin.panchang.show', $c) }}" class="flex-1 font-bold text-slate-800 hover:text-red-700">📅 {{ $c->title }}
                    @if ($c->category === now()->toDateString()) <span class="ml-2 text-xs text-emerald-700 font-semibold">● Aaj</span> @endif
                </a>
                <form method="POST" action="{{ route('admin.panchang.destroy', $c) }}" onsubmit="return confirm('Ye panchang delete karein?')">
                    @csrf @method('DELETE')
                    <button class="text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg">🗑 Delete</button>
                </form>
            </div>
        @empty
            <p class="text-sm text-slate-500 text-center py-4">Abhi koi panchang nahi.</p>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
@include('admin.partials._canvas_kit')
<script>
(function () {
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const COMPUTE_URL = @json(route('admin.panchang.compute'));
    const SAVE_URL = @json(route('admin.panchang.save'));
    const CITIES = @json($cities);
    const K = window.CanvasKit;
    const W = 1080, H = 1920;
    const el = id => document.getElementById(id);
    let data = null, lang = 'gujarati', form = {};

    const T = {
        gujarati: {
            head: 'આજનું પંચાંગ', chog: 'આજના ચોઘડિયા', tithi: 'તિથિ', nakshatra: 'નક્ષત્ર', yog: 'યોગ', karan: 'કરણ', chandra: 'ચંદ્ર રાશિ',
            sunrise: 'સૂર્યોદય', sunset: 'સૂર્યાસ્ત', rahu: 'રાહુ કાળ', abhijit: 'અભિજિત મુહૂર્ત', muhurat: 'શુભ મુહૂર્ત',
            day: '☀️ દિવસના ચોઘડિયા', night: '🌙 રાત્રિના ચોઘડિયા', to: 'થી', cta: 'પરિવાર સાથે શેર કરો 🙏',
            rahuNote: 'શુભ કાર્ય ટાળો', abhijitNote: 'શુભ કાર્ય માટે ઉત્તમ', abhijitWed: 'બુધવારે ટાળવું',
            paksha: { sud: 'સુદ', vad: 'વદ' }, cityNote: (c) => `સમય: ${c} પ્રમાણે`,
            tag: { 1: 'શુભ', 0: 'સામાન્ય', '-1': 'અશુભ' },
            names: { amrit: 'અમૃત', shubh: 'શુભ', labh: 'લાભ', chal: 'ચલ', udveg: 'ઉદ્વેગ', rog: 'રોગ', kaal: 'કાળ' },
            locale: 'gu-IN', digits: '૦૧૨૩૪૫૬૭૮૯', ci: 0,
        },
        hindi: {
            head: 'आज का पंचांग', chog: 'आज के चौघड़िया', tithi: 'तिथि', nakshatra: 'नक्षत्र', yog: 'योग', karan: 'करण', chandra: 'चंद्र राशि',
            sunrise: 'सूर्योदय', sunset: 'सूर्यास्त', rahu: 'राहु काल', abhijit: 'अभिजीत मुहूर्त', muhurat: 'शुभ मुहूर्त',
            day: '☀️ दिन के चौघड़िया', night: '🌙 रात के चौघड़िया', to: 'से', cta: 'परिवार के साथ शेयर करें 🙏',
            rahuNote: 'शुभ कार्य टालें', abhijitNote: 'शुभ कार्य के लिए उत्तम', abhijitWed: 'बुधवार को टालें',
            paksha: { sud: 'शुक्ल', vad: 'कृष्ण' }, cityNote: (c) => `समय: ${c} के अनुसार`,
            tag: { 1: 'शुभ', 0: 'सामान्य', '-1': 'अशुभ' },
            names: { amrit: 'अमृत', shubh: 'शुभ', labh: 'लाभ', chal: 'चर', udveg: 'उद्वेग', rog: 'रोग', kaal: 'काल' },
            locale: 'hi-IN', digits: '०१२३४५६७८९', ci: 1,
        },
    };
    const t = () => T[lang] || T.gujarati;
    const num = s => String(s ?? '').replace(/\d/g, d => t().digits[d]);
    /** "17:58" → "૫:૫૮" (12 ghante wala; din/raat ka section saaf hai) */
    const tm = hm => { const [h, m] = String(hm).split(':').map(Number); return num(((h % 12) || 12) + ':' + String(m).padStart(2, '0')); };
    const range = r => tm(r.from) + ' – ' + tm(r.to);
    const cityName = () => (CITIES[form.city] || CITIES.ahmedabad)[t().ci];
    const dateParts = () => {
        const d = new Date(form.date + 'T00:00:00');
        return [d.toLocaleDateString(t().locale, { weekday: 'long' }), num(d.toLocaleDateString(t().locale, { day: 'numeric', month: 'long', year: 'numeric' }))];
    };

    // ---------- Drawing helpers ----------
    function bg(ctx) {
        const g = ctx.createLinearGradient(0, 0, 0, H);
        g.addColorStop(0, '#3b0a0a'); g.addColorStop(0.5, '#7c2d12'); g.addColorStop(1, '#431407');
        ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
        // Halka mandala
        ctx.save(); ctx.strokeStyle = 'rgba(250, 204, 21, 0.08)'; ctx.lineWidth = 3;
        for (let r = 120; r < 700; r += 80) { ctx.beginPath(); ctx.arc(W / 2, H / 2, r, 0, Math.PI * 2); ctx.stroke(); }
        ctx.restore();
        // Toran
        ctx.save();
        ctx.strokeStyle = 'rgba(250, 204, 21, 0.7)'; ctx.lineWidth = 3;
        ctx.beginPath(); ctx.moveTo(0, 18);
        for (let x = 0; x <= W; x += 90) ctx.quadraticCurveTo(x + 45, 46, x + 90, 18);
        ctx.stroke();
        for (let x = 45, i = 0; x < W; x += 90, i++) {
            ctx.fillStyle = '#16a34a';
            ctx.beginPath(); ctx.ellipse(x - 14, 40, 9, 16, -0.6, 0, Math.PI * 2); ctx.fill();
            ctx.beginPath(); ctx.ellipse(x + 14, 40, 9, 16, 0.6, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = i % 2 ? '#facc15' : '#f97316';
            ctx.beginPath(); ctx.arc(x, 44, 17, 0, Math.PI * 2); ctx.fill();
        }
        ctx.restore();
    }

    function header(ctx, title) {
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillStyle = '#facc15';
        const s = '🙏 ' + title + ' 🙏';
        K.fitOneLine(ctx, s, W - 120, 76, 44);
        ctx.fillText(s, W / 2, 125);

        // vaar • taarikh pill
        const [day, date] = dateParts(), sep = '  •  ';
        const ds = K.fitOneLine(ctx, day + sep + date, W - 220, 46, 30);
        const dw = ctx.measureText(day).width, sw = ctx.measureText(sep).width, tw = ctx.measureText(date).width;
        const pw = dw + sw + tw + 90, ph = 82, py = 215;
        ctx.fillStyle = 'rgba(59, 10, 10, 0.85)'; ctx.strokeStyle = '#facc15'; ctx.lineWidth = 3;
        ctx.beginPath(); ctx.roundRect(W / 2 - pw / 2, py - ph / 2, pw, ph, ph / 2); ctx.fill(); ctx.stroke();
        ctx.font = K.font(ds); ctx.textAlign = 'left';
        let x = W / 2 - (dw + sw + tw) / 2;
        ctx.fillStyle = '#facc15'; ctx.fillText(day, x, py + 2); x += dw;
        ctx.fillStyle = 'rgba(250, 204, 21, 0.6)'; ctx.fillText(sep, x, py + 2); x += sw;
        ctx.fillStyle = '#ffffff'; ctx.fillText(date, x, py + 2);
        ctx.textAlign = 'center';
    }

    function box(ctx, x, y, w, h, label, value, opt = {}) {
        ctx.fillStyle = opt.fill || 'rgba(255,255,255,0.10)';
        ctx.strokeStyle = opt.stroke || 'rgba(250, 204, 21, 0.45)'; ctx.lineWidth = 3;
        ctx.beginPath(); ctx.roundRect(x, y, w, h, 26); ctx.fill(); ctx.stroke();
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillStyle = opt.labelColor || '#fcd34d';
        K.fitOneLine(ctx, label, w - 40, 34, 22, '600');
        ctx.fillText(label, x + w / 2, y + (opt.note ? h * 0.24 : h * 0.32));
        ctx.fillStyle = '#ffffff';
        K.fitOneLine(ctx, value, w - 40, opt.size || 50, 26);
        ctx.fillText(value, x + w / 2, y + (opt.note ? h * 0.54 : h * 0.68));
        if (opt.note) {
            ctx.fillStyle = 'rgba(255,255,255,0.75)';
            K.fitOneLine(ctx, opt.note, w - 40, 28, 20, '600');
            ctx.fillText(opt.note, x + w / 2, y + h * 0.82);
        }
    }

    // ---------- Card 1: Panchang ----------
    function drawPanchang(c) {
        const ctx = c.getContext('2d'); const L = t();
        K.reset(ctx); bg(ctx); header(ctx, L.head);

        let y = 290;
        const line = [form.samvat, [form.maas, L.paksha[form.paksha] || ''].filter(Boolean).join(' ')].filter(s => s && s.trim()).join('  •  ');
        if (line) {
            ctx.fillStyle = 'rgba(255,255,255,0.9)';
            K.fitOneLine(ctx, num(line), W - 160, 40, 26, '600');
            ctx.fillText(num(line), W / 2, y + 10); y += 60;
        }

        if (form.festival) {
            const fh = 110;
            const g = ctx.createLinearGradient(80, 0, W - 80, 0);
            g.addColorStop(0, '#f59e0b'); g.addColorStop(0.5, '#fde047'); g.addColorStop(1, '#f59e0b');
            ctx.fillStyle = g; ctx.beginPath(); ctx.roundRect(80, y, W - 160, fh, 55); ctx.fill();
            ctx.fillStyle = '#7c2d12';
            const ft = '🪔 ' + form.festival + ' 🪔';
            K.fitOneLine(ctx, ft, W - 240, 60, 34);
            ctx.fillText(ft, W / 2, y + fh / 2 + 3); y += fh + 30;
        } else { y += 10; }

        // Tithi / nakshatra / yog / karan / chandra — sirf bhare hue
        const tithiVal = [L.paksha[form.paksha] || '', form.tithi].filter(Boolean).join(' ');
        const info = [[L.tithi, form.tithi ? tithiVal : ''], [L.nakshatra, form.nakshatra], [L.yog, form.yog], [L.karan, form.karan], [L.chandra, form.chandra]]
            .filter(([, v]) => v && v.trim());
        const bw = (W - 160 - 30) / 2, bh = 135;
        info.forEach(([lab, val], i) => {
            const full = info.length % 2 === 1 && i === info.length - 1;
            const x = full ? 80 : 80 + (i % 2) * (bw + 30);
            box(ctx, x, y + Math.floor(i / 2) * (bh + 22), full ? W - 160 : bw, bh, lab, num(val));
        });
        if (info.length) y += Math.ceil(info.length / 2) * (bh + 22) + 8;

        // Sunrise / sunset
        box(ctx, 80, y, bw, bh, '🌅 ' + L.sunrise, tm(data.sunrise));
        box(ctx, 80 + bw + 30, y, bw, bh, '🌇 ' + L.sunset, tm(data.sunset));
        y += bh + 22;

        // Rahu kaal + Abhijit
        box(ctx, 80, y, bw, 160, '⚠️ ' + L.rahu, range(data.rahu), { fill: 'rgba(239,68,68,0.20)', stroke: '#ef4444', labelColor: '#fecaca', note: L.rahuNote, size: 44 });
        box(ctx, 80 + bw + 30, y, bw, 160, '🌞 ' + L.abhijit, range(data.abhijit), { fill: 'rgba(34,197,94,0.20)', stroke: '#22c55e', labelColor: '#bbf7d0', note: data.abhijit.wednesday ? L.abhijitWed : L.abhijitNote, size: 44 });
        y += 160 + 26;

        // Shubh muhurat
        const lines = (form.muhurat || '').split(/\n/).map(s => s.trim()).filter(Boolean).slice(0, 4);
        const footerTop = 1740;
        if (lines.length && y < footerTop - 150) {
            const mh = Math.min(footerTop - 20 - y, 90 + lines.length * 66);
            ctx.fillStyle = 'rgba(250, 204, 21, 0.14)'; ctx.strokeStyle = '#facc15'; ctx.lineWidth = 3;
            ctx.beginPath(); ctx.roundRect(80, y, W - 160, mh, 30); ctx.fill(); ctx.stroke();
            ctx.fillStyle = '#facc15'; K.fitOneLine(ctx, '✨ ' + L.muhurat + ' ✨', W - 240, 42, 28);
            ctx.fillText('✨ ' + L.muhurat + ' ✨', W / 2, y + 48);
            const lh = Math.min(66, (mh - 90) / lines.length);
            lines.forEach((ln, i) => {
                const [a, b] = ln.includes('=') ? ln.split('=').map(s => s.trim()) : [ln, ''];
                const txt = b ? a + ':  ' + b : a;
                ctx.fillStyle = '#ffffff'; K.fitOneLine(ctx, num(txt), W - 240, 40, 24, '600');
                ctx.fillText(num(txt), W / 2, y + 100 + i * lh + lh / 2 - 10);
            });
        }

        footer(ctx);
    }

    function footer(ctx) {
        const L = t();
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillStyle = 'rgba(255,255,255,0.7)';
        K.fitOneLine(ctx, '📍 ' + L.cityNote(cityName()), W - 160, 32, 22, '600');
        ctx.fillText('📍 ' + L.cityNote(cityName()), W / 2, 1790);
        ctx.fillStyle = '#facc15';
        K.fitOneLine(ctx, L.cta, W - 160, 46, 30);
        ctx.fillText(L.cta, W / 2, 1860);
    }

    // ---------- Card 2: Choghadiya ----------
    function drawChoghadiya(c) {
        const ctx = c.getContext('2d'); const L = t();
        K.reset(ctx); bg(ctx); header(ctx, L.chog);

        const colors = { 1: ['rgba(34,197,94,0.22)', '#22c55e', '#bbf7d0'], 0: ['rgba(59,130,246,0.20)', '#60a5fa', '#bfdbfe'], '-1': ['rgba(239,68,68,0.18)', '#ef4444', '#fecaca'] };
        const section = (title, slots, top) => {
            ctx.textAlign = 'center'; ctx.fillStyle = '#facc15';
            K.fitOneLine(ctx, title, W - 160, 44, 28); ctx.fillText(title, W / 2, top);
            const rh = 70, gap = 10;
            slots.forEach((s, i) => {
                const y = top + 42 + i * (rh + gap);
                const [fill, stroke, tagC] = colors[s.good];
                ctx.fillStyle = fill; ctx.strokeStyle = stroke; ctx.lineWidth = 2.5;
                ctx.beginPath(); ctx.roundRect(70, y, W - 140, rh, 20); ctx.fill(); ctx.stroke();
                ctx.textBaseline = 'middle';
                ctx.textAlign = 'left'; ctx.fillStyle = '#ffffff'; ctx.font = K.font(40);
                ctx.fillText(L.names[s.name], 105, y + rh / 2 + 2);
                ctx.fillStyle = tagC; ctx.font = K.font(28, '600');
                ctx.fillText(L.tag[s.good], 330, y + rh / 2 + 2);
                ctx.textAlign = 'right'; ctx.fillStyle = '#ffffff'; ctx.font = K.font(40, '600');
                ctx.fillText(range(s), W - 105, y + rh / 2 + 2);
            });
            return top + 42 + slots.length * (rh + gap);
        };
        const end = section(L.day, data.day, 320);
        section(L.night, data.night, end + 50);
        footer(ctx);
    }

    function caption() {
        const L = t(), [day, date] = dateParts();
        const good = data.day.filter(s => s.good === 1).map(s => L.names[s.name] + ' ' + range(s)).join('\n');
        return `📅 ${L.head} — ${day}, ${date}` + (form.festival ? `\n🪔 ${form.festival}` : '') +
            (form.tithi ? `\n🌙 ${L.tithi}: ${[L.paksha[form.paksha] || '', form.tithi].filter(Boolean).join(' ')}` : '') +
            `\n🌅 ${L.sunrise}: ${tm(data.sunrise)}  🌇 ${L.sunset}: ${tm(data.sunset)}` +
            `\n⚠️ ${L.rahu}: ${range(data.rahu)}` +
            `\n\n✨ ${L.day}:\n${good}` +
            `\n\n${L.cta}\n📍 ${L.cityNote(cityName())}` +
            '\n\n#panchang #aajnupanchang #choghadiya #shubhmuhurat #gujarati #rashifal #hindupanchang';
    }

    const readForm = () => {
        form = {};
        ['date', 'city', 'festival', 'samvat', 'maas', 'paksha', 'tithi', 'nakshatra', 'yog', 'karan', 'chandra', 'muhurat'].forEach(k => form[k] = el(k).value.trim());
        lang = el('language').value;
    };

    function render() {
        const grid = el('previewGrid'); grid.innerHTML = ''; grid.classList.remove('hidden');
        [drawPanchang, drawChoghadiya].forEach((fn, i) => {
            const c = document.createElement('canvas'); c.width = W; c.height = H; fn(c);
            c.className = 'w-full rounded-lg border border-slate-200 shadow';
            const wrap = document.createElement('div'); wrap.appendChild(c);
            const cap = document.createElement('p'); cap.className = 'text-xs text-center text-slate-500 mt-1'; cap.textContent = i ? 'Card 2 — Choghadiya' : 'Card 1 — Panchang';
            wrap.appendChild(cap); grid.appendChild(wrap);
        });
    }

    async function postJson(url, body) {
        const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: JSON.stringify(body) });
        const d = await r.json();
        if (!d.ok) throw new Error(K.saveError(d));
        return d;
    }

    el('genBtn').addEventListener('click', async () => {
        readForm();
        if (!form.date) { alert('Date chuno'); return; }
        el('msg').textContent = '⏳ Ganit ho raha hai…';
        try {
            data = await postJson(COMPUTE_URL, { date: form.date, city: form.city });
            await K.ensureFonts();
            render();
            el('saveBtn').disabled = false;
            el('msg').textContent = '✓ Card ready — check karke Save karo.';
        } catch (e) { el('msg').textContent = '⚠ ' + e.message; }
    });
    // Language/samvat default
    el('language').addEventListener('change', () => {
        if (/^(વિક્રમ સંવત|विक्रम संवत)/.test(el('samvat').value)) el('samvat').value = el('language').value === 'hindi' ? 'विक्रम संवत 2082' : 'વિક્રમ સંવત ૨૦૮૨';
    });

    el('saveBtn').addEventListener('click', async () => {
        if (!data) return;
        const btn = el('saveBtn'); btn.disabled = true; el('msg').textContent = '⏳ Save ho raha hai…';
        try {
            await K.ensureFonts();
            const off = document.createElement('canvas'); off.width = W; off.height = H;
            const cap = caption();
            const cards = [drawPanchang, drawChoghadiya].map((fn, i) => {
                fn(off);
                return { order: i + 1, text: i ? t().chog : t().head, caption: cap, image: off.toDataURL('image/webp', 0.92) };
            });
            const d = await postJson(SAVE_URL, { date: form.date, language: lang, cards });
            window.location = d.redirect;
        } catch (e) { el('msg').textContent = '⚠ Save fail: ' + e.message; btn.disabled = false; }
    });

    window.__panchangTest = { readForm, render, setData: d => { data = d; } };
})();
</script>
@endpush
