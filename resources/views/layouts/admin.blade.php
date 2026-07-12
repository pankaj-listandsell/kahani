<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Kahani Admin</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;600;700&family=Noto+Serif+Devanagari:wght@400;500;600;700&family=Noto+Sans+Gujarati:wght@400;500;600;700&family=Noto+Serif+Gujarati:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans Devanagari', sans-serif; }
        .kahani-text { font-family: 'Noto Serif Devanagari', serif; line-height: 2.1; }
        .nav-link.active { background: rgba(255,255,255,.15); color: #fff; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800">

@php
    $nav = [
        ['route' => 'admin.dashboard',      'active' => 'admin.dashboard',   'icon' => '🏠', 'label' => 'Dashboard'],
        ['route' => 'admin.stories.index',  'active' => 'admin.stories.*',   'icon' => '📚', 'label' => 'Stories'],
        ['route' => 'admin.studio.index',   'active' => 'admin.studio.*',    'icon' => '✨', 'label' => 'Shayari & Jokes'],
        ['route' => 'admin.quiz.index',     'active' => 'admin.quiz.*',      'icon' => '🎯', 'label' => 'Quiz'],
        ['route' => 'admin.instagram.index','active' => 'admin.instagram.*', 'icon' => '📸', 'label' => 'Instagram'],
        ['route' => 'admin.youtube.index',  'active' => 'admin.youtube.*',   'icon' => '▶️', 'label' => 'YouTube'],
        ['route' => 'admin.facebook.index', 'active' => 'admin.facebook.*',  'icon' => '📘', 'label' => 'Facebook'],
        ['route' => 'admin.settings.index', 'active' => 'admin.settings.*', 'icon' => '⚙️', 'label' => 'Settings'],
    ];

    // Users menu sirf admin ko dikhe
    if (auth()->user()?->isAdmin()) {
        $nav[] = ['route' => 'admin.users.index', 'active' => 'admin.users.*', 'icon' => '👥', 'label' => 'Users'];
    }
@endphp

<div class="min-h-screen flex">
    {{-- Sidebar --}}
    <aside id="sidebar"
           class="fixed lg:static inset-y-0 left-0 z-30 w-64 bg-gradient-to-b from-rose-700 to-rose-900 text-rose-100 flex flex-col
                  -translate-x-full lg:translate-x-0 transition-transform duration-200">
        <div class="p-5 border-b border-white/10">
            <a href="{{ route('admin.dashboard') }}" class="text-xl font-bold text-white flex items-center gap-2">
                📖 Kahani
            </a>
            <p class="text-xs text-rose-200 mt-1">Admin Panel</p>
        </div>

        <nav class="flex-1 p-3 space-y-1">
            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}"
                   class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm hover:bg-white/10 transition {{ request()->routeIs($item['active']) ? 'active' : '' }}">
                    <span class="text-lg">{{ $item['icon'] }}</span>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="p-3 border-t border-white/10">
            <div class="flex items-center gap-3 px-3 py-2">
                <div class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center font-semibold">
                    {{ mb_substr(auth()->user()->name ?? 'A', 0, 1) }}
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-white truncate">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-rose-200 truncate">{{ auth()->user()->email }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="w-full mt-1 text-left px-3 py-2 rounded-lg text-sm hover:bg-white/10 flex items-center gap-3">
                    <span class="text-lg">🚪</span> Logout
                </button>
            </form>
        </div>
    </aside>

    {{-- Mobile overlay --}}
    <div id="overlay" class="fixed inset-0 bg-black/40 z-20 hidden lg:hidden"></div>

    {{-- Main --}}
    <div class="flex-1 flex flex-col min-w-0">
        <header class="bg-white border-b border-slate-200 sticky top-0 z-10">
            <div class="px-4 sm:px-6 py-3 flex items-center gap-3">
                <button id="menuBtn" class="lg:hidden text-2xl text-slate-600">☰</button>
                <h1 class="text-lg font-semibold">@yield('title', 'Dashboard')</h1>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-6 max-w-6xl w-full mx-auto">
            @if (session()->has('impersonator_id'))
                <div class="mb-4 rounded-lg bg-amber-100 border border-amber-300 text-amber-800 px-4 py-3 flex items-center justify-between gap-3 flex-wrap">
                    <span>👤 Aap <b>{{ auth()->user()->name }}</b> ki tarah logged-in ho (impersonation).</span>
                    <form method="POST" action="{{ route('admin.users.returnToAdmin') }}">
                        @csrf
                        <button class="text-sm bg-amber-600 hover:bg-amber-700 text-white rounded-lg px-3 py-1.5">↩ Admin par wapas</button>
                    </form>
                </div>
            @endif
            @if (session('success'))
                <div class="mb-4 rounded-lg bg-green-100 border border-green-300 text-green-800 px-4 py-3">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-lg bg-red-100 border border-red-300 text-red-800 px-4 py-3">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg bg-red-100 border border-red-300 text-red-800 px-4 py-3">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script>
    const sb = document.getElementById('sidebar');
    const ov = document.getElementById('overlay');
    document.getElementById('menuBtn')?.addEventListener('click', () => {
        sb.classList.remove('-translate-x-full');
        ov.classList.remove('hidden');
    });
    ov?.addEventListener('click', () => {
        sb.classList.add('-translate-x-full');
        ov.classList.add('hidden');
    });
</script>
@stack('scripts')
</body>
</html>
