<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartCard extends Model
{
    protected $fillable = [
        'part_id',
        'sort_order',
        'image_path',
        'ig_status',
        'ig_media_id',
        'ig_posted_at',
        'ig_error',
    ];

    protected $casts = [
        'ig_posted_at' => 'datetime',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function isPosted(): bool
    {
        return $this->ig_status === 'posted';
    }
}
