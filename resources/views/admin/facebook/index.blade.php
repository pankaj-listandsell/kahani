@extends('layouts.admin')
@section('title', 'Facebook')

@section('content')
<div class="max-w-4xl">

    {{-- ================= SUB-MENU TABS ================= --}}
    <div class="flex gap-1 border-b border-slate-200 mb-6 overflow-x-auto">
        <button type="button" class="fb-tab whitespace-nowrap px-4 py-2.5 text-sm border-b-2 border-transparent -mb-px" data-tab="manual">
            🚀 Manual Post
        </button>
        <button type="button" class="fb-tab whitespace-nowrap px-4 py-2.5 text-sm border-b-2 border-transparent -mb-px" data-tab="settings">
            ⚙️ Settings (Connection + Auto Post)
        </button>
    </div>

    {{-- ================= SETTINGS: CONNECTION ================= --}}
    <div data-panel="settings" class="fb-panel space-y-6 hidden">
        <div class="bg-white rounded-xl border border-slate-200 p-5 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
                <span class="text-2xl">📘</span>
                <div>
                    <p class="font-semibold">Facebook Page Connection</p>
                    @if ($configured)
                        <p class="text-sm text-green-600">✓ Configured — ready to post</p>
                    @else
                        <p class="text-sm text-amber-600">⚠️ Not configured — Page ID + token daalo</p>
                    @endif
                </div>
            </div>
            <form method="POST" action="{{ route('admin.facebook.test') }}">@csrf
                <button class="text-sm border border-slate-300 rounded-lg px-4 py-2 hover:bg-slate-50">Test Connection</button>
            </form>
        </div>

        <details class="bg-blue-50 border border-blue-200 rounded-xl p-5 text-sm text-blue-900">
            <summary class="font-semibold cursor-pointer">📖 Page ID + token kaise laayein (setup)</summary>
            <ol class="list-decimal list-inside mt-3 space-y-1.5">
                <li>Ek Facebook <b>Page</b> ho (aap uske admin ho).</li>
                <li><a href="https://developers.facebook.com/tools/explorer" target="_blank" class="underline">Graph API Explorer</a> me apna app choose karo.</li>
                <li>Permissions add karo: <code class="bg-white px-1 rounded">pages_manage_posts</code>, <code class="bg-white px-1 rounded">pages_read_engagement</code>, <code class="bg-white px-1 rounded">pages_show_list</code>.</li>
                <li>Token generate karo, phir <code class="bg-white px-1 rounded">/me/accounts</code> se apni Page ka <b>Page ID</b> + <b>Page access token</b> lo (long-lived behtar).</li>
                <li>Neeche paste karke Save karo, phir Test Connection.</li>
            </ol>
            <p class="mt-3 text-green-800">✓ Reel ka video Instagram wala hi (720x1280, voice/music) reuse hota hai — koi extra setup nahi.</p>
        </details>

        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h3 class="font-semibold mb-4">⚙️ Connection Settings</h3>
            <form method="POST" action="{{ route('admin.facebook.settings') }}" class="space-y-4">
                @csrf @method('PUT')
                <div>
                    <label class="block text-sm font-medium mb-1">Page ID</label>
                    <input type="text" name="fb_page_id" value="{{ old('fb_page_id', $settings['fb_page_id']) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:outline-none" placeholder="e.g. 1234567890">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Page Access Token</label>
                    <textarea name="fb_page_token" rows="3"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:ring-2 focus:ring-blue-400 focus:outline-none"
                              placeholder="EAA...">{{ old('fb_page_token', $settings['fb_page_token']) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Default Caption Suffix (hashtags — optional)</label>
                    <textarea name="fb_caption_suffix" rows="2"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:outline-none"
                              placeholder="#hindistory #kahani">{{ old('fb_caption_suffix', $settings['fb_caption_suffix']) }}</textarea>
                    <p class="text-xs text-slate-500 mt-1">Caption Instagram wali (per-card) hi reuse hoti hai — yahan sirf default hashtags.</p>
                </div>
                <button class="bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg px-5 py-2.5">Save Settings</button>
            </form>
        </div>
    </div>

    {{-- ================= SETTINGS: AUTO POST ================= --}}
    <div data-panel="settings" class="fb-panel space-y-6 hidden">
        <form method="POST" action="{{ route('admin.facebook.autopost') }}" id="fbAutoForm"
              class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            @csrf @method('PUT')

            <div class="bg-blue-50 border-b border-blue-100 px-6 py-4">
                <h3 class="font-semibold flex items-center gap-2">🕒 Auto Post (Automatic Uploading)</h3>
            </div>

            <div class="p-6 space-y-5">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="fb_auto_enabled" value="1" id="fbAutoEnabled" class="sr-only peer" @checked($settings['fb_auto_enabled'] === '1')>
                            <span class="relative w-11 h-6 bg-slate-300 rounded-full peer-checked:bg-green-500 transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition peer-checked:after:translate-x-5"></span>
                            <span class="font-medium">Enable Auto Post</span>
                        </label>
                        <p class="text-sm text-slate-500 mt-1">ON hone par cards apne-aap Facebook Page par post honge — sirf time windows me.</p>
                    </div>
                    <div id="fbAutoStatus" class="rounded-lg border px-4 py-2 text-sm"></div>
                </div>

                <div class="flex items-center gap-3">
                    <label class="text-sm font-medium">Post as:</label>
                    <select name="fb_post_type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="image" @selected($settings['fb_post_type'] === 'image')>Photo (image)</option>
                        <option value="reel" @selected($settings['fb_post_type'] === 'reel')>Reel (video)</option>
                    </select>
                </div>

                <div class="border-t pt-4">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <h4 class="font-semibold flex items-center gap-2">🗓️ Time Windows</h4>
                            <p class="text-sm text-slate-500">Start, end &amp; interval — post sirf inhi me hoga.</p>
                        </div>
                        <button type="button" id="fbAddWindow" class="text-sm border border-blue-300 text-blue-700 rounded-lg px-3 py-1.5 hover:bg-blue-50">＋ Add Window</button>
                    </div>
                    <div id="fbWindows" class="space-y-3"></div>
                </div>

                <div class="bg-slate-50 border border-slate-200 rounded-lg p-3">
                    <p class="text-sm font-medium mb-2">⚡ Quick Presets</p>
                    <div class="flex flex-wrap gap-2 text-sm">
                        <button type="button" class="fb-preset border border-blue-300 text-blue-700 rounded-lg px-3 py-1.5 hover:bg-blue-100" data-preset="best">Best (12-2, 6-8, 8-11 PM)</button>
                        <button type="button" class="fb-preset border border-slate-300 rounded-lg px-3 py-1.5 hover:bg-slate-100" data-preset="evening">Evening (6-11 PM)</button>
                        <button type="button" class="fb-preset border border-slate-300 rounded-lg px-3 py-1.5 hover:bg-slate-100" data-preset="allday">All Day (8 AM-11 PM)</button>
                        <button type="button" class="fb-preset border border-red-300 text-red-600 rounded-lg px-3 py-1.5 hover:bg-red-50" data-preset="clear">✕ Clear All</button>
                    </div>
                </div>

                <button class="bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg px-5 py-2.5">Save Auto-Post Settings</button>
                <p class="text-xs text-slate-500">Auto-post ke liye background me <code>php artisan schedule:work</code> chalna chahiye. Time server timezone me ({{ config('app.timezone') }}).</p>
            </div>
        </form>

        <template id="fbWindowTemplate">
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
                        <option value="30" selected>30 min</option>
                        <option value="60">60 min</option>
                        <option value="120">120 min</option>
                    </select>
                </div>
                <div class="col-span-8 sm:col-span-2">
                    <span class="est inline-block bg-blue-500 text-white text-xs rounded-full px-3 py-1">~0</span>
                </div>
                <div class="col-span-4 sm:col-span-1 text-right">
                    <button type="button" class="remove text-red-500 hover:text-red-700 border border-red-200 rounded-lg px-2 py-2">🗑</button>
                </div>
            </div>
        </template>
    </div>

    {{-- ================= MANUAL POST ================= --}}
    <div data-panel="manual" class="fb-panel">
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h3 class="font-semibold mb-1">🚀 Post Manually</h3>
            <p class="text-sm text-slate-500 mb-4">
                Har card ko <b>Photo</b> ya <b>Reel</b> ki tarah Facebook Page par post karo. Caption Instagram wali hi (per-card) use hoti hai.
            </p>

            @if (! $configured)
                <p class="text-amber-600 text-sm mb-4">⚠️ Pehle Settings tab me Page ID + token daalo.</p>
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
                                            <div class="flex gap-2">
                                                <form method="POST" action="{{ route('admin.instagram.part.captions', $part) }}" class="fb-form" onsubmit="return confirm('Is part ke sabhi cards ke liye AI caption banayein?')">
                                                    @csrf
                                                    <button class="text-xs bg-sky-600 hover:bg-sky-700 text-white rounded-lg px-3 py-1.5">✨ All Captions</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.facebook.part.photos', $part) }}" class="fb-form" onsubmit="return confirm('Post all cards as PHOTOS?')">
                                                    @csrf
                                                    <button @disabled(!$configured) class="text-xs bg-blue-600 hover:bg-blue-700 disabled:bg-slate-300 text-white rounded-lg px-3 py-1.5">All as Photos</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.facebook.part.reels', $part) }}" class="fb-form" onsubmit="return confirm('Post all cards as REELS? Thoda time lagega.')">
                                                    @csrf
                                                    <button @disabled(!$configured) class="text-xs bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-300 text-white rounded-lg px-3 py-1.5">All as Reels</button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="flex gap-3 overflow-x-auto pb-1">
                                            @foreach ($part->cards as $card)
                                                <div class="shrink-0 text-center w-28">
                                                    @if ($card->isFbPosted())
                                                        <div class="h-28 w-28 flex flex-col items-center justify-center rounded-lg border border-green-200 bg-green-50 text-green-600">
                                                            <span class="text-2xl">✓</span>
                                                            <span class="text-[10px] mt-1">Posted</span>
                                                        </div>
                                                        <span class="block text-[11px] text-green-600 mt-1">✓ Facebook</span>
                                                        @if ($card->fb_posted_at)
                                                            <span class="block text-[10px] text-slate-400 leading-tight">🕒 {{ $card->fb_posted_at->format('d M, h:i A') }}</span>
                                                        @endif
                                                    @else
                                                        <img src="{{ asset('storage/' . $card->image_path) }}" class="h-28 w-28 object-cover rounded-lg border border-slate-200" alt="">
                                                        <div class="flex gap-1 mt-1 justify-center">
                                                            <form method="POST" action="{{ route('admin.facebook.card.photo', $card) }}" class="fb-form">
                                                                @csrf
                                                                <button @disabled(!$configured) class="text-[11px] bg-blue-50 text-blue-700 border border-blue-200 rounded px-2 py-0.5 hover:bg-blue-100 disabled:opacity-40">Photo</button>
                                                            </form>
                                                            <form method="POST" action="{{ route('admin.facebook.card.reel', $card) }}" class="fb-form">
                                                                @csrf
                                                                <button @disabled(!$configured) class="text-[11px] bg-indigo-50 text-indigo-700 border border-indigo-200 rounded px-2 py-0.5 hover:bg-indigo-100 disabled:opacity-40">Reel</button>
                                                            </form>
                                                        </div>
                                                        <button type="button"
                                                            class="fb-caption-btn mt-1 w-full text-[11px] border rounded px-2 py-0.5 hover:bg-slate-50 {{ filled($card->ig_caption) ? 'bg-amber-50 text-amber-700 border-amber-200' : 'text-slate-500 border-slate-200' }}"
                                                            data-get="{{ route('admin.instagram.card.caption.get', $card) }}"
                                                            data-generate="{{ route('admin.instagram.card.caption.generate', $card) }}"
                                                            data-save="{{ route('admin.instagram.card.caption.save', $card) }}">
                                                            {{ filled($card->ig_caption) ? '📝 Caption' : '✨ Caption' }}
                                                        </button>
                                                        @if ($card->fb_status === 'failed')
                                                            <span class="block text-[10px] font-semibold text-red-500 mt-0.5">failed</span>
                                                            @if (filled($card->fb_error))
                                                                <span class="block text-[9px] text-red-400 leading-tight mt-0.5" title="{{ $card->fb_error }}">{{ Str::limit($card->fb_error, 90) }}</span>
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

{{-- ================= AI CAPTION MODAL (Instagram caption reuse) ================= --}}
<div id="fbCapModal" class="fixed inset-0 z-40 hidden items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-xl w-full max-w-lg shadow-xl">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-200">
            <h3 class="font-semibold">✨ Caption (Instagram + Facebook shared)</h3>
            <button type="button" id="fbCapClose" class="text-slate-400 hover:text-slate-700 text-xl leading-none">&times;</button>
        </div>
        <div class="p-5 space-y-3">
            <textarea id="fbCapText" rows="8"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:outline-none"
                      placeholder="Yahan caption aayegi… ✨ AI se banao ya khud likho."></textarea>
            <p id="fbCapMsg" class="text-xs text-slate-500"></p>
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <button type="button" id="fbCapGenerate" class="text-sm bg-sky-600 hover:bg-sky-700 text-white rounded-lg px-4 py-2">✨ Generate with AI</button>
                <div class="flex gap-2">
                    <button type="button" id="fbCapCancel" class="text-sm text-slate-500 hover:underline px-3 py-2">Cancel</button>
                    <button type="button" id="fbCapSave" class="text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-4 py-2">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Tabs
    const fbTabs = document.querySelectorAll('.fb-tab');
    const fbPanels = document.querySelectorAll('.fb-panel');
    function showFbTab(name) {
        fbPanels.forEach(p => p.classList.toggle('hidden', p.dataset.panel !== name));
        fbTabs.forEach(t => {
            const on = t.dataset.tab === name;
            t.classList.toggle('border-blue-600', on);
            t.classList.toggle('text-blue-700', on);
            t.classList.toggle('font-semibold', on);
            t.classList.toggle('border-transparent', !on);
            t.classList.toggle('text-slate-500', !on);
        });
        try { localStorage.setItem('fbTab', name); } catch (e) {}
    }
    fbTabs.forEach(t => t.addEventListener('click', () => showFbTab(t.dataset.tab)));
    let fbInitial = 'manual';
    try { fbInitial = localStorage.getItem('fbTab') || 'manual'; } catch (e) {}
    if (![...fbTabs].some(t => t.dataset.tab === fbInitial)) fbInitial = 'manual';
    showFbTab(fbInitial);

    // Time windows
    const wrap = document.getElementById('fbWindows');
    if (wrap) {
        const savedWindows = @json($settings['fb_auto_windows']);
        const tpl = document.getElementById('fbWindowTemplate');
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
            const iv = parseInt(node.querySelector('[data-k=interval]').value, 10) || 30;
            let n = 0;
            if (s !== null && e !== null && e >= s) n = Math.floor((e - s) / iv) + 1;
            node.querySelector('.est').textContent = '~' + n;
            updateStatus();
        }
        function updateStatus() {
            const on = document.getElementById('fbAutoEnabled').checked;
            const count = wrap.querySelectorAll('.window-row').length;
            const box = document.getElementById('fbAutoStatus');
            if (on) {
                box.className = 'rounded-lg border px-4 py-2 text-sm bg-green-50 border-green-200 text-green-700';
                box.innerHTML = '✓ <b>Auto Post: ON</b><br><span class="text-xs">' + count + ' window(s)</span>';
            } else {
                box.className = 'rounded-lg border px-4 py-2 text-sm bg-slate-50 border-slate-200 text-slate-500';
                box.innerHTML = '○ Auto Post: OFF';
            }
        }
        const PRESETS = {
            best:    [{start:'12:00',end:'14:00',interval:30},{start:'18:00',end:'20:00',interval:30},{start:'20:00',end:'23:00',interval:30}],
            evening: [{start:'18:00',end:'23:00',interval:30}],
            allday:  [{start:'08:00',end:'23:00',interval:30}],
            clear:   [],
        };
        document.getElementById('fbAddWindow').addEventListener('click', () => addRow({interval:30}));
        document.getElementById('fbAutoEnabled').addEventListener('change', updateStatus);
        document.querySelectorAll('.fb-preset').forEach(b => b.addEventListener('click', () => {
            wrap.innerHTML = '';
            (PRESETS[b.dataset.preset] || []).forEach(addRow);
            updateStatus();
        }));
        (savedWindows && savedWindows.length ? savedWindows : []).forEach(addRow);
        updateStatus();
    }

    // Manual buttons loading
    document.querySelectorAll('.fb-form').forEach(form => form.addEventListener('submit', () => {
        const btn = form.querySelector('button');
        if (btn) { btn.disabled = true; btn.textContent = '⏳...'; }
    }));

    // Caption modal (uses Instagram caption routes → ig_caption, shared)
    (function () {
        const modal = document.getElementById('fbCapModal');
        if (!modal) return;
        const txt = document.getElementById('fbCapText');
        const msg = document.getElementById('fbCapMsg');
        const csrf = document.querySelector('meta[name=csrf-token]')?.content;
        let cur = null;
        const open  = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
        const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); cur = null; };
        document.getElementById('fbCapClose').addEventListener('click', close);
        document.getElementById('fbCapCancel').addEventListener('click', close);
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
        document.querySelectorAll('.fb-caption-btn').forEach(b => b.addEventListener('click', async () => {
            cur = { get: b.dataset.get, generate: b.dataset.generate, save: b.dataset.save, btn: b };
            txt.value = ''; msg.textContent = 'Loading…'; open();
            try {
                const r = await fetch(cur.get, { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                txt.value = d.caption || '';
                msg.textContent = d.caption ? '' : 'Abhi koi caption nahi — ✨ AI se banao ya khud likho.';
            } catch (e) { msg.textContent = 'Caption load nahi hui.'; }
        }));
        document.getElementById('fbCapGenerate').addEventListener('click', async () => {
            if (!cur) return;
            const gb = document.getElementById('fbCapGenerate');
            gb.disabled = true; gb.textContent = '⏳ Ban rahi hai…'; msg.textContent = '';
            try {
                const r = await fetch(cur.generate, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
                const d = await r.json();
                if (d.ok) { txt.value = d.caption; msg.textContent = '✓ AI caption ban gayi — Save karna na bhoolein.'; markHas(); }
                else { msg.textContent = '⚠ ' + (d.error || 'Caption nahi bani.'); }
            } catch (e) { msg.textContent = '⚠ Error aaya, dobara try karein.'; }
            gb.disabled = false; gb.textContent = '✨ Generate with AI';
        });
        document.getElementById('fbCapSave').addEventListener('click', async () => {
            if (!cur) return;
            const sb = document.getElementById('fbCapSave');
            sb.disabled = true; sb.textContent = 'Saving…';
            try {
                const r = await fetch(cur.save, {
                    method: 'PUT',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ caption: txt.value }),
                });
                const d = await r.json();
                if (d.ok) { markHas(); close(); } else { msg.textContent = '⚠ Save nahi hui.'; }
            } catch (e) { msg.textContent = '⚠ Save me error.'; }
            sb.disabled = false; sb.textContent = 'Save';
        });
    })();
</script>
@endpush
@endsection
