@extends('layouts.admin')
@section('title', 'New Story')

@section('content')
<div class="max-w-2xl">
    <a href="{{ route('admin.stories.index') }}" class="text-sm text-slate-500 hover:text-rose-700">← Back</a>
    <h2 class="text-xl font-bold mt-2 mb-6">New Story</h2>

    {{-- ✨ AI: Topic se poori kahani banao --}}
    <div class="bg-gradient-to-br from-violet-50 to-rose-50 border border-violet-200 rounded-xl p-5 mb-6">
        <h3 class="font-semibold flex items-center gap-2">✨ AI se Kahani banao (Topic se)</h3>
        <p class="text-sm text-slate-600 mt-1 mb-3">Sirf topic likho — AI poori Hindi kahani + title + description bana dega. Neeche form apne-aap bhar jayega, phir aap edit/Save kar sakte ho.</p>

        <div class="space-y-3">
            <input type="text" id="aiTopic"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none"
                   placeholder="e.g. Ek garib ladke aur jaadui deepak ki kahani">
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
            if (!topic) { msg.textContent = '⚠ Pehle topic likho.'; return; }

            btn.disabled = true;
            const label = btn.textContent;
            btn.textContent = '⏳ Kahani ban rahi hai…';
            msg.textContent = 'AI likh raha hai, thoda ruko…';

            try {
                const r = await fetch('{{ route('admin.stories.generate') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ topic, length: document.getElementById('aiLength').value }),
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
