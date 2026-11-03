<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Reel ka background music (absolute path) — kabhi khaali na rahe:
 *   1) us platform ka upload kiya music (Instagram: ig_reel_music, YouTube: yt_music)
 *   2) dusre platform ka upload kiya music
 *   3) project ka built-in track — Reel settings ka "🎵 Background Music" (quiz_bgm_track)
 * "None" chuna ho aur kuch upload na ho to null (silent).
 */
class ReelMusic
{
    public static function path(?int $userId, string $primaryKey): ?string
    {
        $disk = Storage::disk('public');

        foreach (array_unique([$primaryKey, 'ig_reel_music', 'yt_music']) as $key) {
            $file = $userId ? Setting::getFor($userId, $key) : Setting::get($key);
            if ($file && $disk->exists($file)) {
                return $disk->path($file);
            }
        }

        $track = (string) ($userId ? Setting::getFor($userId, 'quiz_bgm_track', 'suspense') : Setting::get('quiz_bgm_track', 'suspense'));
        $file  = public_path('audio/bgm/' . basename($track) . '.mp3');

        return $track !== 'none' && is_file($file) ? $file : null;
    }
}
