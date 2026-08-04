<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Reel/Short ki still image par halka "Ken Burns" motion (dheema zoom).
 *
 * Bina iske card 6 second tak bilkul jamaa hua frame rehta hai aur video
 * slideshow jaisa lagta hai. Halka zoom usi image ko "zinda" bana deta hai —
 * bilkul free, sirf ffmpeg se (koi AI video service nahi).
 *
 * Teeno reel builders ({@see InstagramService}, {@see YoutubeService},
 * {@see QuizReelService}) yahi chain use karte hain taaki output ek jaisa rahe.
 */
class ReelMotion
{
    /**
     * Zoom kitna — 1.06 = 6%.
     *
     * Jaan-boojh kar bahut halka: apne zyadatar cards TEXT ke hain aur zoom
     * kinaare kaat deta hai. 6% par har taraf ~2.8% katta hai jo card ki padding
     * ke andar aa jaata hai, yaani text safe rehta hai. Isse zyada karne par
     * shayari/quiz cards ke akshar kat-ne lagte hain (testing me 2.2x par poora
     * text kat gaya tha).
     */
    private const ZOOM = 1.06;

    /**
     * Motion se pehle image ko itna bada rakhte hain taaki zoom par dhundhla na ho.
     * 1.5 × 720 = 1080 — bilkul utna hi jitne ke cards bante hain, isliye image
     * pehle chhoti karke wapas badi nahi karni padti (1.25 par PSNR 44.9 dB tha).
     */
    private const OVERSCAN = 1.5;

    public static function enabled(?int $userId): bool
    {
        return (string) Setting::getFor($userId, 'reel_motion', '1') === '1';
    }

    /**
     * Ek image input ka poora video filter chain (input/output label ke bina).
     *
     * @param  int   $index    Segment number — ek-ek card baari-baari zoom-in /
     *                         zoom-out karta hai, warna saari slides ek jaisi lagti hain.
     * @param  bool  $motion   false = pehle wala static chain
     */
    public static function chain(int $w, int $h, float $seconds, int $index = 0, bool $motion = true): string
    {
        $fit = "scale={$w}:{$h}:force_original_aspect_ratio=decrease:in_range=full:out_range=tv,"
            . "pad={$w}:{$h}:(ow-iw)/2:(oh-ih)/2:color=black";

        if (! $motion) {
            return $fit . ',setsar=1,format=yuv420p';
        }

        // Kaam ki resolution — zoom karne par sharp rahe
        $bw = self::even($w * self::OVERSCAN);
        $bh = self::even($h * self::OVERSCAN);

        $big = "scale={$bw}:{$bh}:force_original_aspect_ratio=decrease:in_range=full:out_range=tv,"
            . "pad={$bw}:{$bh}:(ow-iw)/2:(oh-ih)/2:color=black";

        $frames = max(1, (int) round($seconds * 25));
        $step   = number_format((self::ZOOM - 1) / $frames, 6, '.', '');
        $zoom   = number_format(self::ZOOM, 2, '.', '');

        // `on` = global output frame number. `zoom` variable yahan kaam NAHI karta:
        // `-loop 1` input par har input frame ke saath wo 1 par reset ho jaata hai,
        // isliye zoom badhta hi nahi (testing me frames bilkul same aaye the).
        $z = $index % 2 === 0
            ? "min(1+{$step}*on,{$zoom})"          // andar ki taraf
            : "max({$zoom}-{$step}*on,1)";         // bahar ki taraf

        return $big
            . ",zoompan=z='{$z}':d=1:x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)'"
            . ":s={$w}x{$h}:fps=25"
            . ',setsar=1,format=yuv420p';
    }

    /** libx264 ko even dimensions chahiye. */
    private static function even(float $n): int
    {
        $v = (int) round($n);

        return $v % 2 === 0 ? $v : $v + 1;
    }
}
