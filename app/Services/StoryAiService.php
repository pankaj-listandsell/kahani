<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ek "topic" se poori Hindi kahani (title + description + body) AI se generate
 * karta hai. Agar Gemini API key set hai to Gemini (behtar quality) use karta
 * hai, warna Pollinations text API (free, bina key) par gir jaata hai.
 */
class StoryAiService
{
    /**
     * @return array{title:string, description:string, body:string}
     * @throws \RuntimeException
     */
    public function generate(string $topic, string $length = 'short'): array
    {
        $topic = trim($topic);
        if ($topic === '') {
            throw new \RuntimeException('Topic khaali hai.');
        }

        $words = match ($length) {
            'long'   => '500-700',
            'medium' => '250-350',
            default  => '120-200',
        };

        $prompt = <<<TXT
        Tum ek expert Hindi kahani-lekhak ho. Neeche diye gaye topic par ek dilchasp, emotional Hindi (Devanagari) kahani likho jo YouTube Shorts / Instagram Reel ke liye perfect ho.

        Rules:
        - Kahani lगभग {$words} shabd ki ho — engaging aur curiosity-driven.
        - Chhote paragraphs, saral Hindi, natural kahani-sunane wala andaaz.
        - Ek strong hook se shuru karo, aur ek satisfying moral ya twist ending do.
        - SIRF ek valid JSON object return karo (koi markdown, koi backticks nahi), bilkul is format me:
        {"title": "chhota aakarshak Hindi title", "description": "1-2 line ka summary", "body": "poori kahani yahan (Devanagari)"}

        Topic: {$topic}
        TXT;

        $raw = $this->callAi($prompt);

        return $this->parse($raw);
    }

    /**
     * Kahani ke content se ek vivid English image-generation prompt banao
     * (cover/thumbnail ke liye). "Thumbnail Prompt" step.
     */
    public function coverPrompt(string $title, string $body): string
    {
        $body = Str::limit(strip_tags($body), 800, '');

        $prompt = <<<TXT
        You are an art director. Based on this Hindi story, write ONE vivid English image-generation prompt for a vertical 9:16 poster/thumbnail.
        Rules:
        - Describe the main scene, character(s), setting and mood cinematically.
        - Style: cinematic, dramatic lighting, high detail, eye-catching.
        - IMPORTANT: absolutely NO text, NO letters, NO words, NO watermark in the image.
        - Return ONLY the prompt (one line), nothing else.

        Story title: {$title}
        Story: {$body}
        TXT;

        $out = trim($this->callAi($prompt));
        // Kabhi-kabhi model quotes/label laga deta hai
        $out = trim($out, "\"' \n\r\t");

        $scene = $out !== '' ? $out : ($title . ', dramatic scene');

        // Image model (Pollinations flux) ke liye strong poster/quality keywords —
        // Gemini-jaisi cinematic quality free me paane ke liye.
        $style = ', cinematic movie poster, ultra detailed, dramatic cinematic lighting, '
            . 'moody atmosphere, highly detailed realistic faces, sharp focus, 8k, '
            . 'professional photography, vertical 9:16 poster, absolutely no text or letters';

        return $scene . $style;
    }

    /**
     * Poster ke liye ek chhoti punchy Hindi tagline (1 line).
     */
    public function tagline(string $title, string $body): string
    {
        $body = Str::limit(strip_tags($body), 600, '');

        $prompt = <<<TXT
        Neeche di gayi Hindi kahani ke liye ek chhoti, dramatic, curiosity-driven POSTER tagline likho — sirf 1 line Hindi (Devanagari) me, max 8 shabd. Koi quotes, koi explanation, sirf tagline.

        Title: {$title}
        Kahani: {$body}
        TXT;

        $out = trim($this->callAi($prompt), "\"' \n\r\t");
        // Ek hi line lo
        $out = trim(Str::of($out)->explode("\n")->first() ?? '');

        return $out !== '' ? Str::limit($out, 80, '') : 'एक कहानी जो दिल छू जाए';
    }

    /**
     * Gemini (agar key ho) warna Pollinations se text lao.
     */
    protected function callAi(string $prompt): string
    {
        if (filled(config('services.gemini.key'))) {
            try {
                return $this->callGemini($prompt);
            } catch (\Throwable $e) {
                Log::warning('Story AI: Gemini fail, Pollinations par fallback', ['error' => $e->getMessage()]);
            }
        }

        return $this->callPollinations($prompt);
    }

    protected function callGemini(string $prompt): string
    {
        $model = 'gemini-2.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $res = Http::timeout(150)
            ->retry(2, 2000, throw: false)
            ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->post($url, [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json', // saaf JSON force karo
                    'temperature'      => 1.0,
                ],
            ]);

        if (! $res->successful()) {
            Log::error('Story AI Gemini failed', ['status' => $res->status(), 'body' => $res->json() ?: $res->body()]);
            throw new \RuntimeException($res->json('error.message') ?? 'Gemini story generation fail.');
        }

        $text = (string) $res->json('candidates.0.content.parts.0.text');
        if ($text === '') {
            throw new \RuntimeException('Gemini se khaali jawab aaya.');
        }

        return $text;
    }

    protected function callPollinations(string $prompt): string
    {
        $res = Http::timeout(120)
            ->retry(2, 1500, throw: false)
            ->get('https://text.pollinations.ai/' . rawurlencode($prompt), ['model' => 'openai']);

        if (! $res->successful()) {
            throw new \RuntimeException('AI story service ne error diya (HTTP ' . $res->status() . '). Thodi der baad try karein.');
        }

        $body = trim($res->body());
        if ($body === '') {
            throw new \RuntimeException('AI se khaali jawab aaya. Dobara try karein.');
        }

        return $body;
    }

    /**
     * AI ke jawab me se JSON nikaal kar {title, description, body} do.
     * Agar JSON na mile to poore text ko body maan lo.
     */
    protected function parse(string $raw): array
    {
        // ```json ... ``` fences hatao
        $clean = preg_replace('/^```(?:json)?|```$/mi', '', trim($raw));

        // Pehla { se aakhri } tak (JSON block)
        $json = null;
        if (preg_match('/\{.*\}/s', $clean, $m)) {
            $json = json_decode($m[0], true);
        }

        if (is_array($json) && filled($json['body'] ?? null)) {
            return [
                'title'       => trim((string) ($json['title'] ?? '')) ?: 'Nayi Kahani',
                'description' => trim((string) ($json['description'] ?? '')),
                'body'        => trim((string) $json['body']),
            ];
        }

        // JSON nahi mila — poore text ko kahani maan lo, pehli line = title
        $text = trim($clean);
        if ($text === '') {
            throw new \RuntimeException('AI se valid kahani nahi bani. Dobara try karein.');
        }

        $firstLine = Str::of($text)->explode("\n")->first();

        return [
            'title'       => Str::limit(trim((string) $firstLine), 100, ''),
            'description' => '',
            'body'        => $text,
        ];
    }
}
