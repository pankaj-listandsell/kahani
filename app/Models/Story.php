<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Story extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'source_url', // dusri website se import ki gayi kahani ka original URL
        'description',
        'cover_image',
        'status',
        'type',       // story | shayari | joke | quote
        'category',   // topic/mood (love, sad, funny...)
        'language',   // hindi | gujarati | hinglish
        'tts_mode',   // per-story audio override: voice | voice_music | music (null=global)
        'tts_voice',  // per-story voice (Kore, Puck...) — null=global
        'platforms',  // auto-post target platforms: ["instagram","youtube","facebook"] (null=sab)
    ];

    protected $casts = [
        'platforms' => 'array',
    ];

    /**
     * Collection types — inke cards auto-post me RANDOM order me jaate hain.
     * Baaki (kahani) cards sequence me. Naya studio type yahin add karo.
     */
    public const COLLECTION_TYPES = [
        'shayari', 'joke', 'quote', 'quiz', 'status', 'fact', 'yoga', 'ukhana',
        'puzzle', 'mind_reader', 'this_or_that', 'name_secret', 'salah', 'nuskha', 'prem_patra',
    ];

    public function isCollection(): bool
    {
        return in_array($this->type, self::COLLECTION_TYPES, true);
    }

    /**
     * Rashifal sirf apne din (category = 'Y-m-d') par post ho, Mesh→Meen kram me,
     * aur us din baaki sab cards se pehle. Auto-post commands pehle ye poochhte hain.
     *
     * @param  string  $platform  instagram | youtube | facebook
     * @param  string  $statusCol  ig_status | yt_status | fb_status
     */
    /**
     * Aaj ke Rashifal YouTube Shorts (type 'rashifal_short', category = din) me se agla part
     * jo upload nahi hua — har part = ek Short (12 rashi, fir Top 3). Status pehle card par.
     */
    public static function nextRashifalShortPart(int $userId): ?Part
    {
        $stories = static::where('status', 'published')->where('user_id', $userId)
            ->where('type', 'rashifal_short')->where('category', now()->toDateString())
            ->with(['parts' => fn ($q) => $q->orderBy('sort_order'), 'parts.cards' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('id')->get();

        foreach ($stories as $story) {
            foreach ($story->parts as $part) {
                $first = $part->cards->first();
                $stuck = $first?->yt_status === 'posting'
                    && $first->updated_at?->lt(now()->subMinutes(PartCard::POSTING_STALE_MINUTES));
                if ($first && ($first->yt_status === null || $stuck)) {
                    return $part;
                }
            }
        }

        return null;
    }

    public static function nextRashifalCard(int $userId, string $platform, string $statusCol): ?PartCard
    {
        $stories = static::where('status', 'published')
            ->where('user_id', $userId)
            ->where('type', 'rashifal')
            ->where('category', now()->toDateString())
            ->with(['parts' => fn ($q) => $q->orderBy('sort_order'), 'parts.cards' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('id')
            ->get();

        foreach ($stories as $story) {
            if (! $story->targetsPlatform($platform)) {
                continue;
            }
            foreach ($story->parts as $part) {
                foreach ($part->cards as $card) {
                    if ($card->{$statusCol} === null) {
                        return $card;
                    }
                }
            }
        }

        return null;
    }

    /** TTS narration ka andaaz (GeminiTtsService::instructionFor ka style). */
    public function voiceStyle(): string
    {
        return match ($this->type) {
            'shayari', 'quote', 'joke', 'yoga', 'quiz', 'ukhana' => $this->type,
            // Love letter — shayari jaisa dheema, ehsaas bhara padhna
            'prem_patra' => 'shayari',
            // Interactive studios — quiz-master wala andaaz
            'puzzle', 'mind_reader', 'this_or_that', 'name_secret' => 'quiz',
            default => 'story',
        };
    }

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

    /** Saare parts ke saare cards (ginti/filter ke liye). */
    public function cards(): HasManyThrough
    {
        return $this->hasManyThrough(PartCard::class, Part::class);
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
