<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Story extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'cover_image',
        'status',
    ];

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
