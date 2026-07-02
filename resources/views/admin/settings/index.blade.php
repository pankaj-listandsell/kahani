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

</div>
@endsection
