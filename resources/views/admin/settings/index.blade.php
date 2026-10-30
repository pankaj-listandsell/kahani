@extends('layouts.admin')
@section('title', 'Settings')

@section('content')
<div class="max-w-2xl space-y-6">

    {{-- Profile --}}
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h2 class="font-semibold mb-4">👤 Profile</h2>
        <form method="POST" action="{{ route('admin.settings.profile') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium mb-1">Name</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Email (login)</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none">
            </div>
            <button class="bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg px-5 py-2.5">Save Profile</button>
        </form>
    </div>

    {{-- Password --}}
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h2 class="font-semibold mb-4">🔒 Change Password</h2>
        <form method="POST" action="{{ route('admin.settings.password') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium mb-1">Current Password</label>
                <input type="password" name="current_password" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">New Password</label>
                <input type="password" name="password" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Confirm New Password</label>
                <input type="password" name="password_confirmation" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none">
            </div>
            <button class="bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg px-5 py-2.5">Change Password</button>
        </form>
    </div>

    {{-- Quiz caption --}}
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h2 class="font-semibold mb-1">🎯 Quiz Caption (default)</h2>
        <p class="text-sm text-slate-500 mb-4">
            Yahan kuch likhoge to har quiz post par <b>yahi</b> caption jaayega.
            Khaali chhod do to AI har sawaal ke liye alag hook line banayega.
            Hashtags dono case me apne aap neeche jud jaate hain.
        </p>
        <form method="POST" action="{{ route('admin.settings.quizcaption') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <textarea name="quiz_caption" rows="5" maxlength="1000"
                      placeholder="&#10;🎯 રોજ એક નવો GK સવાલ&#10;&#10;👇 તમારો જવાબ કોમેન્ટમાં લખો&#10;🔔 Follow for daily GK — GPSC | Talati | Bin Sachivalay"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 focus:outline-none">{{ \App\Models\Setting::getFor(auth()->id(), 'quiz_caption', '') }}</textarea>
            <p class="text-xs text-slate-400">
                ⚠️ Sawaal aur jawab caption me jaan-boojh kar nahi jaate — jawab reel ke andar
                dikhta hai, taaki log guess karke comment karein.
            </p>
            <button class="bg-violet-600 hover:bg-violet-700 text-white font-medium rounded-lg px-5 py-2.5">Save Caption</button>
        </form>
    </div>

    {{-- Reel end "Follow karo" outro --}}
    @php
        $uid = auth()->id();
        $o = fn ($k, $d = '') => \App\Models\Setting::getFor($uid, 'reel_outro_' . $k, $d);
        $outroImg = $o('image') ? \Illuminate\Support\Facades\Storage::disk('public')->url($o('image')) : null;
    @endphp
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h2 class="font-semibold mb-1">🔔 Reel ke end me "Follow karo"</h2>
        <p class="text-sm text-slate-500 mb-4">
            ON karoge to har nayi reel (Instagram, Facebook, YouTube) ke aakhir me ye card 2-3 second dikhega,
            voice line ke saath. Reel dekhne wale ko seedha follow karne ka ishara milta hai.
        </p>

        <div class="grid sm:grid-cols-2 gap-5">
            <div class="space-y-3">
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" id="oEnabled" class="rounded" @checked($o('enabled', '0') === '1')> Outro ON
                </label>
                <div>
                    <label class="block text-xs font-medium mb-1">Badi line</label>
                    <input id="oHeadline" maxlength="120" value="{{ $o('headline', 'આવી વાતો રોજ જોવી છે?') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Chhoti line</label>
                    <input id="oSub" maxlength="120" value="{{ $o('sub', 'અત્યારે જ Follow કરો 👇') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Page ka naam / handle</label>
                    <input id="oHandle" maxlength="60" value="{{ $o('handle') }}" placeholder="@aapka.page"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Voice line (AI bolega — khaali = bina voice)</label>
                    <input id="oVoice" maxlength="200" value="{{ $o('voice', 'આવી વધુ વાતો માટે, અત્યારે જ ફોલો કરો!') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1">Kitne second (voice lambi ho to apne aap badhega)</label>
                    <select id="oSeconds" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach (['2.5', '3', '4', '5'] as $s)
                            <option value="{{ $s }}" @selected($o('seconds', '3') === $s)>{{ $s }} sec</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" id="oSave" class="bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg px-5 py-2.5">Save Outro</button>
                <p id="oMsg" class="text-sm"></p>
            </div>
            <div>
                <canvas id="oCanvas" width="1080" height="1920" class="w-full max-w-[240px] mx-auto rounded-lg border border-slate-200"></canvas>
                @if ($outroImg)
                    <p class="text-xs text-slate-400 text-center mt-2">Saved: <a href="{{ $outroImg }}" target="_blank" class="underline">abhi wala card</a></p>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
@include('admin.partials._canvas_kit')
<script>
(() => {
    const K = window.CanvasKit;
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const $ = id => document.getElementById(id);
    const cv = $('oCanvas'), ctx = cv.getContext('2d');
    const EMOJI = '"Segoe UI Emoji", "Apple Color Emoji", "Noto Color Emoji"';

    function roundRect(x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    async function draw() {
        await K.ensureFonts();
        K.reset(ctx);
        const W = 1080, H = 1920;

        const g = ctx.createLinearGradient(0, 0, W, H);
        g.addColorStop(0, '#4c1d95');
        g.addColorStop(0.55, '#be185d');
        g.addColorStop(1, '#f59e0b');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);

        // halke gol dhabbe
        ctx.fillStyle = 'rgba(255,255,255,0.07)';
        [[160, 260, 220], [930, 560, 260], [180, 1650, 280], [960, 1700, 160]].forEach(([x, y, r]) => {
            ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.fill();
        });

        // bell
        ctx.fillStyle = 'rgba(255,255,255,0.18)';
        ctx.beginPath(); ctx.arc(W / 2, 520, 150, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = '#ffffff';
        ctx.font = `150px ${EMOJI}`;
        ctx.fillText('🔔', W / 2, 530);

        // badi line
        ctx.fillStyle = '#ffffff';
        ctx.shadowColor = 'rgba(0,0,0,0.35)'; ctx.shadowBlur = 18;
        const head = K.fit(ctx, $('oHeadline').value, { maxW: 900, maxH: 330, size: 96, minSize: 54, lineHeight: 1.3 });
        K.drawLines(ctx, head.lines, W / 2, 860, head.size * 1.3);
        ctx.shadowBlur = 0;

        // chhoti line
        ctx.fillStyle = '#fde68a';
        const sub = K.fit(ctx, $('oSub').value, { maxW: 900, maxH: 160, size: 62, minSize: 40, lineHeight: 1.3 });
        K.drawLines(ctx, sub.lines, W / 2, 1150, sub.size * 1.3);

        // Follow button
        ctx.shadowColor = 'rgba(0,0,0,0.3)'; ctx.shadowBlur = 30;
        ctx.fillStyle = '#ffffff';
        roundRect(W / 2 - 300, 1300, 600, 150, 75);
        ctx.fill();
        ctx.shadowBlur = 0;
        ctx.fillStyle = '#be185d';
        ctx.font = 'bold 76px "Segoe UI", Arial, sans-serif';
        ctx.fillText('+ FOLLOW', W / 2, 1378);

        // pointing hand
        ctx.font = `110px ${EMOJI}`;
        ctx.fillText('👆', W / 2 + 250, 1530);

        // handle
        const handle = $('oHandle').value.trim();
        if (handle) {
            ctx.fillStyle = '#ffffff';
            K.fitOneLine(ctx, handle, 900, 58, 32);
            ctx.fillText(handle, W / 2, 1680);
        }
    }

    ['oHeadline', 'oSub', 'oHandle'].forEach(id => $(id).addEventListener('input', draw));
    draw();

    $('oSave').addEventListener('click', async () => {
        const btn = $('oSave'), msg = $('oMsg');
        btn.disabled = true; msg.textContent = 'Save ho raha hai…'; msg.className = 'text-sm text-slate-500';
        try {
            await draw();
            const r = await fetch(@json(route('admin.settings.reeloutro')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({
                    enabled: $('oEnabled').checked,
                    headline: $('oHeadline').value, sub: $('oSub').value, handle: $('oHandle').value,
                    voice: $('oVoice').value, seconds: $('oSeconds').value,
                    image: cv.toDataURL('image/png'),
                }),
            });
            const d = await r.json();
            if (!d.ok) throw new Error(K.saveError(d));
            msg.textContent = $('oEnabled').checked ? '✅ Save ho gaya — ab nayi reels ke end me ye dikhega.' : '✅ Save ho gaya (outro OFF hai).';
            msg.className = 'text-sm text-emerald-600';
        } catch (e) {
            msg.textContent = '❌ ' + e.message; msg.className = 'text-sm text-rose-600';
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>
@endpush
