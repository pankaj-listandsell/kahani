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

        // Sirf DB ki asli value cache karo ([] = row nahi hai). Default cache me
        // nahi jaata, warna pehle caller ka default baaki sab callers ko milta.
        $cached = Cache::rememberForever(static::cacheKey($userId, $key), function () use ($userId, $key) {
            $row = static::where('user_id', $userId)->where('key', $key)->first();

            return $row ? [$row->value] : [];
        });

        return $cached ? $cached[0] : $default;
    }

    protected static function cacheKey(int $userId, string $key): string
    {
        return "setting.v2.{$userId}.{$key}";
    }

    public static function putFor(int $userId, string $key, ?string $value): void
    {
        static::updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $value],
        );
        Cache::forget(static::cacheKey($userId, $key));
    }

    public static function removeFor(int $userId, string $key): void
    {
        static::where('user_id', $userId)->where('key', $key)->delete();
        Cache::forget(static::cacheKey($userId, $key));
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
