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
        - Uske baad ek khaali line, phir 10-12 relevant hashtags (Hindi + English mix, sab # ke saath, ek hi line me).
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
        return trim($caption, "\"' \n\r\t");
    }
}
