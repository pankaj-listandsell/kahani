<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['user_id', 'key', 'value'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ===================================================================
     |  Per-user (explicit user id) — services & console commands isse use karein
     * =================================================================== */

    public static function getFor(?int $userId, string $key, mixed $default = null): mixed
    {
        if (! $userId) {
            return $default;
        }

        return Cache::rememberForever("setting.{$userId}.{$key}", function () use ($userId, $key, $default) {
            $row = static::where('user_id', $userId)->where('key', $key)->first();

            return $row ? $row->value : $default;
        });
    }

    public static function putFor(int $userId, string $key, ?string $value): void
    {
        static::updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $value],
        );
        Cache::forget("setting.{$userId}.{$key}");
    }

    public static function removeFor(int $userId, string $key): void
    {
        static::where('user_id', $userId)->where('key', $key)->delete();
        Cache::forget("setting.{$userId}.{$key}");
    }

    /* ===================================================================
     |  Current logged-in user ke liye convenience wrappers (request context)
     * =================================================================== */

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::getFor(Auth::id(), $key, $default);
    }

    public static function put(string $key, ?string $value): void
    {
        if ($userId = Auth::id()) {
            static::putFor($userId, $key, $value);
        }
    }
}
