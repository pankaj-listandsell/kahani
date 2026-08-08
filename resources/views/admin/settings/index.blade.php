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

</div>
@endsection
