<?php

namespace App\Models;

use App\Exceptions\CardBusyException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartCard extends Model
{
    protected $fillable = [
        'part_id',
        'sort_order',
        'image_path',
        'answer_image_path',
        'text',
        'answer_text',
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

    /** Itne minute se zyada 'posting' me atka card = crash hua process, dobara claim ho sakta hai. */
    public const POSTING_STALE_MINUTES = 30;

    /**
     * Card ko kisi platform (ig/yt/fb) par post karne ke liye atomically "claim" karo:
     * status → 'posting'. Agar koi aur (button ya cron) abhi isi card ko post kar
     * raha hai to CardBusyException — isse double-post nahi hota.
     *
     * @return string|null claim se pehle ka status (releaseClaim ke liye)
     */
    public function claimFor(string $platform): ?string
    {
        $col = $platform . '_status';

        $fresh = static::query()->whereKey($this->id)->first([$col, 'updated_at']);
        if (! $fresh) {
            throw new CardBusyException('Card nahi mila.');
        }

        $previous = $fresh->{$col};
        $stale = $fresh->updated_at?->lt(now()->subMinutes(self::POSTING_STALE_MINUTES)) ?? true;

        if ($previous === 'posting' && ! $stale) {
            throw new CardBusyException("Card #{$this->id} abhi post ho raha hai — dobara mat dabao.");
        }

        // Compare-and-swap: sirf tab update jab status abhi bhi wahi hai jo humne padha
        $query = static::query()->whereKey($this->id);
        $previous === null ? $query->whereNull($col) : $query->where($col, $previous);

        if ($query->update([$col => 'posting', 'updated_at' => now()]) === 0) {
            throw new CardBusyException("Card #{$this->id} abhi post ho raha hai — dobara mat dabao.");
        }

        $this->{$col} = 'posting';
        $this->syncOriginalAttribute($col);

        return $previous === 'posting' ? null : $previous;
    }

    /** Post na hua aur card ko 'failed' bhi nahi karna (jaise rate-limit) — purana status wapas. */
    public function releaseClaim(string $platform, ?string $previous): void
    {
        $col = $platform . '_status';

        static::query()->whereKey($this->id)->where($col, 'posting')->update([$col => $previous]);
        $this->{$col} = $previous;
        $this->syncOriginalAttribute($col);
    }
}
