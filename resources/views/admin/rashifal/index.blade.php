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
            <span class="text-xs text-slate-500">(roj 3 Shorts, 13 themes ma thi — tick nahi hoy to sirf 12 cards, Instagram + Facebook mate)</span>
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
        <p class="text-xs text-slate-500 mb-3">Upar ke 12 cards ab sirf Instagram + Facebook par jaate hain. YouTube par us din ye 3 Shorts jaate hain (13 themes me se — tyohar / poonam-amas / somvar ho to us din ka khaas theme pehle). Na joie to upar "📺 YouTube Shorts pan banavo" no tick kaadho.</p>
        <div class="rows space-y-3"></div>
    </div>

    {{-- 🎬 Saptahik YouTube Video (16:9, lamba) — hafte me ek baar --}}
    <div id="weekBox" class="mt-8 bg-white rounded-2xl border-2 border-red-200 p-5 shadow-sm space-y-4">
        <div>
            <h2 class="text-lg font-bold text-slate-800">🎬 Saptahik YouTube Video <span class="text-xs font-normal text-slate-500">— 16:9 • 12 rashi • ~8-10 min • chapters + thumbnail</span></h2>
            <p class="text-xs text-slate-500 mt-1">Hafte me <b>ek baar</b> banao — Shanivar/Ravivar ko agle hafte ka. Somvar se auto-post pehle khaali time slot me (Shorts se pehle) upload karega. Bhasha upar wali "Language" se.</p>
        </div>
        <div class="grid sm:grid-cols-3 gap-4 items-end">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">📅 Hafta (koi bhi din chuno)</label>
                <input id="weekDate" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:outline-none font-medium">
                <p id="weekRange" class="text-[11px] text-red-700 font-semibold mt-1"></p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">🔊 Awaaz</label>
                <select id="weekAudio" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:outline-none font-medium">
                    <option value="voice_music" selected>🎙 AI Voice + halka music (salah)</option>
                    <option value="music">🎵 Sirf music</option>
                </select>
            </div>
            <button id="weekGenBtn" type="button" class="bg-gradient-to-r from-red-600 to-rose-700 hover:from-red-700 hover:to-rose-800 text-white font-bold py-2 px-4 rounded-lg shadow transition disabled:opacity-50">
                🎬 Video ke frames banao
            </button>
        </div>
        {{-- Kaun kaun se hafte ke video ban chuke hain --}}
        @php
            $thisMonday = now()->startOfWeek(\Illuminate\Support\Carbon::MONDAY);
            $weekList = $weeks ?? collect();
        @endphp
        <div class="border border-slate-200 rounded-xl overflow-hidden">
            <div class="bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700">📋 Bane hue saptahik videos</div>
            @forelse ($weekList as $w)
                @php
                    $mon = \Illuminate\Support\Carbon::parse($w->category);
                    $part = $w->parts->first();
                    $first = $part?->cards->first();
                    $st = $first?->yt_status;
                    $when = $mon->equalTo($thisMonday) ? 'Is hafte' : ($mon->greaterThan($thisMonday) ? ($mon->equalTo($thisMonday->copy()->addWeek()) ? 'Agle hafte' : 'Aage') : 'Pichhla');
                @endphp
                <a href="{{ route('admin.rashifal.show', $w) }}" class="flex items-center gap-3 px-3 py-2 border-t border-slate-100 hover:bg-red-50/40 text-sm">
                    @if ($part?->image_path)
                        <img src="{{ asset('storage/' . $part->image_path) }}" class="h-10 aspect-video object-cover rounded border border-slate-200" alt="">
                    @endif
                    <span class="font-semibold text-slate-800">{{ $mon->format('d M') }} – {{ $mon->copy()->addDays(6)->format('d M Y') }}</span>
                    <span class="text-[11px] px-2 py-0.5 rounded-full {{ $when === 'Is hafte' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600' }}">{{ $when }}</span>
                    <span class="ml-auto text-xs font-semibold {{ $st === 'posted' ? 'text-green-700' : ($st === 'failed' ? 'text-red-600' : 'text-amber-700') }}">
                        @if ($st === 'posted') ✅ YouTube par upload ho gaya
                        @elseif ($st === 'posting') ⏳ Upload ho raha hai
                        @elseif ($st === 'failed') ❌ Fail — kholo, dobara upload karo
                        @elseif ($mon->lessThan($thisMonday)) ⚠️ Upload nahi hua (hafta nikal gaya)
                        @else ⏳ Saved — {{ $mon->format('d M') }} (Somvar) se auto-upload
                        @endif
                    </span>
                </a>
            @empty
                <p class="px-3 py-3 text-xs text-slate-500 border-t border-slate-100">Abhi koi saptahik video nahi bana. Neeche hafta chun ke "🎬 Video ke frames banao" dabao.</p>
            @endforelse
            @if ($weekList->doesntContain(fn ($w) => $w->category === $thisMonday->copy()->addWeek()->toDateString()))
                <p class="px-3 py-2 text-[11px] font-semibold text-red-700 bg-red-50 border-t border-red-100">⚠️ Agle hafte ({{ $thisMonday->copy()->addWeek()->format('d M') }}) ka video abhi nahi bana.</p>
            @endif
        </div>

        <p id="weekStatus" class="text-xs font-semibold text-slate-600"></p>
        <div id="weekPreview" class="hidden space-y-3">
            <div class="flex items-start gap-4 flex-wrap">
                <div>
                    <p class="text-xs font-bold text-slate-600 mb-1">🖼 Thumbnail (cover)</p>
                    <div id="weekThumb"></div>
                </div>
                <div class="flex-1 min-w-[240px]">
                    <p class="text-xs font-bold text-slate-600 mb-1">📝 YouTube title</p>
                    <p id="weekTitle" class="text-sm font-semibold text-slate-800 bg-slate-50 border border-slate-200 rounded-lg p-2"></p>
                    <button id="weekSaveBtn" type="button" class="mt-3 bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-5 rounded-lg shadow disabled:opacity-50">💾 Video Save karo</button>
                    <p class="text-[11px] text-slate-500 mt-1">Save ke baad detail page par "▶ Video dekho" se poora video check kar sakte ho.</p>
                </div>
            </div>
            <p class="text-xs font-bold text-slate-600">🎞 Frames</p>
            <div id="weekFrames" class="flex gap-2 overflow-x-auto pb-2"></div>
        </div>
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
                            <span class="font-bold text-slate-800 group-hover:text-indigo-700 transition">{{ $c->type === 'rashifal_short' ? '📺' : ($c->type === 'rashifal_week' ? '🎬' : '🔯') }} {{ $c->title }}</span>
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
    const WEEK_GEN_URL = @json(route('admin.rashifal.week.generate'));
    const WEEK_SAVE_URL = @json(route('admin.rashifal.week.save'));

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
    //  📺 YouTube-only Shorts — roz 13 themes me se 3 Shorts (har Short = 3 rashi)
    //  Frames: intro → 3 rashi frames → 👍 Like • ↗️ Share • 🔔 Subscribe
    //  Kaunse 3: tarikh ke seed se (jo jhalak dikhe wahi save ho). Tyohar / Poonam-Amas /
    //  Somvar ho to us din ka khaas theme pehle, baaki random (ek jaise 2 theme saath nahi).
    // =====================================================================
    const EMOJI = '"Segoe UI Emoji", "Apple Color Emoji", "Noto Color Emoji", sans-serif';
    const SHORT_I18N = {
        gujarati: {
            day: 'આજનો દિવસ', like: 'લાઇક', share: 'શેર', subscribe: 'સબ્સ્ક્રાઇબ',
            endLine: 'રોજ સવારે તમારું રાશિફળ', endAsk: 'તમારી રાશિ કોમેન્ટમાં લખો 👇',
            sayEnd: 'વીડિયો ગમ્યો હોય તો લાઇક, શેર અને સબ્સ્ક્રાઇબ કરો',
            tags: '#rashifal #aajnurashifal #gujaratirashifal #horoscope #dailyhoroscope #shorts',
        },
        hindi: {
            day: 'आज का दिन', like: 'लाइक', share: 'शेयर', subscribe: 'सब्सक्राइब',
            endLine: 'रोज़ सुबह आपका राशिफल', endAsk: 'अपनी राशि कमेंट में लिखें 👇',
            sayEnd: 'वीडियो पसंद आया हो तो लाइक, शेयर और सब्सक्राइब करें',
            tags: '#rashifal #aajkarashifal #horoscope #dailyhoroscope #astrology #shorts',
        },
        hinglish: {
            day: 'Aaj ka din', like: 'Like', share: 'Share', subscribe: 'Subscribe',
            endLine: 'Roz subah aapka rashifal', endAsk: 'Apni rashi comment me likho 👇',
            sayEnd: 'Video pasand aaya ho to like, share aur subscribe karo',
            tags: '#rashifal #aajkarashifal #horoscope #dailyhoroscope #astrology #shorts',
        },
    };
    const st = () => SHORT_I18N[itemsLang] || SHORT_I18N.gujarati;

    // Har theme: [title, sub (intro par), label (rashi frame ke panel par)]
    const THEME_TXT = {
        gujarati: {
            savdhan: ['આજે સાવધાન રહે આ ૩ રાશિ', 'નાની ભૂલ મોટી ન બને — જુઓ શું ટાળવું', '❌ આજે આ ટાળો'],
            prem:    ['આજે પ્રેમમાં લકી ૩ રાશિ', 'શું તમારી રાશિ આમાં છે? 💕', '💕 પ્રેમ'],
            dhan:    ['આજે ધન લાભ — ટોપ ૩ રાશિ', 'પૈસા આવવાના યોગ 💰', '💰 કામકાજ અને ધન'],
            rang:    ['આજનો શુભ રંગ — ૩ રાશિ', 'આ રંગ પહેરો, દિવસ સુધરશે 🎨', '🎨 શુભ રંગ'],
            ank:     ['આજનો લકી નંબર — ૩ રાશિ', 'તમારો નંબર કયો છે? 🔢', '🔢 શુભ અંક'],
            upay:    ['આજે આ ૩ રાશિ કરે આ ઉપાય', 'સરળ અને મફત ઉપાય 🙏', '🙏 ઉપાય'],
            galti:   ['આજે આ ભૂલ ન કરતા — ૩ રાશિ', 'ધ્યાનથી જુઓ 🚫', '🚫 આ ભૂલ ન કરો'],
            shaadi:  ['આજે સંબંધ પાકો થઈ શકે — ૩ રાશિ', 'લગ્ન અને સંબંધના યોગ 💍', '💍 સંબંધ'],
            naukri:  ['નોકરી-ધંધામાં પ્રગતિ — ૩ રાશિ', 'મહેનતનું ફળ મળશે 📈', '📈 કરિયર'],
            jodi:    ['આજની બેસ્ટ જોડી', 'તમારી જોડી કઈ છે? 🤝', '🤝 જોડી'],
            week:    ['આ અઠવાડિયાની ટોપ ૩ લકી રાશિ', 'અઠવાડિયું કેવું જશે? 🗓️', '🍀 આ અઠવાડિયે'],
            poonam:  ['પૂનમ પર આ ૩ રાશિનું ભાગ્ય ચમકશે', 'ચંદ્ર દેવની કૃપા 🌕', '🌕 પૂનમ'],
            amas:    ['અમાસ પર આ ૩ રાશિ કરે આ ઉપાય', 'નકારાત્મકતા દૂર થશે 🌑', '🙏 ઉપાય'],
            fest:    ['{f} પર આ ૩ રાશિ પર વિશેષ કૃપા', 'શું તમારી રાશિ આમાં છે? 🪔', '✨ {f}'],
            festNames: { dhanteras: 'ધનતેરસ', kali_chaudas: 'કાળી ચૌદસ', diwali: 'દિવાળી', navu_varsh: 'નૂતન વર્ષ', bhai_beej: 'ભાઈબીજ', labh_pancham: 'લાભ પાંચમ', dev_diwali: 'દેવ દિવાળી' },
            rangTpl: 'આજે {c} રંગ પહેરો — {x}', ankTpl: 'આજનો શુભ અંક {n} — {x}',
            jodiLine: { same: 'એક જેવો સ્વભાવ — એકબીજાને તરત સમજે', fa: 'જોશ અને વિચારોનો સુંદર મેળ', ew: 'ભરોસો અને લાગણીનો મજબૂત સાથ' },
            dayLove: 'પ્રેમ', dayMoney: 'ધન યોગ', dayCareer: 'કરિયર', dayRel: 'સંબંધ યોગ', dayJodi: 'જોડીનો મેળ',
        },
        hindi: {
            savdhan: ['आज सावधान रहें ये ३ राशि', 'छोटी गलती बड़ी न बने — देखें क्या टालें', '❌ आज यह टालें'],
            prem:    ['आज प्यार में लकी ३ राशि', 'क्या आपकी राशि इनमें है? 💕', '💕 प्रेम'],
            dhan:    ['आज धन लाभ — टॉप ३ राशि', 'पैसा आने के योग 💰', '💰 काम और धन'],
            rang:    ['आज का शुभ रंग — ३ राशि', 'यह रंग पहनें, दिन बनेगा 🎨', '🎨 शुभ रंग'],
            ank:     ['आज का लकी नंबर — ३ राशि', 'आपका नंबर कौन सा है? 🔢', '🔢 शुभ अंक'],
            upay:    ['आज ये ३ राशि करें यह उपाय', 'आसान और मुफ़्त उपाय 🙏', '🙏 उपाय'],
            galti:   ['आज यह गलती न करें — ३ राशि', 'ध्यान से देखें 🚫', '🚫 यह गलती न करें'],
            shaadi:  ['आज रिश्ता पक्का हो सकता है — ३ राशि', 'शादी और रिश्ते के योग 💍', '💍 रिश्ता'],
            naukri:  ['नौकरी-बिज़नेस में तरक्की — ३ राशि', 'मेहनत का फल मिलेगा 📈', '📈 करियर'],
            jodi:    ['आज की बेस्ट जोड़ी', 'आपकी जोड़ी कौन सी है? 🤝', '🤝 जोड़ी'],
            week:    ['इस हफ़्ते की टॉप ३ लकी राशि', 'हफ़्ता कैसा रहेगा? 🗓️', '🍀 इस हफ़्ते'],
            poonam:  ['पूर्णिमा पर इन ३ राशियों का भाग्य चमकेगा', 'चंद्र देव की कृपा 🌕', '🌕 पूर्णिमा'],
            amas:    ['अमावस्या पर ये ३ राशि करें यह उपाय', 'नकारात्मकता दूर होगी 🌑', '🙏 उपाय'],
            fest:    ['{f} पर इन ३ राशियों पर विशेष कृपा', 'क्या आपकी राशि इनमें है? 🪔', '✨ {f}'],
            festNames: { dhanteras: 'धनतेरस', kali_chaudas: 'काली चौदस', diwali: 'दिवाली', navu_varsh: 'नव वर्ष', bhai_beej: 'भाई दूज', labh_pancham: 'लाभ पंचमी', dev_diwali: 'देव दिवाली' },
            rangTpl: 'आज {c} रंग पहनें — {x}', ankTpl: 'आज का शुभ अंक {n} — {x}',
            jodiLine: { same: 'एक जैसा स्वभाव — एक-दूसरे को तुरंत समझें', fa: 'जोश और विचारों का सुंदर मेल', ew: 'भरोसे और भावनाओं का मज़बूत साथ' },
            dayLove: 'प्रेम', dayMoney: 'धन योग', dayCareer: 'करियर', dayRel: 'रिश्ता योग', dayJodi: 'जोड़ी का मेल',
        },
        hinglish: {
            savdhan: ['Aaj Savdhan Rahe Ye 3 Rashi', 'Chhoti galti badi na bane — dekho kya na kare', '❌ Aaj ye avoid karo'],
            prem:    ['Aaj Pyaar Me Lucky 3 Rashi', 'Kya aapki rashi isme hai? 💕', '💕 Love'],
            dhan:    ['Aaj Dhan Labh — Top 3 Rashi', 'Paisa aane ke yog 💰', '💰 Kaam aur Dhan'],
            rang:    ['Aaj Ka Lucky Color — 3 Rashi', 'Ye rang pehno, din banega 🎨', '🎨 Lucky Color'],
            ank:     ['Aaj Ka Lucky Number — 3 Rashi', 'Aapka number kaunsa hai? 🔢', '🔢 Lucky Number'],
            upay:    ['Aaj Ye 3 Rashi Kare Ye Upay', 'Aasan aur free upay 🙏', '🙏 Upay'],
            galti:   ['Aaj Ye Galti Mat Karna — 3 Rashi', 'Dhyan se dekho 🚫', '🚫 Ye galti mat karo'],
            shaadi:  ['Aaj Rishta Pakka Ho Sakta Hai — 3 Rashi', 'Shaadi aur rishte ke yog 💍', '💍 Rishta'],
            naukri:  ['Naukri-Business Me Tarakki — 3 Rashi', 'Mehnat ka phal milega 📈', '📈 Career'],
            jodi:    ['Aaj Ki Best Jodi', 'Aapki jodi kaunsi hai? 🤝', '🤝 Jodi'],
            week:    ['Is Hafte Ki Top 3 Lucky Rashi', 'Hafta kaisa jayega? 🗓️', '🍀 Is hafte'],
            poonam:  ['Purnima Par In 3 Rashi Ka Bhagya Chamkega', 'Chandra Dev ki kripa 🌕', '🌕 Purnima'],
            amas:    ['Amavasya Par Ye 3 Rashi Kare Ye Upay', 'Negativity door hogi 🌑', '🙏 Upay'],
            fest:    ['{f} Par In 3 Rashi Par Vishesh Kripa', 'Kya aapki rashi isme hai? 🪔', '✨ {f}'],
            festNames: { dhanteras: 'Dhanteras', kali_chaudas: 'Kali Chaudas', diwali: 'Diwali', navu_varsh: 'Navu Varsh', bhai_beej: 'Bhai Beej', labh_pancham: 'Labh Pancham', dev_diwali: 'Dev Diwali' },
            rangTpl: 'Aaj {c} color pehno — {x}', ankTpl: 'Aaj ka lucky number {n} — {x}',
            jodiLine: { same: 'Ek jaisa swabhav — ek-dusre ko turant samjhe', fa: 'Josh aur vichaaron ka sundar mel', ew: 'Bharose aur emotions ka mazboot saath' },
            dayLove: 'Love', dayMoney: 'Dhan Yog', dayCareer: 'Career', dayRel: 'Rishta Yog', dayJodi: 'Jodi ka mel',
        },
    };
    const tt = () => THEME_TXT[itemsLang] || THEME_TXT.gujarati;
    const fill = (tpl, vars) => tpl.replace(/\{(\w)\}/g, (_, k) => vars[k] ?? '');

    const shortLine = item => K.str(item.short).trim() || K.str(item.prediction).split(/[.।]/)[0];
    const ratingOf = item => Math.max(1, Math.min(5, parseInt(item.rating, 10) || 3));
    const score = (item, key) => Math.max(1, Math.min(10, parseInt(item[key], 10) || 5));
    const toStars = s => Math.max(1, Math.min(5, Math.round(s / 2)));
    const desc = f => (a, b) => (f(b) - f(a)) || a._idx - b._idx;
    const asc = f => (a, b) => (f(a) - f(b)) || a._idx - b._idx;
    const top3 = cmp => [...items].sort(cmp).slice(0, 3);
    const moonDay = () => pj()?.moon || ''; // 'poonam' | 'amas' | ''

    /** Seed ko achhe se ghol do — warna lagataar tarikhon (…24, …25) par ek jaise theme aate. */
    function mix(n) {
        n = (n ^ 61) ^ (n >>> 16);
        n = Math.imul(n, 0x27d4eb2d);
        n ^= n >>> 15;
        return (Math.abs(n) % 2147483646) + 1;
    }
    let shortShuffle = 0; // "🔄 Bija 3" button
    const daySeed = () => mix((parseInt(String(itemsDate).replace(/\D/g, ''), 10) || 1) + shortShuffle * 7919);
    function shuffled(arr, r) {
        const a = [...arr];
        for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(r() * (i + 1)); [a[i], a[j]] = [a[j], a[i]]; }
        return a;
    }

    /**
     * 13 themes. pick(r) → 3 rashi; line → panel ki line; stars/day → ★ aur uske upar ka shabd;
     * rank → #1 #2 #3 badge; group → ek din ek group se ek hi theme; special → sirf us din.
     */
    const THEMES = [
        { key: 'savdhan', group: 'warn', emoji: '⚠️',
          pick: () => top3(asc(it => ratingOf(it) * 10 + score(it, 'health_score'))),
          line: it => K.str(it.dont).trim() || shortLine(it) },
        { key: 'prem', group: 'love', emoji: '💕', rank: true,
          pick: () => top3(desc(it => score(it, 'love_score') * 10 + ratingOf(it))),
          line: it => K.str(it.love).trim() || shortLine(it),
          stars: it => toStars(score(it, 'love_score')), day: T => T.dayLove },
        { key: 'dhan', group: 'career', emoji: '💰', rank: true,
          pick: () => top3(desc(it => ratingOf(it) * 10 + score(it, 'career_score'))),
          line: it => K.str(it.career).trim() || shortLine(it), day: T => T.dayMoney },
        { key: 'rang', emoji: '🎨',
          pick: r => shuffled(items.filter(it => colorHex(it.lucky_color)), r).concat(shuffled(items, r)).filter((it, k, a) => a.indexOf(it) === k).slice(0, 3),
          line: it => fill(tt().rangTpl, { c: K.str(it.lucky_color), x: shortLine(it) }),
          swatch: it => colorHex(it.lucky_color) },
        { key: 'ank', emoji: '🔢',
          pick: r => shuffled(items, r).slice(0, 3),
          line: it => fill(tt().ankTpl, { n: num(it.lucky_number), x: shortLine(it) }) },
        { key: 'upay', group: 'remedy', emoji: '🙏',
          pick: r => shuffled(items, r).slice(0, 3),
          line: it => K.str(it.upay).trim() || shortLine(it) },
        { key: 'galti', group: 'warn', emoji: '🚫',
          pick: r => shuffled(items, r).slice(0, 3),
          line: it => K.str(it.dont).trim() || shortLine(it) },
        { key: 'shaadi', group: 'love', emoji: '💍', rank: true,
          pick: () => top3(desc(it => ratingOf(it) * 10 + score(it, 'love_score'))),
          line: it => K.str(it.love).trim() || shortLine(it),
          stars: it => toStars(score(it, 'love_score')), day: T => T.dayRel },
        { key: 'naukri', group: 'career', emoji: '📈', rank: true,
          pick: () => top3(desc(it => score(it, 'career_score') * 10 + ratingOf(it))),
          line: it => K.str(it.career).trim() || shortLine(it),
          stars: it => toStars(score(it, 'career_score')), day: T => T.dayCareer },
        { key: 'jodi', emoji: '🤝', jodi: true },
        { key: 'week', emoji: '🗓️', rank: true,
          special: () => new Date(itemsDate + 'T00:00:00').getDay() === 1, // Somvar
          pick: () => top3(desc(it => ratingOf(it) * 100 + score(it, 'love_score') + score(it, 'career_score') + score(it, 'health_score'))),
          line: shortLine },
        { key: 'chandra', group: 'remedy', emoji: () => (moonDay() === 'amas' ? '🌑' : '🌕'), rank: true,
          special: () => moonDay() !== '' && itemsFestival !== 'navu_varsh',
          pick: () => top3(desc(it => ratingOf(it) * 10 + score(it, 'health_score'))),
          line: it => (moonDay() === 'amas' ? (K.str(it.upay).trim() || shortLine(it)) : shortLine(it)) },
        { key: 'fest', emoji: () => fest()?.deco || '🪔', rank: true,
          special: () => !!fest(),
          pick: () => top3(desc(it => ratingOf(it) * 10 + score(it, 'career_score'))),
          line: shortLine },
    ];
    const themeEmoji = th => (typeof th.emoji === 'function' ? th.emoji() : th.emoji);
    function themeTxt(th) {
        const T = tt();
        if (th.key === 'chandra') return T[moonDay() === 'amas' ? 'amas' : 'poonam'];
        if (th.key === 'fest') {
            const f = T.festNames[itemsFestival] || '';
            return T.fest.map(s => fill(s, { f }));
        }
        return T[th.key];
    }

    /** Is din ke 3 themes: khaas din ka ek (Tyohar > Poonam/Amas > Somvar), baaki random. */
    function pickThemes() {
        const r = rng(daySeed());
        const byKey = k => THEMES.find(t => t.key === k);
        const chosen = [];
        const special = ['fest', 'chandra', 'week'].map(byKey).find(t => t.special());
        if (special) chosen.push(special);
        for (const th of shuffled(THEMES.filter(t => !t.special), r)) {
            if (chosen.length >= 3) break;
            if (th.group && chosen.some(c => c.group === th.group)) continue;
            chosen.push(th);
        }
        return chosen;
    }

    /** Best jodi: ek hi tatva ya mel khate tatva (Agni+Vayu, Prithvi+Jal = index ki same parity). */
    function bestPairs() {
        const pairs = [];
        for (let a = 0; a < items.length; a++) {
            for (let b = a + 1; b < items.length; b++) {
                const x = items[a], y = items[b];
                if (x._idx % 2 !== y._idx % 2) continue;
                pairs.push([x, y, (ratingOf(x) + ratingOf(y)) * 10 + score(x, 'love_score') + score(y, 'love_score')]);
            }
        }
        pairs.sort((p, q) => (q[2] - p[2]) || (p[0]._idx - q[0]._idx) || (p[1]._idx - q[1]._idx));
        const used = new Set(), out = [];
        for (const [x, y] of pairs) {
            if (used.has(x._idx) || used.has(y._idx)) continue;
            used.add(x._idx); used.add(y._idx);
            out.push([x, y]);
            if (out.length === 3) break;
        }
        return out;
    }
    function jodiLine(a, b) {
        const J = tt().jodiLine;
        if (a._idx % 4 === b._idx % 4) return J.same;
        return a._idx % 2 === 0 ? J.fa : J.ew; // 0/2 = Agni/Vayu, 1/3 = Prithvi/Jal
    }

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

    function drawSymbolRow(ctx, y0) {
        ctx.save();
        ctx.font = `58px "Segoe UI Symbol", ${K.STACK}`;
        ctx.fillStyle = 'rgba(250, 204, 21, 0.75)';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        SYMBOLS.forEach((s, k) => ctx.fillText(s + '︎', 90 + (k % 6) * 180, y0 + Math.floor(k / 6) * 110));
        ctx.restore();
    }

    /** Text par moti kaali outline + safed/peela fill — chhote thumbnail me bhi padha jaaye. */
    function strokedLines(ctx, lines, cy, lh, fillColor, stroke = 16, x = W / 2) {
        ctx.save();
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.lineJoin = 'round'; ctx.miterLimit = 2;
        const y0 = cy - ((lines.length - 1) * lh) / 2;
        lines.forEach((l, i) => {
            const y = y0 + i * lh;
            ctx.shadowColor = 'rgba(0,0,0,0.55)'; ctx.shadowBlur = 24; ctx.shadowOffsetY = 6;
            ctx.strokeStyle = '#1e0538'; ctx.lineWidth = stroke;
            ctx.strokeText(l, x, y);
            ctx.shadowColor = 'transparent';
            ctx.fillStyle = fillColor;
            ctx.fillText(l, x, y);
        });
        ctx.restore();
    }

    /**
     * Intro = Short ka COVER (YouTube aksar pehla frame thumbnail banata hai):
     * bada emoji gola, bahut bada outline wala title, laal pill me sub-line, aur neeche
     * is Short ki 3 rashi ke bade sunehre chinh + naam. Zaroori cheezein y 100–1560 me
     * (neeche YouTube ka title/buttons aa jaate hain).
     * chips = [{glyph, name}] (jodi me glyph = do chinh).
     */
    function drawShortIntro(c, emoji, title, sub, chips = []) {
        const ctx = c.getContext('2d');
        K.reset(ctx);
        drawSky(ctx, 77, 380, [215, 260]);

        // Beech me halki roshni — title ubhar ke aaye
        const glow = ctx.createRadialGradient(W / 2, 800, 40, W / 2, 800, 620);
        glow.addColorStop(0, 'rgba(250, 204, 21, 0.20)');
        glow.addColorStop(1, 'rgba(250, 204, 21, 0)');
        ctx.fillStyle = glow;
        ctx.fillRect(0, 0, W, H);

        drawDatePill(ctx, 105, 40, 74);
        drawSymbol(ctx, emoji, 0, `190px ${EMOJI}`, 380, 170);

        // Title — jitna bada fit ho (2-3 line)
        const f = K.fit(ctx, title, { maxW: W - 110, maxH: 430, size: 150, minSize: 78, lineHeight: 1.14 });
        strokedLines(ctx, f.lines, 805, f.lh, '#facc15', Math.max(12, Math.round(f.size / 8)));

        // Sub-line — laal pill
        ctx.save();
        const ss = K.fitOneLine(ctx, sub, W - 220, 54, 34);
        const sw = Math.min(W - 100, ctx.measureText(sub).width + 110), sh = ss + 56;
        ctx.shadowColor = 'rgba(0,0,0,0.4)'; ctx.shadowBlur = 20;
        ctx.fillStyle = '#dc2626';
        ctx.beginPath(); ctx.roundRect(W / 2 - sw / 2, 1085 - sh / 2, sw, sh, sh / 2); ctx.fill();
        ctx.shadowColor = 'transparent';
        ctx.strokeStyle = '#facc15'; ctx.lineWidth = 4; ctx.stroke();
        ctx.fillStyle = '#ffffff';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(sub, W / 2, 1088);
        ctx.restore();

        // 3 rashi — bade sunehre gole + naam
        const n = chips.length;
        if (n) {
            const step = Math.min(330, (W - 80) / n), r = 118, cy = 1320;
            chips.forEach((ch, k) => {
                const x = W / 2 + (k - (n - 1) / 2) * step;
                ctx.save();
                ctx.shadowColor = 'rgba(250, 204, 21, 0.75)'; ctx.shadowBlur = 36;
                const g = ctx.createRadialGradient(x, cy, 16, x, cy, r + 8);
                g.addColorStop(0, '#fde68a'); g.addColorStop(1, '#d97706');
                ctx.fillStyle = g;
                ctx.beginPath(); ctx.arc(x, cy, r, 0, Math.PI * 2); ctx.fill();
                ctx.restore();
                ctx.save();
                ctx.strokeStyle = '#ffffff'; ctx.lineWidth = 6;
                ctx.beginPath(); ctx.arc(x, cy, r, 0, Math.PI * 2); ctx.stroke();
                ctx.fillStyle = '#3b0764';
                ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                const pair = [...ch.glyph.replace(/︎/g, '')].length > 1;
                ctx.font = `${pair ? 86 : 132}px "Segoe UI Symbol", "Noto Sans Symbols 2", ${K.STACK}`;
                ctx.fillText(ch.glyph, x, cy + 6);
                // Naam — outline ke saath
                const ns = K.fitOneLine(ctx, ch.name, step - 24, 56, 30);
                strokedLines(ctx, [ch.name], cy + r + 62, ns, "#ffffff", 10, x);
                ctx.restore();
            });
        }

        // Neeche halki rashi chinh ki kataar (YouTube ke overlay ke peeche bhi chale)
        ctx.save();
        ctx.globalAlpha = 0.55;
        drawSymbolRow(ctx, 1700);
        ctx.restore();
    }

    /** Panel: upar label (sunehra), beech me theme ki line. swatch ho to border us rang ka. */
    function drawShortPanel(ctx, label, line, swatch = null) {
        const px = 60, pw = W - 120, py = 1010, ph = 360;
        ctx.fillStyle = 'rgba(255,255,255,0.12)';
        ctx.strokeStyle = swatch || 'rgba(250, 204, 21, 0.55)';
        ctx.lineWidth = swatch ? 9 : 3;
        ctx.beginPath(); ctx.roundRect(px, py, pw, ph, 36); ctx.fill(); ctx.stroke();
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        let cy = py + ph / 2, maxH = ph - 70;
        if (label) {
            ctx.fillStyle = '#facc15';
            K.fitOneLine(ctx, label, pw - 80, 46, 30);
            ctx.fillText(label, W / 2, py + 54);
            cy = py + ph / 2 + 32; maxH = ph - 136;
        }
        ctx.fillStyle = '#ffffff';
        const f = K.fit(ctx, line, { maxW: pw - 90, maxH, size: 64, minSize: 38, lineHeight: 1.3 });
        K.drawLines(ctx, f.lines, W / 2, cy, f.lh);
    }

    /** Progress: 2 / 3 + bindiyan. */
    function drawProgress(ctx, pos, total) {
        ctx.fillStyle = 'rgba(255,255,255,0.85)';
        ctx.font = K.font(40);
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(num(pos) + ' / ' + num(total), W / 2, 1590);
        const gap = Math.min(60, (W - 200) / total), x0 = W / 2 - gap * (total - 1) / 2;
        for (let k = 0; k < total; k++) {
            ctx.fillStyle = k < pos ? '#facc15' : 'rgba(255,255,255,0.25)';
            ctx.beginPath(); ctx.arc(x0 + k * gap, 1650, k === pos - 1 ? 13 : 9, 0, Math.PI * 2); ctx.fill();
        }
    }

    function drawRankBadge(ctx, rank, x, y) {
        ctx.save();
        ctx.fillStyle = '#16a34a';
        ctx.strokeStyle = '#ffffff'; ctx.lineWidth = 6;
        ctx.beginPath(); ctx.arc(x, y, 70, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
        ctx.fillStyle = '#ffffff';
        ctx.font = K.font(64);
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText('#' + num(rank), x, y + 5);
        ctx.restore();
    }

    /**
     * Ek rashi ka Short frame: chinh, naam, ★, theme ki line (panel), shubh ank/rang, progress.
     * opt = {label, line, stars, day, swatch}
     */
    function drawShortRashi(c, item, pos, total, rank = null, opt = {}) {
        const ctx = c.getContext('2d');
        const L = tr(), S = st();
        const i = item._idx;
        K.reset(ctx);
        drawSky(ctx, i);
        drawDatePill(ctx, 120);
        drawSymbol(ctx, SYMBOLS[i] + '︎');
        if (rank) drawRankBadge(ctx, rank, W / 2 + 165, CY - 120);

        ctx.fillStyle = '#ffffff';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.font = K.font(104);
        ctx.fillText(L.names[i], W / 2, 690);
        if (L.letters) {
            ctx.fillStyle = '#fcd34d';
            K.fitOneLine(ctx, '(' + L.letters[i] + ')', W - 200, 42, 28, '400');
            ctx.fillText('(' + L.letters[i] + ')', W / 2, 770);
        }

        const dayWord = opt.day || S.day;
        ctx.fillStyle = 'rgba(255,255,255,0.85)';
        K.fitOneLine(ctx, dayWord, W - 200, 40, 28, '400');
        ctx.fillText(dayWord, W / 2, 850);
        drawStars(ctx, Math.max(1, Math.min(5, opt.stars ?? ratingOf(item))), 935);

        drawShortPanel(ctx, opt.label || '', opt.line ?? shortLine(item), opt.swatch || null);
        drawLuckyPills(ctx, item, 1470);
        drawProgress(ctx, pos, total);
    }

    /** Jodi frame: do sunehre gole (dono chinh) + ❤️, "Mesh + Simha", ★, mel ki line. */
    function drawShortJodi(c, a, b, pos, total, label) {
        const ctx = c.getContext('2d');
        const L = tr(), T = tt();
        K.reset(ctx);
        drawSky(ctx, a._idx);
        drawDatePill(ctx, 120);
        [[a, W * 0.29], [b, W * 0.71]].forEach(([it, x]) => {
            ctx.save();
            ctx.shadowColor = 'rgba(250, 204, 21, 0.7)'; ctx.shadowBlur = 44;
            const g = ctx.createRadialGradient(x, CY, 18, x, CY, 140);
            g.addColorStop(0, '#fde68a'); g.addColorStop(1, '#d97706');
            ctx.fillStyle = g;
            ctx.beginPath(); ctx.arc(x, CY, 130, 0, Math.PI * 2); ctx.fill();
            ctx.restore();
            ctx.fillStyle = '#3b0764';
            ctx.font = `140px "Segoe UI Symbol", "Noto Sans Symbols 2", ${K.STACK}`;
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillText(SYMBOLS[it._idx] + '︎', x, CY + 7);
        });
        ctx.font = `100px ${EMOJI}`;
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText('❤️', W / 2, CY + 5);
        drawRankBadge(ctx, pos, W / 2, CY - 175);

        const nm = L.names[a._idx] + ' + ' + L.names[b._idx];
        ctx.fillStyle = '#ffffff';
        K.fitOneLine(ctx, nm, W - 120, 96, 56);
        ctx.fillText(nm, W / 2, 700);
        ctx.fillStyle = 'rgba(255,255,255,0.85)';
        K.fitOneLine(ctx, T.dayJodi, W - 200, 40, 28, '400');
        ctx.fillText(T.dayJodi, W / 2, 850);
        drawStars(ctx, Math.round((ratingOf(a) + ratingOf(b)) / 2), 935);

        drawShortPanel(ctx, label, jodiLine(a, b));
        drawProgress(ctx, pos, total);
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
        drawSymbolRow(ctx, 1460);
    }

    /** Is din ke 3 Shorts: [{kind, label, caption, frames:[{text, draw}]}] — draw(canvas) frame banata hai. */
    function shortPlans() {
        const L = tr(), S = st(), T = tt();
        const date = dateParts()[1];
        return pickThemes().map((th, ti) => {
            const [title, sub, label] = themeTxt(th);
            const emoji = themeEmoji(th);
            let frames, lines, chips;
            if (th.jodi) {
                const pairs = bestPairs();
                chips = pairs.map(([a, b]) => ({ glyph: SYMBOLS[a._idx] + '︎' + SYMBOLS[b._idx] + '︎', name: L.names[a._idx] + '+' + L.names[b._idx] }));
                frames = pairs.map(([a, b], k) => ({
                    text: '#' + (k + 1) + '. ' + L.names[a._idx] + ' + ' + L.names[b._idx] + '. ' + jodiLine(a, b),
                    draw: cv => drawShortJodi(cv, a, b, k + 1, pairs.length, label),
                }));
                lines = pairs.map(([a, b], k) => `#${k + 1} ${SYMBOLS[a._idx]} ${L.names[a._idx]} + ${SYMBOLS[b._idx]} ${L.names[b._idx]} — ${jodiLine(a, b)}`);
            } else {
                const picks = th.pick(rng(mix(daySeed() + (ti + 1) * 7777)));
                chips = picks.map(it => ({ glyph: SYMBOLS[it._idx] + '︎', name: L.names[it._idx] }));
                const opt = it => ({
                    label, line: th.line(it), swatch: th.swatch ? th.swatch(it) : null,
                    stars: th.stars ? th.stars(it) : ratingOf(it), day: th.day ? th.day(T) : S.day,
                });
                frames = picks.map((it, k) => ({
                    text: (th.rank ? '#' + (k + 1) + '. ' : '') + L.names[it._idx] + '. ' + th.line(it),
                    draw: cv => drawShortRashi(cv, it, k + 1, picks.length, th.rank ? k + 1 : null, opt(it)),
                }));
                lines = picks.map((it, k) => (th.rank ? '#' + (k + 1) + ' ' : '') + SYMBOLS[it._idx] + ' ' + L.names[it._idx] + ' — ' + th.line(it));
            }
            return {
                kind: th.key,
                label: emoji + ' ' + title,
                // Pehli line = YouTube title
                caption: `${title} ${emoji} | ${date}\n\n${sub}\n\n${lines.join('\n')}\n\n${S.endAsk}\n\n${S.tags}`,
                frames: [{ text: title + '. ' + date, draw: cv => drawShortIntro(cv, emoji, title, sub, chips) }, ...frames, { text: S.sayEnd, draw: cv => drawShortEnd(cv) }],
            };
        });
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

    /** Generate ke baad is din ke 3 Shorts ki chhoti jhalak. "🔄 Bija 3" = doosre 3 themes. */
    function renderShortPreviews() {
        const wrap = el('shortPreview');
        if (!wrap) return;
        if (!wantShorts()) { wrap.classList.add('hidden'); return; }
        wrap.classList.remove('hidden');
        const rows = wrap.querySelector('.rows');
        rows.innerHTML = '';
        const head = document.createElement('div');
        head.className = 'flex items-center justify-between gap-2 flex-wrap';
        head.innerHTML = `<p class="text-xs text-slate-500">Aaje na 3 Shorts — 13 themes ma thi (tarikh pramane). Je dekhay che e j save thashe.</p>
            <button type="button" class="text-xs font-semibold text-red-700 border border-red-200 rounded-lg px-3 py-1 hover:bg-red-50">🔄 Bija 3 batavo</button>`;
        head.querySelector('button').addEventListener('click', () => { shortShuffle++; renderShortPreviews(); });
        rows.appendChild(head);
        shortPlans().forEach(p => {
            const row = document.createElement('div');
            row.innerHTML = `<p class="text-xs font-bold text-slate-600 mb-1"></p>
                <div class="flex gap-2 overflow-x-auto pb-2 frames"></div>`;
            row.querySelector('p').textContent = p.label + ' — ' + p.frames.length + ' frames';
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

    // =====================================================================
    //  🎬 SAPTAHIK YOUTUBE VIDEO (16:9) — intro → 12 rashi → aakhri frame, + thumbnail
    //  Frames 1920×1080 (server 720p video banata hai). Har frame ka text = voice-over line,
    //  label = YouTube chapter ka naam (aakhri frame ka chapter nahi).
    // =====================================================================
    const VW = 1920, VH = 1080;
    const WEEK_I18N = {
        gujarati: {
            head: 'સાપ્તાહિક રાશિફળ', sub: '૧૨ રાશિ • પ્રેમ • કામકાજ • ધન • સ્વાસ્થ્ય',
            week: 'આ અઠવાડિયું', overview: '📅 આ અઠવાડિયું કેવું રહેશે',
            love: '💕 પ્રેમ', career: '💼 કામકાજ', money: '💰 ધન', health: '🌿 સ્વાસ્થ્ય',
            luckyDays: '✅ શુભ દિવસ', careful: '⚠️ સાવધાન', upay: '🙏 ઉપાય',
            days: ['સોમ', 'મંગળ', 'બુધ', 'ગુરુ', 'શુક્ર', 'શનિ', 'રવિ'],
            daysLong: ['સોમવાર', 'મંગળવાર', 'બુધવાર', 'ગુરુવાર', 'શુક્રવાર', 'શનિવાર', 'રવિવાર'],
            hint: 'તમારી રાશિ પર સીધા જવા નીચે ચેપ્ટર જુઓ 👇',
            endLine: 'દર સોમવારે નવું સાપ્તાહિક રાશિફળ', endShorts: 'રોજનું રાશિફળ — અમારા Shorts માં 📱',
            start: 'શરૂઆત', chapterHead: '⏱ તમારી રાશિ પર જાઓ:',
            introSay: 'સાપ્તાહિક રાશિફળ. {r}. ચાલો જાણીએ, બારે રાશિ માટે આ અઠવાડિયું કેવું રહેશે.',
            rashiSay: '{n} રાશિ. {o} પ્રેમમાં: {l} કામકાજમાં: {c} ધનની બાબતમાં: {m} સ્વાસ્થ્ય: {h} આ અઠવાડિયે શુભ દિવસ: {d}. ઉપાય: {u}',
            endSay: 'વીડિયો ગમ્યો હોય તો લાઇક, શેર અને સબ્સ્ક્રાઇબ કરો.',
            title: 'સાપ્તાહિક રાશિફળ {r} | ૧૨ રાશિ | Weekly Rashifal',
            descIntro: '{r} નું સાપ્તાહિક રાશિફળ — દરેક રાશિ માટે પ્રેમ, કામકાજ, ધન, સ્વાસ્થ્ય, શુભ દિવસ અને ઉપાય.',
            tags: '#rashifal #saptahikrashifal #weeklyhoroscope #gujaratirashifal #horoscope #astrology',
        },
        hindi: {
            head: 'साप्ताहिक राशिफल', sub: '१२ राशि • प्रेम • करियर • धन • स्वास्थ्य',
            week: 'यह सप्ताह', overview: '📅 यह सप्ताह कैसा रहेगा',
            love: '💕 प्रेम', career: '💼 करियर', money: '💰 धन', health: '🌿 स्वास्थ्य',
            luckyDays: '✅ शुभ दिन', careful: '⚠️ सावधान', upay: '🙏 उपाय',
            days: ['सोम', 'मंगल', 'बुध', 'गुरु', 'शुक्र', 'शनि', 'रवि'],
            daysLong: ['सोमवार', 'मंगलवार', 'बुधवार', 'गुरुवार', 'शुक्रवार', 'शनिवार', 'रविवार'],
            hint: 'अपनी राशि पर सीधे जाने के लिए नीचे चैप्टर देखें 👇',
            endLine: 'हर सोमवार नया साप्ताहिक राशिफल', endShorts: 'रोज़ का राशिफल — हमारे Shorts में 📱',
            start: 'शुरुआत', chapterHead: '⏱ अपनी राशि पर जाएँ:',
            introSay: 'साप्ताहिक राशिफल. {r}. आइए जानते हैं, बारह राशियों के लिए यह सप्ताह कैसा रहेगा.',
            rashiSay: '{n} राशि. {o} प्रेम में: {l} करियर में: {c} धन के मामले में: {m} स्वास्थ्य: {h} इस सप्ताह शुभ दिन: {d}. उपाय: {u}',
            endSay: 'वीडियो पसंद आया हो तो लाइक, शेयर और सब्सक्राइब करें.',
            title: 'साप्ताहिक राशिफल {r} | 12 राशि | Weekly Rashifal',
            descIntro: '{r} का साप्ताहिक राशिफल — हर राशि के लिए प्रेम, करियर, धन, स्वास्थ्य, शुभ दिन और उपाय.',
            tags: '#rashifal #saptahikrashifal #weeklyhoroscope #hindirashifal #horoscope #astrology',
        },
        hinglish: {
            head: 'Saptahik Rashifal', sub: '12 Rashi • Love • Career • Dhan • Health',
            week: 'Is hafte', overview: '📅 Ye hafta kaisa rahega',
            love: '💕 Love', career: '💼 Career', money: '💰 Dhan', health: '🌿 Health',
            luckyDays: '✅ Shubh din', careful: '⚠️ Savdhan', upay: '🙏 Upay',
            days: ['Som', 'Mangal', 'Budh', 'Guru', 'Shukra', 'Shani', 'Ravi'],
            daysLong: ['Somvar', 'Mangalvar', 'Budhvar', 'Guruvar', 'Shukravar', 'Shanivar', 'Ravivar'],
            hint: 'Apni rashi par seedha jane ke liye neeche chapters dekho 👇',
            endLine: 'Har Somvar naya Saptahik Rashifal', endShorts: 'Roz ka rashifal — hamare Shorts me 📱',
            start: 'Shuruaat', chapterHead: '⏱ Apni rashi par jao:',
            introSay: 'Saptahik rashifal. {r}. Chaliye jaante hain, barah rashiyon ke liye ye hafta kaisa rahega.',
            rashiSay: '{n} rashi. {o} Love me: {l} Career me: {c} Dhan ke maamle me: {m} Health: {h} Is hafte shubh din: {d}. Upay: {u}',
            endSay: 'Video pasand aaya ho to like, share aur subscribe karo.',
            title: 'Saptahik Rashifal {r} | 12 Rashi | Weekly Horoscope',
            descIntro: '{r} ka saptahik rashifal — har rashi ke liye love, career, dhan, health, shubh din aur upay.',
            tags: '#rashifal #saptahikrashifal #weeklyhoroscope #horoscope #astrology',
        },
    };

    // ⭐ "Is hafte ka Special" — har hafte badalta hai (intro ke turant baad + title + thumbnail)
    // head = frame ka title, yt = YouTube title ({r} tarikh), badge = thumbnail ki pill, say = voice ({l} rashi list, {f} tyohar)
    const WEEK_SP = {
        gujarati: {
            lucky4:   { head: 'આ અઠવાડિયાની ટોપ ૪ લકી રાશિ', yt: 'આ અઠવાડિયે ૪ રાશિની ચમકશે કિસ્મત 🍀 | સાપ્તાહિક રાશિફળ {r}', badge: '🍀 ૪ લકી રાશિ', say: 'આ અઠવાડિયાની ટોપ ચાર લકી રાશિ છે: {l}.' },
            savdhan3: { head: 'આ અઠવાડિયે સાવધાન રહે આ ૩ રાશિ', yt: '⚠️ આ અઠવાડિયે ૩ રાશિ સાવધાન રહે | સાપ્તાહિક રાશિફળ {r}', badge: '⚠️ ૩ રાશિ સાવધાન', say: 'આ અઠવાડિયે આ ત્રણ રાશિએ થોડું સાવધાન રહેવું: {l}. ગભરાશો નહીં, દરેક માટે સરળ ઉપાય પણ જણાવીશું.' },
            dhan3:    { head: 'આ અઠવાડિયે ધન લાભ — ટોપ ૩ રાશિ', yt: '💰 આ અઠવાડિયે ૩ રાશિને ધન લાભ | સાપ્તાહિક રાશિફળ {r}', badge: '💰 ધન લાભ', say: 'આ અઠવાડિયે ધન લાભના સૌથી સારા યોગ આ ત્રણ રાશિ માટે છે: {l}.' },
            jodi3:    { head: 'આ અઠવાડિયાની બેસ્ટ જોડી', yt: '🤝 આ અઠવાડિયાની બેસ્ટ જોડી | સાપ્તાહિક રાશિફળ {r}', badge: '🤝 બેસ્ટ જોડી', say: 'આ અઠવાડિયાની બેસ્ટ જોડી છે: {l}.' },
            fest:     { head: '{f} — ૪ રાશિ પર વિશેષ કૃપા', yt: '🪔 {f} વાળું અઠવાડિયું — ૪ રાશિ પર કૃપા | સાપ્તાહિક રાશિફળ {r}', badge: '🪔 {f} સ્પેશિયલ', say: '{f} વાળા આ અઠવાડિયે આ ચાર રાશિ પર વિશેષ કૃપા રહેશે: {l}.' },
            outro: 'હવે જાણીએ દરેક રાશિનું વિગતવાર રાશિફળ.', chapter: '⭐ આ અઠવાડિયાનું સ્પેશિયલ',
            hint: 'દરેક રાશિનું પૂરું રાશિફળ આગળ જુઓ ⏩', and: 'અને',
        },
        hindi: {
            lucky4:   { head: 'इस सप्ताह की टॉप ४ लकी राशि', yt: 'इस सप्ताह ४ राशियों की चमकेगी किस्मत 🍀 | साप्ताहिक राशिफल {r}', badge: '🍀 ४ लकी राशि', say: 'इस सप्ताह की टॉप चार लकी राशियाँ हैं: {l}.' },
            savdhan3: { head: 'इस सप्ताह सावधान रहें ये ३ राशि', yt: '⚠️ इस सप्ताह ३ राशि सावधान रहें | साप्ताहिक राशिफल {r}', badge: '⚠️ ३ राशि सावधान', say: 'इस सप्ताह इन तीन राशियों को थोड़ा सावधान रहना है: {l}. घबराइए नहीं, हर राशि के लिए आसान उपाय भी बताएँगे.' },
            dhan3:    { head: 'इस सप्ताह धन लाभ — टॉप ३ राशि', yt: '💰 इस सप्ताह ३ राशियों को धन लाभ | साप्ताहिक राशिफल {r}', badge: '💰 धन लाभ', say: 'इस सप्ताह धन लाभ के सबसे अच्छे योग इन तीन राशियों के लिए हैं: {l}.' },
            jodi3:    { head: 'इस सप्ताह की बेस्ट जोड़ी', yt: '🤝 इस सप्ताह की बेस्ट जोड़ी | साप्ताहिक राशिफल {r}', badge: '🤝 बेस्ट जोड़ी', say: 'इस सप्ताह की बेस्ट जोड़ी है: {l}.' },
            fest:     { head: '{f} — ४ राशियों पर विशेष कृपा', yt: '🪔 {f} वाला सप्ताह — ४ राशियों पर कृपा | साप्ताहिक राशिफल {r}', badge: '🪔 {f} स्पेशल', say: '{f} वाले इस सप्ताह इन चार राशियों पर विशेष कृपा रहेगी: {l}.' },
            outro: 'अब जानते हैं हर राशि का विस्तार से राशिफल.', chapter: '⭐ इस सप्ताह का स्पेशल',
            hint: 'हर राशि का पूरा राशिफल आगे देखें ⏩', and: 'और',
        },
        hinglish: {
            lucky4:   { head: 'Is Hafte Ki Top 4 Lucky Rashi', yt: 'Is Hafte 4 Rashi Ki Chamkegi Kismat 🍀 | Saptahik Rashifal {r}', badge: '🍀 4 Lucky Rashi', say: 'Is hafte ki top chaar lucky rashi hain: {l}.' },
            savdhan3: { head: 'Is Hafte Savdhan Rahe Ye 3 Rashi', yt: '⚠️ Is Hafte 3 Rashi Savdhan Rahe | Saptahik Rashifal {r}', badge: '⚠️ 3 Rashi Savdhan', say: 'Is hafte in teen rashiyon ko thoda savdhan rehna hai: {l}. Ghabraiye mat, har rashi ke liye aasan upay bhi batayenge.' },
            dhan3:    { head: 'Is Hafte Dhan Labh — Top 3 Rashi', yt: '💰 Is Hafte 3 Rashi Ko Dhan Labh | Saptahik Rashifal {r}', badge: '💰 Dhan Labh', say: 'Is hafte dhan labh ke sabse achhe yog in teen rashiyon ke liye hain: {l}.' },
            jodi3:    { head: 'Is Hafte Ki Best Jodi', yt: '🤝 Is Hafte Ki Best Jodi | Saptahik Rashifal {r}', badge: '🤝 Best Jodi', say: 'Is hafte ki best jodi hai: {l}.' },
            fest:     { head: '{f} — 4 Rashi Par Vishesh Kripa', yt: '🪔 {f} Wala Hafta — 4 Rashi Par Kripa | Saptahik Rashifal {r}', badge: '🪔 {f} Special', say: '{f} wale is hafte in chaar rashiyon par vishesh kripa rahegi: {l}.' },
            outro: 'Ab jaante hain har rashi ka vistaar se rashifal.', chapter: '⭐ Is hafte ka special',
            hint: 'Har rashi ka poora rashifal aage dekho ⏩', and: 'aur',
        },
    };
    const SP_ROTATION = ['lucky4', 'savdhan3', 'dhan3', 'jodi3'];

    let weekItems = [], weekLang = 'gujarati', weekStart = '', weekEnd = '', weekFest = '';
    const wt = () => WEEK_I18N[weekLang] || WEEK_I18N.gujarati;
    const wl = () => I18N[weekLang] || I18N.gujarati;
    const wnum = n => String(n ?? '').replace(/\d/g, d => wl().digits[d]);
    const ymd = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    function mondayOf(ds) {
        const d = new Date(ds + 'T00:00:00');
        d.setDate(d.getDate() - ((d.getDay() + 6) % 7));
        return ymd(d);
    }
    const DAY_KEYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    const dayIdx = s => DAY_KEYS.indexOf(String(s || '').toLowerCase().trim());
    const wDays = it => (Array.isArray(it.lucky_days) ? it.lucky_days : String(it.lucky_days || '').split(/[,/]/))
        .map(dayIdx).filter(i => i >= 0).slice(0, 2);
    const wDayNames = (it, long = false) => wDays(it).map(i => (long ? wt().daysLong : wt().days)[i]).join(', ');
    const wCareful = it => { const i = dayIdx(it.careful_day); return i >= 0 ? wt().days[i] : '—'; };
    const wScore = (it, k) => Math.max(1, Math.min(10, parseInt(it[k], 10) || 6));
    const wRating = it => Math.max(1, Math.min(5, parseInt(it.rating, 10) || 3));
    const wShort = it => K.str(it.short).trim() || K.str(it.overview).split(/[.।]/)[0];
    const endDot = s => { s = K.str(s).trim(); return s === '' || /[.।!?]$/.test(s) ? s : s + '.'; };

    function weekRangeLabel() {
        try {
            const L = wl();
            const a = new Date(weekStart + 'T00:00:00'), b = new Date(weekEnd + 'T00:00:00');
            const f = d => d.toLocaleDateString(L.locale, { day: 'numeric', month: 'long' });
            return wnum(f(a) + ' – ' + f(b) + ' ' + b.getFullYear());
        } catch (e) { return weekStart + ' – ' + weekEnd; }
    }

    /**
     * Is hafte ka special: tyohar ho to 'fest', warna 4 hafton ka chakra (lucky4 → savdhan3 → dhan3 → jodi3).
     * Chakra Somvar ki tarikh se — har hafte apne aap badalta hai, ek hafte me dobara banao to wahi rahe.
     * Return {key, head, yt, badge, say, emoji, chips:[{glyph, name, line, pair}], names}
     */
    function weekSpecial() {
        const L = wl(), T = WEEK_SP[weekLang] || WEEK_SP.gujarati;
        const fname = weekFest ? ((THEME_TXT[weekLang] || THEME_TXT.gujarati).festNames[weekFest] || '') : '';
        const weekNo = Math.floor(Date.parse(weekStart + 'T00:00:00Z') / (7 * 864e5));
        const key = fname ? 'fest' : SP_ROTATION[((weekNo % 4) + 4) % 4];
        const txt = T[key];
        const total = it => wRating(it) * 100 + ['love', 'career', 'money', 'health'].reduce((s, k) => s + wScore(it, k + '_score'), 0);
        const byDesc = f => (a, b) => (f(b) - f(a)) || a._idx - b._idx;
        let chips, list;

        if (key === 'jodi3') {
            // Mel khate tatva: Agni+Vayu, Prithvi+Jal (index ki same parity)
            const pairs = [];
            for (let a = 0; a < weekItems.length; a++) {
                for (let b = a + 1; b < weekItems.length; b++) {
                    const x = weekItems[a], y = weekItems[b];
                    if (x._idx % 2 !== y._idx % 2) continue;
                    pairs.push([x, y, (wRating(x) + wRating(y)) * 20 + wScore(x, 'love_score') + wScore(y, 'love_score')]);
                }
            }
            pairs.sort((p, q) => (q[2] - p[2]) || (p[0]._idx - q[0]._idx) || (p[1]._idx - q[1]._idx));
            const used = new Set(), best = [];
            for (const [x, y] of pairs) {
                if (used.has(x._idx) || used.has(y._idx)) continue;
                used.add(x._idx); used.add(y._idx);
                best.push([x, y]);
                if (best.length === 3) break;
            }
            chips = best.map(([x, y]) => ({
                glyph: SYMBOLS[x._idx] + '︎' + SYMBOLS[y._idx] + '︎', pair: true,
                name: L.names[x._idx] + ' + ' + L.names[y._idx],
                line: '💕 ' + wnum(Math.round((wScore(x, 'love_score') + wScore(y, 'love_score')) * 5)) + '%',
            }));
            list = best.map(([x, y]) => L.names[x._idx] + ' ' + T.and + ' ' + L.names[y._idx]).join(', ');
        } else {
            const picks = key === 'savdhan3' ? [...weekItems].sort((a, b) => (total(a) - total(b)) || a._idx - b._idx).slice(0, 3)
                : key === 'dhan3' ? [...weekItems].sort(byDesc(it => wScore(it, 'money_score') * 10 + wRating(it))).slice(0, 3)
                : [...weekItems].sort(byDesc(total)).slice(0, 4);
            chips = picks.map(it => ({
                glyph: SYMBOLS[it._idx] + '︎', pair: false, name: L.names[it._idx],
                line: key === 'savdhan3' ? wt().careful + ': ' + wCareful(it)
                    : key === 'dhan3' ? wt().money + ' ' + wnum(wScore(it, 'money_score') * 10) + '%'
                    : wt().luckyDays + ': ' + (wDayNames(it) || '—'),
            }));
            list = picks.map(it => L.names[it._idx]).join(', ');
        }

        return {
            key,
            head: fill(txt.head, { f: fname }),
            yt: txt.yt.replace('{f}', fname),
            badge: fill(txt.badge, { f: fname }),
            say: fill(txt.say, { l: list, f: fname }) + ' ' + T.outro,
            chapter: T.chapter, hint: T.hint,
            emoji: key === 'fest' ? '🪔' : ({ lucky4: '🍀', savdhan3: '⚠️', dhan3: '💰', jodi3: '🤝' })[key],
            names: chips.map(c => c.name).join(', '),
            chips, rank: key !== 'savdhan3',
        };
    }

    // ---------- Drawing helpers (16:9) ----------
    const symFont = px => `${px}px "Segoe UI Symbol", "Noto Sans Symbols 2", ${K.STACK}`;
    function wSky(ctx, idx, colors = null) {
        const sky = colors || (idx < 12 ? ELEMENT_SKY[idx % 4] : ['#0b1033', '#2a1260', '#4a1450']);
        const g = ctx.createLinearGradient(0, 0, VW, VH);
        g.addColorStop(0, sky[0]); g.addColorStop(0.55, sky[1]); g.addColorStop(1, sky[2]);
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, VW, VH);
        const r = rng(11 + idx * 131);
        for (let i = 0; i < 320; i++) {
            ctx.fillStyle = `rgba(255,255,255,${0.2 + r() * 0.6})`;
            ctx.beginPath(); ctx.arc(r() * VW, r() * VH, r() * 2.4 + 0.4, 0, Math.PI * 2); ctx.fill();
        }
    }
    function wGold(ctx, x, y, r, glyph, font) {
        ctx.save();
        ctx.shadowColor = 'rgba(250, 204, 21, 0.7)'; ctx.shadowBlur = r / 3;
        const g = ctx.createRadialGradient(x, y, r / 7, x, y, r + 8);
        g.addColorStop(0, '#fde68a'); g.addColorStop(1, '#d97706');
        ctx.fillStyle = g;
        ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.fill();
        ctx.restore();
        ctx.save();
        ctx.fillStyle = '#3b0764'; ctx.font = font;
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(glyph, x, y + r / 19);
        ctx.restore();
    }
    function wPanel(ctx, x, y, w, h, fill = 'rgba(255,255,255,0.10)', stroke = 'rgba(250, 204, 21, 0.45)') {
        ctx.save();
        ctx.fillStyle = fill;
        ctx.beginPath(); ctx.roundRect(x, y, w, h, 28); ctx.fill();
        if (stroke) { ctx.strokeStyle = stroke; ctx.lineWidth = 3; ctx.stroke(); }
        ctx.restore();
    }
    function wText(ctx, text, x, cy, maxW, maxH, size, min, color = '#ffffff', align = 'left', lh = 1.3) {
        ctx.save();
        ctx.fillStyle = color; ctx.textAlign = align;
        const f = K.fit(ctx, K.str(text), { maxW, maxH, size, minSize: min, lineHeight: lh });
        K.drawLines(ctx, f.lines, x, cy, f.lh);
        ctx.restore();
    }
    /** Fixed size dabba, beech me ek line (rang ka gola optional). */
    function wBox(ctx, x, y, w, h, bg, text, color, size, swatch = null) {
        ctx.save();
        ctx.fillStyle = bg;
        ctx.beginPath(); ctx.roundRect(x, y, w, h, h / 2); ctx.fill();
        ctx.fillStyle = color; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        const dot = swatch ? 24 : 0, gap = swatch ? 14 : 0;
        const s = K.fitOneLine(ctx, text, w - 50 - dot * 2 - gap, size, 20);
        const tw = ctx.measureText(text).width;
        const left = x + w / 2 - (dot * 2 + gap + tw) / 2;
        if (swatch) {
            ctx.fillStyle = swatch; ctx.strokeStyle = '#ffffff'; ctx.lineWidth = 4;
            ctx.beginPath(); ctx.arc(left + dot, y + h / 2, dot, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
            ctx.fillStyle = color; ctx.font = K.font(s);
        }
        ctx.fillText(text, left + dot * 2 + gap + tw / 2, y + h / 2 + 2);
        ctx.restore();
    }
    function wPill(ctx, text, cx, cy, size, bg, color, maxW) {
        ctx.save();
        const s = K.fitOneLine(ctx, text, maxW - 70, size, 22);
        const w = ctx.measureText(text).width + 80, h = s + 44;
        ctx.shadowColor = 'rgba(0,0,0,0.35)'; ctx.shadowBlur = 18;
        ctx.fillStyle = bg;
        ctx.beginPath(); ctx.roundRect(cx - w / 2, cy - h / 2, w, h, h / 2); ctx.fill();
        ctx.shadowColor = 'transparent';
        ctx.strokeStyle = '#facc15'; ctx.lineWidth = 4; ctx.stroke();
        ctx.fillStyle = color; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(text, cx, cy + 2);
        ctx.restore();
    }
    function wStars(ctx, n, cx, cy, size = 62, step = 72) {
        ctx.save();
        ctx.font = `${size}px "Segoe UI Symbol", ${K.STACK}`;
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        for (let k = 0; k < 5; k++) {
            ctx.fillStyle = k < n ? '#facc15' : 'rgba(255,255,255,0.22)';
            ctx.fillText('★', cx + (k - 2) * step, cy);
        }
        ctx.restore();
    }
    /** 12 rashi ka chakra — beech me bada emoji gola. */
    function wWheel(ctx, cx, cy, R, centerGlyph) {
        ctx.save();
        ctx.strokeStyle = 'rgba(250, 204, 21, 0.35)'; ctx.lineWidth = 3;
        [R + 72, R - 72].forEach(rad => { ctx.beginPath(); ctx.arc(cx, cy, rad, 0, Math.PI * 2); ctx.stroke(); });
        ctx.restore();
        SYMBOLS.forEach((s, k) => {
            const a = -Math.PI / 2 + k * Math.PI / 6;
            wGold(ctx, cx + R * Math.cos(a), cy + R * Math.sin(a), 54, s + '︎', symFont(60));
        });
        wGold(ctx, cx, cy, R * 0.5, centerGlyph, `${Math.round(R * 0.55)}px ${EMOJI}`);
    }

    /** Upar ki patti: "Saptahik Rashifal • tarikh" + daayi taraf chhota text + patli progress line. */
    function wTopBar(ctx, right, progress) {
        const T = wt();
        ctx.fillStyle = 'rgba(0,0,0,0.28)';
        ctx.fillRect(0, 0, VW, 84);
        ctx.save();
        ctx.textBaseline = 'middle'; ctx.textAlign = 'left'; ctx.fillStyle = '#facc15';
        const top = T.head + '  •  ' + weekRangeLabel();
        K.fitOneLine(ctx, top, 1400, 40, 24);
        ctx.fillText(top, 48, 44);
        ctx.textAlign = 'right'; ctx.fillStyle = '#ffffff';
        K.fitOneLine(ctx, right, 420, 40, 24);
        ctx.fillText(right, VW - 48, 44);
        ctx.restore();
        ctx.fillStyle = 'rgba(255,255,255,0.15)'; ctx.fillRect(0, 84, VW, 6);
        ctx.fillStyle = '#facc15'; ctx.fillRect(0, 84, VW * progress, 6);
    }

    /** Sunehre gole + ranking badge + naam (outline) — special frame aur thumbnail dono me. */
    function wChip(ctx, ch, x, cy, r, nameSize, rank = null) {
        wGold(ctx, x, cy, r, ch.glyph, symFont(Math.round(r * (ch.pair ? 0.62 : 1.0))));
        ctx.save();
        ctx.strokeStyle = '#ffffff'; ctx.lineWidth = 6;
        ctx.beginPath(); ctx.arc(x, cy, r, 0, Math.PI * 2); ctx.stroke();
        ctx.restore();
        if (rank) {
            const bx = x + r * 0.74, by = cy - r * 0.74, br = Math.max(30, r * 0.27);
            ctx.save();
            ctx.fillStyle = '#16a34a'; ctx.strokeStyle = '#ffffff'; ctx.lineWidth = 5;
            ctx.beginPath(); ctx.arc(bx, by, br, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
            ctx.fillStyle = '#ffffff'; ctx.font = K.font(Math.round(br * 0.95));
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillText('#' + wnum(rank), bx, by + 3);
            ctx.restore();
        }
        ctx.save();
        const ns = K.fitOneLine(ctx, ch.name, r * 2.6, nameSize, 26);
        strokedLines(ctx, [ch.name], cy + r + ns * 0.9, ns, '#ffffff', 10, x);
        ctx.restore();
    }

    /** ⭐ Is hafte ka special: bada title, 3-4 rashi ke gole + naam + chhoti line. */
    function drawWeekSpecial(c, sp) {
        const ctx = c.getContext('2d');
        K.reset(ctx);
        wSky(ctx, 98, ['#1e0b3a', '#4c1d95', '#7c2d12']);
        wTopBar(ctx, '⭐ ' + sp.badge, 0);
        const f = K.fit(ctx, sp.head, { maxW: 1700, maxH: 230, size: 112, minSize: 60, lineHeight: 1.12 });
        strokedLines(ctx, f.lines, 240, f.lh, '#facc15', Math.max(10, Math.round(f.size / 8)), VW / 2);
        const n = sp.chips.length, step = n === 4 ? 430 : 540, r = n === 4 ? 135 : 150, cy = 545;
        sp.chips.forEach((ch, k) => {
            const x = VW / 2 + (k - (n - 1) / 2) * step;
            wChip(ctx, ch, x, cy, r, 58, sp.rank ? k + 1 : null);
            wBox(ctx, x - (step - 60) / 2, cy + r + 100, step - 60, 66, 'rgba(0,0,0,0.38)', ch.line, '#fde68a', 32);
        });
        wText(ctx, sp.hint, VW / 2, 1000, 1600, 60, 40, 26, '#fcd34d', 'center');
    }

    function drawWeekIntro(c) {
        const ctx = c.getContext('2d');
        const T = wt();
        K.reset(ctx);
        wSky(ctx, 99);
        wWheel(ctx, 480, 560, 300, '🔮');
        const tx = 1330;
        const f = K.fit(ctx, T.head, { maxW: 980, maxH: 330, size: 150, minSize: 80, lineHeight: 1.12 });
        strokedLines(ctx, f.lines, 330, f.lh, '#facc15', Math.max(12, Math.round(f.size / 8)), tx);
        wPill(ctx, weekRangeLabel(), tx, 590, 52, '#dc2626', '#ffffff', 980);
        wText(ctx, T.sub, tx, 730, 980, 70, 46, 28, '#ffffff', 'center');
        wText(ctx, T.hint, tx, 880, 980, 60, 36, 24, '#fcd34d', 'center');
    }

    function drawWeekRashi(c, it, pos) {
        const ctx = c.getContext('2d');
        const L = wl(), T = wt();
        const i = it._idx;
        K.reset(ctx);
        wSky(ctx, i);

        wTopBar(ctx, wnum(pos) + ' / ' + wnum(12), pos / 12);

        // Baayi taraf: chinh, naam, akshar, ★, shubh ank/rang
        const lx = 360;
        wGold(ctx, lx, 320, 175, SYMBOLS[i] + '︎', symFont(190));
        ctx.save();
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillStyle = '#ffffff';
        K.fitOneLine(ctx, L.names[i], 620, 100, 60);
        ctx.fillText(L.names[i], lx, 580);
        if (L.letters) {
            ctx.fillStyle = '#fcd34d';
            K.fitOneLine(ctx, '(' + L.letters[i] + ')', 620, 38, 22, '400');
            ctx.fillText('(' + L.letters[i] + ')', lx, 648);
        }
        ctx.fillStyle = 'rgba(255,255,255,0.85)';
        K.fitOneLine(ctx, T.week, 600, 36, 24, '400');
        ctx.fillText(T.week, lx, 722);
        ctx.restore();
        wStars(ctx, wRating(it), lx, 790);
        wBox(ctx, 80, 862, 560, 78, 'rgba(250, 204, 21, 0.95)', L.num + ': ' + wnum(it.lucky_number), '#3b0764', 38);
        wBox(ctx, 80, 960, 560, 78, 'rgba(250, 204, 21, 0.95)', L.color + ': ' + K.str(it.lucky_color), '#3b0764', 38, colorHex(it.lucky_color));

        // Daayi taraf: hafte ka saar
        const x0 = 720, rw = 1140;
        wPanel(ctx, x0, 112, rw, 330);
        ctx.save();
        ctx.textAlign = 'left'; ctx.textBaseline = 'middle'; ctx.fillStyle = '#facc15';
        K.fitOneLine(ctx, T.overview, rw - 64, 38, 26);
        ctx.fillText(T.overview, x0 + 32, 160);
        ctx.restore();
        wText(ctx, K.str(it.overview) || wShort(it), x0 + 32, 312, rw - 64, 225, 44, 24);

        // Prem / Kaam / Dhan / Swasthya — % meter + line
        const boxes = [['love', T.love, '#ec4899'], ['career', T.career, '#38bdf8'], ['money', T.money, '#facc15'], ['health', T.health, '#22c55e']];
        boxes.forEach(([k, label, col], j) => {
            const bx = x0 + (j % 2) * 575, by = 468 + Math.floor(j / 2) * 188, bw = 565, bh = 176;
            wPanel(ctx, bx, by, bw, bh, 'rgba(255,255,255,0.10)', null);
            const sc = wScore(it, k + '_score');
            ctx.save();
            ctx.textBaseline = 'middle'; ctx.textAlign = 'left'; ctx.fillStyle = '#fde68a'; ctx.font = K.font(32);
            ctx.fillText(label, bx + 24, by + 36);
            ctx.textAlign = 'right'; ctx.fillStyle = '#ffffff';
            ctx.fillText(wnum(sc * 10) + '%', bx + bw - 24, by + 36);
            ctx.fillStyle = 'rgba(255,255,255,0.2)';
            ctx.beginPath(); ctx.roundRect(bx + 24, by + 62, bw - 48, 14, 7); ctx.fill();
            ctx.fillStyle = col;
            ctx.beginPath(); ctx.roundRect(bx + 24, by + 62, (bw - 48) * sc / 10, 14, 7); ctx.fill();
            ctx.restore();
            wText(ctx, K.str(it[k]), bx + 24, by + 126, bw - 48, 84, 30, 20);
        });

        // Shubh din / Savdhan din / Upay
        wBox(ctx, x0, 852, 565, 70, 'rgba(22, 163, 74, 0.9)', T.luckyDays + ': ' + (wDayNames(it) || '—'), '#ffffff', 32);
        wBox(ctx, x0 + 575, 852, 565, 70, 'rgba(217, 119, 6, 0.9)', T.careful + ': ' + wCareful(it), '#ffffff', 32);
        wPanel(ctx, x0, 938, rw, 104, 'rgba(250, 204, 21, 0.95)', null);
        wText(ctx, T.upay + ': ' + K.str(it.upay), x0 + rw / 2, 990, rw - 60, 90, 34, 22, '#3b0764', 'center', 1.25);
    }

    function drawWeekEnd(c) {
        const ctx = c.getContext('2d');
        const T = wt(), S = SHORT_I18N[weekLang] || SHORT_I18N.gujarati;
        K.reset(ctx);
        wSky(ctx, 55);
        const f = K.fit(ctx, T.endLine, { maxW: 1500, maxH: 220, size: 96, minSize: 56, lineHeight: 1.15 });
        strokedLines(ctx, f.lines, 210, f.lh, '#facc15', 12, VW / 2);
        [['👍', S.like, '#2563eb', 420], ['↗️', S.share, '#16a34a', 960], ['🔔', S.subscribe, '#dc2626', 1500]].forEach(([icon, label, color, x]) => {
            const bw = 470, bh = 150, y = 480;
            ctx.save();
            ctx.shadowColor = 'rgba(0,0,0,0.35)'; ctx.shadowBlur = 24;
            ctx.fillStyle = color;
            ctx.beginPath(); ctx.roundRect(x - bw / 2, y - bh / 2, bw, bh, bh / 2); ctx.fill();
            ctx.restore();
            ctx.save();
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillStyle = '#ffffff';
            ctx.font = `70px ${EMOJI}`;
            ctx.fillText(icon, x - bw / 2 + 90, y + 4);
            K.fitOneLine(ctx, label, bw - 200, 60, 30);
            ctx.fillText(label, x + 45, y + 4);
            ctx.restore();
        });
        wText(ctx, T.endShorts, VW / 2, 690, 1500, 80, 54, 30, '#fcd34d', 'center');
        wText(ctx, S.endAsk, VW / 2, 800, 1500, 70, 46, 28, '#ffffff', 'center');
        ctx.save();
        ctx.font = symFont(64); ctx.fillStyle = 'rgba(250, 204, 21, 0.6)';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        SYMBOLS.forEach((s, k) => ctx.fillText(s + '︎', VW / 2 + (k - 5.5) * 140, 960));
        ctx.restore();
    }

    /**
     * Thumbnail (cover): baayi taraf special ki laal pill + bada "Saptahik Rashifal" + tarikh;
     * daayi taraf us hafte ke special ki 3-4 rashi ke bade gole. 1920×1080 par, save 1280×720.
     */
    function drawWeekThumb(c, sp) {
        const ctx = c.getContext('2d');
        const T = wt();
        K.reset(ctx);
        wSky(ctx, 5, ['#3b0764', '#86198f', '#b91c1c']);
        [[560, 470], [1450, 540]].forEach(([gx, gy]) => {
            const glow = ctx.createRadialGradient(gx, gy, 40, gx, gy, 640);
            glow.addColorStop(0, 'rgba(250, 204, 21, 0.20)'); glow.addColorStop(1, 'rgba(250, 204, 21, 0)');
            ctx.fillStyle = glow; ctx.fillRect(0, 0, VW, VH);
        });

        wPill(ctx, sp.badge, 560, 150, 66, '#dc2626', '#ffffff', 1020);
        const f = K.fit(ctx, T.head, { maxW: 1020, maxH: 470, size: 210, minSize: 100, lineHeight: 1.08 });
        strokedLines(ctx, f.lines, 470, f.lh, '#facc15', Math.max(14, Math.round(f.size / 7)), 560);
        wPill(ctx, weekRangeLabel(), 560, 805, 56, 'rgba(11, 16, 51, 0.92)', '#ffffff', 1020);
        ctx.save();
        K.fitOneLine(ctx, T.sub, 1020, 48, 28);
        strokedLines(ctx, [T.sub], 940, 56, '#ffffff', 10, 560);
        ctx.restore();

        // Daayi taraf: 4 = 2×2, 3 = trikon
        const pos = sp.chips.length === 4
            ? [[1270, 300], [1650, 300], [1270, 690], [1650, 690]]
            : [[1460, 280], [1250, 690], [1670, 690]];
        sp.chips.forEach((ch, k) => {
            const [x, y] = pos[k] || pos[0];
            wChip(ctx, ch, x, y, 135, 54, sp.rank ? k + 1 : null);
        });
    }

    /** Video ke frames + YouTube title/description ([CHAPTERS] server bharta hai). */
    function weekPlan() {
        const L = wl(), T = wt(), S = SHORT_I18N[weekLang] || SHORT_I18N.gujarati;
        const range = weekRangeLabel();
        const sp = weekSpecial();
        const frames = [
            { label: T.start, text: fill(T.introSay, { r: range }), draw: cv => drawWeekIntro(cv) },
            { label: sp.chapter, text: sp.say, draw: cv => drawWeekSpecial(cv, sp) },
        ];
        weekItems.forEach((it, k) => frames.push({
            label: SYMBOLS[it._idx] + ' ' + L.names[it._idx],
            text: fill(T.rashiSay, {
                n: L.names[it._idx], o: endDot(it.overview), l: endDot(it.love), c: endDot(it.career),
                m: endDot(it.money), h: endDot(it.health), d: wDayNames(it, true) || '—', u: endDot(it.upay),
            }),
            draw: cv => drawWeekRashi(cv, it, k + 1),
        }));
        frames.push({ label: null, text: T.endSay, draw: cv => drawWeekEnd(cv) });
        // Title har hafte special ke hisaab se (search ke liye "Saptahik Rashifal" + tarikh bani rehti hai)
        const title = fill(sp.yt, { r: range });
        const lines = weekItems.map(it => SYMBOLS[it._idx] + ' ' + L.names[it._idx] + ': ' + wShort(it)).join('\n');
        return {
            title, special: sp,
            caption: `${title}\n\n${sp.emoji} ${sp.head}: ${sp.names}\n\n${fill(T.descIntro, { r: range })}\n\n[CHAPTERS]\n\n${lines}\n\n${S.endAsk}\n\n${T.tags}`,
            frames,
        };
    }

    function renderWeekPreview() {
        const p = weekPlan();
        el('weekTitle').textContent = p.title;
        const th = document.createElement('canvas');
        th.width = VW; th.height = VH;
        th.className = 'w-80 max-w-full rounded-lg border border-slate-200';
        drawWeekThumb(th, p.special);
        el('weekThumb').replaceChildren(th);
        const row = el('weekFrames');
        row.innerHTML = '';
        p.frames.forEach(f => {
            const cv = document.createElement('canvas');
            cv.width = VW; cv.height = VH;
            cv.className = 'h-36 w-auto aspect-video rounded border border-slate-200 shrink-0';
            f.draw(cv);
            row.appendChild(cv);
        });
        el('weekPreview').classList.remove('hidden');
    }

    // Hafte ki tarikh: Somvar–Budhvar ho to yahi hafta, warna agla Somvar
    (function initWeekDate() {
        const inp = el('weekDate');
        if (!inp) return;
        const t = new Date(), dow = t.getDay();
        if (!(dow >= 1 && dow <= 3)) t.setDate(t.getDate() + (8 - dow) % 7);
        inp.value = ymd(t);
        const show = () => {
            if (!inp.value) return;
            const m = mondayOf(inp.value), e = new Date(m + 'T00:00:00');
            e.setDate(e.getDate() + 6);
            el('weekRange').textContent = '🗓 Somvar ' + m + ' → Ravivar ' + ymd(e);
        };
        inp.addEventListener('change', show);
        show();
    })();

    el('weekGenBtn')?.addEventListener('click', async () => {
        const btn = el('weekGenBtn');
        const date = el('weekDate').value;
        if (!date) { alert('Hafta chuno'); return; }
        btn.disabled = true;
        el('weekStatus').textContent = '⏳ AI is hafte ka rashifal likh raha hai… (30-90 sec)';
        try {
            const d = await postJson(WEEK_GEN_URL, { date, language: el('language').value });
            const its = orderItems(Array.isArray(d.items) ? d.items : []);
            if (its.length < 12) throw new Error('AI ne sirf ' + its.length + ' rashi di — dobara try karo');
            weekItems = its;
            weekLang = el('language').value;
            weekStart = d.week_start;
            weekEnd = d.week_end;
            weekFest = d.festival || '';
            await K.ensureFonts();
            renderWeekPreview();
            el('weekStatus').textContent = '✅ Frames ready — check karke "💾 Video Save karo" dabao.'
                + (d.exists ? ' (Is hafte ka video pehle se saved hai — save karne par naya wala lagega.)' : '');
        } catch (e) {
            el('weekStatus').textContent = '⚠ ' + e.message;
        }
        btn.disabled = false;
    });

    el('weekSaveBtn')?.addEventListener('click', async () => {
        if (!weekItems.length) return;
        const btn = el('weekSaveBtn');
        btn.disabled = true;
        el('weekStatus').textContent = '⏳ Save ho raha hai… (14 frames + thumbnail)';
        try {
            await K.ensureFonts();
            const p = weekPlan();
            const off = document.createElement('canvas');
            off.width = VW; off.height = VH;
            const frames = p.frames.map(f => { f.draw(off); return { text: f.text, label: f.label, image: off.toDataURL('image/webp', 0.85) }; });
            drawWeekThumb(off, p.special);
            const small = document.createElement('canvas');
            small.width = 1280; small.height = 720;
            small.getContext('2d').drawImage(off, 0, 0, 1280, 720);
            const d = await postJson(WEEK_SAVE_URL, {
                week_start: weekStart, language: weekLang, audio: el('weekAudio').value,
                caption: p.caption, chapter_head: wt().chapterHead,
                thumbnail: small.toDataURL('image/jpeg', 0.9), frames,
            });
            el('weekStatus').textContent = '✅ Save ho gaya!';
            setTimeout(() => { window.location = d.redirect; }, 500);
        } catch (e) {
            el('weekStatus').textContent = '⚠ Save error: ' + e.message;
            btn.disabled = false;
        }
    });

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

        // YouTube ke 3 Shorts (checkbox tick ho to) — cards save ho chuke, Shorts fail ho to saaf batao
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
