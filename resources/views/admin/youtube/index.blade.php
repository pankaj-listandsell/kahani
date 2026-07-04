@extends('layouts.admin')
@section('title', 'YouTube')

@section('content')
<div class="max-w-4xl">

    {{-- ================= SUB-MENU TABS ================= --}}
    <div class="flex gap-1 border-b border-slate-200 mb-6 overflow-x-auto">
        <button type="button" class="yt-tab whitespace-nowrap px-4 py-2.5 text-sm border-b-2 border-transparent -mb-px" data-tab="manual">
            🚀 Manual Post
        </button>
        <button type="button" class="yt-tab whitespace-nowrap px-4 py-2.5 text-sm border-b-2 border-transparent -mb-px" data-tab="settings">
            ⚙️ Settings (Connection + Auto Post)
        </button>
    </div>

    {{-- ================================================= --}}
    {{--  TAB "Settings" — CONNECTION                      --}}
    {{-- ================================================= --}}
    <div data-panel="settings" class="yt-panel space-y-6 hidden">

        {{-- Status --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
                <span class="text-2xl">▶️</span>
                <div>
                    <p class="font-semibold">YouTube Connection</p>
                    @if ($configured)
                        <p class="text-sm text-green-600">✓ Connected @if($settings['yt_channel_title'])— <b>{{ $settings['yt_channel_title'] }}</b>@endif</p>
                    @elseif (! $appConfigured)
                        <p class="text-sm text-red-600">⚠️ Google client ID/secret .env me set nahi (setup guide dekho)</p>
                    @else
                        <p class="text-sm text-amber-600">⚠️ Channel connect nahi — "Connect YouTube" dabao</p>
                    @endif
                </div>
            </div>
            <div class="flex gap-2">
                @if ($configured)
                    <form method="POST" action="{{ route('admin.youtube.test') }}">@csrf
                        <button class="text-sm border border-slate-300 rounded-lg px-4 py-2 hover:bg-slate-50">Test</button>
                    </form>
                    <form method="POST" action="{{ route('admin.youtube.disconnect') }}" onsubmit="return confirm('Channel disconnect karein?')">@csrf
                        <button class="text-sm border border-red-300 text-red-600 rounded-lg px-4 py-2 hover:bg-red-50">Disconnect</button>
                    </form>
                @else
                    <a href="{{ route('admin.youtube.connect') }}"
                       class="text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg px-4 py-2 {{ $appConfigured ? '' : 'pointer-events-none opacity-40' }}">
                        ▶ Connect YouTube
                    </a>
                @endif
            </div>
        </div>

        {{-- Setup guide --}}
        <details class="bg-blue-50 border border-blue-200 rounded-xl p-5 text-sm text-blue-900">
            <summary class="font-semibold cursor-pointer">📖 Setup guide (Google Cloud — ek baar ka kaam)</summary>
            <ol class="list-decimal list-inside mt-3 space-y-1.5">
                <li><a href="https://console.cloud.google.com/" target="_blank" class="underline">Google Cloud Console</a> me ek project banao.</li>
                <li><b>YouTube Data API v3</b> enable karo (APIs &amp; Services → Library).</li>
                <li><b>OAuth consent screen</b> set karo (External), apni Google email ko <b>Test user</b> me add karo.</li>
                <li><b>Credentials → OAuth client ID → Web application</b> banao.</li>
                <li>Authorized redirect URI me exactly ye daalo (aur ye <code>GOOGLE_REDIRECT_URI</code> se bilkul match kare):<br>
                    <code class="bg-white px-1 rounded break-all">{{ config('services.youtube.redirect') ?: url('/admin/youtube/callback') }}</code></li>
                <li>Client ID &amp; secret ko <code class="bg-white px-1 rounded">.env</code> me <code>GOOGLE_CLIENT_ID</code> / <code>GOOGLE_CLIENT_SECRET</code> me daalo, phir <code class="bg-white px-1 rounded">php artisan config:clear</code>.</li>
                <li>Upar <b>Connect YouTube</b> dabao.</li>
            </ol>
            <p class="mt-3 text-amber-800">⚠️ Free quota: <b>10,000 units/din</b>, aur har upload <b>1600 units</b> — yaani ~<b>6 Shorts/din</b>. Slideshow mode kam upload use karta hai (ek part = ek Short).</p>
        </details>
    </div>

    {{-- ================================================= --}}
    {{--  TAB "Settings" — AUTO POST                       --}}
    {{-- ================================================= --}}
    <div data-panel="settings" class="yt-panel space-y-6 hidden">

        <form method="POST" action="{{ route('admin.youtube.autopost') }}" id="autoForm"
              class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            @csrf
            @method('PUT')

            <div class="bg-red-50 border-b border-red-100 px-6 py-4">
                <h3 class="font-semibold flex items-center gap-2">🕒 Auto Post (Automatic Shorts Upload)</h3>
            </div>

            <div class="p-6 space-y-5">
                {{-- Enable + status --}}
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="yt_auto_enabled" value="1" id="ytAutoEnabled" class="sr-only peer" @checked($settings['yt_auto_enabled'] === '1')>
                            <span class="relative w-11 h-6 bg-slate-300 rounded-full peer-checked:bg-green-500 transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition peer-checked:after:translate-x-5"></span>
                            <span class="font-medium">Enable Auto Post</span>
                        </label>
                        <p class="text-sm text-slate-500 mt-1">ON hone par cards apne-aap YouTube Shorts ban ke upload honge — sirf neeche ke time windows me.</p>
                    </div>
                    <div id="ytAutoStatus" class="rounded-lg border px-4 py-2 text-sm"></div>
                </div>

                {{-- Post mode --}}
                <div class="grid sm:grid-cols-3 gap-4 border-t pt-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Post Mode</label>
                        <select name="yt_post_mode" id="ytPostMode" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="single" @selected($settings['yt_post_mode'] === 'single')>Single Card (har card = ek Short)</option>
                            <option value="slideshow" @selected($settings['yt_post_mode'] === 'slideshow')>Slideshow (poora part = ek Short)</option>
                        </select>
                    </div>
                    <div id="slideSecondsWrap">
                        <label class="block text-sm font-medium mb-1">Slideshow: sec/card</label>
                        <input type="number" name="yt_slide_seconds" min="2" max="15" value="{{ $settings['yt_slide_seconds'] }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Privacy</label>
                        <select name="yt_privacy" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="public" @selected($settings['yt_privacy'] === 'public')>Public</option>
                            <option value="unlisted" @selected($settings['yt_privacy'] === 'unlisted')>Unlisted</option>
                            <option value="private" @selected($settings['yt_privacy'] === 'private')>Private</option>
                        </select>
                    </div>
                </div>

                {{-- Cover intro --}}
                <div class="flex items-center justify-between gap-4 flex-wrap border-t pt-4">
                    <div>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="yt_cover_enabled" value="1" class="sr-only peer" @checked($settings['yt_cover_enabled'] === '1')>
                            <span class="relative w-11 h-6 bg-slate-300 rounded-full peer-checked:bg-green-500 transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition peer-checked:after:translate-x-5"></span>
                            <span class="font-medium">🖼️ Cover Intro</span>
                        </label>
                        <p class="text-sm text-slate-500 mt-1">Story ki cover image Short ki shuruaat me dikhegi, phir cards. (Cover na ho to skip.)</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Cover: seconds</label>
                        <input type="number" name="yt_cover_seconds" min="1" max="10" value="{{ $settings['yt_cover_seconds'] }}"
                               class="w-28 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>

                {{-- Hashtags --}}
                <div>
                    <label class="block text-sm font-medium mb-1">Description hashtags <span class="text-slate-400">(#Shorts apne-aap lag jaata hai)</span></label>
                    <input type="text" name="yt_title_suffix" value="{{ $settings['yt_title_suffix'] }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="#Shorts #hindi #kahani #story">
                </div>

                {{-- Time windows --}}
                <div class="border-t pt-4">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <h4 class="font-semibold flex items-center gap-2">🗓️ Time Windows</h4>
                            <p class="text-sm text-slate-500">Start, end &amp; interval — upload sirf inhi ke andar hoga.</p>
                        </div>
                        <button type="button" id="addWindow" class="text-sm border border-red-300 text-red-700 rounded-lg px-3 py-1.5 hover:bg-red-50">＋ Add Window</button>
                    </div>
                    <div id="windows" class="space-y-3"></div>
                </div>

                {{-- Presets --}}
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-3">
                    <p class="text-sm font-medium mb-2">⚡ Quick Presets</p>
                    <div class="flex flex-wrap gap-2 text-sm">
                        <button type="button" class="preset border border-red-300 text-red-700 rounded-lg px-3 py-1.5 hover:bg-red-100" data-preset="best">Best (12-2, 6-8, 8-11 PM)</button>
                        <button type="button" class="preset border border-slate-300 rounded-lg px-3 py-1.5 hover:bg-slate-100" data-preset="evening">Evening (6-11 PM)</button>
                        <button type="button" class="preset border border-slate-300 rounded-lg px-3 py-1.5 hover:bg-slate-100" data-preset="allday">All Day (8 AM-11 PM)</button>
                        <button type="button" class="preset border border-red-300 text-red-600 rounded-lg px-3 py-1.5 hover:bg-red-50" data-preset="clear">✕ Clear All</button>
                    </div>
                </div>

                <button class="bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg px-5 py-2.5">Save Auto-Post Settings</button>
                <p class="text-xs text-slate-500">Auto-post ke liye background me <code>php artisan schedule:work</code> chalna chahiye. Time server timezone me ({{ config('app.timezone') }}). ⚠️ Quota: ~6 uploads/din.</p>
            </div>
        </form>

        {{-- Music --}}
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h3 class="font-semibold mb-1">🎵 Background Music (sabhi Shorts par)</h3>
            <p class="text-sm text-slate-500 mb-4">Ek <b>royalty-free</b> mp3 upload karo — har Short ke video me yahi bajega. (Copyright strike se bachne ke liye sirf royalty-free.)</p>

            @if (! empty($settings['yt_music']))
                <div class="flex items-center gap-3 mb-4 flex-wrap">
                    <audio controls src="{{ asset('storage/' . $settings['yt_music']) }}" class="h-10"></audio>
                    <form method="POST" action="{{ route('admin.youtube.music.remove') }}" onsubmit="return confirm('Music hata dein?')">@csrf @method('DELETE')
                        <button class="text-sm text-red-600 hover:underline">Remove</button>
                    </form>
                </div>
            @else
                <p class="text-sm text-amber-600 mb-4">⚠️ Abhi koi music nahi — Shorts silent banenge.</p>
            @endif

            <form method="POST" action="{{ route('admin.youtube.music') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <input type="file" name="yt_music" accept="audio/*" required
                       class="w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-red-100 file:px-4 file:py-2 file:text-red-800">
                <p class="text-xs text-slate-500">mp3 / m4a / wav · max 20 MB · sirf royalty-free.</p>
                <button class="bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg px-5 py-2.5">Save Music</button>
            </form>
        </div>

        {{-- Window row template --}}
        <template id="windowTemplate">
            <div class="window-row grid grid-cols-12 gap-2 items-end border border-slate-200 rounded-lg p-3">
                <div class="col-span-4 sm:col-span-3">
                    <label class="block text-xs text-slate-500 mb-1">Start</label>
                    <input type="time" data-k="start" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm">
                </div>
                <div class="col-span-4 sm:col-span-3">
                    <label class="block text-xs text-slate-500 mb-1">End</label>
                    <input type="time" data-k="end" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm">
                </div>
                <div class="col-span-4 sm:col-span-3">
                    <label class="block text-xs text-slate-500 mb-1">Interval</label>
                    <select data-k="interval" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm">
                        <option value="15">15 min</option>
                        <option value="30">30 min</option>
                        <option value="60" selected>60 min</option>
                        <option value="120">120 min</option>
                        <option value="180">180 min</option>
                    </select>
                </div>
                <div class="col-span-8 sm:col-span-2">
                    <span class="est inline-block bg-red-500 text-white text-xs rounded-full px-3 py-1">~0</span>
                </div>
                <div class="col-span-4 sm:col-span-1 text-right">
                    <button type="button" class="remove text-red-500 hover:text-red-700 border border-red-200 rounded-lg px-2 py-2">🗑</button>
                </div>
            </div>
        </template>
    </div>

    {{-- ================================================= --}}
    {{--  TAB 1: MANUAL POST                               --}}
    {{-- ================================================= --}}
    <div data-panel="manual" class="yt-panel">
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h3 class="font-semibold mb-1">🚀 Post Manually</h3>
            <p class="text-sm text-slate-500 mb-4">
                <b>Part Short</b> = poore part ka slideshow ek Short me · <b>Short</b> (per card) = us card ka apna Short.
                Button dabate hi video ban ke YouTube par upload hota hai — thoda time lag sakta hai.
            </p>

            @if (! $configured)
                <p class="text-amber-600 text-sm mb-4">⚠️ Pehle Settings tab me apna YouTube channel connect karo.</p>
            @endif

            @if ($stories->isEmpty())
                <p class="text-slate-500 text-sm">No stories yet.</p>
            @else
                <div class="space-y-5">
                    @foreach ($stories as $story)
                        <div>
                            <p class="font-medium">{{ $story->title }} <span class="text-xs text-slate-400">({{ ucfirst($story->status) }})</span></p>
                            @foreach ($story->parts as $part)
                                @if ($part->cards->isNotEmpty())
                                    <div class="mt-2 border border-slate-200 rounded-lg p-3">
                                        <div class="flex items-center justify-between mb-2 gap-2 flex-wrap">
                                            <span class="text-sm font-medium">Part {{ $part->sort_order }} · {{ $part->cards->count() }} cards</span>
                                            <form method="POST" action="{{ route('admin.youtube.part.short', $part) }}" class="yt-form" onsubmit="return confirm('Poore part ka ek slideshow Short banayein aur upload karein?')">
                                                @csrf
                                                <button @disabled(!$configured) class="text-xs bg-red-600 hover:bg-red-700 disabled:bg-slate-300 text-white rounded-lg px-3 py-1.5">▶ Part Short (slideshow)</button>
                                            </form>
                                        </div>
                                        <div class="flex gap-3 overflow-x-auto pb-1">
                                            @foreach ($part->cards as $card)
                                                <div class="shrink-0 text-center w-28">
                                                    @if ($card->isYtPosted())
                                                        <a href="https://youtube.com/watch?v={{ $card->yt_video_id }}" target="_blank"
                                                           class="h-28 w-28 flex flex-col items-center justify-center rounded-lg border border-green-200 bg-green-50 text-green-600 hover:bg-green-100">
                                                            <span class="text-2xl">✓</span>
                                                            <span class="text-[10px] mt-1">On YouTube ↗</span>
                                                        </a>
                                                        <span class="block text-[11px] text-green-600 mt-1">✓ Uploaded</span>
                                                        @if ($card->yt_posted_at)
                                                            <span class="block text-[10px] text-slate-400 leading-tight">🕒 {{ $card->yt_posted_at->format('d M, h:i A') }}</span>
                                                        @endif
                                                    @else
                                                        <img src="{{ asset('storage/' . $card->image_path) }}" class="h-28 w-28 object-cover rounded-lg border border-slate-200" alt="">
                                                        <form method="POST" action="{{ route('admin.youtube.card.short', $card) }}" class="yt-form mt-1">
                                                            @csrf
                                                            <button @disabled(!$configured) class="w-full text-[11px] bg-red-50 text-red-700 border border-red-200 rounded px-2 py-0.5 hover:bg-red-100 disabled:opacity-40">▶ Short</button>
                                                        </form>
                                                        <button type="button"
                                                            class="yt-caption-btn mt-1 w-full text-[11px] border rounded px-2 py-0.5 hover:bg-slate-50 {{ filled($card->yt_caption) ? 'bg-amber-50 text-amber-700 border-amber-200' : 'text-slate-500 border-slate-200' }}"
                                                            data-get="{{ route('admin.youtube.card.caption.get', $card) }}"
                                                            data-generate="{{ route('admin.youtube.card.caption.generate', $card) }}"
                                                            data-save="{{ route('admin.youtube.card.caption.save', $card) }}">
                                                            {{ filled($card->yt_caption) ? '📝 Caption' : '✨ Caption' }}
                                                        </button>
                                                        @if ($card->yt_status === 'failed')
                                                            <span class="block text-[10px] font-semibold text-red-500 mt-0.5">failed</span>
                                                            @if (filled($card->yt_error))
                                                                <span class="block text-[9px] text-red-400 leading-tight mt-0.5" title="{{ $card->yt_error }}">{{ Str::limit($card->yt_error, 90) }}</span>
                                                            @endif
                                                        @endif
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

</div>

{{-- ================= AI CAPTION MODAL ================= --}}
<div id="ytCapModal" class="fixed inset-0 z-40 hidden items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-xl w-full max-w-lg shadow-xl">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200">
            <h3 class="font-semibold">✨ YouTube Caption <span class="text-xs font-normal text-slate-400">(title + description + #hashtags)</span></h3>
            <button type="button" id="ytCapClose" class="text-slate-400 hover:text-slate-700 text-xl leading-none">&times;</button>
        </div>
        <div class="p-5 space-y-3">
            <textarea id="ytCapText" rows="9"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 focus:outline-none"
                      placeholder="Pehli line = Short ka title, baaki = description. ✨ AI se banao ya khud likho. Khaali chhodo to story title se default ban jayega."></textarea>
            <p id="ytCapMsg" class="text-xs text-slate-500"></p>
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <button type="button" id="ytCapGenerate"
                        class="text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg px-4 py-2">✨ Generate with AI</button>
                <div class="flex gap-2">
                    <button type="button" id="ytCapCancel" class="text-sm text-slate-500 hover:underline px-3 py-2">Cancel</button>
                    <button type="button" id="ytCapSave" class="text-sm bg-slate-800 hover:bg-slate-900 text-white rounded-lg px-4 py-2">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // ---------- Sub-menu tabs ----------
    const ytTabs = document.querySelectorAll('.yt-tab');
    const ytPanels = document.querySelectorAll('.yt-panel');

    function showYtTab(name) {
        ytPanels.forEach(p => p.classList.toggle('hidden', p.dataset.panel !== name));
        ytTabs.forEach(t => {
            const on = t.dataset.tab === name;
            t.classList.toggle('border-red-600', on);
            t.classList.toggle('text-red-700', on);
            t.classList.toggle('font-semibold', on);
            t.classList.toggle('border-transparent', !on);
            t.classList.toggle('text-slate-500', !on);
        });
        try { localStorage.setItem('ytTab', name); } catch (e) {}
    }
    ytTabs.forEach(t => t.addEventListener('click', () => showYtTab(t.dataset.tab)));
    let ytInitial = 'manual';
    try { ytInitial = localStorage.getItem('ytTab') || 'manual'; } catch (e) {}
    if (![...ytTabs].some(t => t.dataset.tab === ytInitial)) ytInitial = 'manual';
    showYtTab(ytInitial);

    // ---------- Slideshow seconds sirf slideshow mode me dikhe ----------
    const modeSel = document.getElementById('ytPostMode');
    const slideWrap = document.getElementById('slideSecondsWrap');
    function syncMode() { if (slideWrap) slideWrap.style.opacity = (modeSel.value === 'slideshow') ? '1' : '0.4'; }
    modeSel?.addEventListener('change', syncMode); syncMode();

    // ---------- Time windows ----------
    const wrap = document.getElementById('windows');
    if (wrap) {
        const savedWindows = @json($settings['yt_auto_windows']);
        const tpl = document.getElementById('windowTemplate');
        let idx = 0;

        function addRow(w) {
            const node = tpl.content.firstElementChild.cloneNode(true);
            const i = idx++;
            node.querySelectorAll('[data-k]').forEach(el => {
                const k = el.dataset.k;
                el.name = `windows[${i}][${k}]`;
                if (w && w[k] !== undefined && w[k] !== null) el.value = w[k];
            });
            node.querySelector('.remove').addEventListener('click', () => node.remove());
            node.querySelectorAll('input,select').forEach(el => el.addEventListener('change', () => updateEst(node)));
            wrap.appendChild(node);
            updateEst(node);
        }
        function toMin(t) { if (!t) return null; const [h, m] = t.split(':').map(Number); return h * 60 + m; }
        function updateEst(node) {
            const s = toMin(node.querySelector('[data-k=start]').value);
            const e = toMin(node.querySelector('[data-k=end]').value);
            const iv = parseInt(node.querySelector('[data-k=interval]').value, 10) || 60;
            let n = 0;
            if (s !== null && e !== null && e >= s) n = Math.floor((e - s) / iv) + 1;
            node.querySelector('.est').textContent = '~' + n;
            updateStatus();
        }
        function updateStatus() {
            const on = document.getElementById('ytAutoEnabled').checked;
            const count = wrap.querySelectorAll('.window-row').length;
            const box = document.getElementById('ytAutoStatus');
            if (on) {
                box.className = 'rounded-lg border px-4 py-2 text-sm bg-green-50 border-green-200 text-green-700';
                box.innerHTML = '✓ <b>Auto Post: ON</b><br><span class="text-xs">' + count + ' window(s)</span>';
            } else {
                box.className = 'rounded-lg border px-4 py-2 text-sm bg-slate-50 border-slate-200 text-slate-500';
                box.innerHTML = '○ Auto Post: OFF';
            }
        }
        const PRESETS = {
            best:    [{start:'12:00',end:'14:00',interval:60},{start:'18:00',end:'20:00',interval:60},{start:'20:00',end:'23:00',interval:60}],
            evening: [{start:'18:00',end:'23:00',interval:60}],
            allday:  [{start:'08:00',end:'23:00',interval:120}],
            clear:   [],
        };
        document.getElementById('addWindow').addEventListener('click', () => addRow({interval:60}));
        document.getElementById('ytAutoEnabled').addEventListener('change', updateStatus);
        document.querySelectorAll('.preset').forEach(b => b.addEventListener('click', () => {
            wrap.innerHTML = '';
            (PRESETS[b.dataset.preset] || []).forEach(addRow);
            updateStatus();
        }));
        (savedWindows && savedWindows.length ? savedWindows : []).forEach(addRow);
        updateStatus();
    }

    // manual buttons -> loading
    document.querySelectorAll('.yt-form').forEach(form => form.addEventListener('submit', () => {
        const btn = form.querySelector('button');
        if (btn) { btn.disabled = true; btn.textContent = '⏳...'; }
    }));

    // ---------- AI Caption modal ----------
    (function () {
        const modal = document.getElementById('ytCapModal');
        if (!modal) return;
        const txt = document.getElementById('ytCapText');
        const msg = document.getElementById('ytCapMsg');
        const csrf = document.querySelector('meta[name=csrf-token]')?.content;
        let cur = null; // { get, generate, save, btn }

        const open  = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
        const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); cur = null; };

        document.getElementById('ytCapClose').addEventListener('click', close);
        document.getElementById('ytCapCancel').addEventListener('click', close);
        modal.addEventListener('click', e => { if (e.target === modal) close(); });

        function markHas() {
            if (!cur) return;
            const has = txt.value.trim() !== '';
            cur.btn.classList.toggle('bg-amber-50', has);
            cur.btn.classList.toggle('text-amber-700', has);
            cur.btn.classList.toggle('border-amber-200', has);
            cur.btn.classList.toggle('text-slate-500', !has);
            cur.btn.classList.toggle('border-slate-200', !has);
            cur.btn.textContent = has ? '📝 Caption' : '✨ Caption';
        }

        document.querySelectorAll('.yt-caption-btn').forEach(b => b.addEventListener('click', async () => {
            cur = { get: b.dataset.get, generate: b.dataset.generate, save: b.dataset.save, btn: b };
            txt.value = '';
            msg.textContent = 'Loading…';
            open();
            try {
                const r = await fetch(cur.get, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                txt.value = d.caption || '';
                msg.textContent = d.caption ? '' : 'Abhi koi caption nahi — ✨ AI se banao ya khud likho.';
            } catch (e) { msg.textContent = 'Caption load nahi hui.'; }
        }));

        document.getElementById('ytCapGenerate').addEventListener('click', async () => {
            if (!cur) return;
            const gb = document.getElementById('ytCapGenerate');
            gb.disabled = true; gb.textContent = '⏳ Ban rahi hai…'; msg.textContent = '';
            try {
                const r = await fetch(cur.generate, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                const d = await r.json();
                if (d.ok) { txt.value = d.caption; msg.textContent = '✓ AI caption ban gayi — Save karna na bhoolein.'; markHas(); }
                else { msg.textContent = '⚠ ' + (d.error || 'Caption nahi bani.'); }
            } catch (e) { msg.textContent = '⚠ Error aaya, dobara try karein.'; }
            gb.disabled = false; gb.textContent = '✨ Generate with AI';
        });

        document.getElementById('ytCapSave').addEventListener('click', async () => {
            if (!cur) return;
            const sb = document.getElementById('ytCapSave');
            sb.disabled = true; sb.textContent = 'Saving…';
            try {
                const r = await fetch(cur.save, {
                    method: 'PUT',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ caption: txt.value }),
                });
                const d = await r.json();
                if (d.ok) { markHas(); close(); }
                else { msg.textContent = '⚠ Save nahi hui.'; }
            } catch (e) { msg.textContent = '⚠ Save me error.'; }
            sb.disabled = false; sb.textContent = 'Save';
        });
    })();
</script>
@endpush
@endsection
