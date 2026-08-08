<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings.index', ['user' => Auth::user()]);
    }

    /**
     * Naam / email update.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    /**
     * Quiz ka default caption (per-user).
     *
     * Set ho to har quiz card par yahi jaata hai; khaali chhodo to AI har sawaal
     * ke liye apni hook line banata hai. Hashtags dono case me apne aap judte hain.
     */
    public function updateQuizCaption(Request $request)
    {
        $data = $request->validate([
            'quiz_caption' => ['nullable', 'string', 'max:1000'],
        ]);

        $caption = trim((string) ($data['quiz_caption'] ?? ''));

        if ($caption === '') {
            Setting::removeFor(Auth::id(), 'quiz_caption');

            return back()->with('success', 'Default caption hata diya — ab AI khud banayega.');
        }

        Setting::putFor(Auth::id(), 'quiz_caption', $caption);

        return back()->with('success', 'Quiz caption save ho gaya! 🎯');
    }

    /**
     * Reel me halka Ken Burns zoom on/off (per-user).
     * Reels har baar naye sire se banti hain, isliye badalne ke baad bas
     * "Generate Reel" dobara dabana hota hai.
     */
    public function updateReelMotion(Request $request)
    {
        $request->validate(['motion' => ['required', 'boolean']]);

        Setting::putFor(Auth::id(), 'reel_motion', $request->boolean('motion') ? '1' : '0');

        return response()->json(['ok' => true]);
    }

    /**
     * Password change.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'password'         => ['required', 'confirmed', Password::min(6)],
        ]);

        if (! Hash::check($request->current_password, Auth::user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        Auth::user()->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Password changed.');
    }
}
