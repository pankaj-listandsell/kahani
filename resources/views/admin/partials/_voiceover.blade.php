{{-- Voice-over (AI TTS) settings — Instagram + YouTube dono me include hota hai.
     Keys shared hain (tts_audio_mode, tts_voice). Expects $settings. --}}
<div class="border-t pt-4">
    <h4 class="font-semibold flex items-center gap-2">🎙️ Voice-over (AI)</h4>
    <p class="text-sm text-slate-500 mb-3">
        Card ke text se Hindi voice-over banta hai (Google AI Studio / Gemini TTS).
        Har card apni narration ki length tak dikhta hai.
    </p>

    @if (empty($settings['tts_configured']))
        <p class="text-sm text-amber-600 mb-3">⚠️ <code>GEMINI_API_KEY</code> .env me set nahi — voice tab tak band rahega. Free key: <a href="https://aistudio.google.com/apikey" target="_blank" class="underline">aistudio.google.com/apikey</a></p>
    @endif

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Audio Mode</label>
            <select name="tts_audio_mode" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="music" @selected(($settings['tts_audio_mode'] ?? 'music') === 'music')>🎵 Music only (default)</option>
                <option value="voice" @selected(($settings['tts_audio_mode'] ?? '') === 'voice')>🎙️ Voice only</option>
                <option value="voice_music" @selected(($settings['tts_audio_mode'] ?? '') === 'voice_music')>🎙️ + 🎵 Voice + soft music</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Voice</label>
            <select name="tts_voice" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @php $voices = ['Kore'=>'Kore (female, firm)','Aoede'=>'Aoede (female, breezy)','Leda'=>'Leda (female, youthful)','Zephyr'=>'Zephyr (female, bright)','Puck'=>'Puck (male, upbeat)','Charon'=>'Charon (male, informative)','Fenrir'=>'Fenrir (male, lively)','Orus'=>'Orus (male, firm)']; @endphp
                @foreach ($voices as $val => $label)
                    <option value="{{ $val }}" @selected(($settings['tts_voice'] ?? 'Kore') === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <p class="text-xs text-slate-500 mt-2">⚠️ Voice-over sirf un cards par lagega jinka text save hai (naye cards). Purane cards ke liye card editor se dobara "generate" karo.</p>
</div>
