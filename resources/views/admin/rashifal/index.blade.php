@extends('layouts.admin')
@section('title', '🔯 Aaj nu Rashifal — 12 Rashi Cards')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-gradient-to-r from-indigo-700 via-purple-700 to-fuchsia-700 rounded-2xl p-6 text-white shadow-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="inline-block px-2.5 py-1 bg-white/20 rounded-full text-xs font-semibold uppercase tracking-wider mb-2">🔯 Daily Fixed Content</span>
                <h1 class="text-2xl font-bold">Aaj nu Rashifal — 12 Rashi Cards</h1>
                <p class="text-indigo-100 text-sm mt-1">Ek din ke 12 cards (Mesh → Meen). Auto-post inhe <b>sirf usi din</b>, kram me, baaki cards se pehle post karta hai.</p>
            </div>
            <button id="saveBtn" disabled class="bg-white text-indigo-800 font-bold px-5 py-2.5 rounded-xl shadow hover:bg-indigo-50 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-2">
                <span>💾</span> Save 12 Cards
            </button>
        </div>
    </div>

    {{-- Controls --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-4">
        <div class="grid sm:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🪔 Festival</label>
                <select id="festival" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none font-medium">
                    <option value="auto" selected>🔮 Auto (tarikh se khud)</option>
                    <option value="">Normal (roz wala)</option>
                    <option value="dhanteras">🪙 Dhanteras</option>
                    <option value="kali_chaudas">🌑 Kali Chaudas</option>
                    <option value="diwali">🪔 Diwali</option>
                    <option value="navu_varsh">🎉 Navu Varsh 2083 (varshik rashifal)</option>
                    <option value="bhai_beej">👫 Bhai Beej</option>
                    <option value="labh_pancham">💰 Labh Pancham</option>
                    <option value="dev_diwali">✨ Dev Diwali</option>
                </select>
                <p id="festHint" class="text-[11px] text-orange-700 font-semibold mt-1"></p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">📅 Date</label>
                <input id="date" type="date" value="{{ now()->addDay()->toDateString() }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none font-medium">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🌐 Language</label>
                <select id="language" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none font-medium">
                    <option value="gujarati" selected>ગુજરાતી (Gujarati)</option>
                    <option value="hindi">हिंदी (Hindi)</option>
                    <option value="hinglish">Hinglish</option>
                </select>
            </div>
            <div class="flex items-end">
                <button id="genBtn" class="w-full bg-gradient-to-r from-indigo-600 to-purple-700 hover:from-indigo-700 hover:to-purple-800 text-white font-bold py-2 px-4 rounded-lg shadow transition">
                    <span id="genText">Generate Rashifal</span>
                </button>
            </div>
        </div>
        <label class="flex items-center gap-2 pt-1 text-sm cursor-pointer select-none w-fit">
            <input id="makeShorts" type="checkbox" checked class="rounded border-slate-300 accent-red-600 w-4 h-4">
            <span class="font-semibold text-slate-700">📺 YouTube Shorts pan banavo</span>
            <span class="text-xs text-slate-500">(12 Rashi + Top 3 — tick nahi hoy to sirf 12 cards, Instagram + Facebook mate)</span>
        </label>
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-1 border-t border-slate-100">
            <button id="weekBtn" class="shrink-0 bg-gradient-to-r from-fuchsia-600 to-purple-700 hover:from-fuchsia-700 hover:to-purple-800 text-white font-bold py-2 px-4 rounded-lg shadow transition disabled:opacity-50">
                📅 Agle 7 din banao
            </button>
            <p class="text-xs text-slate-500">Upar ki date se 7 din ka rashifal ek click me ban ke <b>seedha save</b> ho jayega (pehle se save din skip). Har din usi din auto-post hoga. Page band mat karna — 3-7 minute lag sakte hain.</p>
        </div>

        <div id="progressBox" class="hidden pt-2">
            <div class="text-xs font-semibold text-slate-600 mb-1"><span id="progressText">AI likh raha hai… (20–60 sec)</span></div>
            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                <div id="progressBar" class="bg-gradient-to-r from-indigo-500 to-purple-600 h-2 transition-all duration-300" style="width: 0%"></div>
            </div>
        </div>
    </div>

    <div id="emptyState" class="bg-white rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center text-slate-400">
        <span class="text-4xl block mb-2">🔯</span>
        <p class="font-bold text-slate-600">Abhi koi rashifal nahi bana</p>
        <p class="text-xs mt-1">Date chuno aur "Generate Rashifal" dabao — har card ke neeche ✏️ Edit se text sudhaar sakte ho.</p>
    </div>

    <div id="previewGrid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 hidden"></div>

    {{-- 📺 YouTube Shorts (sirf YouTube) — Save ke saath apne aap bante hain --}}
    <div id="shortPreview" class="hidden bg-white rounded-xl border border-red-200 p-4 shadow-sm">
        <h3 class="font-bold text-slate-800 mb-1">📺 YouTube Shorts <span class="text-xs font-normal text-slate-500">— sirf YouTube ke liye, "Save" ke saath apne aap save honge</span></h3>
        <p class="text-xs text-slate-500 mb-3">Upar ke 12 cards ab sirf Instagram + Facebook par jaate hain. YouTube par us din ye 2 Shorts jaate hain (pehle 12 Rashi, fir Top 3). Na joie to upar "📺 YouTube Shorts pan banavo" no tick kaadho.</p>
        <div class="rows space-y-3"></div>
    </div>

    {{-- Saved --}}
    <div class="mt-10 bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <form id="bulkForm" method="POST" action="{{ route('admin.stories.bulk_destroy') }}">
            @csrf
            <div class="flex items-center justify-between gap-4 mb-4 flex-wrap pb-3 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                    <span>💾</span> Saved Rashifal ({{ $collections->count() }})
                </h3>
                @if ($collections->isNotEmpty())
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-600 cursor-pointer select-none">
                            <input type="checkbox" id="selectAll" class="rounded border-slate-300 accent-indigo-600 w-4 h-4 cursor-pointer">
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
                @php
                    $isToday = $c->category === now()->toDateString();
                    $isPast = $c->category < now()->toDateString();
                @endphp
                <div class="flex items-center justify-between bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 mb-2.5 hover:border-indigo-400 hover:bg-indigo-50/20 transition gap-3 {{ $isPast ? 'opacity-60' : '' }}">
                    <input type="checkbox" name="ids[]" value="{{ $c->id }}" class="bulk-item rounded border-slate-300 accent-indigo-600 w-4 h-4 cursor-pointer">
                    <a href="{{ route('admin.rashifal.show', $c) }}" class="flex-1 flex items-center justify-between gap-3 group">
                        <div>
                            <span class="font-bold text-slate-800 group-hover:text-indigo-700 transition">{{ $c->type === 'rashifal_short' ? '📺' : '🔯' }} {{ $c->title }}</span>
                            <span class="text-xs text-slate-500 ml-2">({{ ucfirst($c->language ?? 'gujarati') }})</span>
                            @if ($isToday)
                                <span class="text-[10px] font-bold px-2 py-0.5 bg-green-100 text-green-800 rounded-full ml-1">AAJ</span>
                            @elseif ($isPast)
                                <span class="text-[10px] font-bold px-2 py-0.5 bg-slate-200 text-slate-600 rounded-full ml-1">Purana</span>
                            @endif
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 bg-indigo-100 text-indigo-800 rounded-full">{{ ucfirst($c->status) }}</span>
                    </a>
                    <button type="button" onclick="if(confirm('Ye rashifal delete karein?')) document.getElementById('single-del-{{ $c->id }}').submit();" class="text-xs font-semibold text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                        <span>🗑</span> Delete
                    </button>
                </div>
            @empty
                <p class="text-sm text-slate-500 text-center py-4">Abhi koi rashifal save nahi hai — upar se naya banayein! 🔯</p>
            @endforelse
        </form>

        @foreach ($collections as $c)
            <form id="single-del-{{ $c->id }}" method="POST" action="{{ route('admin.rashifal.destroy', $c) }}" class="hidden">
                @csrf @method('DELETE')
            </form>
        @endforeach
    </div>
</div>

@push('scripts')
@include('admin.partials._canvas_kit')
<script>
(function() {
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const GEN_URL = @json(route('admin.rashifal.generate'));
    const SAVE_URL = @json(route('admin.rashifal.save'));
    const SHORT_URL = @json(route('admin.rashifal.short.save'));
    const FEST_URL = @json(route('admin.rashifal.festival'));

    const K = window.CanvasKit;
    const W = 1080, H = 1920;
    let items = [];
    let itemsLang = 'gujarati';
    let itemsDate = '';
    let dateExists = false;
    let itemsPanchang = null; // server ka ganit: {chandra, tithi, shubh:{name,from,to}, rahu:{from,to}}
    const el = id => document.getElementById(id);

    // 📺 YouTube Shorts banane hain? — pasand browser me yaad (khaali / blocked storage par default: haan)
    const wantShorts = () => !el('makeShorts') || el('makeShorts').checked;
    try {
        const saved = localStorage.getItem('rashifal.makeShorts');
        if (saved !== null && el('makeShorts')) el('makeShorts').checked = saved === '1';
    } catch (e) {}
    el('makeShorts')?.addEventListener('change', () => {
        try { localStorage.setItem('rashifal.makeShorts', el('makeShorts').checked ? '1' : '0'); } catch (e) {}
        if (items.length) renderShortPreviews(); // jhalak turant dikhao / chhupao
    });

    const KEYS = ['mesh', 'vrishabh', 'mithun', 'kark', 'simha', 'kanya', 'tula', 'vrishchik', 'dhanu', 'makar', 'kumbh', 'meen'];
    const SYMBOLS = ['♈', '♉', '♊', '♋', '♌', '♍', '♎', '♏', '♐', '♑', '♒', '♓'];
    const I18N = {
        gujarati: {
            names: ['મેષ', 'વૃષભ', 'મિથુન', 'કર્ક', 'સિંહ', 'કન્યા', 'તુલા', 'વૃશ્ચિક', 'ધન', 'મકર', 'કુંભ', 'મીન'],
            letters: ['અ, લ, ઈ', 'બ, વ, ઉ', 'ક, છ, ઘ', 'ડ, હ', 'મ, ટ', 'પ, ઠ, ણ', 'ર, ત', 'ન, ય', 'ભ, ધ, ફ, ઢ', 'ખ, જ', 'ગ, શ, સ, ષ', 'દ, ચ, ઝ, થ'],
            head: 'આજનું રાશિફળ', love: 'પ્રેમ:', career: 'કામકાજ:', health: 'સ્વાસ્થ્ય:',
            num: 'શુભ અંક', color: 'શુભ રંગ', cta: 'તમારી રાશિ કઈ છે? કોમેન્ટમાં લખો 👇',
            locale: 'gu-IN', digits: '૦૧૨૩૪૫૬૭૮૯',
        },
        hindi: {
            names: ['मेष', 'वृषभ', 'मिथुन', 'कर्क', 'सिंह', 'कन्या', 'तुला', 'वृश्चिक', 'धनु', 'मकर', 'कुंभ', 'मीन'],
            letters: [
                'चू, चे, चो, ला, ली, लू, ले, लो, अ', 'इ, उ, ए, ओ, वा, वी, वू, वे, वो', 'का, की, कु, घ, ङ, छ, के, को, ह',
                'ही, हू, हे, हो, डा, डी, डू, डे, डो', 'मा, मी, मू, मे, मो, टा, टी, टू, टे', 'टो, पा, पी, पू, ष, ण, ठ, पे, पो',
                'रा, री, रू, रे, रो, ता, ती, तू, ते', 'तो, ना, नी, नू, ने, नो, या, यी, यू', 'ये, यो, भा, भी, भू, धा, फा, ढा, भे',
                'भो, जा, जी, खी, खू, खे, खो, गा, गी', 'गू, गे, गो, सा, सी, सू, से, सो, दा', 'दी, दू, थ, झ, ञ, दे, दो, चा, ची',
            ],
            head: 'आज का राशिफल', love: 'प्रेम:', career: 'करियर:', health: 'स्वास्थ्य:',
            num: 'शुभ अंक', color: 'शुभ रंग', cta: 'आपकी राशि कौन सी है? कमेंट करें 👇',
            locale: 'hi-IN', digits: '०१२३४५६७८९',
        },
        hinglish: {
            names: ['Mesh', 'Vrishabh', 'Mithun', 'Kark', 'Simha', 'Kanya', 'Tula', 'Vrishchik', 'Dhanu', 'Makar', 'Kumbh', 'Meen'],
            letters: ['Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo', 'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'],
            head: 'Aaj Ka Rashifal', love: 'Love:', career: 'Career:', health: 'Health:',
            num: 'Lucky Number', color: 'Lucky Color', cta: 'Aapki rashi kaunsi hai? Comment karo 👇',
            locale: 'en-IN', digits: '0123456789',
        },
    };
    const tr = () => I18N[itemsLang] || I18N.gujarati;

    // ---------- Card ke naye hisse: tatva ka rang, disha, mantra, chandra, shubh samay ----------
    // Tatva (rashi index % 4): 0 Agni (Mesh/Simha/Dhanu), 1 Prithvi, 2 Vayu, 3 Jal
    const ELEMENT_SKY = [
        ['#2a0505', '#7a1a0a', '#b4410f'], // 🔥 Agni — laal/naarangi
        ['#03140b', '#0f3d24', '#4d3b10'], // 🌿 Prithvi — hara/bhoora
        ['#061833', '#1d4e7d', '#56658a'], // 🌬 Vayu — aasmani/chaandi
        ['#020a24', '#0a3a5c', '#0b5b5d'], // 💧 Jal — neela/firozi
    ];
    // Rashi swami: 0 Mangal, 1 Shukra, 2 Budh, 3 Chandra, 4 Surya, 5 Guru, 6 Shani
    const LORD = [0, 1, 2, 3, 4, 2, 1, 0, 5, 6, 6, 5];
    const CARD_I18N = {
        gujarati: {
            day: 'આજનો દિવસ', dir: 'શુભ દિશા', dirs: ['પૂર્વ', 'દક્ષિણ', 'પશ્ચિમ', 'ઉત્તર'],
            doL: '✅ આ કરો:', dontL: '❌ આ ન કરો:', upay: '🙏 ઉપાય:', mantra: '🕉 મંત્ર:',
            samay: 'શુભ સમય', rahu: 'રાહુ કાળ',
            moonIn: '🌙 આજે ચંદ્ર તમારી રાશિમાં', moon8: '🌙 આઠમો ચંદ્ર — આજે ધીરજ રાખો',
            dmTop: 'આખું રાશિફળ જોઈએ છે?', dmBottom: '💬 કોમેન્ટમાં લખો “RASHI” 👉 DM માં મોકલીશું 📩',
            chog: { amrit: 'અમૃત', labh: 'લાભ', shubh: 'શુભ' },
            mantras: ['ૐ અં અંગારકાય નમઃ', 'ૐ શું શુક્રાય નમઃ', 'ૐ બું બુધાય નમઃ', 'ૐ સોં સોમાય નમઃ', 'ૐ ઘૃણિ સૂર્યાય નમઃ', 'ૐ બૃં બૃહસ્પતયે નમઃ', 'ૐ શં શનૈશ્ચરાય નમઃ'],
        },
        hindi: {
            day: 'आज का दिन', dir: 'शुभ दिशा', dirs: ['पूर्व', 'दक्षिण', 'पश्चिम', 'उत्तर'],
            doL: '✅ यह करें:', dontL: '❌ यह न करें:', upay: '🙏 उपाय:', mantra: '🕉 मंत्र:',
            samay: 'शुभ समय', rahu: 'राहु काल',
            moonIn: '🌙 आज चंद्र आपकी राशि में', moon8: '🌙 आठवाँ चंद्र — आज धैर्य रखें',
            dmTop: 'पूरा राशिफल चाहिए?', dmBottom: '💬 कमेंट में लिखें “RASHI” 👉 DM में भेजेंगे 📩',
            chog: { amrit: 'अमृत', labh: 'लाभ', shubh: 'शुभ' },
            mantras: ['ॐ अं अंगारकाय नमः', 'ॐ शुं शुक्राय नमः', 'ॐ बुं बुधाय नमः', 'ॐ सों सोमाय नमः', 'ॐ घृणि सूर्याय नमः', 'ॐ बृं बृहस्पतये नमः', 'ॐ शं शनैश्चराय नमः'],
        },
        hinglish: {
            day: 'Aaj ka din', dir: 'Lucky Disha', dirs: ['East', 'South', 'West', 'North'],
            doL: '✅ Karo:', dontL: '❌ Na karo:', upay: '🙏 Upay:', mantra: '🕉 Mantra:',
            samay: 'Shubh Samay', rahu: 'Rahu Kaal',
            moonIn: '🌙 Aaj Chandra aapki rashi me', moon8: '🌙 8th Chandra — aaj dhairya rakho',
            dmTop: 'Pura rashifal chahiye?', dmBottom: '💬 Comment karo “RASHI” 👉 DM me bhejenge 📩',
            chog: { amrit: 'Amrit', labh: 'Labh', shubh: 'Shubh' },
            mantras: ['Om Angarakaya Namah', 'Om Shukraya Namah', 'Om Budhaya Namah', 'Om Somaya Namah', 'Om Ghrini Suryaya Namah', 'Om Brihaspataye Namah', 'Om Shanaishcharaya Namah'],
        },
    };
    const ct = () => CARD_I18N[itemsLang] || CARD_I18N.gujarati;
    // Navu Varsh ka rashifal poore saal ka hai — us din ka chandra / choghadiya mat dikhao
    const pj = () => (itemsFestival === 'navu_varsh' ? null : itemsPanchang);
    /** 'in' = Chandra isi rashi me, '8' = Chandra is rashi se 8va (chandrashtam), warna null. */
    function moonState(i) {
        const p = pj();
        if (!p || p.chandra === null || p.chandra === undefined) return null;
        if (p.chandra === i) return 'in';
        if ((i + 7) % 12 === p.chandra) return '8';
        return null;
    }
    const num = n => String(n ?? '').replace(/\d/g, d => tr().digits[d]);
    const dateLabel = () => {
        try {
            return num(new Date(itemsDate + 'T00:00:00').toLocaleDateString(tr().locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
        } catch (e) { return itemsDate; }
    };
    /** Card ke liye [vaar, taarikh] alag — dono alag rang me dikhte hain. */
    const dateParts = () => {
        try {
            const d = new Date(itemsDate + 'T00:00:00');
            return [
                d.toLocaleDateString(tr().locale, { weekday: 'long' }),
                num(d.toLocaleDateString(tr().locale, { day: 'numeric', month: 'long', year: 'numeric' })),
            ];
        } catch (e) { return ['', itemsDate]; }
    };


    // ---------- 🪔 Festival theme ----------
    // head: card ki upar wali line (bhasha anusaar); sky: background; deco: festival emoji
    let itemsFestival = '';
    const FESTIVALS = {
        dhanteras:    { sky: ['#1a0f00', '#5c3a00', '#7a4a00'], deco: '🪙', head: { gujarati: 'ધનતેરસ સ્પેશિયલ રાશિફળ', hindi: 'धनतेरस स्पेशल राशिफल', hinglish: 'Dhanteras Special Rashifal' } },
        kali_chaudas: { sky: ['#05030f', '#1e1035', '#3b0d2e'], deco: '🪔', head: { gujarati: 'કાળી ચૌદસનું રાશિફળ', hindi: 'काली चौदस का राशिफल', hinglish: 'Kali Chaudas Rashifal' } },
        diwali:       { sky: ['#2b0a00', '#7c1d06', '#4a0d3a'], deco: '🪔', head: { gujarati: 'દિવાળી સ્પેશિયલ રાશિફળ', hindi: 'दिवाली स्पेशल राशिफल', hinglish: 'Diwali Special Rashifal' } },
        navu_varsh:   { sky: ['#3b0018', '#8a1538', '#3d0a5c'], deco: '🎉', head: { gujarati: 'નૂતન વર્ષ ૨૦૮૩ નું રાશિફળ', hindi: 'नव वर्ष 2083 का राशिफल', hinglish: 'Navu Varsh 2083 Rashifal' } },
        bhai_beej:    { sky: ['#2a0530', '#6d1a5e', '#8a2348'], deco: '👫', head: { gujarati: 'ભાઈબીજ સ્પેશિયલ રાશિફળ', hindi: 'भाई दूज स्पेशल राशिफल', hinglish: 'Bhai Beej Special Rashifal' } },
        labh_pancham: { sky: ['#032013', '#0b5132', '#5c4300'], deco: '💰', head: { gujarati: 'લાભ પાંચમનું રાશિફળ', hindi: 'लाभ पंचमी का राशिफल', hinglish: 'Labh Pancham Rashifal' } },
        dev_diwali:   { sky: ['#06123a', '#1d3a8a', '#5a2a7a'], deco: '✨', head: { gujarati: 'દેવ દિવાળી સ્પેશિયલ રાશિફળ', hindi: 'देव दिवाली स्पेशल राशिफल', hinglish: 'Dev Diwali Special Rashifal' } },
    };
    const fest = () => FESTIVALS[itemsFestival] || null;

    // Date chunte hi batao ki us din kaunsa tyohar hai (Auto chuna ho to wahi theme lagegi)
    async function showFestHint() {
        const opt = [...el('festival').options];
        try {
            const r = await fetch(FEST_URL, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: JSON.stringify({ date: el('date').value }) });
            const d = await r.json();
            const o = opt.find(x => x.value === d.festival);
            el('festHint').textContent = o ? '🔮 Is din: ' + o.textContent : '🔮 Is din koi tyohar nahi — roz wala rashifal';
        } catch (e) { el('festHint').textContent = ''; }
    }
    el('date').addEventListener('change', showFestHint);
    showFestHint();
    const headText = () => { const f = fest(); return f ? (f.head[itemsLang] || f.head.gujarati) : tr().head; };

    /** Festival card ki sajavat — upar genda-phool ka toran, neeche diyon ki line. */
    function drawFestDeco(ctx, bottomRow = true) {
        const f = fest();
        if (!f) return;
        // Toran: dori + genda (naarangi/peela) + hare patte
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
            ctx.fillStyle = 'rgba(0,0,0,0.18)';
            ctx.beginPath(); ctx.arc(x, 44, 7, 0, Math.PI * 2); ctx.fill();
        }
        if (!bottomRow) { ctx.restore(); return; } // neeche banner hai
        // Neeche diyon / festival emoji ki line
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.font = `64px ${K.STACK}`;
        ctx.globalAlpha = 1; ctx.shadowBlur = 0; ctx.shadowColor = 'transparent';
        // Card ke neeche tak content hai — diye chhote aur bilkul neeche (1805 se neeche)
        ctx.font = `54px ${K.STACK}`;
        for (let k = 0; k < 7; k++) {
            const x = 120 + k * 140;
            const glow = ctx.createRadialGradient(x, 1862, 4, x, 1862, 55);
            glow.addColorStop(0, 'rgba(253, 224, 71, 0.55)'); glow.addColorStop(1, 'rgba(253, 224, 71, 0)');
            ctx.fillStyle = glow; ctx.beginPath(); ctx.arc(x, 1862, 55, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = '#ffffff';
            ctx.fillText(k === 3 ? f.deco : '🪔', x, 1872);
        }
        ctx.restore();
    }

    /** AI ke items ko Mesh→Meen kram me lagao (rashi key se, warna jis kram me aaye). */
    function orderItems(raw) {
        const out = new Array(12).fill(null);
        const rest = [];
        raw.forEach(it => {
            const k = String(it.rashi || '').toLowerCase().replace(/[^a-z]/g, '').replace('dhan', 'dhanu').replace('dhanuu', 'dhanu').replace('singh', 'simha');
            const i = KEYS.indexOf(k);
            if (i >= 0 && !out[i]) out[i] = it; else rest.push(it);
        });
        for (let i = 0; i < 12; i++) if (!out[i]) out[i] = rest.shift() || null;
        return out.map((it, i) => it ? Object.assign(it, { _idx: i }) : null).filter(Boolean);
    }

    el('genBtn').addEventListener('click', async () => {
        const btn = el('genBtn');
        if (!el('date').value) { alert('Date chuno'); return; }
        btn.disabled = true;
        el('genText').textContent = 'Generating…';
        el('progressBox').classList.remove('hidden');
        el('progressText').textContent = 'AI likh raha hai… (20–60 sec)';
        el('progressBar').style.width = '40%';

        try {
            const r = await fetch(GEN_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ date: el('date').value, language: el('language').value, festival: el('festival').value || null })
            });
            const d = await r.json();
            if (!d.ok) throw new Error(K.saveError(d));

            items = orderItems(Array.isArray(d.items) ? d.items : []);
            if (!items.length) throw new Error('AI ne koi rashi nahi di — dobara try karo.');
            itemsLang = el('language').value;
            itemsDate = el('date').value;
            itemsFestival = d.festival ?? (el('festival').value === 'auto' ? '' : el('festival').value);
            itemsPanchang = d.panchang || null;
            dateExists = !!d.exists;
            el('progressBar').style.width = '80%';
            await K.ensureFonts();
            renderAllPreviews();
            renderShortPreviews();

            el('progressBar').style.width = '100%';
            el('saveBtn').disabled = false;
            setTimeout(() => el('progressBox').classList.add('hidden'), 500);
        } catch (e) {
            alert('Error: ' + e.message);
            el('progressBox').classList.add('hidden');
        } finally {
            btn.disabled = false;
            el('genText').textContent = 'Generate Rashifal';
        }
    });

    // ---------- Previews + edit ----------
    const FIELDS = [
        ['prediction', 'Bhavishya', 4], ['rating', 'Aaj ka din ★ (1-5)', 1],
        ['love', 'Prem', 2], ['love_score', 'Prem meter (1-10)', 1],
        ['career', 'Kaam', 2], ['career_score', 'Kaam meter (1-10)', 1],
        ['health', 'Swasthya', 2], ['health_score', 'Swasthya meter (1-10)', 1],
        ['do', '✅ Karo', 1], ['dont', '❌ Na karo', 1], ['upay', '🙏 Upay', 2],
        ['lucky_number', 'Shubh ank', 1], ['lucky_color', 'Shubh rang', 1],
    ];

    function renderAllPreviews() {
        el('emptyState').classList.add('hidden');
        const grid = el('previewGrid');
        grid.classList.remove('hidden');
        grid.innerHTML = '';

        items.forEach(item => {
            const box = document.createElement('div');
            box.className = 'bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm flex flex-col';
            box.innerHTML = `
                <div class="p-2 bg-indigo-50 border-b border-indigo-100 text-xs font-bold text-indigo-900">${SYMBOLS[item._idx]} ${K.esc(tr().names[item._idx])}</div>
                <div class="p-2 canvas-slot"></div>
                <details class="border-t border-slate-100 text-xs">
                    <summary class="px-3 py-2 cursor-pointer font-semibold text-slate-600 hover:bg-slate-50">✏️ Edit</summary>
                    <div class="p-3 space-y-2 fields"></div>
                </details>`;

            const canvas = document.createElement('canvas');
            canvas.width = W; canvas.height = H;
            canvas.className = 'w-full aspect-[9/16] rounded-lg';
            box.querySelector('.canvas-slot').appendChild(canvas);
            drawRashi(canvas, item);

            const fields = box.querySelector('.fields');
            FIELDS.forEach(([key, label, rows]) => {
                const wrap = document.createElement('label');
                wrap.className = 'block';
                wrap.innerHTML = `<span class="font-semibold text-slate-500">${label}</span>
                    <textarea rows="${rows}" class="mt-0.5 w-full rounded border border-slate-300 px-2 py-1 text-xs"></textarea>`;
                const ta = wrap.querySelector('textarea');
                ta.value = K.str(item[key]);
                ta.addEventListener('input', () => { item[key] = ta.value; drawRashi(canvas, item); });
                fields.appendChild(wrap);
            });
            grid.appendChild(box);
        });
    }

    // ---------- Card drawing ----------
    const CY = 455; // rashi chinh / chakra ka centre (y)

    // AI ka "shubh rang" (gu/hi/en) → asli colour. Lambe naam pehle, taaki "આસમાની" "લાલ" se pehle mile.
    const COLORS = [
        [['આસમાની', 'આકાશી', 'आसमानी', 'sky blue', 'sky'], '#38bdf8'],
        [['મરૂન', 'मैरून', 'maroon'], '#7f1d1d'],
        [['ગુલાબી', 'गुलाबी', 'pink'], '#ec4899'],
        [['જાંબલી', 'જાંબુડી', 'बैंगनी', 'जामुनी', 'purple', 'violet'], '#7c3aed'],
        [['કેસરી', 'નારંગી', 'केसरिया', 'नारंगी', 'saffron', 'orange'], '#f97316'],
        [['સોનેરી', 'सुनहरा', 'golden', 'gold'], '#d4a017'],
        [['ચાંદી', 'રૂપેરી', 'चांदी', 'सिल्वर', 'silver'], '#c0c0c0'],
        [['ક્રીમ', 'क्रीम', 'cream'], '#fef3c7'],
        [['કથ્થઈ', 'ભૂરો', 'भूरा', 'brown'], '#8b5a2b'],
        [['રાખોડી', 'ગ્રે', 'स्लेटी', 'ग्रे', 'grey', 'gray'], '#9ca3af'],
        [['પીળો', 'પીળા', 'पीला', 'yellow'], '#facc15'],
        [['લીલો', 'લીલા', 'हरा', 'green'], '#16a34a'],
        [['વાદળી', 'નીલો', 'નીલા', 'नीला', 'blue'], '#2563eb'],
        [['લાલ', 'लाल', 'red'], '#dc2626'],
        [['સફેદ', 'सफेद', 'सफ़ेद', 'white'], '#ffffff'],
        [['કાળો', 'કાળા', 'काला', 'black'], '#111827'],
    ];
    function colorHex(name) {
        const s = K.str(name).toLowerCase();
        if (!s) return null;
        for (const [words, hex] of COLORS) {
            if (words.some(w => s.includes(w))) return hex;
        }
        return null;
    }

    function rng(seed) {
        return () => (seed = (seed * 16807) % 2147483647) / 2147483647;
    }

    /** cy / rings: chakra ka centre aur do rings ki radius (card par chhota, Shorts me bada). */
    function drawSky(ctx, idx, cy = CY, rings = [200, 245]) {
        const g = ctx.createLinearGradient(0, 0, 0, H);
        // Tyohar ho to uska rang; warna rashi ke tatva ka rang (intro/end frames par purana jaamuni)
        const sky = fest()?.sky || (idx < 12 ? ELEMENT_SKY[idx % 4] : ['#0b1033', '#2a1260', '#4a1450']);
        g.addColorStop(0, sky[0]);
        g.addColorStop(0.55, sky[1]);
        g.addColorStop(1, sky[2]);
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);

        // Taare — har rashi ka alag pattern
        const r = rng(7 + idx * 101);
        for (let i = 0; i < 260; i++) {
            const s = r() * 2.6 + 0.4;
            ctx.fillStyle = `rgba(255,255,255,${0.25 + r() * 0.6})`;
            ctx.beginPath(); ctx.arc(r() * W, r() * H, s, 0, Math.PI * 2); ctx.fill();
        }

        // Rashi chakra ki halki rings
        ctx.save();
        ctx.strokeStyle = 'rgba(250, 204, 21, 0.18)';
        ctx.lineWidth = 3;
        rings.forEach(rad => { ctx.beginPath(); ctx.arc(W / 2, cy, rad, 0, Math.PI * 2); ctx.stroke(); });
        const mid = (rings[0] + rings[1]) / 2;
        ctx.font = K.font(Math.round(mid / 7.4), '400');
        ctx.fillStyle = 'rgba(250, 204, 21, 0.35)';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        SYMBOLS.forEach((sym, i) => {
            const a = (i / 12) * Math.PI * 2 - Math.PI / 2;
            ctx.fillText(sym + '︎', W / 2 + Math.cos(a) * mid, cy + Math.sin(a) * mid);
        });
        ctx.restore();
    }

    const CARD_CY = 385; // card par chakra ka centre (Shorts me CY)

    /** Kaanch jaisa panel. */
    function glass(ctx, x, y, w, h) {
        ctx.save();
        ctx.fillStyle = 'rgba(255,255,255,0.10)';
        ctx.strokeStyle = 'rgba(250, 204, 21, 0.45)';
        ctx.lineWidth = 3;
        ctx.beginPath(); ctx.roundRect(x, y, w, h, 30); ctx.fill(); ctx.stroke();
        ctx.restore();
    }

    /** Meter ka score 1-10 → % (AI na de to din ki rating se). */
    function scorePct(item, key) {
        let v = parseInt(item[key], 10);
        if (!v) v = ratingOf(item) * 2;
        return Math.max(1, Math.min(10, v)) * 10;
    }

    /** "13:30" → "1:30" (bhasha ke ank me). */
    const t12 = s => {
        const [h, m] = String(s || '').split(':').map(Number);
        return isNaN(h) || isNaN(m) ? '' : num(((h + 11) % 12 + 1) + ':' + String(m).padStart(2, '0'));
    };

    /** Tithi line — "કારતક સુદ ત્રીજ • રોહિણી નક્ષત્ર" (Hinglish me nahi). */
    const tithiLine = () => K.str(pj()?.tithi);

    /** ⏰ Shubh samay (din ka shubh choghadiya) • ⚠️ Rahu kaal */
    function timeLine() {
        const p = pj(), C = ct();
        if (!p) return '';
        const out = [];
        if (p.shubh?.from) out.push('⏰ ' + C.samay + ' ' + t12(p.shubh.from) + '–' + t12(p.shubh.to) + (C.chog[p.shubh.name] ? ' (' + C.chog[p.shubh.name] + ')' : ''));
        if (p.rahu?.from) out.push('⚠️ ' + C.rahu + ' ' + t12(p.rahu.from) + '–' + t12(p.rahu.to));
        return out.join('   •   ');
    }

    function drawRashi(c, item) {
        const ctx = c.getContext('2d');
        const L = tr(), C = ct();
        const i = item._idx;
        const f = !!fest();
        const cy = CARD_CY + (f ? 25 : 0); // tyohar par upar toran — sab thoda neeche
        const gold = '#fcd34d';
        K.reset(ctx);
        drawSky(ctx, i, cy, [128, 158]);

        // Upar: heading + date + tithi
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillStyle = '#facc15';
        const hd = (f ? fest().deco + ' ' : '✨ ') + headText() + (f ? ' ' + fest().deco : ' ✨');
        K.fitOneLine(ctx, hd, W - 120, 58, 38);
        ctx.fillText(hd, W / 2, f ? 100 : 68);
        drawDatePill(ctx, f ? 165 : 138, 40, 70);

        const tl = tithiLine();
        if (tl) {
            ctx.fillStyle = 'rgba(253, 230, 138, 0.92)';
            K.fitOneLine(ctx, tl, W - 160, 32, 22, '400');
            ctx.fillText(tl, W / 2, f ? 222 : 198);
        }

        drawSymbol(ctx, SYMBOLS[i] + '︎', 110, null, cy, 100);

        // Rashi naam + akshar + ★ aaj ka din
        ctx.fillStyle = '#ffffff';
        ctx.font = K.font(76);
        ctx.fillText(L.names[i], W / 2, cy + 208);
        if (L.letters) {
            ctx.fillStyle = gold;
            K.fitOneLine(ctx, '(' + L.letters[i] + ')', W - 200, 32, 22, '400');
            ctx.fillText('(' + L.letters[i] + ')', W / 2, cy + 262);
        }
        drawStars(ctx, ratingOf(item), cy + 322, 58, 68);
        let y = cy + 362;

        // 🌙 Chandra badge — ganit se (isi rashi me / 8va chandra)
        const ms = moonState(i);
        if (ms) {
            const txt = ms === 'in' ? C.moonIn : C.moon8;
            ctx.save();
            const s = K.fitOneLine(ctx, txt, W - 260, 34, 24);
            const bw = ctx.measureText(txt).width + 70, bh = 62;
            ctx.fillStyle = ms === 'in' ? '#fde68a' : '#fdba74';
            ctx.shadowColor = 'rgba(0,0,0,0.35)'; ctx.shadowBlur = 16;
            ctx.beginPath(); ctx.roundRect(W / 2 - bw / 2, y + 6, bw, bh, bh / 2); ctx.fill();
            ctx.shadowBlur = 0;
            ctx.fillStyle = '#3b0764';
            ctx.font = K.font(s);
            ctx.textAlign = 'center';
            ctx.fillText(txt, W / 2, y + 6 + bh / 2 + 2);
            ctx.restore();
            y += 82;
        }

        const px = 60, pw = W - 120;

        // (A) Bhavishya
        const pa = 210;
        glass(ctx, px, y, pw, pa);
        ctx.fillStyle = '#ffffff';
        const la = K.fitRich(ctx, [[{ t: K.str(item.prediction), b: false }]], pw - 70, pa - 40, 44, 24);
        K.drawRich(ctx, la, px + 35, y + (pa - la.height) / 2);
        y += pa + 16;

        // (B) Meters — ❤️ Prem / 💼 Kaam / 🩺 Swasthya: patti + % + ek line
        const meters = [
            ['❤️ ', L.love, 'love', '#f472b6'],
            ['💼 ', L.career, 'career', '#facc15'],
            ['🩺 ', L.health, 'health', '#4ade80'],
        ];
        const rowH = 86, pb = 30 + rowH * meters.length;
        glass(ctx, px, y, pw, pb);
        meters.forEach(([ico, label, key, color], k) => {
            const ry = y + 22 + k * rowH;
            const pct = scorePct(item, key + '_score');
            const lbl = ico + String(label).replace(/[:：]\s*$/, '');
            ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
            ctx.fillStyle = gold;
            K.fitOneLine(ctx, lbl, 230, 34, 22);
            ctx.fillText(lbl, px + 35, ry + 20);

            const bx = 330, bw = 540, bh = 22;
            ctx.fillStyle = 'rgba(255,255,255,0.18)';
            ctx.beginPath(); ctx.roundRect(bx, ry + 20 - bh / 2, bw, bh, bh / 2); ctx.fill();
            const grad = ctx.createLinearGradient(bx, 0, bx + bw, 0);
            grad.addColorStop(0, color + '99'); grad.addColorStop(1, color);
            ctx.fillStyle = grad;
            ctx.beginPath(); ctx.roundRect(bx, ry + 20 - bh / 2, Math.max(bh, bw * pct / 100), bh, bh / 2); ctx.fill();

            ctx.textAlign = 'right';
            ctx.fillStyle = '#ffffff';
            ctx.font = K.font(34);
            ctx.fillText(num(pct) + '%', px + pw - 30, ry + 22);

            const hint = K.str(item[key]).trim();
            if (hint) {
                ctx.textAlign = 'left';
                ctx.fillStyle = 'rgba(255,255,255,0.88)';
                K.fitOneLine(ctx, hint, pw - 70, 30, 18, '400');
                ctx.fillText(hint, px + 35, ry + 60);
            }
        });
        ctx.textAlign = 'center';
        y += pb + 16;

        // (C) Karo / Na karo / Upay / Mantra
        const pc = 230;
        glass(ctx, px, y, pw, pc);
        const paras = [
            [{ t: C.doL, b: true, c: '#86efac' }, { t: K.str(item.do), b: false }],
            [{ t: C.dontL, b: true, c: '#fca5a5' }, { t: K.str(item.dont), b: false }],
            [{ t: C.upay, b: true, c: gold }, { t: K.str(item.upay), b: false }],
            [{ t: C.mantra, b: true, c: gold }, { t: C.mantras[LORD[i]], b: true }],
        ].filter(p => p.some(s => !s.c && String(s.t).trim()));
        ctx.fillStyle = '#ffffff';
        const lc = K.fitRich(ctx, paras, pw - 70, pc - 40, 36, 20, { paraGap: 0.3 });
        K.drawRich(ctx, lc, px + 35, y + (pc - lc.height) / 2);
        y += pc + 18;

        // Shubh ank / rang / disha
        drawLuckyChips(ctx, item, y + 38);
        y += 94;

        // ⏰ Shubh samay • ⚠️ Rahu kaal
        const tline = timeLine();
        if (tline) {
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillStyle = '#ffffff';
            K.fitOneLine(ctx, tline, W - 100, 32, 20);
            ctx.fillText(tline, W / 2, y + 22);
            y += 50;
        }

        // 📩 Neeche banner: "comment me RASHI likho → DM" (Instagram rashi auto-reply + DM isi se chalta hai)
        drawDmBanner(ctx, f);

        drawFestDeco(ctx, false); // toran upar; neeche diyon ki jagah banner (banner ke kono par diye)
    }

    /** Card ke bilkul neeche sunehra banner — 2 line: sawaal + "comment RASHI → DM". */
    function drawDmBanner(ctx, festive) {
        const C = ct();
        const bw = W - 100, bh = 104, bx = 50, by = H - 30 - bh;
        ctx.save();
        ctx.shadowColor = 'rgba(0,0,0,0.45)'; ctx.shadowBlur = 22;
        const g = ctx.createLinearGradient(bx, 0, bx + bw, 0);
        g.addColorStop(0, '#fbbf24'); g.addColorStop(0.5, '#fde68a'); g.addColorStop(1, '#fbbf24');
        ctx.fillStyle = g;
        ctx.beginPath(); ctx.roundRect(bx, by, bw, bh, 26); ctx.fill();
        ctx.shadowBlur = 0;
        ctx.strokeStyle = '#ffffff'; ctx.lineWidth = 3; ctx.stroke();

        const side = festive ? 70 : 0; // tyohar par kono par diye
        if (festive) {
            ctx.font = `46px ${EMOJI}`;
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillText('🪔', bx + 42, by + bh / 2 + 2);
            ctx.fillText('🪔', bx + bw - 42, by + bh / 2 + 2);
        }
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillStyle = '#7c2d12';
        K.fitOneLine(ctx, C.dmTop, bw - 60 - side * 2, 28, 18, '400');
        ctx.fillText(C.dmTop, W / 2, by + 30);
        ctx.fillStyle = '#3b0764';
        K.fitOneLine(ctx, C.dmBottom, bw - 50 - side * 2, 36, 20);
        ctx.fillText(C.dmBottom, W / 2, by + 70);
        ctx.restore();
    }

    /** Shubh ank • Shubh rang (rang ke gole ke saath) • Shubh disha (tatva se) — 3 chhoti pills. */
    function drawLuckyChips(ctx, item, cy) {
        const L = tr(), C = ct();
        const chips = [
            [L.num + ': ' + num(item.lucky_number), null],
            [L.color + ': ' + K.str(item.lucky_color), colorHex(item.lucky_color)],
            [C.dir + ': ' + C.dirs[item._idx % 4], null],
        ];
        const w = 310, h = 76, gap = 15, x0 = W / 2 - (w * 3 + gap * 2) / 2;
        chips.forEach(([t, swatch], k) => {
            const cx = x0 + k * (w + gap) + w / 2;
            ctx.fillStyle = 'rgba(250, 204, 21, 0.95)';
            ctx.beginPath(); ctx.roundRect(cx - w / 2, cy - h / 2, w, h, h / 2); ctx.fill();
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            const dot = swatch ? 18 : 0, dg = swatch ? 10 : 0;
            const size = K.fitOneLine(ctx, t, w - 36 - dot * 2 - dg, 30, 18);
            const tw = ctx.measureText(t).width;
            const left = cx - (dot * 2 + dg + tw) / 2;
            if (swatch) {
                ctx.fillStyle = swatch;
                ctx.strokeStyle = '#ffffff'; ctx.lineWidth = 4;
                ctx.beginPath(); ctx.arc(left + dot, cy, dot, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
            }
            ctx.fillStyle = '#3b0764';
            ctx.font = K.font(size);
            ctx.fillText(t, left + dot * 2 + dg + tw / 2, cy + 2);
        });
    }

    /** Vaar + taarikh — gold border wali pill me, vaar gold, taarikh safed. */
    function drawDatePill(ctx, py2, size = 46, ph2 = 82) {
        const [day, date] = dateParts();
        const sep = '  •  ';
        ctx.textBaseline = 'middle';
        const ds = K.fitOneLine(ctx, day + sep + date, W - 220, size, 26);
        const dw = ctx.measureText(day).width, sw = ctx.measureText(sep).width, tw = ctx.measureText(date).width;
        const pw2 = dw + sw + tw + 90;
        ctx.fillStyle = 'rgba(11, 16, 51, 0.85)';
        ctx.strokeStyle = '#facc15';
        ctx.lineWidth = 3;
        ctx.beginPath(); ctx.roundRect(W / 2 - pw2 / 2, py2 - ph2 / 2, pw2, ph2, ph2 / 2); ctx.fill(); ctx.stroke();
        ctx.font = K.font(ds);
        ctx.textAlign = 'left';
        let x = W / 2 - (dw + sw + tw) / 2;
        ctx.fillStyle = '#facc15'; ctx.fillText(day, x, py2 + 2); x += dw;
        ctx.fillStyle = 'rgba(250, 204, 21, 0.6)'; ctx.fillText(sep, x, py2 + 2); x += sw;
        ctx.fillStyle = '#ffffff'; ctx.fillText(date, x, py2 + 2);
        ctx.textAlign = 'center';
    }

    /** Beech ka sunehra gola, andar chinh (rashi symbol / 🍀 / 12). */
    function drawSymbol(ctx, glyph, size = 160, font = null, cy = CY, r = 150) {
        ctx.save();
        ctx.shadowColor = 'rgba(250, 204, 21, 0.7)';
        ctx.shadowBlur = r / 3;
        const cg = ctx.createRadialGradient(W / 2, cy, r / 7, W / 2, cy, r + 10);
        cg.addColorStop(0, '#fde68a');
        cg.addColorStop(1, '#d97706');
        ctx.fillStyle = cg;
        ctx.beginPath(); ctx.arc(W / 2, cy, r, 0, Math.PI * 2); ctx.fill();
        ctx.restore();
        ctx.fillStyle = '#3b0764';
        ctx.font = font || `${size}px "Segoe UI Symbol", "Noto Sans Symbols 2", ${K.STACK}`;
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(glyph, W / 2, cy + r / 19);
    }

    /** Shubh ank + shubh rang (rang ke gole ke saath) — do pills, cy par. */
    function drawLuckyPills(ctx, item, cy) {
        const L = tr();
        const pills = [
            L.num + ': ' + num(item.lucky_number),
            L.color + ': ' + K.str(item.lucky_color),
        ];
        const swatch = colorHex(item.lucky_color);
        pills.forEach((t, k) => {
            const cx = k === 0 ? W * 0.27 : W * 0.73, w = 450, h = 100;
            ctx.fillStyle = 'rgba(250, 204, 21, 0.95)';
            ctx.beginPath(); ctx.roundRect(cx - w / 2, cy - h / 2, w, h, 48); ctx.fill();
            ctx.fillStyle = '#3b0764';
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';

            // Shubh rang wali pill me us rang ka gola (rang pehchana na jaaye to bina gole)
            if (k === 1 && swatch) {
                const dot = 30, gap = 14;
                const size = K.fitOneLine(ctx, t, w - 60 - dot * 2 - gap, 38, 24);
                const tw = ctx.measureText(t).width;
                const left = cx - (dot * 2 + gap + tw) / 2;
                ctx.fillStyle = swatch;
                ctx.strokeStyle = '#ffffff';
                ctx.lineWidth = 5;
                ctx.beginPath(); ctx.arc(left + dot, cy, dot, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
                ctx.fillStyle = '#3b0764';
                ctx.font = K.font(size);
                ctx.fillText(t, left + dot * 2 + gap + tw / 2, cy + 2);
                return;
            }

            K.fitOneLine(ctx, t, w - 40, 38, 24);
            ctx.fillText(t, cx, cy + 2);
        });
    }

    // =====================================================================
    //  📺 YouTube-only Shorts — (1) 12 Rashi ek Short  (2) Top 3 Lucky Rashi
    //  Frames: intro → rashi frames → 👍 Like • ↗️ Share • 🔔 Subscribe
    // =====================================================================
    const EMOJI = '"Segoe UI Emoji", "Apple Color Emoji", "Noto Color Emoji", sans-serif';
    const SHORT_I18N = {
        gujarati: {
            allSub: '૧૨ રાશિ • ૧ મિનિટમાં', pause: 'તમારી રાશિ પર પોઝ કરો ⏸',
            topTitle: 'આજની ટોપ ૩ લકી રાશિ', topSub: 'શું તમારી રાશિ આમાં છે? 👀',
            day: 'આજનો દિવસ', like: 'લાઇક', share: 'શેર', subscribe: 'સબ્સ્ક્રાઇબ',
            endLine: 'રોજ સવારે તમારું રાશિફળ', endAsk: 'તમારી રાશિ કોમેન્ટમાં લખો 👇',
            ytAll: d => `આજનું રાશિફળ ${d} | 12 રાશિ | Daily Rashifal`,
            ytTop: d => `આજની ટોપ 3 લકી રાશિ 🍀 ${d} | Rashifal`,
            sayEnd: 'વીડિયો ગમ્યો હોય તો લાઇક, શેર અને સબ્સ્ક્રાઇબ કરો',
            tags: '#rashifal #aajnurashifal #gujaratirashifal #horoscope #dailyhoroscope #shorts',
        },
        hindi: {
            allSub: '१२ राशि • १ मिनट में', pause: 'अपनी राशि पर पॉज़ करें ⏸',
            topTitle: 'आज की टॉप ३ लकी राशि', topSub: 'क्या आपकी राशि इनमें है? 👀',
            day: 'आज का दिन', like: 'लाइक', share: 'शेयर', subscribe: 'सब्सक्राइब',
            endLine: 'रोज़ सुबह आपका राशिफल', endAsk: 'अपनी राशि कमेंट में लिखें 👇',
            ytAll: d => `आज का राशिफल ${d} | 12 राशि | Daily Rashifal`,
            ytTop: d => `आज की टॉप 3 लकी राशि 🍀 ${d} | Rashifal`,
            sayEnd: 'वीडियो पसंद आया हो तो लाइक, शेयर और सब्सक्राइब करें',
            tags: '#rashifal #aajkarashifal #horoscope #dailyhoroscope #astrology #shorts',
        },
        hinglish: {
            allSub: '12 Rashi • 1 Minute me', pause: 'Apni rashi par pause karo ⏸',
            topTitle: 'Aaj ki Top 3 Lucky Rashi', topSub: 'Kya aapki rashi isme hai? 👀',
            day: 'Aaj ka din', like: 'Like', share: 'Share', subscribe: 'Subscribe',
            endLine: 'Roz subah aapka rashifal', endAsk: 'Apni rashi comment me likho 👇',
            ytAll: d => `Aaj Ka Rashifal ${d} | 12 Rashi | Daily Horoscope`,
            ytTop: d => `Aaj Ki Top 3 Lucky Rashi 🍀 ${d} | Rashifal`,
            sayEnd: 'Video pasand aaya ho to like, share aur subscribe karo',
            tags: '#rashifal #aajkarashifal #horoscope #dailyhoroscope #astrology #shorts',
        },
    };
    const st = () => SHORT_I18N[itemsLang] || SHORT_I18N.gujarati;
    const shortLine = item => K.str(item.short).trim() || K.str(item.prediction).split(/[.।]/)[0];
    const ratingOf = item => Math.max(1, Math.min(5, parseInt(item.rating, 10) || 3));
    /** Top 3: rating sabse zyada (barabar ho to Mesh→Meen kram). */
    const topThree = () => [...items].sort((a, b) => ratingOf(b) - ratingOf(a) || a._idx - b._idx).slice(0, 3);

    /** Badi, saaf line beech me — Short ka bada title text. */
    function bigText(ctx, text, cy, size, color = '#ffffff', maxH = 260) {
        ctx.save();
        ctx.fillStyle = color;
        ctx.shadowColor = 'rgba(0,0,0,0.45)'; ctx.shadowBlur = 18;
        const f = K.fit(ctx, text, { maxW: W - 140, maxH, size, minSize: 40, lineHeight: 1.25 });
        K.drawLines(ctx, f.lines, W / 2, cy, f.lh);
        ctx.restore();
    }

    function drawStars(ctx, n, cy, size = 86, step = 96) {
        ctx.save();
        ctx.font = `${size}px "Segoe UI Symbol", ${K.STACK}`;
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        const x0 = W / 2 - step * 2;
        for (let k = 0; k < 5; k++) {
            ctx.fillStyle = k < n ? '#facc15' : 'rgba(255,255,255,0.22)';
            ctx.fillText('★', x0 + k * step, cy);
        }
        ctx.restore();
    }

    /** Intro frame — 'all' (12 rashi) ya 'top3'. */
    function drawShortIntro(c, kind) {
        const ctx = c.getContext('2d');
        const S = st();
        K.reset(ctx);
        drawSky(ctx, kind === 'top3' ? 77 : 33);
        drawDatePill(ctx, 120);
        if (kind === 'top3') {
            drawSymbol(ctx, '🍀', 0, `170px ${EMOJI}`);
            bigText(ctx, S.topTitle, 870, 104, '#facc15');
            bigText(ctx, S.topSub, 1080, 64);
        } else {
            drawSymbol(ctx, num(12), 0, K.font(170));
            bigText(ctx, tr().head, 800, 112, '#facc15');
            bigText(ctx, S.allSub, 960, 60);
            // Pause pill
            ctx.save();
            ctx.font = K.font(52);
            const pw = Math.min(W - 120, ctx.measureText(S.pause).width + 100);
            ctx.fillStyle = '#facc15';
            ctx.beginPath(); ctx.roundRect(W / 2 - pw / 2, 1110, pw, 110, 55); ctx.fill();
            ctx.fillStyle = '#3b0764';
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            K.fitOneLine(ctx, S.pause, pw - 60, 52, 32);
            ctx.fillText(S.pause, W / 2, 1167);
            ctx.restore();
        }
        // Neeche 12 rashi chinh ki kataar
        ctx.save();
        ctx.font = `58px "Segoe UI Symbol", ${K.STACK}`;
        ctx.fillStyle = 'rgba(250, 204, 21, 0.75)';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        SYMBOLS.forEach((s, k) => ctx.fillText(s + '︎', 90 + (k % 6) * 180, 1420 + Math.floor(k / 6) * 110));
        ctx.restore();
    }

    /** Ek rashi ka Short frame: chinh, naam, ★ rating, ek line, shubh ank/rang, progress. */
    function drawShortRashi(c, item, pos, total, rank = null) {
        const ctx = c.getContext('2d');
        const L = tr(), S = st();
        const i = item._idx;
        K.reset(ctx);
        drawSky(ctx, i);
        drawDatePill(ctx, 120);
        drawSymbol(ctx, SYMBOLS[i] + '︎');

        if (rank) {
            ctx.save();
            ctx.fillStyle = '#16a34a';
            ctx.strokeStyle = '#ffffff'; ctx.lineWidth = 6;
            ctx.beginPath(); ctx.arc(W / 2 + 165, CY - 120, 70, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
            ctx.fillStyle = '#ffffff';
            ctx.font = K.font(64);
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillText('#' + num(rank), W / 2 + 165, CY - 115);
            ctx.restore();
        }

        ctx.fillStyle = '#ffffff';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.font = K.font(104);
        ctx.fillText(L.names[i], W / 2, 690);
        if (L.letters) {
            ctx.fillStyle = '#fcd34d';
            K.fitOneLine(ctx, '(' + L.letters[i] + ')', W - 200, 42, 28, '400');
            ctx.fillText('(' + L.letters[i] + ')', W / 2, 770);
        }

        // Aaj ka din ★★★★☆
        ctx.fillStyle = 'rgba(255,255,255,0.85)';
        K.fitOneLine(ctx, S.day, W - 200, 40, 28, '400');
        ctx.fillText(S.day, W / 2, 850);
        drawStars(ctx, ratingOf(item), 935);

        // Ek line — bada, panel me
        const px = 60, pw = W - 120, py = 1010, ph = 360;
        ctx.fillStyle = 'rgba(255,255,255,0.12)';
        ctx.strokeStyle = 'rgba(250, 204, 21, 0.55)';
        ctx.lineWidth = 3;
        ctx.beginPath(); ctx.roundRect(px, py, pw, ph, 36); ctx.fill(); ctx.stroke();
        ctx.fillStyle = '#ffffff';
        const f = K.fit(ctx, shortLine(item), { maxW: pw - 90, maxH: ph - 70, size: 68, minSize: 40, lineHeight: 1.3 });
        K.drawLines(ctx, f.lines, W / 2, py + ph / 2, f.lh);

        drawLuckyPills(ctx, item, 1470);

        // Progress: 3 / 12 + bindiyan
        ctx.fillStyle = 'rgba(255,255,255,0.85)';
        ctx.font = K.font(40);
        ctx.fillText(num(pos) + ' / ' + num(total), W / 2, 1590);
        const gap = Math.min(60, (W - 200) / total), x0 = W / 2 - gap * (total - 1) / 2;
        for (let k = 0; k < total; k++) {
            ctx.fillStyle = k < pos ? '#facc15' : 'rgba(255,255,255,0.25)';
            ctx.beginPath(); ctx.arc(x0 + k * gap, 1650, k === pos - 1 ? 13 : 9, 0, Math.PI * 2); ctx.fill();
        }
    }

    /** Aakhri frame — 👍 Like • ↗️ Share • 🔔 Subscribe. */
    function drawShortEnd(c) {
        const ctx = c.getContext('2d');
        const S = st();
        K.reset(ctx);
        drawSky(ctx, 55);
        bigText(ctx, S.endLine, 300, 80, '#facc15', 200);

        const btns = [
            ['👍', S.like, '#2563eb', 560],
            ['↗️', S.share, '#16a34a', 780],
            ['🔔', S.subscribe, '#dc2626', 1000],
        ];
        btns.forEach(([icon, label, color, y], k) => {
            const bw = k === 2 ? 820 : 720, bh = k === 2 ? 170 : 150;
            ctx.save();
            ctx.shadowColor = 'rgba(0,0,0,0.35)'; ctx.shadowBlur = 24;
            ctx.fillStyle = color;
            ctx.beginPath(); ctx.roundRect(W / 2 - bw / 2, y - bh / 2, bw, bh, bh / 2); ctx.fill();
            ctx.restore();
            ctx.textBaseline = 'middle';
            ctx.font = `${k === 2 ? 84 : 74}px ${EMOJI}`;
            ctx.textAlign = 'center';
            ctx.fillStyle = '#ffffff';
            ctx.fillText(icon, W / 2 - bw / 2 + 110, y + 4);
            ctx.font = K.font(k === 2 ? 76 : 68);
            ctx.fillText(label, W / 2 + 50, y + 4);
        });

        bigText(ctx, S.endAsk, 1260, 60, '#ffffff', 180);
        ctx.save();
        ctx.font = `58px "Segoe UI Symbol", ${K.STACK}`;
        ctx.fillStyle = 'rgba(250, 204, 21, 0.75)';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        SYMBOLS.forEach((s, k) => ctx.fillText(s + '︎', 90 + (k % 6) * 180, 1460 + Math.floor(k / 6) * 110));
        ctx.restore();
    }

    /** Dono Shorts ke frames: [{kind, caption, frames:[{text, draw}]}] — draw(canvas) frame banata hai. */
    function shortPlans() {
        const L = tr(), S = st();
        const [day, date] = dateParts();
        const dayTitle = date;
        const all = items.map((item, k) => ({
            text: L.names[item._idx] + '. ' + shortLine(item),
            draw: cv => drawShortRashi(cv, item, k + 1, items.length),
        }));
        const top = topThree().map((item, k) => ({
            text: '#' + (k + 1) + '. ' + L.names[item._idx] + '. ' + shortLine(item),
            draw: cv => drawShortRashi(cv, item, k + 1, 3, k + 1),
        }));
        const end = { text: S.sayEnd, draw: cv => drawShortEnd(cv) };
        const descAll = items.map(it => SYMBOLS[it._idx] + ' ' + L.names[it._idx] + ': ' + shortLine(it)).join('\n');
        const descTop = topThree().map((it, k) => '#' + (k + 1) + ' ' + SYMBOLS[it._idx] + ' ' + L.names[it._idx] + ' ' + '★'.repeat(ratingOf(it)) + ' — ' + shortLine(it)).join('\n');

        return [
            {
                kind: 'all',
                caption: S.ytAll(dayTitle) + '\n\n' + L.head + ' — ' + day + ', ' + date + '\n\n' + descAll + '\n\n' + S.tags,
                frames: [{ text: L.head + '. ' + day + ', ' + date, draw: cv => drawShortIntro(cv, 'all') }, ...all, end],
            },
            {
                kind: 'top3',
                caption: S.ytTop(dayTitle) + '\n\n' + descTop + '\n\n' + S.tags,
                frames: [{ text: S.topTitle + '. ' + date, draw: cv => drawShortIntro(cv, 'top3') }, ...top, end],
            },
        ];
    }

    /** YouTube Shorts ke frames bana ke save karo (sirf YouTube par jaate hain). */
    async function saveShorts() {
        await K.ensureFonts();
        const off = document.createElement('canvas'); off.width = W; off.height = H;
        const parts = shortPlans().map(p => ({
            kind: p.kind,
            caption: p.caption,
            frames: p.frames.map(f => { f.draw(off); return { text: f.text, image: off.toDataURL('image/webp', 0.85) }; }),
        }));
        return postJson(SHORT_URL, { date: itemsDate, language: itemsLang, parts });
    }

    /** Generate ke baad Shorts ke frames ki chhoti jhalak (12 Rashi + Top 3). */
    function renderShortPreviews() {
        const wrap = el('shortPreview');
        if (!wrap) return;
        if (!wantShorts()) { wrap.classList.add('hidden'); return; }
        wrap.classList.remove('hidden');
        const rows = wrap.querySelector('.rows');
        rows.innerHTML = '';
        shortPlans().forEach(p => {
            const row = document.createElement('div');
            row.innerHTML = `<p class="text-xs font-bold text-slate-600 mb-1">${p.kind === 'top3' ? '🍀 Top 3 Lucky Rashi' : '🔯 12 Rashi'} — ${p.frames.length} frames</p>
                <div class="flex gap-2 overflow-x-auto pb-2 frames"></div>`;
            p.frames.forEach(f => {
                const cv = document.createElement('canvas');
                cv.width = W; cv.height = H;
                cv.className = 'h-48 w-auto aspect-[9/16] rounded border border-slate-200 shrink-0';
                f.draw(cv);
                row.querySelector('.frames').appendChild(cv);
            });
            rows.appendChild(row);
        });
    }

    /** Reel voiceover: rashi ka naam + bhavishya + prem/kaam/swasthya. */
    function voiceText(item) {
        const L = tr();
        return [
            L.head + ', ' + L.names[item._idx],
            K.str(item.prediction),
            L.love + ' ' + K.str(item.love),
            L.career + ' ' + K.str(item.career),
            L.health + ' ' + K.str(item.health),
            K.str(item.do) ? ct().doL.replace(/^\S+\s/, '') + ' ' + K.str(item.do) : '',
            K.str(item.upay) ? ct().upay.replace(/^\S+\s/, '') + ' ' + K.str(item.upay) : '',
        ].map(s => s.trim()).filter(Boolean).join('. ');
    }

    // ---------- Save ----------
    async function postJson(url, body) {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(body)
        });
        const d = await r.json();
        if (!d.ok) throw new Error(K.saveError(d));
        return d;
    }

    /** Abhi ke items (itemsDate / itemsLang) ke 12 cards bana ke save karo. */
    async function saveCurrent() {
        await K.ensureFonts();
        const off = document.createElement('canvas'); off.width = W; off.height = H;
        const cardsToSave = items.map(item => {
            drawRashi(off, item);
            return {
                order: item._idx + 1,
                text: voiceText(item),
                // Caption ke ant me bhi "comment RASHI → DM" (card wala banner)
                caption: (K.str(item.caption) || (headText() + ' — ' + tr().names[item._idx] + ' ' + SYMBOLS[item._idx]))
                    + '\n\n' + ct().dmTop + ' ' + ct().dmBottom,
                hashtags: K.str(item.hashtags),
                image: off.toDataURL('image/webp', 0.92),
            };
        });
        const saved = await postJson(SAVE_URL, { date: itemsDate, language: itemsLang, cards: cardsToSave });

        // YouTube ke 2 Shorts (checkbox tick ho to) — cards save ho chuke, Shorts fail ho to saaf batao
        if (!wantShorts()) return saved;
        try {
            await saveShorts();
        } catch (e) {
            throw new Error('12 cards save ho gaye, lekin YouTube Shorts save nahi hue: ' + e.message);
        }

        return saved;
    }

    el('saveBtn').addEventListener('click', async () => {
        if (!items.length) return;
        if (dateExists && !confirm('Is date ka rashifal pehle se save hai. Phir bhi naya save karein? (dono post honge)')) return;
        const btn = el('saveBtn');
        btn.disabled = true;
        el('progressBox').classList.remove('hidden');
        el('progressText').textContent = 'Saving…';
        el('progressBar').style.width = '50%';

        try {
            const d = await saveCurrent();
            el('progressBar').style.width = '100%';
            setTimeout(() => window.location = d.redirect, 400);
        } catch (e) {
            alert('Save error: ' + e.message);
            btn.disabled = false;
            el('progressBox').classList.add('hidden');
        }
    });

    // ---------- Agle 7 din: generate + seedha save, ek ke baad ek ----------
    el('weekBtn').addEventListener('click', async () => {
        const start = el('date').value;
        if (!start) { alert('Date chuno'); return; }
        const lang = el('language').value;
        const days = [...Array(7)].map((_, i) => {
            const d = new Date(start + 'T00:00:00');
            d.setDate(d.getDate() + i);
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        });
        if (!confirm(`${days[0]} se ${days[6]} tak 7 din ka rashifal banake save karein?`)) return;

        const btns = ['weekBtn', 'genBtn', 'saveBtn'].map(el);
        btns.forEach(b => b.disabled = true);
        el('progressBox').classList.remove('hidden');
        const done = [], skipped = [], failed = [];

        for (const [i, day] of days.entries()) {
            el('progressText').textContent = `Din ${i + 1}/7 (${day}) — AI likh raha hai…`;
            el('progressBar').style.width = Math.round((i / 7) * 100) + '%';
            try {
                const d = await postJson(GEN_URL, { date: day, language: lang, skip_existing: true, festival: 'auto' });
                if (d.skipped) { skipped.push(day); continue; }

                items = orderItems(Array.isArray(d.items) ? d.items : []);
                if (items.length < 12) throw new Error(`AI ne sirf ${items.length} rashi di`);
                itemsLang = lang;
                itemsDate = day;
                itemsFestival = d.festival || ''; // tyohar wala din apne aap festival theme me
                itemsPanchang = d.panchang || null;
                el('progressText').textContent = `Din ${i + 1}/7 (${day}) — save ho raha hai…`;
                await saveCurrent();
                done.push(day);
            } catch (e) {
                failed.push(day + ': ' + e.message);
            }
        }

        el('progressBar').style.width = '100%';
        alert(
            `✅ Save hua: ${done.length} din` +
            (skipped.length ? `\n⏭ Pehle se tha (skip): ${skipped.join(', ')}` : '') +
            (failed.length ? `\n⚠ Fail (dobara try karo):\n${failed.join('\n')}` : '')
        );
        window.location.reload();
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
                if (count > 0 && confirm(`Kya aap sach me chune hue ${count} rashifal delete karna chahte hain?`)) {
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
