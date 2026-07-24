<?php

namespace App\Services;

use App\Models\PartCard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        '#jantarmantar', '#reels', '#reelsinstagram', '#trending', '#viral', '#explore',
        '#explorepage', '#foryou', '#fyp', '#instareels', '#trendingreels',
        '#viralvideo', '#reelitfeelit', '#storytime', '#hindikahani', '#kahani',
    ];

    /**
     * YouTube Shorts ke liye trending hashtags (Hindi kahani niche).
     * #Shorts sabse zaroori — isi se YouTube video ko Short maanta hai.
     */
    private const YOUTUBE_TRENDING_HASHTAGS = [
        '#jantarmantar', '#shorts', '#youtubeshorts', '#ytshorts', '#shortsfeed', '#shortvideo',
        '#viral', '#trending', '#trendingshorts', '#viralshorts', '#storytime',
        '#hindikahani', '#kahani', '#moralstory', '#story', '#hindistory',
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

        return $this->appendHashtags($this->callAi($prompt), self::TRENDING_HASHTAGS, 28);
    }

    /**
     * Ek card ke liye YouTube Shorts caption (title-line + description + YouTube
     * trending hashtags) generate karo.
     *
     * @throws \RuntimeException agar AI se caption na bane.
     */
    public function forYoutube(PartCard $card): string
    {
        $part  = $card->part;
        $story = $part?->story;

        $title  = $story?->title ?: 'Hindi Kahani';
        $desc   = $story?->description ? Str::limit(strip_tags($story->description), 300) : '';
        $partNo = $part?->sort_order ?? 1;

        $prompt = <<<TXT
        Tum ek expert YouTube Shorts creator ho. Neeche di gayi Hindi kahani ke liye ek YouTube Short ka caption likho.

        Rules:
        - Pehli line: ek chhota, curiosity-driven TITLE Hindi (Devanagari) me (max 80 characters, halka emoji chalega).
        - Uske baad 1-2 lines ka chhota description jo viewer ko dekhne par majboor kare.
        - Phir ek khaali line, phir 8-10 relevant YouTube Shorts hashtags (Hindi + English mix, sab # ke saath, ek hi line me, #Shorts zaroor ho).
        - Sirf caption do — koi explanation, quotes ya "Title:"/"Caption:" jaisa label mat likho.

        Kahani ka title: {$title}
        Part number: {$partNo}
        Details: {$desc}
        TXT;

        return $this->appendHashtags($this->callAi($prompt), self::YOUTUBE_TRENDING_HASHTAGS, 15);
    }

    /**
     * Pollinations text API ko prompt bhejo aur saaf caption text wapas lao.
     *
     * @throws \RuntimeException
     */
    private function callAi(string $prompt): string
    {
        // Gemini (agar key ho) — behtar + reliable; warna Pollinations par fallback
        if (filled(config('services.gemini.key'))) {
            try {
                return $this->callGemini($prompt);
            } catch (\Throwable $e) {
                Log::warning('Caption AI: Gemini fail, Pollinations fallback', ['error' => $e->getMessage()]);
            }
        }

        return $this->callPollinations($prompt);
    }

    private function callGemini(string $prompt): string
    {
        $payload = [
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 1.0],
        ];
        $lastError = 'Gemini caption fail.';

        // Quota per-model — 2.5-flash busy ho to 2.0-flash try karo
        foreach (['gemini-2.5-flash', 'gemini-2.0-flash'] as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
            $res = Http::timeout(60)
                ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                ->post($url, $payload);

            if ($res->successful()) {
                $text = trim((string) $res->json('candidates.0.content.parts.0.text'));
                if ($text !== '') {
                    return $this->cleanCaption($text);
                }
            }
            $lastError = $res->json('error.message') ?? $lastError;
        }

        throw new \RuntimeException($lastError);
    }

    private function callPollinations(string $prompt): string
    {
        $url = 'https://text.pollinations.ai/' . rawurlencode($prompt);
        $status = 0;

        // openai-fast clean text; openai backup (mistral/llama Pollinations ne hata diye)
        foreach (['openai-fast', 'openai'] as $i => $model) {
            $res = Http::timeout(60)->get($url, ['model' => $model]);
            $caption = $this->unwrapChat(trim($res->body()));

            if ($res->successful() && $caption !== '' && ! Str::startsWith($caption, ['<', '{'])) {
                return $this->cleanCaption($caption);
            }
            $status = $res->status() ?: $status;
            if ($status === 429 && $i < 2) {
                sleep(2);
            }
        }

        throw new \RuntimeException('AI caption service abhi busy hai (HTTP ' . $status . '). Thodi der baad try karein.');
    }

    /** Pollinations kabhi chat-object / reasoning wrapper deta hai — actual text nikaalo. */
    private function unwrapChat(string $body): string
    {
        if (! str_starts_with($body, '{')) {
            return $body;
        }
        $obj = json_decode($body, true);
        if (! is_array($obj)) {
            return $body;
        }
        foreach ([
            $obj['choices'][0]['message']['content'] ?? null,
            $obj['choices'][0]['text'] ?? null,
            $obj['content'] ?? null,
            $obj['message']['content'] ?? null,
            $obj['text'] ?? null,
            $obj['response'] ?? null,
            is_string($obj['message'] ?? null) ? $obj['message'] : null,
        ] as $p) {
            if (is_string($p) && trim($p) !== '') {
                return trim($p);
            }
        }

        return $body;
    }

    /** Aage/peeche ke quote marks hata do agar AI ne laga diye. */
    private function cleanCaption(string $caption): string
    {
        return trim($caption, "\"' \n\r\t");
    }

    /**
     * Caption ke ant me trending hashtags jodo — jo pehle se present nahi hain
     * sirf wahi, aur `$maxTotal` hashtag limit ke andar rehte hue.
     */
    private function appendHashtags(string $caption, array $tags, int $maxTotal): string
    {
        // Caption me pehle se maujood hashtags (case-insensitive)
        preg_match_all('/#[\p{L}\p{N}_]+/u', $caption, $matches);
        $existing = array_map(fn ($h) => Str::lower($h), $matches[0]);

        $toAdd = array_values(array_filter(
            $tags,
            fn ($tag) => ! in_array(Str::lower($tag), $existing, true)
        ));

        $room = max(0, $maxTotal - count($existing));
        $toAdd = array_slice($toAdd, 0, $room);

        if (empty($toAdd)) {
            return $caption;
        }

        return rtrim($caption) . "\n" . implode(' ', $toAdd);
    }
}
