@extends('layouts.admin')
@section('title', '🔞 Adult Kahani — 1 Card Story')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-rose-700 via-pink-600 to-fuchsia-700 rounded-2xl p-6 text-white shadow-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="inline-block px-2.5 py-1 bg-white/20 rounded-full text-xs font-semibold uppercase tracking-wider mb-2">🔞 18+ Romantic · 1 Card = 1 Poori Kahani</span>
                <h1 class="text-2xl font-bold">Adult Kahani — Ek Card ma Aakhi Story</h1>
                <p class="text-rose-100 text-sm mt-1">Title → romantic kahani (~150–200 shabd, mota font ma aakhu card bharay): dekhav/figure, hoth-gardan par kiss, aalingan → pachhi fade-to-black. Sharirik drashya ishaara ma j — IG/FB par safe.</p>
            </div>
            <button id="saveBtn" disabled class="bg-white text-amber-800 font-bold px-5 py-2.5 rounded-xl shadow hover:bg-amber-50 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-2">
                <span>💾</span> Save All Cards
            </button>
        </div>
    </div>

    {{-- Controls --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-4">
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
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🏷️ Category</label>
                <select id="category" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-medium">
                    <option value="">✍️ Koi category nahi — fakt Topic par</option>
                    <option value="Mix — pick any adult romance / affair / first-time theme, different for every card" selected>🎲 Mix — badha topic (random)</option>
                    <optgroup label="💋 Pehli vaar">
                        <option value="First kiss between two adults — the nervousness, the moment and what changed after">💋 Pehlo Kiss</option>
                        <option value="Suhagraat — first night of an arranged marriage, two near-strangers, nervousness turning into trust and closeness">🌙 Suhagraat (Pehli Raat)</option>
                        <option value="First time getting intimate in a love relationship — nervousness, trust, consent">🔥 Pehli vaar ni nazdeeki</option>
                        <option value="Honeymoon trip of a newly married couple (hill station / Goa / Udaipur) — shyness melting away">🏔️ Honeymoon</option>
                        <option value="First date that turned into an unforgettable night">🍷 Pehli Date</option>
                    </optgroup>
                    <optgroup label="💔 Affair">
                        <option value="How an extramarital affair begins — one weak moment, temptation, guilt and consequences">💔 Affair ni sharuaat — ek kamzor pal</option>
                        <option value="Late night working in office, attraction between two adult colleagues crosses the limit">🏢 Office ma moda sudhi kaam</option>
                        <option value="Affair with the boss / senior at work — power, attraction and guilt">💼 Boss sathe sambandh</option>
                        <option value="Attraction to the neighbour while the spouse is away (adult married neighbours)">🏘️ Padosi sathe akarshan</option>
                        <option value="Husband away for work / abroad, lonely wife gets close to someone — guilt and choice">✈️ Pati bahar, patni ekli</option>
                        <option value="Wife busy / at her parents' home, husband gets close to someone else">🧳 Patni piyar, pati ekla</option>
                        <option value="Business trip / hotel stay where two adult colleagues get close">🏨 Business trip / Hotel</option>
                        <option value="Secret affair hidden from family — almost caught">🤫 Chhupo sambandh — pakdai jata bachya</option>
                        <option value="Online chat with a stranger turns into a real-life meeting">📲 Online chat thi mulakat</option>
                        <option value="Spouse's best friend — forbidden attraction between adults">👫 Patni / Pati no best friend</option>
                    </optgroup>
                    <optgroup label="❤️ Prem / Romance">
                        <option value="Meeting first love / ex again after years, both married now — one evening together">🔙 Varso pachhi pehlo prem</option>
                        <option value="College reunion — old crush, old feelings, one night">🎓 College reunion</option>
                        <option value="Husband and wife rekindling passion after years of routine and distance">💑 Pati-Patni no farithi prem</option>
                        <option value="Long-distance couple finally meeting after months apart">🛫 Long distance milan</option>
                        <option value="Monsoon evening romance — stuck together in the rain">🌧️ Varsad ni saanj</option>
                        <option value="Navratri garba night romance between two adults">💃 Garba ni raat</option>
                        <option value="Train / bus overnight journey — two adult strangers, one night of connection">🚆 Raat ni musafari</option>
                        <option value="Divorcee or widow/widower finding love and closeness again">🌹 Bijo moko — divorce / vidhva pachhi prem</option>
                        <option value="Age-gap romance between two adults (older woman / younger man or vice versa)">⏳ Age-gap prem</option>
                        <option value="Live-in relationship / secret relationship before marriage">🏠 Live-in / lagn pehla no sambandh</option>
                    </optgroup>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🔢 Kahani Count</label>
                <select id="count" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-medium">
                    <option value="1">1 Kahani</option>
                    <option value="3" selected>3 Kahani</option>
                    <option value="5">5 Kahani</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🏷 Page / Brand Name</label>
                <input id="brand" type="text" maxlength="30" placeholder="e.g. પ્રેમની વાતો"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-medium">
            </div>
        </div>
        <div class="grid sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">📄 1 Kahani = Ketla Card</label>
                <select id="parts" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-medium">
                    <option value="1" selected>1 Card (aakhi kahani)</option>
                    <option value="2">2 Card (Bhaag 1–2)</option>
                    <option value="3">3 Card (Bhaag 1–3)</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">✍️ Topic (optional — category khali hoy to jaruri)</label>
                <input id="topic" type="text" maxlength="200" placeholder="e.g. વિસનગરની ઓફિસમાં મોડે સુધી કામ / ટ્રેનમાં મળેલી અજાણી સ્ત્રી"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
            </div>
            <div class="flex items-end">
                <button id="genBtn" class="w-full bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white font-bold py-2 px-4 rounded-lg shadow transition">
                    <span id="genText">Generate Cards</span>
                </button>
            </div>
        </div>

        <div id="progressBox" class="hidden pt-2">
            <div class="text-xs font-semibold text-slate-600 mb-1"><span id="progressText">AI likh raha hai… (20–60 sec)</span></div>
            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div id="progressBar" class="bg-gradient-to-r from-amber-500 to-orange-600 h-2 transition-all duration-300" style="width: 0%"></div>
            </div>
        </div>
    </div>

    <div id="emptyState" class="bg-white rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center text-slate-400">
        <span class="text-4xl block mb-2">🔞</span>
        <p class="font-bold text-slate-600">Abhi koi kahani card nahi bana</p>
        <p class="text-xs mt-1">"Generate Cards" dabao — har card ke neeche ✏️ Edit se text sudhaar sakte ho.</p>
    </div>

    <div id="previewGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 hidden"></div>

    {{-- Saved collections --}}
    <div class="mt-10 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <form id="bulkForm" method="POST" action="{{ route('admin.stories.bulk_destroy') }}">
            @csrf
            <div class="flex items-center justify-between gap-4 mb-4 flex-wrap pb-3 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                    <span>💾</span> Saved Kahani Collections ({{ $collections->count() }})
                </h3>
                @if ($collections->isNotEmpty())
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-600 cursor-pointer select-none">
                            <input type="checkbox" id="selectAll" class="rounded border-slate-300 accent-amber-600 w-4 h-4 cursor-pointer">
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
                <div class="flex items-center justify-between bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 mb-2.5 hover:border-amber-400 hover:bg-amber-50/20 transition gap-3">
                    <input type="checkbox" name="ids[]" value="{{ $c->id }}" class="bulk-item rounded border-slate-300 accent-amber-600 w-4 h-4 cursor-pointer">
                    <a href="{{ route('admin.romance.show', $c) }}" class="flex-1 flex items-center justify-between gap-3 group">
                        <div>
                            <span class="font-bold text-slate-800 group-hover:text-amber-700 transition">🔞 {{ $c->title }}</span>
                            <span class="text-xs text-slate-500 ml-2">({{ ucfirst($c->language ?? 'gujarati') }})</span>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 bg-amber-100 text-amber-800 rounded-full">
                            {{ $c->parts_count }} cards · {{ ucfirst($c->status) }}
                        </span>
                    </a>
                    <button type="button" onclick="if(confirm('Kya aap sach me ye collection delete karna chahte hain?')) document.getElementById('single-del-{{ $c->id }}').submit();" class="text-xs font-semibold text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                        <span>🗑</span> Delete
                    </button>
                </div>
            @empty
                <p class="text-sm text-slate-500 text-center py-4">Abhi koi kahani collection save nahi hai — upar se navi banavo! 🔞</p>
            @endforelse
        </form>

        @foreach ($collections as $c)
            <form id="single-del-{{ $c->id }}" method="POST" action="{{ route('admin.romance.destroy', $c) }}" class="hidden">
                @csrf @method('DELETE')
            </form>
        @endforeach
    </div>
</div>

@push('scripts')
@include('admin.partials._canvas_kit')
@include('admin.partials._category_multi')
<script>
(function() {
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const GEN_URL = @json(route('admin.romance.generate'));
    const SAVE_URL = @json(route('admin.romance.save'));

    const K = window.CanvasKit;
    const W = 1080, H = 1920;
    let items = [];
    let itemsLang = 'gujarati'; // jis language me items generate hue — labels bhi usi me
    let itemsCategory = '';
    const el = id => document.getElementById(id);
    // 🏷️ Kai category ek saath (checkbox dropdown)
    const cat = window.MultiCategory(el('category'));

    const I18N = {
        gujarati: { q: 'પ્રશ્ન:', a: 'જવાબ:', s: 'સમાધાન:', brand: 'દિલની વાત', digits: '૦૧૨૩૪૫૬૭૮૯',
            part: 'ભાગ', next: '👉 આગળ વાંચવા માટે ફોલો કરો', nextSub: 'ભાગ {n} જલ્દી આવશે…', end: '❤️ આવી વધુ કહાનીઓ માટે ફોલો કરો', endSub: 'કહાની ગમી હોય તો શેર કરો' },
        hindi:    { q: 'प्रश्न:', a: 'जवाब:', s: 'समाधान:', brand: 'दिल की बात', digits: '०१२३४५६७८९',
            part: 'भाग', next: '👉 आगे पढ़ने के लिए फॉलो करें', nextSub: 'भाग {n} जल्द आएगा…', end: '❤️ ऐसी और कहानियों के लिए फॉलो करें', endSub: 'कहानी पसंद आई हो तो शेयर करें' },
        hinglish: { q: 'Sawaal:', a: 'Jawab:', s: 'Samadhan:', brand: 'Dil Ki Baat', digits: '0123456789',
            part: 'Part', next: '👉 Aage padhne ke liye Follow karo', nextSub: 'Part {n} jald aayega…', end: '❤️ Aisi aur kahaniyon ke liye Follow karo', endSub: 'Kahani pasand aayi ho to Share karo' },
    };
    const tr = () => I18N[itemsLang] || I18N.gujarati;
    /** "ભાગ ૧/૩" */
    const partLabel = item => tr().part + ' ' + num(item.part) + '/' + num(item.total);
    const num = n => String(n).replace(/\d/g, d => tr().digits[d]);
    const brandName = () => el('brand').value.trim() || tr().brand;

    // Brand naam yaad rakho (sirf is browser me)
    try { el('brand').value = localStorage.getItem('romanceBrand') || ''; } catch (e) {}
    el('brand').addEventListener('input', () => {
        try { localStorage.setItem('romanceBrand', el('brand').value); } catch (e) {}
        if (items.length) renderAllPreviews();
    });

    const solutionsOf = item => (Array.isArray(item.solutions) ? item.solutions : [])
        .map(s => typeof s === 'string' ? { heading: '', text: s } : { heading: K.str(s.heading), text: K.str(s.text) })
        .filter(s => s.heading || s.text);

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
                body: JSON.stringify({ count: el('count').value, language: el('language').value, categories: cat.values(), topic: el('topic').value, style: 'kahani', parts: el('parts').value })
            });
            const d = await r.json();
            if (!d.ok) throw new Error(K.saveError(d));

            // 2-3 card: har bhaag = ek card (group = kahani no., part/total = Bhaag 1/3)
            items = (Array.isArray(d.items) ? d.items : []).flatMap((it, k) => Array.isArray(it.parts) && it.parts.length > 1
                ? it.parts.map((p, j) => ({ ...it, style: 'kahani', story: p, part: j + 1, total: it.parts.length, group: k + 1 }))
                : [{ ...it, style: 'kahani', story: it.story || (it.parts || [])[0] || '' }]);
            if (!items.length) throw new Error('AI ne koi card nahi diya — dobara try karo.');
            itemsLang = el('language').value;
            // Story ka naam / category: pasand kari category na naam (emoji vagar), " + " thi jodi
            itemsCategory = cat.labels().map(l => l.replace(/^\S+\s/, '')).join(' + ').slice(0, 250);
            el('progressBar').style.width = '80%';
            await K.ensureFonts();
            renderAllPreviews();

            el('progressBar').style.width = '100%';
            el('saveBtn').disabled = false;
            setTimeout(() => el('progressBox').classList.add('hidden'), 500);
        } catch (e) {
            alert('Error: ' + e.message);
            el('progressBox').classList.add('hidden');
        } finally {
            btn.disabled = false;
            el('genText').textContent = 'Generate Cards';
        }
    });

    // ---------- Previews + edit ----------
    const FIELDS = [
        ['title', 'Title', 2], ['question', 'Prashna (sawaal)', 5], ['asker', 'Signature', 1],
        ['answer', 'Jawab', 4], ['solutions', 'Samadhan (har line: heading | text)', 5],
        ['takeaway', 'Takeaway', 2], ['cta_question', 'Comment question', 2],
    ];
    const KAHANI_FIELDS = [
        // Card par sirf title + kahani
        ['title', 'Title', 2], ['story', 'Kahani (paragraph ke beech khaali line)', 12],
    ];

    function renderAllPreviews() {
        el('emptyState').classList.add('hidden');
        const grid = el('previewGrid');
        grid.classList.remove('hidden');
        grid.innerHTML = '';

        items.forEach((item, idx) => {
            const box = document.createElement('div');
            box.className = 'bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm flex flex-col';
            box.innerHTML = `
                <div class="p-3 bg-amber-50 border-b border-amber-100 flex items-center justify-between gap-2 text-xs font-bold text-amber-900">
                    <span class="truncate">#${idx + 1}: ${K.esc(item.title)}${item.total > 1 ? ' — ' + K.esc(partLabel(item)) : ''}</span>
                    <button type="button" class="remove shrink-0 text-red-600 hover:underline">✕</button>
                </div>
                <div class="p-3 canvas-slot"></div>
                <details class="border-t border-slate-100 text-xs">
                    <summary class="px-3 py-2 cursor-pointer font-semibold text-slate-600 hover:bg-slate-50">✏️ Edit text</summary>
                    <div class="p-3 space-y-2 fields"></div>
                </details>`;

            const canvas = document.createElement('canvas');
            canvas.width = W; canvas.height = H;
            canvas.className = 'w-full aspect-[9/16] rounded-lg';
            box.querySelector('.canvas-slot').appendChild(canvas);
            drawSalah(canvas, item);

            const fields = box.querySelector('.fields');
            (item.style === 'kahani' ? KAHANI_FIELDS : FIELDS).forEach(([key, label, rows]) => {
                const value = key === 'solutions'
                    ? solutionsOf(item).map(s => s.heading + ' | ' + s.text).join('\n')
                    : K.str(item[key]);
                const wrap = document.createElement('label');
                wrap.className = 'block';
                wrap.innerHTML = `<span class="font-semibold text-slate-500">${label}</span>
                    <textarea rows="${rows}" class="mt-0.5 w-full rounded border border-slate-300 px-2 py-1 text-xs"></textarea>`;
                const ta = wrap.querySelector('textarea');
                ta.value = value;
                ta.addEventListener('input', () => {
                    item[key] = key === 'solutions'
                        ? ta.value.split('\n').map(l => l.trim()).filter(Boolean).map(l => {
                            const [h, ...rest] = l.split('|');
                            return rest.length ? { heading: h.trim(), text: rest.join('|').trim() } : { heading: '', text: h.trim() };
                        })
                        : ta.value;
                    drawSalah(canvas, item);
                });
                fields.appendChild(wrap);
            });

            box.querySelector('.remove').addEventListener('click', () => {
                items.splice(idx, 1);
                el('saveBtn').disabled = !items.length;
                renderAllPreviews();
            });
            grid.appendChild(box);
        });
    }

    // ---------- Card drawing ----------

    /** Chhota deterministic random — har render me same kaagaz texture. */
    function rng(seed) {
        return () => (seed = (seed * 16807) % 2147483647) / 2147483647;
    }

    // 🎨 Bhasha ke hisaab se kaagaz — har bhasha ka account alag dikhe.
    // Gujarati = purana peela kaagaz. Hindi = gulabi ruled notebook. English/Hinglish = saaf neela-saleti card + bindu jaali.
    const PAPER = {
        gujarati: { hl: 'rgba(253, 224, 71, 0.92)', title: '#1c140b', ink: '#21180e' },
        hindi:    { hl: 'rgba(251, 113, 133, 0.42)', title: '#3b0a1e', ink: '#1f1b3a' },
        hinglish: { hl: 'rgba(45, 212, 191, 0.45)', title: '#0f2a2e', ink: '#0f172a' },
    };
    const pp = () => PAPER[itemsLang] || PAPER.gujarati;

    /** Hindi: gulabi notebook — neeli rekhayein, laal double margin, kono me phool, beech me dil wala watermark. */
    function drawPaperHindi(ctx) {
        const g = ctx.createLinearGradient(0, 0, 0, H);
        g.addColorStop(0, '#fff8f6'); g.addColorStop(0.6, '#fdecea'); g.addColorStop(1, '#f6d3cf');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);
        ctx.save();
        ctx.strokeStyle = 'rgba(59, 130, 246, 0.13)'; ctx.lineWidth = 2;
        for (let y = 150; y < H - 40; y += 64) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke(); }
        ctx.strokeStyle = 'rgba(225, 29, 72, 0.35)'; ctx.lineWidth = 3;
        [44, 54].forEach(x => { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, H); ctx.stroke(); });
        // Kono me chhote phool
        const flower = (cx, cy, r) => {
            ctx.fillStyle = 'rgba(225, 29, 72, 0.22)';
            for (let p = 0; p < 6; p++) {
                const a = (p / 6) * Math.PI * 2;
                ctx.beginPath(); ctx.ellipse(cx + Math.cos(a) * r, cy + Math.sin(a) * r, r * 0.75, r * 0.42, a, 0, Math.PI * 2); ctx.fill();
            }
            ctx.fillStyle = 'rgba(234, 88, 12, 0.35)';
            ctx.beginPath(); ctx.arc(cx, cy, r * 0.45, 0, Math.PI * 2); ctx.fill();
        };
        flower(W - 70, 70, 26); flower(W - 70, H - 70, 26);
        // Watermark: dil + brand
        ctx.globalAlpha = 0.07;
        ctx.fillStyle = '#be123c';
        const cx = W / 2, cy = H * 0.55, s = 300;
        ctx.beginPath();
        ctx.moveTo(cx, cy + s * 0.9);
        ctx.bezierCurveTo(cx - s * 1.5, cy - s * 0.1, cx - s * 0.7, cy - s * 1.1, cx, cy - s * 0.45);
        ctx.bezierCurveTo(cx + s * 0.7, cy - s * 1.1, cx + s * 1.5, cy - s * 0.1, cx, cy + s * 0.9);
        ctx.fill();
        ctx.globalAlpha = 0.12;
        ctx.fillStyle = '#881337';
        K.fitDraw(ctx, brandName(), cx, cy - 20, { maxW: 420, maxH: 300, size: 100, minSize: 46, lineHeight: 1.2 });
        ctx.restore();
    }

    /** English/Hinglish: saaf modern card — bindu jaali, firozi frame + upar patti, gol-chaukor watermark. */
    function drawPaperEnglish(ctx) {
        const g = ctx.createLinearGradient(0, 0, W, H);
        g.addColorStop(0, '#f8fafc'); g.addColorStop(0.6, '#eef2f7'); g.addColorStop(1, '#d9e2ec');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);
        ctx.save();
        ctx.fillStyle = 'rgba(15, 23, 42, 0.08)';
        for (let y = 40; y < H; y += 36) for (let x = 40; x < W; x += 36) { ctx.beginPath(); ctx.arc(x, y, 1.6, 0, Math.PI * 2); ctx.fill(); }
        ctx.strokeStyle = 'rgba(13, 148, 136, 0.55)'; ctx.lineWidth = 4;
        ctx.beginPath(); ctx.roundRect(24, 24, W - 48, H - 48, 28); ctx.stroke();
        ctx.fillStyle = '#0d9488';
        ctx.beginPath(); ctx.roundRect(W / 2 - 90, 58, 180, 8, 4); ctx.fill();
        ctx.globalAlpha = 0.07;
        ctx.strokeStyle = '#0f766e';
        ctx.lineWidth = 14;
        ctx.beginPath(); ctx.roundRect(W / 2 - 320, H * 0.55 - 320, 640, 640, 90); ctx.stroke();
        ctx.lineWidth = 5;
        ctx.beginPath(); ctx.roundRect(W / 2 - 280, H * 0.55 - 280, 560, 560, 70); ctx.stroke();
        ctx.fillStyle = '#0f766e';
        K.fitDraw(ctx, brandName(), W / 2, H * 0.55, { maxW: 480, maxH: 360, size: 110, minSize: 50, lineHeight: 1.2 });
        ctx.restore();
    }

    function drawPaper(ctx) {
        if (itemsLang === 'hindi') return drawPaperHindi(ctx);
        if (itemsLang === 'hinglish') return drawPaperEnglish(ctx);
        const g = ctx.createRadialGradient(W / 2, H * 0.45, 200, W / 2, H / 2, H * 0.75);
        g.addColorStop(0, '#f7ecd3');
        g.addColorStop(0.6, '#ead6ad');
        g.addColorStop(1, '#c9a46b');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);

        // Kaagaz ke daag / dots
        const r = rng(42);
        for (let i = 0; i < 2600; i++) {
            ctx.fillStyle = r() < 0.5 ? 'rgba(120,85,40,0.07)' : 'rgba(255,255,255,0.10)';
            ctx.fillRect(r() * W, r() * H, 1 + r() * 3, 1 + r() * 3);
        }

        // Beech me halka watermark
        ctx.save();
        ctx.globalAlpha = 0.07;
        ctx.strokeStyle = '#5b3a12';
        ctx.lineWidth = 14;
        ctx.beginPath(); ctx.arc(W / 2, H * 0.55, 330, 0, Math.PI * 2); ctx.stroke();
        ctx.lineWidth = 5;
        ctx.beginPath(); ctx.arc(W / 2, H * 0.55, 290, 0, Math.PI * 2); ctx.stroke();
        ctx.fillStyle = '#5b3a12';
        K.fitDraw(ctx, brandName(), W / 2, H * 0.55, { maxW: 480, maxH: 360, size: 110, minSize: 50, lineHeight: 1.2 });
        ctx.restore();
    }

    /** Title: har line ke peeche peela highlighter. Return: title ka bottom y. */
    function drawTitle(ctx, title, top) {
        const text = '"' + String(title || '').trim() + '"';
        const r = K.fit(ctx, text, { maxW: W - 140, maxH: 330, size: 54, minSize: 34, lineHeight: 1.32 });
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        r.lines.forEach((line, i) => {
            const y = top + r.lh * (i + 0.5);
            const w = ctx.measureText(line).width;
            ctx.fillStyle = pp().hl;
            ctx.fillRect(W / 2 - w / 2 - 16, y - r.lh / 2 + 4, w + 32, r.lh - 4);
            ctx.fillStyle = pp().title;
            ctx.fillText(line, W / 2, y);
        });
        return top + r.lines.length * r.lh;
    }

    function drawSalah(c, item) {
        if (item.style === 'kahani' && item.total > 1) return drawSeriesCard(c, item); // 2-3 card wali kahani ka alag design
        const ctx = c.getContext('2d');
        const L = tr();
        K.reset(ctx);

        drawPaper(ctx);

        const series = item.style === 'kahani' && item.total > 1;
        if (series) drawPartPill(ctx, partLabel(item));
        const titleBottom = drawTitle(ctx, item.title, series ? 130 : 110);

        // Body paragraphs
        const sol = solutionsOf(item);
        const solSegs = [{ t: L.s, b: true }];
        sol.forEach((s, i) => {
            if (s.heading) solSegs.push({ t: num(i + 1) + '. ' + s.heading + ':', b: true });
            else solSegs.push({ t: num(i + 1) + '.', b: true });
            solSegs.push({ t: s.text, b: false });
        });

        const paras = item.style === 'kahani' ? [
            // Sirf kahani — koi signature / takeaway / comment question nahi
            ...K.str(item.story).split(/\n\s*\n|\n/).map(p => p.trim()).filter(Boolean).map(p => [{ t: p, b: false }]),
        ] : [
            [{ t: L.q, b: true }, { t: '"' + K.str(item.question).trim() + '"', b: false }, ...(item.asker ? [{ t: '— ' + K.str(item.asker), b: true }] : [])],
            [{ t: L.a, b: true }, { t: K.str(item.answer), b: false }],
            sol.length ? solSegs : null,
            item.takeaway ? [{ t: brandName() + ': ' + K.str(item.takeaway), b: true }] : null,
            item.cta_question ? [{ t: '(' + K.str(item.cta_question) + ')', b: true }] : null,
        ].filter(Boolean);

        const x = 70, maxW = W - 140;
        const top = titleBottom + 30, bottom = series ? H - 250 : H - 120;
        ctx.fillStyle = pp().ink;
        // Kahani: sabse bada font jo fit ho + bachi jagah line/para ke beech — card neeche tak bhara dikhe
        const layout = item.style === 'kahani'
            ? fillRich(ctx, paras, maxW, bottom - top)
            : K.fitRich(ctx, paras, maxW, bottom - top, 44);
        K.drawRich(ctx, layout, x, top);
        if (series) drawFollowBar(ctx, item);
    }

    // ---------- 📚 2-3 card wali kahani: alag design (raat jaisa gehra, gulabi chamak) ----------
    const SERIES = {
        gujarati: { glow: '236, 72, 153', accent: '#f472b6', accent2: '#fbcfe8', bg: ['#3a0b24', '#1c0614', '#0a0208'] },
        hindi:    { glow: '225, 29, 72', accent: '#fb7185', accent2: '#fecdd3', bg: ['#3d0a16', '#1d050c', '#0b0205'] },
        hinglish: { glow: '139, 92, 246', accent: '#a78bfa', accent2: '#ddd6fe', bg: ['#24104a', '#120826', '#06030f'] },
    };
    function drawSeriesCard(c, item) {
        const ctx = c.getContext('2d');
        const L = tr(), T = SERIES[itemsLang] || SERIES.gujarati;
        K.reset(ctx);

        // Background: gehra radial + dhundhli roshni ke gole (bokeh)
        const g = ctx.createRadialGradient(W / 2, H * 0.38, 80, W / 2, H / 2, H * 0.8);
        g.addColorStop(0, T.bg[0]); g.addColorStop(0.55, T.bg[1]); g.addColorStop(1, T.bg[2]);
        ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
        const r = rng(97 + (item.group || 1) * 13);
        for (let i = 0; i < 26; i++) {
            const x = r() * W, y = r() * H, rad = 30 + r() * 110;
            const bg = ctx.createRadialGradient(x, y, 0, x, y, rad);
            bg.addColorStop(0, `rgba(${T.glow}, ${0.10 + r() * 0.12})`); bg.addColorStop(1, `rgba(${T.glow}, 0)`);
            ctx.fillStyle = bg; ctx.beginPath(); ctx.arc(x, y, rad, 0, Math.PI * 2); ctx.fill();
        }

        // Bhaag ki ribbon
        ctx.save();
        const pl = partLabel(item);
        const ps = K.fitOneLine(ctx, pl, 420, 40, 26);
        const pw = ctx.measureText(pl).width + 90, ph = ps + 30, py = 60;
        const rg = ctx.createLinearGradient(W / 2 - pw / 2, 0, W / 2 + pw / 2, 0);
        rg.addColorStop(0, T.accent); rg.addColorStop(1, T.accent2);
        ctx.fillStyle = rg;
        ctx.beginPath(); ctx.roundRect(W / 2 - pw / 2, py, pw, ph, ph / 2); ctx.fill();
        ctx.fillStyle = T.bg[2]; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(pl, W / 2, py + ph / 2 + 2);
        ctx.restore();

        // Title: safed, gulabi chamak, neeche patli lakeer
        ctx.save();
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        const tf = K.fit(ctx, String(item.title || '').trim(), { maxW: W - 150, maxH: 200, size: 62, minSize: 40, lineHeight: 1.22 });
        ctx.shadowColor = `rgba(${T.glow}, 0.8)`; ctx.shadowBlur = 26;
        ctx.fillStyle = '#ffffff';
        const ty = 165 + (tf.lines.length * tf.lh) / 2;
        K.drawLines(ctx, tf.lines, W / 2, ty, tf.lh);
        ctx.restore();
        const lineY = 165 + tf.lines.length * tf.lh + 22;
        const lg = ctx.createLinearGradient(W / 2 - 220, 0, W / 2 + 220, 0);
        lg.addColorStop(0, `rgba(${T.glow}, 0)`); lg.addColorStop(0.5, T.accent); lg.addColorStop(1, `rgba(${T.glow}, 0)`);
        ctx.fillStyle = lg; ctx.fillRect(W / 2 - 220, lineY, 440, 4);

        // Kahani: halka gulabi-safed text
        const paras = K.str(item.story).split(/\n\s*\n|\n/).map(p => p.trim()).filter(Boolean).map(p => [{ t: p, b: false }]);
        const top = lineY + 40, bottom = H - 220;
        ctx.fillStyle = '#fdf2f8';
        const layout = fillRich(ctx, paras, W - 140, bottom - top);
        K.drawRich(ctx, layout, 70, top);

        // Neeche: follow patti (gulabi)
        const last = item.part >= item.total;
        const main = last ? L.end : L.next;
        const sub = last ? L.endSub : L.nextSub.replace('{n}', num(item.part + 1));
        const bx = 60, bw = W - 120, bh = 150, by = H - 45 - bh;
        ctx.save();
        const fg = ctx.createLinearGradient(bx, 0, bx + bw, 0);
        fg.addColorStop(0, T.accent); fg.addColorStop(1, T.accent2);
        ctx.shadowColor = `rgba(${T.glow}, 0.6)`; ctx.shadowBlur = 30;
        ctx.fillStyle = fg;
        ctx.beginPath(); ctx.roundRect(bx, by, bw, bh, 75); ctx.fill();
        ctx.shadowColor = 'transparent';
        ctx.fillStyle = T.bg[2]; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        K.fitOneLine(ctx, main, bw - 80, 46, 28);
        ctx.fillText(main, W / 2, by + 58);
        ctx.fillStyle = 'rgba(0,0,0,0.65)';
        K.fitOneLine(ctx, sub, bw - 100, 32, 22, '400');
        ctx.fillText(sub, W / 2, by + 106);
        ctx.restore();
    }

    /** Upar beech me "ભાગ ૧/૩" ki chhoti pill. */
    function drawPartPill(ctx, text) {
        ctx.save();
        const s = K.fitOneLine(ctx, text, 400, 36, 24);
        const w = ctx.measureText(text).width + 70, h = s + 26, y = 52;
        ctx.fillStyle = pp().title;
        ctx.beginPath(); ctx.roundRect(W / 2 - w / 2, y, w, h, h / 2); ctx.fill();
        ctx.fillStyle = '#ffffff'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(text, W / 2, y + h / 2 + 2);
        ctx.restore();
    }

    /** Neeche patti: beech ke bhaag = "aage padhne ke liye follow karo", aakhri = "aisi aur kahaniyon ke liye follow". */
    function drawFollowBar(ctx, item) {
        const L = tr(), last = item.part >= item.total;
        const main = last ? L.end : L.next;
        const sub = last ? L.endSub : L.nextSub.replace('{n}', num(item.part + 1));
        const x = 60, w = W - 120, h = 150, y = H - 60 - h;
        ctx.save();
        ctx.shadowColor = 'rgba(0,0,0,0.25)'; ctx.shadowBlur = 18;
        ctx.fillStyle = pp().title;
        ctx.beginPath(); ctx.roundRect(x, y, w, h, 30); ctx.fill();
        ctx.shadowColor = 'transparent';
        ctx.strokeStyle = pp().hl; ctx.lineWidth = 6; ctx.stroke();
        ctx.fillStyle = '#ffffff'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        K.fitOneLine(ctx, main, w - 60, 46, 28);
        ctx.fillText(main, W / 2, y + 58);
        ctx.fillStyle = 'rgba(255,255,255,0.85)';
        K.fitOneLine(ctx, sub, w - 80, 32, 22, '400');
        ctx.fillText(sub, W / 2, y + 108);
        ctx.restore();
    }

    /**
     * Kahani ke liye: 52px se neeche tak — pehla font jisme poori kahani aa jaaye.
     * Phir bachi jagah: pehle line-height (thoda), phir paragraph ke beech — taaki neeche khaali na rahe.
     */
    function fillRich(ctx, paras, maxW, maxH, maxSize = 52) {
        let layout;
        for (let s = maxSize; s >= 26; s--) {
            layout = K.layoutRich(ctx, paras, maxW, s);
            if (layout.height <= maxH) break;
        }
        let extra = maxH - layout.height;
        if (extra > 0 && layout.lines.length) {
            const n = layout.lines.length;
            const lhAdd = Math.min(extra / n, layout.size * 0.22); // line-height 1.38 → ~1.6 tak
            layout.lh += lhAdd;
            extra -= lhAdd * n;
            const gapLines = layout.lines.filter(l => l.gapBefore > 0);
            if (extra > 0 && gapLines.length) {
                const g = Math.min(extra / gapLines.length, layout.size * 1.2);
                gapLines.forEach(l => { l.gapBefore += g; });
                extra -= g * gapLines.length;
            }
            layout.height = maxH - Math.max(0, extra);
        }
        return layout;
    }

    /** Reel ki voiceover ke liye — poora card nahi, sirf mukhya baatein (reel chhoti rahe). */
    function voiceText(item) {
        return [item.title, item.story]
            .map(K.str).map(s => s.trim()).filter(Boolean).join('. ');
    }

    // ---------- Save ----------
    el('saveBtn').addEventListener('click', async () => {
        if (!items.length) return;
        const btn = el('saveBtn');
        btn.disabled = true;
        el('progressBox').classList.remove('hidden');
        el('progressText').textContent = 'Saving…';
        el('progressBar').style.width = '30%';
        await K.ensureFonts();

        const off = document.createElement('canvas'); off.width = W; off.height = H;
        const cardsToSave = items.map((item, idx) => {
            drawSalah(off, item);
            return {
                order: idx + 1,
                text: voiceText(item),
                caption: item.total > 1
                    ? [partLabel(item) + ' — ' + K.str(item.title), K.str(item.caption), item.part >= item.total ? tr().end : tr().next].join('\n\n')
                    : K.str(item.caption),
                group: item.total > 1 ? item.group : null,
                hashtags: K.str(item.hashtags),
                image: off.toDataURL('image/webp', 0.92),
            };
        });

        el('progressBar').style.width = '75%';

        try {
            const r = await fetch(SAVE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ language: itemsLang, category: itemsCategory, cards: cardsToSave })
            });
            const d = await r.json();
            if (!d.ok) throw new Error(K.saveError(d));

            el('progressBar').style.width = '100%';
            setTimeout(() => window.location = d.redirect, 400);
        } catch (e) {
            alert('Save error: ' + e.message);
            btn.disabled = false;
            el('progressBox').classList.add('hidden');
        }
    });

    // ---------- Bulk Delete ----------
    const selectAll = el('selectAll');
    const bulkBtn = el('bulkDeleteBtn');
    const countSpan = el('selectedCount');
    const form = el('bulkForm');

    if (selectAll && form) {
        const updateBulkState = () => {
            const all = form.querySelectorAll('.bulk-item');
            const checked = form.querySelectorAll('.bulk-item:checked');
            if (countSpan) countSpan.textContent = checked.length;
            if (bulkBtn) bulkBtn.disabled = checked.length === 0;
            selectAll.checked = all.length > 0 && checked.length === all.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
        };

        selectAll.addEventListener('change', () => {
            form.querySelectorAll('.bulk-item').forEach(cb => cb.checked = selectAll.checked);
            updateBulkState();
        });
        form.querySelectorAll('.bulk-item').forEach(cb => cb.addEventListener('change', updateBulkState));

        if (bulkBtn) {
            bulkBtn.addEventListener('click', () => {
                const count = form.querySelectorAll('.bulk-item:checked').length;
                if (count > 0 && confirm(`Kya aap sach me chuni hui ${count} collections delete karna chahte hain?`)) {
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
