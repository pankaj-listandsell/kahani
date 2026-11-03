{{-- 👥 Followers ka roz ka badhaav. Needs $followers (FollowerTracker::summary). $chart = true → 30 din ka graph bhi. --}}
@php
    $fPlatforms = [
        'ig' => ['📸', 'Instagram', 'followers', 'bg-pink-500'],
        'yt' => ['▶️', 'YouTube', 'subscribers', 'bg-red-500'],
        'fb' => ['📘', 'Facebook', 'followers', 'bg-blue-500'],
    ];
    $sign = fn ($v) => $v === null ? '—' : (($v > 0 ? '+' : '') . number_format($v));
    $tone = fn ($v) => $v === null ? 'text-slate-400' : ($v > 0 ? 'text-emerald-600' : ($v < 0 ? 'text-rose-600' : 'text-slate-500'));
    $hasAny = collect($followers)->contains(fn ($f) => $f['total'] !== null);
@endphp

<div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
    <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
        <h2 class="font-semibold text-slate-800 flex items-center gap-2">👥 Aaj kitne follow aaye</h2>
        <form method="POST" action="{{ route('admin.followers.refresh') }}"
              onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='⏳ Check ho raha hai…'">
            @csrf
            <button class="text-xs font-semibold text-violet-700 bg-violet-50 hover:bg-violet-100 border border-violet-200 px-3 py-1.5 rounded-lg transition">🔄 Abhi check karo</button>
        </form>
    </div>

    @if (! $hasAny)
        <p class="text-sm text-slate-500">
            Abhi tak ginti nahi aayi. "Abhi check karo" dabao — uske baad har ghante apne aap update hoga.
            Aaj ka badhaav pehli ginti ke baad se gina jaata hai, isliye pehle din number chhota dikh sakta hai.
        </p>
    @else
        <div class="grid sm:grid-cols-3 gap-4">
            @foreach ($fPlatforms as $p => [$icon, $name, $unit, $bar])
                @php($f = $followers[$p])
                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">
                        <span>{{ $icon }} {{ $name }}</span>
                        @if ($f['total'] !== null)
                            <span class="normal-case font-semibold text-slate-400">{{ number_format($f['total']) }} {{ $unit }}</span>
                        @endif
                    </div>
                    @if ($f['total'] === null)
                        <p class="text-sm text-slate-400 mt-2">Connect nahi hai ya ginti nahi mili.</p>
                    @else
                        <p class="text-3xl font-extrabold {{ $tone($f['today']) }}">{{ $sign($f['today']) }} <span class="text-sm font-semibold text-slate-400">aaj</span></p>
                        <p class="text-xs text-slate-500 mt-1">
                            Kal: <b class="{{ $tone($f['yesterday']) }}">{{ $sign($f['yesterday']) }}</b>
                            &nbsp;•&nbsp; 7 din: <b class="{{ $tone($f['week']) }}">{{ $sign($f['week']) }}</b>
                        </p>
                    @endif
                </div>
            @endforeach
        </div>

        @if (! empty($chart))
            <div class="mt-6 space-y-5">
                @foreach ($fPlatforms as $p => [$icon, $name, $unit, $bar])
                    @php($days = $followers[$p]['days'])
                    @php($vals = collect($days)->filter(fn ($v) => $v !== null))
                    @continue($vals->isEmpty())
                    @php($max = max(1, $vals->map(fn ($v) => abs($v))->max()))
                    <div>
                        <p class="text-xs font-bold text-slate-600 mb-2">{{ $icon }} {{ $name }} — pichhle {{ count($days) }} din ka roz ka badhaav</p>
                        <div class="flex items-end gap-[3px] h-28 border-b border-slate-200">
                            @foreach ($days as $date => $v)
                                @php($h = $v === null ? 0 : max(3, round(abs($v) / $max * 100)))
                                <div class="flex-1 h-full flex items-end" title="{{ \Illuminate\Support\Carbon::parse($date)->format('d M') }}: {{ $sign($v) }}">
                                    <div class="w-full rounded-t {{ $v === null ? 'bg-slate-100' : ($v < 0 ? 'bg-rose-400' : $bar) }}"
                                         style="height: {{ $v === null ? 3 : $h }}%"></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex justify-between text-[10px] text-slate-400 mt-1">
                            <span>{{ \Illuminate\Support\Carbon::parse(array_key_first($days))->format('d M') }}</span>
                            <span>Aaj</span>
                        </div>
                    </div>
                @endforeach
                <p class="text-xs text-slate-400">
                    Bar par mouse rakho to us din ka number dikhega. Laal bar = us din unfollow zyada hue.
                    YouTube 1000+ subscribers par ginti round karke deta hai, isliye wahan chhota badhaav kabhi-kabhi 0 dikhega.
                </p>
            </div>
        @endif
    @endif
</div>
