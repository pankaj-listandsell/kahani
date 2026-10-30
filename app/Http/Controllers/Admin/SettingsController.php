<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\InstagramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings.index', ['user' => Auth::user()]);
    }

    /**
     * Server par nayi files upload karne ke baad — config/route/view cache saaf karo
     * (terminal me `php artisan view:clear` wagairah chalane ki zaroorat nahi).
     *
     * App ka data cache (cache:clear) jaan-boojhkar NAHI — usme webhook ke
     * "is comment ko reply/DM ho chuka" wale keys aur scheduler locks hote hain;
     * unhe mitane se duplicate reply/DM ja sakte hain.
     */
    public function clearCache()
    {
        $done = [];
        foreach (['config:clear', 'route:clear', 'view:clear', 'event:clear', 'clear-compiled'] as $cmd) {
            try {
                Artisan::call($cmd);
                $done[] = $cmd;
            } catch (\Throwable $e) {
                Log::warning('Clear cache step failed', ['cmd' => $cmd, 'error' => $e->getMessage()]);
            }
        }

        // Kuch hosting par PHP purani files yaad rakhta hai (OPcache) — use bhi reset
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return back()->with('success', '🧹 Cache clear ho gaya (' . implode(', ', $done) . ').');
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
     * Reel me Background music aur Sound effects (Tick-Tick/Ding) setting.
     */
    public function updateReelAudio(Request $request)
    {
        $data = $request->validate([
            'bgm_track'   => ['nullable', 'in:none,suspense,lofi,inspiration'],
            'sfx_enabled' => ['nullable', 'boolean'],
        ]);

        if (isset($data['bgm_track'])) {
            Setting::putFor(Auth::id(), 'quiz_bgm_track', $data['bgm_track']);
        }
        if (isset($data['sfx_enabled'])) {
            Setting::putFor(Auth::id(), 'quiz_sfx_enabled', $data['sfx_enabled'] ? '1' : '0');
        }

        return response()->json(['ok' => true]);
    }

    /**
     * 🔔 Reel ke end ka "Follow karo" outro — card image (browser canvas me bani) + voice line.
     */
    public function updateReelOutro(Request $request, InstagramService $instagram)
    {
        $data = $request->validate([
            'enabled'  => ['required', 'boolean'],
            'headline' => ['nullable', 'string', 'max:120'],
            'sub'      => ['nullable', 'string', 'max:120'],
            'handle'   => ['nullable', 'string', 'max:60'],
            'voice'    => ['nullable', 'string', 'max:200'],
            'seconds'  => ['nullable', 'numeric', 'min:2.5', 'max:6'],
            'image'    => ['nullable', 'string'],
        ]);

        $uid = Auth::id();

        if (! empty($data['image'])) {
            if (! preg_match('/^data:image\/png;base64,(.+)$/s', $data['image'], $m)
                || ($png = base64_decode(str_replace(' ', '+', $m[1]), true)) === false) {
                return response()->json(['ok' => false, 'error' => 'Image sahi nahi bani.'], 422);
            }

            $disk = Storage::disk('public');
            $old  = (string) Setting::getFor($uid, 'reel_outro_image', '');
            $path = 'outro/outro-' . $uid . '-' . Str::random(8) . '.png';
            $disk->put($path, $png);
            if ($old !== '' && $old !== $path) {
                $disk->delete($old);
            }
            Setting::putFor($uid, 'reel_outro_image', $path);
        }

        Setting::putFor($uid, 'reel_outro_enabled', $data['enabled'] ? '1' : '0');
        foreach (['headline', 'sub', 'handle', 'voice', 'seconds'] as $key) {
            Setting::putFor($uid, 'reel_outro_' . $key, trim((string) ($data[$key] ?? '')));
        }

        // Music-mode wali cached reels purani (bina/purane outro wali) — nayi bane
        $instagram->forUser($uid)->clearReelCache();

        return response()->json(['ok' => true, 'image' => ($p = Setting::getFor($uid, 'reel_outro_image')) ? Storage::disk('public')->url($p) : null]);
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
