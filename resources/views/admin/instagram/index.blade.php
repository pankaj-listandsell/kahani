@extends('layouts.admin')
@section('title', 'Instagram')

@section('content')
<div class="max-w-4xl">

    {{-- ================= SUB-MENU TABS ================= --}}
    <div class="flex gap-1 border-b border-slate-200 mb-6 overflow-x-auto">
        <button type="button" class="ig-tab whitespace-nowrap px-4 py-2.5 text-sm border-b-2 border-transparent -mb-px" data-tab="manual">
            🚀 Manual Post
        </button>
        <button type="button" class="ig-tab whitespace-nowrap px-4 py-2.5 text-sm border-b-2 border-transparent -mb-px" data-tab="settings">
            ⚙️ Settings (Connection + Auto Post)
        </button>
    </div>

    {{-- ================================================= --}}
    {{--  TAB "Settings" — part 1: CONNECTION              --}}
    {{-- ================================================= --}}
    <div data-panel="settings" class="ig-panel space-y-6 hidden">

        {{-- Status --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="text-2xl">📸</span>
                <div>
                    <p class="font-semibold">Instagram Connection</p>
                    @if ($configured)
                        <p class="text-sm text-green-600">✓ Configured — ready to post</p>
                    @else
                        <p class="text-sm text-amber-600">⚠️ Not configured — add your access token below</p>
                    @endif
                </div>
            </div>
            <form method="POST" action="{{ route('admin.instagram.test') }}">
                @csrf
                <button class="text-sm border border-slate-300 rounded-lg px-4 py-2 hover:bg-slate-50">Test Connection</button>
            </form>
        </div>

        {{-- Publishing limit (rolling 24h) --}}
        @if ($configured)
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <h3 class="font-semibold text-sm flex items-center gap-2">📊 Posting Limit <span class="text-xs font-normal text-slate-400">(pichhle 24 ghante)</span></h3>
                    <button type="button" id="igLimitRefresh" class="text-xs text-rose-600 hover:underline">↻ Refresh</button>
                </div>
                <div id="igLimitBody" class="text-sm text-slate-500">Loading…</div>
            </div>
            <script>
            (function () {
                const body = document.getElementById('igLimitBody');
                const btn = document.getElementById('igLimitRefresh');
                if (!body) return;

                async function load() {
                    body.textContent = 'Loading…';
                    try {
                        const r = await fetch(@json(route('admin.instagram.limit')), { headers: { 'Accept': 'application/json' } });
                        const d = await r.json();
                        if (!d.ok) { body.innerHTML = '<span class="text-amber-600">⚠️ ' + (d.error || 'Limit nahi mili') + '</span>'; return; }
                        const pct = d.total ? Math.round((d.used / d.total) * 100) : 0;
                        const left = Math.max(0, d.total - d.used);
                        const color = pct >= 90 ? 'bg-red-500' : (pct >= 70 ? 'bg-amber-500' : 'bg-green-500');
                        body.innerHTML =
                            '<div class="flex items-center justify-between mb-1"><span class="font-medium text-slate-700">' + d.used + ' / ' + d.total + ' posts used</span>'
                            + '<span class="text-xs text-slate-500">' + left + ' bache</span></div>'
                            + '<div class="w-full bg-slate-200 rounded-full h-2.5"><div class="' + color + ' h-2.5 rounded-full" style="width:' + pct + '%"></div></div>'
                            + '<p class="text-xs text-slate-400 mt-1">Rolling 24-ghante ki limit. Har post 24h baad apne-aap free ho jaata hai.</p>';
                    } catch (e) { body.innerHTML = '<span class="text-amber-600">⚠️ Limit load nahi hui.</span>'; }
                }
                btn?.addEventListener('click', load);
                load();
            })();
            </script>
        @endif

        {{-- Setup guide --}}
        <details class="bg-blue-50 border border-blue-200 rounded-xl p-5 text-sm text-blue-900">
            <summary class="font-semibold cursor-pointer">📖 How to get the access token (setup guide)</summary>
            <ol class="list-decimal list-inside mt-3 space-y-1.5">
                <li>Use an Instagram <b>Business</b> or <b>Creator</b> account.</li>
                <li>At <a href="https://developers.facebook.com/tools/explorer" target="_blank" class="underline">Graph API Explorer</a>, select your app.</li>
                <li>Add permissions: <code class="bg-white px-1 rounded">instagram_basic</code>, <code class="bg-white px-1 rounded">instagram_content_publish</code>.</li>
                <li>Generate the token, copy it (starts with <code class="bg-white px-1 rounded">IGAA…</code> or <code class="bg-white px-1 rounded">EAA…</code>), paste below, Save, then Test Connection.</li>
            </ol>
            <p class="mt-3 text-green-800">✓ No tunnel needed — images &amp; videos are auto-uploaded to a temporary public host.</p>
        </details>

        {{-- Connection settings --}}
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h3 class="font-semibold mb-4">⚙️ Connection Settings</h3>
            <form method="POST" action="{{ route('admin.instagram.settings') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-medium mb-1">Access Token</label>
                    <textarea name="ig_access_token" rows="3"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:ring-2 focus:ring-rose-400 focus:outline-none"
                              placeholder="IGAA... or EAA...">{{ old('ig_access_token', $settings['ig_access_token']) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Instagram Account ID <span class="text-slate-400">(only for old EAA tokens)</span></label>
                    <input type="text" name="ig_user_id" value="{{ old('ig_user_id', $settings['ig_user_id']) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none" placeholder="(optional)">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Default Caption Suffix (hashtags — optional)</label>
                    <textarea name="ig_caption_suffix" rows="2"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none"
                              placeholder="#hindistory #kahani">{{ old('ig_caption_suffix', $settings['ig_caption_suffix']) }}</textarea>
                </div>
                <button class="bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg px-5 py-2.5">Save Settings</button>
            </form>
        </div>
    </div>

    {{-- ================================================= --}}
    {{--  TAB "Settings" — part 2: AUTO POST               --}}
    {{-- ================================================= --}}
    <div data-panel="settings" class="ig-panel space-y-6 hidden">

        <form method="POST" action="{{ route('admin.instagram.autopost') }}" id="autoForm"
              class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            @csrf
            @method('PUT')

            <div class="bg-sky-50 border-b border-sky-100 px-6 py-4">
                <h3 class="font-semibold flex items-center gap-2">🕒 Auto Post (Automatic Uploading)</h3>
            </div>

            <div class="p-6 space-y-5">
                {{-- Enable + status --}}
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="ig_auto_enabled" value="1" id="autoEnabled" class="sr-only peer" @checked($settings['ig_auto_enabled'] === '1')>
                            <span class="relative w-11 h-6 bg-slate-300 rounded-full peer-checked:bg-green-500 transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition peer-checked:after:translate-x-5"></span>
                            <span class="font-medium">Enable Auto Post</span>
                        </label>
                        <p class="text-sm text-slate-500 mt-1">When ON, completed cards auto-post to Instagram — only inside the time windows below.</p>
                    </div>
                    <div id="autoStatus" class="rounded-lg border px-4 py-2 text-sm"></div>
                </div>

                {{-- Post type --}}
                <div class="flex items-center gap-3">
                    <label class="text-sm font-medium">Post as:</label>
                    <select name="ig_post_type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="image" @selected($settings['ig_post_type'] === 'image')>Post (image)</option>
                        <option value="reel" @selected($settings['ig_post_type'] === 'reel')>Reel (video)</option>
                    </select>
                </div>

                {{-- Reel ke saath Story me bhi --}}
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="ig_also_story" value="1" class="mt-1 rounded border-slate-300" @checked($settings['ig_also_story'] === '1')>
                    <span>
                        <span class="text-sm font-medium">📲 Reel ke saath Instagram Story me bhi daalo</span>
                        <span class="block text-xs text-slate-500">Jab bhi Reel post hoga, us card ki <b>image (text card)</b> 24-ghante wali Story me bhi apne-aap chali jayegi — reel video nahi. (Story bhi daily limit me ginti hai.)</span>
                    </span>
                </label>

                {{-- Voice-over (AI TTS) — sirf reel par lagta hai --}}
                @include('admin.partials._voiceover')

                {{-- Time windows --}}
                <div class="border-t pt-4">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <h4 class="font-semibold flex items-center gap-2">🗓️ Time Windows</h4>
                            <p class="text-sm text-slate-500">Set start, end &amp; interval per window. Posting happens only inside these.</p>
                        </div>
                        <button type="button" id="addWindow" class="text-sm border border-sky-300 text-sky-700 rounded-lg px-3 py-1.5 hover:bg-sky-50">＋ Add Window</button>
                    </div>

                    <div id="windows" class="space-y-3"></div>
                </div>

                {{-- Presets --}}
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-3">
                    <p class="text-sm font-medium mb-2">⚡ Quick Presets (one click)</p>
                    <div class="flex flex-wrap gap-2 text-sm">
                        <button type="button" class="preset border border-sky-300 text-sky-700 rounded-lg px-3 py-1.5 hover:bg-sky-100" data-preset="best">Best Times (12-2, 6-8, 8-11 PM)</button>
                        <button type="button" class="preset border border-slate-300 rounded-lg px-3 py-1.5 hover:bg-slate-100" data-preset="morning">Morning (6 AM - 12 PM)</button>
                        <button type="button" class="preset border border-slate-300 rounded-lg px-3 py-1.5 hover:bg-slate-100" data-preset="evening">Evening (6 PM - 11 PM)</button>
                        <button type="button" class="preset border border-slate-300 rounded-lg px-3 py-1.5 hover:bg-slate-100" data-preset="allday">All Day (8 AM - 11 PM)</button>
                        <button type="button" class="preset border border-red-300 text-red-600 rounded-lg px-3 py-1.5 hover:bg-red-50" data-preset="clear">✕ Clear All</button>
                    </div>
                </div>

                <button class="bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg px-5 py-2.5">Save Auto-Post Settings</button>
                <p class="text-xs text-slate-500">Auto-post needs <code>php artisan schedule:work</code> running in the background. Times are in your server timezone ({{ config('app.timezone') }}).</p>
            </div>
        </form>

        {{-- Reel Music --}}
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h3 class="font-semibold mb-1">🎵 Reel Music (sabhi reels par)</h3>
            <p class="text-sm text-slate-500 mb-4">
                Ek <b>royalty-free</b> mp3 upload karo — har reel ke video me yahi music bajega.
                (Instagram ke trending/licensed songs API se add nahi ho sakte.)
            </p>

            @if (! empty($settings['ig_reel_music']))
                <div class="flex items-center gap-3 mb-4 flex-wrap">
                    <audio controls src="{{ asset('storage/' . $settings['ig_reel_music']) }}" class="h-10"></audio>
                    <form method="POST" action="{{ route('admin.instagram.music.remove') }}" onsubmit="return confirm('Music hata dein?')">
                        @csrf @method('DELETE')
                        <button class="text-sm text-red-600 hover:underline">Remove</button>
                    </form>
                </div>
            @else
                <p class="text-sm text-amber-600 mb-4">⚠️ Abhi koi music set nahi — reels silent (bina awaaz) banenge.</p>
            @endif

            <form method="POST" action="{{ route('admin.instagram.music') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <input type="file" name="reel_music" accept="audio/*" required
                       class="w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sky-100 file:px-4 file:py-2 file:text-sky-800">
                <p class="text-xs text-slate-500">mp3 / m4a / wav · max 20 MB · sirf royalty-free music (copyright strike se bachne ke liye).</p>
                <button class="bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg px-5 py-2.5">Save Music</button>
            </form>
        </div>

        {{-- Row template --}}
        <template id="windowTemplate">
            <div class="window-row grid grid-cols-12 gap-2 items-end border border-slate-200 rounded-lg p-3">
                <div class="col-span-4 sm:col-span-3">
                    <label class="block text-xs text-slate-500 mb-1">Start Time</label>
                    <input type="time" data-k="start" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm">
                </div>
                <div class="col-span-4 sm:col-span-3">
                    <label class="block text-xs text-slate-500 mb-1">End Time</label>
                    <input type="time" data-k="end" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm">
                </div>
                <div class="col-span-4 sm:col-span-3">
                    <label class="block text-xs text-slate-500 mb-1">Interval</label>
                    <select data-k="interval" class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm">
                        <option value="5">5 min</option>
                        <option value="10">10 min</option>
                        <option value="15">15 min</option>
                        <option value="30" selected>30 min</option>
                        <option value="45">45 min</option>
                        <option value="60">60 min</option>
                        <option value="90">90 min</option>
                        <option value="120">120 min</option>
                    </select>
                </div>
                <div class="col-span-8 sm:col-span-2">
                    <span class="est inline-block bg-sky-500 text-white text-xs rounded-full px-3 py-1">~0 posts</span>
                </div>
                <div class="col-span-4 sm:col-span-1 text-right">
                    <button type="button" class="remove text-red-500 hover:text-red-700 border border-red-200 rounded-lg px-2 py-2">🗑</button>
                </div>
            </div>
        </template>
    </div>

    {{-- ================================================= --}}
    {{--  TAB 1: MANUAL POST (default)                     --}}
    {{-- ================================================= --}}
    <div data-panel="manual" class="ig-panel">
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h3 class="font-semibold mb-1">🚀 Post Manually</h3>
            <p class="text-sm text-slate-500 mb-4">
                Choose <b>Post</b> (image) or <b>Reel</b> (video) per card. Button dabate hi card <b>turant</b> Instagram par
                post ho jaata hai — reel ban ne me thoda time lag sakta hai, isliye request poori hone tak wait karein.
            </p>

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
                                            <div class="flex gap-2">
                                                <form method="POST" action="{{ route('admin.instagram.part.captions', $part) }}" class="ig-form" onsubmit="return confirm('Is part ke sabhi cards ke liye AI caption banayein?')">
                                                    @csrf
                                                    <button class="text-xs bg-sky-600 hover:bg-sky-700 text-white rounded-lg px-3 py-1.5">✨ All Captions</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.instagram.part.post', $part) }}" class="ig-form" onsubmit="return confirm('Post all cards as IMAGE posts?')">
                                                    @csrf
                                                    <button @disabled(!$configured) class="text-xs bg-rose-600 hover:bg-rose-700 disabled:bg-slate-300 text-white rounded-lg px-3 py-1.5">All as Posts</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.instagram.part.reels', $part) }}" class="ig-form" onsubmit="return confirm('Post all cards as REELS? May take a few minutes.')">
                                                    @csrf
                                                    <button @disabled(!$configured) class="text-xs bg-purple-600 hover:bg-purple-700 disabled:bg-slate-300 text-white rounded-lg px-3 py-1.5">All as Reels</button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="flex gap-3 overflow-x-auto pb-1">
                                            @foreach ($part->cards as $card)
                                                <div class="shrink-0 text-center w-28">
                                                    @if ($card->isPosted())
                                                        {{-- Post hone ke baad local image/video delete ho jaate hain --}}
                                                        <div class="h-28 w-28 flex flex-col items-center justify-center rounded-lg border border-green-200 bg-green-50 text-green-600">
                                                            <span class="text-2xl">✓</span>
                                                            <span class="text-[10px] mt-1">Uploaded</span>
                                                        </div>
                                                        <span class="block text-[11px] text-green-600 mt-1">✓ Posted</span>
                                                        @if ($card->ig_posted_at)
                                                            <span class="block text-[10px] text-slate-400 leading-tight" title="{{ $card->ig_posted_at->format('d M Y, h:i A') }}">
                                                                🕒 {{ $card->ig_posted_at->format('d M, h:i A') }}
                                                            </span>
                                                        @endif
                                                    @else
                                                        <img src="{{ asset('storage/' . $card->image_path) }}" class="h-28 w-28 object-cover rounded-lg border border-slate-200" alt="">
                                                        <div class="flex gap-1 mt-1 justify-center">
                                                            <form method="POST" action="{{ route('admin.instagram.card.post', $card) }}" class="ig-form">
                                                                @csrf
                                                                <button @disabled(!$configured) class="text-[11px] bg-rose-50 text-rose-700 border border-rose-200 rounded px-2 py-0.5 hover:bg-rose-100 disabled:opacity-40">Post</button>
                                                            </form>
                                                            <form method="POST" action="{{ route('admin.instagram.card.reel', $card) }}" class="ig-form">
                                                                @csrf
                                                                <button @disabled(!$configured) class="text-[11px] bg-purple-50 text-purple-700 border border-purple-200 rounded px-2 py-0.5 hover:bg-purple-100 disabled:opacity-40">Reel</button>
                                                            </form>
                                                        </div>
                                                        <button type="button"
                                                            class="caption-btn mt-1 w-full text-[11px] border rounded px-2 py-0.5 hover:bg-slate-50 {{ filled($card->ig_caption) ? 'bg-amber-50 text-amber-700 border-amber-200' : 'text-slate-500 border-slate-200' }}"
                                                            data-get="{{ route('admin.instagram.card.caption.get', $card) }}"
                                                            data-generate="{{ route('admin.instagram.card.caption.generate', $card) }}"
                                                            data-save="{{ route('admin.instagram.card.caption.save', $card) }}">
                                                            {{ filled($card->ig_caption) ? '📝 Caption' : '✨ Caption' }}
                                                        </button>
                                                        @if ($card->ig_status === 'failed')
                                                            <span class="block text-[10px] font-semibold text-red-500 mt-0.5">failed</span>
                                                            @if (filled($card->ig_error))
                                                                <span class="block text-[9px] text-red-400 leading-tight mt-0.5" title="{{ $card->ig_error }}">{{ Str::limit($card->ig_error, 90) }}</span>
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
<div id="capModal" class="fixed inset-0 z-40 hidden items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-xl w-full max-w-lg shadow-xl">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200">
            <h3 class="font-semibold">✨ Instagram Caption</h3>
            <button type="button" id="capClose" class="text-slate-400 hover:text-slate-700 text-xl leading-none">&times;</button>
        </div>
        <div class="p-5 space-y-3">
            <textarea id="capText" rows="8"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-rose-400 focus:outline-none"
                      placeholder="Yahan caption aayegi… ✨ AI se banao ya khud likho. Khaali chhodo to default caption use hogi."></textarea>
            <p id="capMsg" class="text-xs text-slate-500"></p>
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <button type="button" id="capGenerate"
                        class="text-sm bg-sky-600 hover:bg-sky-700 text-white rounded-lg px-4 py-2">✨ Generate with AI</button>
                <div class="flex gap-2">
                    <button type="button" id="capCancel" class="text-sm text-slate-500 hover:underline px-3 py-2">Cancel</button>
                    <button type="button" id="capSave" class="text-sm bg-rose-600 hover:bg-rose-700 text-white rounded-lg px-4 py-2">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // ---------- Sub-menu tabs ----------
    const igTabs = document.querySelectorAll('.ig-tab');
    const igPanels = document.querySelectorAll('.ig-panel');

    function showIgTab(name) {
        igPanels.forEach(p => p.classList.toggle('hidden', p.dataset.panel !== name));
        igTabs.forEach(t => {
            const on = t.dataset.tab === name;
            t.classList.toggle('border-rose-600', on);
            t.classList.toggle('text-rose-700', on);
            t.classList.toggle('font-semibold', on);
            t.classList.toggle('border-transparent', !on);
            t.classList.toggle('text-slate-500', !on);
        });
        try { localStorage.setItem('igTab', name); } catch (e) {}
    }

    igTabs.forEach(t => t.addEventListener('click', () => showIgTab(t.dataset.tab)));

    // Save karne ke baad (redirect back) usi tab par rahein — localStorage se yaad
    let igInitial = 'manual';
    try { igInitial = localStorage.getItem('igTab') || 'manual'; } catch (e) {}
    if (![...igTabs].some(t => t.dataset.tab === igInitial)) igInitial = 'manual';
    showIgTab(igInitial);

    // ---------- Auto-post time windows (sirf admin ke settings panel me) ----------
    const wrap = document.getElementById('windows');
    if (wrap) {
    const savedWindows = @json($settings['ig_auto_windows']);
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
        node.querySelector('.remove').addEventListener('click', () => { node.remove(); });
        node.querySelectorAll('input,select').forEach(el => el.addEventListener('change', () => updateEst(node)));
        wrap.appendChild(node);
        updateEst(node);
    }

    function toMin(t) { if (!t) return null; const [h, m] = t.split(':').map(Number); return h * 60 + m; }

    function updateEst(node) {
        const s = toMin(node.querySelector('[data-k=start]').value);
        const e = toMin(node.querySelector('[data-k=end]').value);
        const iv = parseInt(node.querySelector('[data-k=interval]').value, 10) || 30;
        let n = 0;
        // Slots start se end tak, dono inclusive (jaise 2:00 … 3:00 @10min = 7)
        if (s !== null && e !== null && e >= s) n = Math.floor((e - s) / iv) + 1;
        node.querySelector('.est').textContent = '~' + n + ' posts';
        updateStatus();
    }

    function updateStatus() {
        const on = document.getElementById('autoEnabled').checked;
        const count = wrap.querySelectorAll('.window-row').length;
        const box = document.getElementById('autoStatus');
        if (on) {
            box.className = 'rounded-lg border px-4 py-2 text-sm bg-green-50 border-green-200 text-green-700';
            box.innerHTML = '✓ <b>Auto Post: ON</b><br><span class="text-xs">' + count + ' time window(s) active</span>';
        } else {
            box.className = 'rounded-lg border px-4 py-2 text-sm bg-slate-50 border-slate-200 text-slate-500';
            box.innerHTML = '○ Auto Post: OFF';
        }
    }

    const PRESETS = {
        best:    [{start:'12:00',end:'14:00',interval:30},{start:'18:00',end:'20:00',interval:30},{start:'20:00',end:'23:00',interval:30}],
        morning: [{start:'06:00',end:'12:00',interval:30}],
        evening: [{start:'18:00',end:'23:00',interval:30}],
        allday:  [{start:'08:00',end:'23:00',interval:30}],
        clear:   [],
    };

    document.getElementById('addWindow').addEventListener('click', () => addRow({interval:30}));
    document.getElementById('autoEnabled').addEventListener('change', updateStatus);
    document.querySelectorAll('.preset').forEach(b => b.addEventListener('click', () => {
        wrap.innerHTML = '';
        (PRESETS[b.dataset.preset] || []).forEach(addRow);
        updateStatus();
    }));

    // init
    (savedWindows && savedWindows.length ? savedWindows : []).forEach(addRow);
    updateStatus();
    } // end if (wrap) — auto-post JS sirf admin ke liye

    // manual post buttons -> loading
    document.querySelectorAll('.ig-form').forEach(form => form.addEventListener('submit', () => {
        const btn = form.querySelector('button');
        if (btn) { btn.disabled = true; btn.textContent = '⏳...'; }
    }));

    // ---------- AI Caption modal ----------
    (function () {
        const modal = document.getElementById('capModal');
        if (!modal) return;
        const txt = document.getElementById('capText');
        const msg = document.getElementById('capMsg');
        const csrf = document.querySelector('meta[name=csrf-token]')?.content;
        let cur = null; // { get, generate, save, btn }

        const open  = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
        const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); cur = null; };

        document.getElementById('capClose').addEventListener('click', close);
        document.getElementById('capCancel').addEventListener('click', close);
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

        document.querySelectorAll('.caption-btn').forEach(b => b.addEventListener('click', async () => {
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

        document.getElementById('capGenerate').addEventListener('click', async () => {
            if (!cur) return;
            const gb = document.getElementById('capGenerate');
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

        document.getElementById('capSave').addEventListener('click', async () => {
            if (!cur) return;
            const sb = document.getElementById('capSave');
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
