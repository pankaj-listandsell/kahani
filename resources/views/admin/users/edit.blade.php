@extends('layouts.admin')
@section('title', 'Edit User')

@section('content')
<div class="max-w-lg">
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="bg-rose-600 text-white px-6 py-4">
            <h3 class="font-semibold flex items-center gap-2">✏ Edit User: {{ $user->name }}</h3>
        </div>
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="p-6 space-y-4">
            @csrf @method('PUT')

            <div>
                <label class="block text-sm font-medium mb-1">Name</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">New Password</label>
                <input type="password" name="password" minlength="6"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none">
                <p class="text-xs text-slate-500 mt-1">Khaali chhodo to purana password rahega.</p>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Role</label>
                <select name="role" required @disabled($user->id === auth()->id())
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none disabled:bg-slate-100">
                    <option value="user" @selected(old('role', $user->role) === 'user')>User</option>
                    <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option>
                </select>
                @if ($user->id === auth()->id())
                    <input type="hidden" name="role" value="{{ $user->role }}">
                    <p class="text-xs text-amber-600 mt-1">Aap apna khud ka role nahi badal sakte.</p>
                @endif
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-500 hover:underline">← Cancel</a>
                <button class="bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg px-5 py-2.5">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
