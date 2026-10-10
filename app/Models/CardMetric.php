<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ek post (card × platform) ke asli numbers — `analytics:fetch` se aate hain.
 */
class CardMetric extends Model
{
    protected $fillable = [
        'part_card_id', 'platform', 'views', 'reach', 'likes', 'comments',
        'saves', 'shares', 'permalink', 'fetched_at',
    ];

    protected $casts = [
        'fetched_at' => 'datetime',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(PartCard::class, 'part_card_id');
    }

    /** Likes + comments + saves + shares (jo mile). */
    public function interactions(): int
    {
        return (int) $this->likes + (int) $this->comments + (int) $this->saves + (int) $this->shares;
    }
}
