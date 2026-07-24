@extends('layouts.admin')
@section('title', 'New Story')

@section('content')
<div class="max-w-2xl">
    <a href="{{ route('admin.stories.index') }}" class="text-sm text-slate-500 hover:text-rose-700">← Back</a>
    <h2 class="text-xl font-bold mt-2 mb-6">New Story</h2>

    {{-- ✨ AI: Topic se poori kahani banao --}}
    <div class="bg-gradient-to-br from-violet-50 to-rose-50 border border-violet-200 rounded-xl p-5 mb-6">
        <h3 class="font-semibold flex items-center gap-2">✨ AI se Kahani banao</h3>
        <p class="text-sm text-slate-600 mt-1 mb-3"><b>Type</b> chuno aur/ya <b>topic</b> likho — AI poori kahani + title + description bana dega (neeche <b>"Language"</b> wali bhasha me). Sirf type chuno to AI usi tarah ki nayi kahani khud likhega. Form apne-aap bhar jayega, phir edit/Save.</p>

        <div class="space-y-3">
            <div class="grid sm:grid-cols-2 gap-3">
                <select id="aiGenre" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">
                    <option value="">— Type / Genre (optional) —</option>
                    <option value="Moral / Naitik">📘 Moral (नैतिक)</option>
                    <option value="Motivational / Prerak">🔥 Motivational (प्रेरक)</option>
                    <option value="Love / Prem">❤️ Love (प्रेम)</option>
                    <option value="Emotional / Bhavuk">😢 Emotional (भावुक)</option>
                    <option value="Horror / Darawni">👻 Horror (डरावनी)</option>
                    <option value="Suspense / Rahasya">🕵️ Suspense (रहस्य)</option>
                    <option value="Comedy / Hasya">😂 Comedy (हास्य)</option>
                    <option value="Kids / Bachchon ki">🧒 Kids (बच्चों की)</option>
                    <option value="Fairy Tale / Pari katha">🧚 Fairy Tale (परी कथा)</option>
                    <option value="Mythology / Pauranik">🕉️ Mythology (पौराणिक)</option>
                    <option value="Bhagwan ki Katha / Devotional Bhakti">🙏 Bhagwan ki Katha (भगवान की कथा)</option>
                    <option value="Krishna Leela / Bhagwan Krishna">🪈 Krishna Leela (कृष्ण लीला)</option>
                    <option value="Ramayan / Bhagwan Ram">🏹 Ram Katha (राम कथा)</option>
                    <option value="Shiv / Mahadev Katha">🔱 Shiv Katha (शिव कथा)</option>
                    <option value="Hanuman / Bhakt katha">🐒 Hanuman Katha (हनुमान कथा)</option>
                    <option value="Panchatantra / Animal">🦊 Panchatantra (पंचतंत्र)</option>
                    <option value="Historical / Aitihasik">🏛️ Historical (ऐतिहासिक)</option>
                    <option value="Adventure / Romanchak">🗺️ Adventure (रोमांचक)</option>
                </select>
                <input type="text" id="aiTopic"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none"
                       placeholder="Topic (optional) — e.g. jaadui deepak">
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <select id="aiLength" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="short">Chhoti (Short/Reel ke liye — ~150 shabd)</option>
                    <option value="medium">Medium (~300 shabd)</option>
                    <option value="long">Lambi (~600 shabd)</option>
                    <option value="1000">~1000 shabd</option>
                    <option value="1500">~1500 shabd</option>
                    <option value="8000">~8000 shabd (bahut lambi)</option>
                    <option value="20000">~20000 shabd (novel-jaisi)</option>
                </select>
                <button type="button" id="aiGenerate"
                        class="bg-violet-600 hover:bg-violet-700 text-white font-medium rounded-lg px-5 py-2.5 text-sm">
                    ✨ Generate Story
                </button>
                <span id="aiMsg" class="text-sm text-slate-500"></span>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.stories.store') }}" enctype="multipart/form-data"
          class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
        @csrf
        @include('admin.stories._fields', ['story' => null])

        <button class="bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg px-5 py-2.5">
            Save Story
        </button>
    </form>
</div>

@push('scripts')
<script>
    (function () {
        const btn = document.getElementById('aiGenerate');
        const msg = document.getElementById('aiMsg');
        const csrf = document.querySelector('meta[name=csrf-token]')?.content;

        btn.addEventListener('click', async () => {
            const topic = document.getElementById('aiTopic').value.trim();
            const genre = document.getElementById('aiGenre').value;
            if (!topic && !genre) { msg.textContent = '⚠ Type chuno ya topic likho.'; return; }

            btn.disabled = true;
            const label = btn.textContent;
            btn.textContent = '⏳ Kahani ban rahi hai…';
            msg.textContent = 'AI likh raha hai, thoda ruko…';

            try {
                const r = await fetch('{{ route('admin.stories.generate') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        topic,
                        genre,
                        length: document.getElementById('aiLength').value,
                        language: document.getElementById('formLanguage')?.value || 'hindi',
                    }),
                });
                const d = await r.json();
                if (d.ok) {
                    // Form fields bhar do
                    document.querySelector('[name=title]').value = d.title || '';
                    document.querySelector('[name=description]').value = d.description || '';
                    document.querySelector('[name=body]').value = d.body || '';
                    msg.textContent = '✓ Ho gaya! Neeche kahani review karke Save karo.';
                    document.querySelector('[name=title]').scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    msg.textContent = '⚠ ' + (d.error || 'Kahani nahi bani.');
                }
            } catch (e) {
                msg.textContent = '⚠ Error aaya, dobara try karo.';
            }

            btn.disabled = false;
            btn.textContent = label;
        });
    })();
</script>
@endpush
@endsection
