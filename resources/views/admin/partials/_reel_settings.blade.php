{{-- Reel & auto-post settings for a story/collection. Needs $story. --}}
@php($sel = $story->platforms ?? [])
@php($isAll = empty($sel))
@php($mode = $story->tts_mode)
@php($voices = ['Kore'=>'Kore (female, firm)','Aoede'=>'Aoede (female, breezy)','Leda'=>'Leda (female, youthful)','Zephyr'=>'Zephyr (female, bright)','Puck'=>'Puck (male, upbeat)','Charon'=>'Charon (male, informative)','Fenrir'=>'Fenrir (male, lively)','Orus'=>'Orus (male, firm)'])

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="bg-gradient-to-r from-violet-50 to-rose-50 border-b border-slate-200 px-4 py-2.5">
        <h3 class="font-semibold text-sm flex items-center gap-2">🎬 Reel &amp; Auto-post Settings</h3>
    </div>

    <div class="p-4 space-y-4">
        {{-- Platforms (pill toggles) --}}
        <div id="platformsBox" data-url="{{ route('admin.stories.platforms', $story) }}">
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">📢 Auto-post to</label>
            <div class="flex items-center gap-2 flex-wrap">
                <label class="cursor-pointer">
                    <input type="checkbox" id="platAll" class="peer sr-only" @checked($isAll)>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-sm font-medium border-slate-300 text-slate-600 bg-white transition hover:border-emerald-400 peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600">✅ All</span>
                </label>
                <span class="text-slate-300">|</span>
                <label class="cursor-pointer">
                    <input type="checkbox" class="plat peer sr-only" value="instagram" @checked($isAll || in_array('instagram', $sel))>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-sm font-medium border-slate-300 text-slate-600 bg-white transition hover:border-pink-400 peer-checked:bg-pink-600 peer-checked:text-white peer-checked:border-pink-600">📸 Instagram</span>
                </label>
                <label class="cursor-pointer">
                    <input type="checkbox" class="plat peer sr-only" value="youtube" @checked($isAll || in_array('youtube', $sel))>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-sm font-medium border-slate-300 text-slate-600 bg-white transition hover:border-red-400 peer-checked:bg-red-600 peer-checked:text-white peer-checked:border-red-600">▶️ YouTube</span>
                </label>
                <label class="cursor-pointer">
                    <input type="checkbox" class="plat peer sr-only" value="facebook" @checked($isAll || in_array('facebook', $sel))>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-sm font-medium border-slate-300 text-slate-600 bg-white transition hover:border-blue-400 peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600">📘 Facebook</span>
                </label>
                <span id="platSaved" class="text-green-600 text-xs font-medium"></span>
            </div>
        </div>

        {{-- Audio mode + Voice --}}
        <div class="grid sm:grid-cols-2 gap-3 border-t border-slate-100 pt-4"
             data-url="{{ route('admin.stories.audiomode', $story) }}" id="audioBox">
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">🔊 Reel Audio</label>
                <select id="audioMode" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
                    <option value="">Default (global setting)</option>
                    <option value="voice" @selected($mode === 'voice')>🎙️ Voice only</option>
                    <option value="voice_music" @selected($mode === 'voice_music')>🎙️🎵 Voice + soft music</option>
                    <option value="music" @selected($mode === 'music')>🎵 Music only</option>
                </select>
            </div>
            <div id="voiceWrap">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">🎙️ Voice</label>
                <select id="voiceSel" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
                    <option value="">Default (global)</option>
                    @foreach ($voices as $val => $label)
                        <option value="{{ $val }}" @selected($story->tts_voice === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span id="audioSaved" class="text-green-600 text-xs font-medium"></span>
            <p class="text-xs text-slate-400">"Default" = aapki global voice-over setting. Voice tabhi aayegi jab Gemini TTS quota bacha ho.</p>
        </div>
    </div>
</div>

<script>
(function () {
    const CSRF = document.querySelector('meta[name=csrf-token]').content;

    // ---------- Platforms ----------
    const box = document.getElementById('platformsBox');
    if (box) {
        const all = document.getElementById('platAll');
        const plats = [...box.querySelectorAll('.plat')];
        const saved = document.getElementById('platSaved');

        async function savePlat() {
            const checked = plats.filter(c => c.checked).map(c => c.value);
            const platforms = (all.checked || checked.length === plats.length) ? [] : checked;
            saved.textContent = '…';
            try {
                const r = await fetch(box.dataset.url, {
                    method: 'PUT',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ platforms }),
                });
                saved.textContent = (await r.json()).ok ? '✓ saved' : '⚠ fail';
            } catch (e) { saved.textContent = '⚠ error'; }
            setTimeout(() => { saved.textContent = ''; }, 2500);
        }
        all.addEventListener('change', () => { plats.forEach(c => { c.checked = all.checked; }); savePlat(); });
        plats.forEach(c => c.addEventListener('change', () => { all.checked = plats.every(p => p.checked); savePlat(); }));
    }

    // ---------- Audio mode + Voice ----------
    const abox = document.getElementById('audioBox');
    if (abox) {
        const mode = document.getElementById('audioMode');
        const voice = document.getElementById('voiceSel');
        const wrap = document.getElementById('voiceWrap');
        const saved = document.getElementById('audioSaved');

        function syncVoice() {
            const off = mode.value === 'music';
            voice.disabled = off;
            wrap.style.opacity = off ? '0.4' : '1';
        }
        async function saveAudio() {
            saved.textContent = '…';
            try {
                const r = await fetch(abox.dataset.url, {
                    method: 'PUT',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ tts_mode: mode.value, tts_voice: voice.value }),
                });
                saved.textContent = (await r.json()).ok ? '✓ saved' : '⚠ fail';
            } catch (e) { saved.textContent = '⚠ error'; }
            setTimeout(() => { saved.textContent = ''; }, 2500);
        }
        mode.addEventListener('change', () => { syncVoice(); saveAudio(); });
        voice.addEventListener('change', saveAudio);
        syncVoice();
    }
})();
</script>
