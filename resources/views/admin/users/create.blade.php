@extends('layouts.admin')
@section('title', 'New User')

@section('content')
<div class="max-w-lg">
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="bg-rose-600 text-white px-6 py-4">
            <h3 class="font-semibold flex items-center gap-2">➕ Create User</h3>
        </div>
        <form method="POST" action="{{ route('admin.users.store') }}" class="p-6 space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Password</label>
                <input type="password" name="password" required minlength="6"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none">
                <p class="text-xs text-slate-500 mt-1">Kam se kam 6 characters.</p>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Role</label>
                <select name="role" required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none">
                    <option value="user" @selected(old('role', 'user') === 'user')>User</option>
                    <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                </select>
                <p class="text-xs text-slate-500 mt-1">Admin sabhi users manage kar sakta hai.</p>
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-500 hover:underline">← Cancel</a>
                <button class="bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg px-5 py-2.5">Create User</button>
            </div>
        </form>
    </div>
</div>
@endsection
