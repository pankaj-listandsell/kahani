<?php

namespace App\Services;

use App\Models\PartCard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Instagram caption + hashtags AI se banata hai.
 * Pollinations.ai ka text API use karta hai — bilkul free, koi API key nahi.
 */
class AiCaptionService
{
    /**
     * Har caption ke saath jodne wale trending/viral hashtags (reels + Hindi
     * kahani niche). AI ke relevant hashtags ke saath merge hote hain —
     * duplicates hat jaate hain aur Instagram ki 30-hashtag limit ka dhyan
     * rakha jaata hai.
     */
    private const TRENDING_HASHTAGS = [
        '#reels', '#reelsinstagram', '#trending', '#viral', '#explore',
        '#explorepage', '#foryou', '#fyp', '#instareels', '#trendingreels',
        '#viralvideo', '#reelitfeelit', '#storytime', '#hindikahani', '#kahani',
    ];

    /**
     * Ek card ke liye Hindi caption + hashtags generate karo.
     *
     * @throws \RuntimeException agar AI se caption na bane.
     */
    public function forCard(PartCard $card): string
    {
        $part  = $card->part;
        $story = $part?->story;

        $title = $story?->title ?: 'Hindi Kahani';
        $desc  = $story?->description ? Str::limit(strip_tags($story->description), 300) : '';
        $partNo = $part?->sort_order ?? 1;

        $prompt = <<<TXT
        Tum ek expert Hindi social media manager ho. Neeche di gayi kahani ke liye ek Instagram caption likho.

        Rules:
        - 2-3 chhoti aakarshak lines Hindi (Devanagari) me jo curiosity badhaye.
        - Emojis ka halka use karo.
        - Uske baad ek khaali line, phir 10-12 relevant + trending/viral hashtags (Hindi + English mix, sab # ke saath, ek hi line me).
        - Sirf caption do — koi explanation, quotes ya "Caption:" jaisa label mat likho.

        Kahani ka title: {$title}
        Part number: {$partNo}
        Details: {$desc}
        TXT;

        $url = 'https://text.pollinations.ai/' . rawurlencode($prompt);

        $response = Http::timeout(60)
            ->retry(2, 1500, throw: false)
            ->get($url, [
                'model' => 'openai',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('AI caption service ne error diya (HTTP ' . $response->status() . '). Thodi der baad try karein.');
        }

        $caption = trim($response->body());

        // Kabhi-kabhi service khaali ya HTML de deti hai
        if ($caption === '' || Str::startsWith($caption, ['<', '{'])) {
            throw new \RuntimeException('AI se valid caption nahi mili. Dobara try karein.');
        }

        // Aage/peeche ke quote marks hata do agar AI ne laga diye
        $caption = trim($caption, "\"' \n\r\t");

        return $this->appendTrendingHashtags($caption);
    }

    /**
     * Caption ke ant me trending hashtags jodo — jo pehle se present nahi hain
     * sirf wahi, aur Instagram ki 30-hashtag limit ke andar rehte hue.
     */
    private function appendTrendingHashtags(string $caption): string
    {
        // Caption me pehle se maujood hashtags (case-insensitive)
        preg_match_all('/#[\p{L}\p{N}_]+/u', $caption, $matches);
        $existing = array_map(fn ($h) => Str::lower($h), $matches[0]);

        $toAdd = array_values(array_filter(
            self::TRENDING_HASHTAGS,
            fn ($tag) => ! in_array(Str::lower($tag), $existing, true)
        ));

        // Instagram max 30 hashtags — total safe rakho
        $room = max(0, 28 - count($existing));
        $toAdd = array_slice($toAdd, 0, $room);

        if (empty($toAdd)) {
            return $caption;
        }

        return rtrim($caption) . "\n" . implode(' ', $toAdd);
    }
}
