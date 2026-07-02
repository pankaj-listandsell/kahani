<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Part extends Model
{
    protected $fillable = [
        'story_id',
        'sort_order',
        'title',
        'body',
        'image_path',
        'image_prompt',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /**
     * Is part ke text-card images (order ke hisaab se).
     */
    public function cards(): HasMany
    {
        return $this->hasMany(PartCard::class)->orderBy('sort_order');
    }

    /**
     * Is part se pehle wala part (padhne ke navigation ke liye).
     */
    public function previous(): ?Part
    {
        return static::where('story_id', $this->story_id)
            ->where('sort_order', '<', $this->sort_order)
            ->orderByDesc('sort_order')
            ->first();
    }

    /**
     * Is part ke baad wala part.
     */
    public function next(): ?Part
    {
        return static::where('story_id', $this->story_id)
            ->where('sort_order', '>', $this->sort_order)
            ->orderBy('sort_order')
            ->first();
    }
}
