@extends('layouts.admin')
@section('title', 'Users')

@section('content')
<div class="max-w-5xl">

    <div class="flex items-center justify-between gap-3 mb-6 flex-wrap">
        <div>
            <h2 class="text-xl font-semibold flex items-center gap-2">👥 User Management</h2>
            <p class="text-sm text-slate-500">Users banao, edit karo, role do ya unki tarah login karo.</p>
        </div>
        <a href="{{ route('admin.users.create') }}"
           class="bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg px-4 py-2.5 text-sm">
            ＋ New User
        </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">#</th>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Role</th>
                        <th class="px-4 py-3 font-medium">Joined</th>
                        <th class="px-4 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $u)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-400">{{ $u->id }}</td>
                            <td class="px-4 py-3 font-medium">
                                {{ $u->name }}
                                @if ($u->id === auth()->id())
                                    <span class="ml-1 text-[10px] bg-sky-100 text-sky-700 rounded-full px-2 py-0.5">You</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $u->email }}</td>
                            <td class="px-4 py-3">
                                <span class="text-[11px] rounded-full px-2.5 py-0.5 font-medium
                                    {{ $u->isAdmin() ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ ucfirst($u->role) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $u->created_at?->diffForHumans() }}</td>
                            <td class="px-4 py-3">
                                <div class="flex gap-1.5 justify-end">
                                    @if ($u->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.users.loginAs', $u) }}"
                                              onsubmit="return confirm('{{ $u->name }} ki tarah login karein? Baad me wapas admin par aa sakte ho.')">
                                            @csrf
                                            <button class="text-xs border border-green-200 text-green-700 rounded-lg px-2.5 py-1.5 hover:bg-green-50" title="Login as {{ $u->name }}">
                                                ↪ Login as
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('admin.users.edit', $u) }}"
                                       class="text-xs border border-slate-200 text-slate-700 rounded-lg px-2.5 py-1.5 hover:bg-slate-100">
                                        ✏ Edit
                                    </a>
                                    @if ($u->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
                                              onsubmit="return confirm('User {{ $u->name }} delete karein?')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs border border-red-200 text-red-600 rounded-lg px-2.5 py-1.5 hover:bg-red-50">
                                                🗑
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-400">Koi user nahi mila.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>

</div>
@endsection
