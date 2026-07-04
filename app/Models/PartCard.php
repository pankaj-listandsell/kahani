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
        'text',
        'ig_caption',
        'ig_status',
        'ig_media_id',
        'ig_posted_at',
        'ig_error',
        'yt_status',
        'yt_video_id',
        'yt_posted_at',
        'yt_error',
        'yt_caption',
        'fb_status',
        'fb_post_id',
        'fb_posted_at',
        'fb_error',
    ];

    protected $casts = [
        'ig_posted_at' => 'datetime',
        'yt_posted_at' => 'datetime',
        'fb_posted_at' => 'datetime',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function isPosted(): bool
    {
        return $this->ig_status === 'posted';
    }

    public function isYtPosted(): bool
    {
        return $this->yt_status === 'posted';
    }

    public function isFbPosted(): bool
    {
        return $this->fb_status === 'posted';
    }
}
