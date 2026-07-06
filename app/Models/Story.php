<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Story extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'description',
        'cover_image',
        'status',
        'type',       // story | shayari | joke | quote
        'category',   // topic/mood (love, sad, funny...)
        'tts_mode',   // per-story audio override: voice | voice_music | music (null=global)
        'tts_voice',  // per-story voice (Kore, Puck...) — null=global
        'platforms',  // auto-post target platforms: ["instagram","youtube","facebook"] (null=sab)
    ];

    protected $casts = [
        'platforms' => 'array',
    ];

    /**
     * Kya is story ko diye gaye platform par auto-post karna hai?
     * null/empty platforms = sab platforms (default).
     */
    public function targetsPlatform(string $platform): bool
    {
        $list = $this->platforms;

        return empty($list) || in_array($platform, $list, true);
    }

    /**
     * Is kahani ka malik (owner).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ek kahani ke bahut saare parts hote hain (order ke hisaab se).
     */
    public function parts(): HasMany
    {
        return $this->hasMany(Part::class)->orderBy('sort_order');
    }

    /**
     * Title se apne aap slug bana do agar diya na gaya ho.
     */
    protected static function booted(): void
    {
        // Nayi story banate waqt owner apne aap logged-in user set ho jaaye
        static::creating(function (Story $story) {
            if (empty($story->user_id) && Auth::check()) {
                $story->user_id = Auth::id();
            }
        });

        static::saving(function (Story $story) {
            if (empty($story->slug)) {
                $base = Str::slug($story->title);
                if ($base === '') {
                    // Hindi title ka slug khaali aa sakta hai, toh fallback
                    $base = 'kahani-' . Str::random(6);
                }
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->where('id', '!=', $story->id)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $story->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
