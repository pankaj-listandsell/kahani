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
        'puzzle', 'mind_reader', 'this_or_that', 'name_secret', 'salah', 'nuskha', 'prem_patra', 'romance', 'dharmik',
    ];

    /**
     * Studios jo menu se hata diye gaye — inka purana data Stories page par
     * "hataye gaye studios" box me dikhta hai (wahan se delete karo).
     */
    public const REMOVED_TYPES = ['mind_reader', 'yoga', 'name_secret', 'puzzle', 'this_or_that'];

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
    /** Rashifal Short ke part ka kind (body = "short:<kind>") → admin me dikhne wala naam. */
    public const RASHI_SHORT_LABELS = [
        'savdhan' => '⚠️ Savdhan Rahe 3 Rashi',
        'prem'    => '💕 Prem me Lucky 3 Rashi',
        'dhan'    => '💰 Dhan Labh Top 3',
        'rang'    => '🎨 Shubh Rang 3 Rashi',
        'ank'     => '🔢 Lucky Number 3 Rashi',
        'upay'    => '🙏 Upay 3 Rashi',
        'galti'   => '🚫 Galti Mat Karna 3 Rashi',
        'shaadi'  => '💍 Rishta Pakka 3 Rashi',
        'naukri'  => '📈 Naukri-Business Tarakki 3 Rashi',
        'jodi'    => '🤝 Aaj ki Best Jodi',
        'week'    => '🗓️ Is Hafte ki Top 3',
        'chandra' => '🌕 Poonam / Amas 3 Rashi',
        'fest'    => '🪔 Tyohar Special 3 Rashi',
        'top3'    => '🍀 Top 3 Lucky Rashi', // purane saved
        'all'     => '🔯 12 Rashi',          // purane saved (ab nahi bante)
    ];

    /**
     * 🎬 Is hafte ka Saptahik video (type 'rashifal_week', category = Somvar) jo upload nahi hua.
     * Sirf usi hafte (Somvar–Ravivar) — purane hafte ka kabhi nahi.
     */
    public static function nextRashifalWeekPart(int $userId): ?Part
    {
        $monday = now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
        $stories = static::where('status', 'published')->where('user_id', $userId)
            ->where('type', 'rashifal_week')->where('category', $monday)
            ->with(['parts' => fn ($q) => $q->orderBy('sort_order'), 'parts.cards' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('id')->get();

        foreach ($stories as $story) {
            foreach ($story->parts as $part) {
                $first = $part->cards->first();
                // Lamba video banne + upload me der lagti hai — 'posting' 60 min tak atka maano
                $stuck = $first?->yt_status === 'posting'
                    && $first->updated_at?->lt(now()->subMinutes(max(60, PartCard::POSTING_STALE_MINUTES)));
                if ($first && ($first->yt_status === null || $stuck)) {
                    return $part;
                }
            }
        }

        return null;
    }

    public static function rashiShortLabel(?string $body): string
    {
        if (str_starts_with(ltrim((string) $body), '{')) {
            return '🎬 Saptahik Video (16:9)';
        }
        $kind = str_starts_with((string) $body, 'short:') ? substr((string) $body, 6) : '';

        return self::RASHI_SHORT_LABELS[$kind] ?? '📺 Rashifal Short';
    }

    /**
     * Aaj ke Rashifal YouTube Shorts (type 'rashifal_short', category = din) me se agla part
     * jo upload nahi hua — har part = ek Short (roz 3 themes). Status pehle card par.
     */
    public static function nextRashifalShortPart(int $userId): ?Part
    {
        $stories = static::where('status', 'published')->where('user_id', $userId)
            ->where('type', 'rashifal_short')->where('category', now()->toDateString())
            ->with(['parts' => fn ($q) => $q->orderBy('sort_order'), 'parts.cards' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('id')->get();

        foreach ($stories as $story) {
            foreach ($story->parts as $part) {
                if ((string) $part->body === 'short:all') {
                    continue; // 12 Rashi Short band — YouTube par sirf Top 3
                }
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
            // 📅 Panchang bhi isi tarah sirf usi din — rashifal se pehle
            ->whereIn('type', ['panchang', 'rashifal'])
            ->where('category', now()->toDateString())
            ->with(['parts' => fn ($q) => $q->orderBy('sort_order'), 'parts.cards' => fn ($q) => $q->orderBy('sort_order')])
            ->orderByRaw("CASE WHEN type = 'panchang' THEN 0 ELSE 1 END")
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
