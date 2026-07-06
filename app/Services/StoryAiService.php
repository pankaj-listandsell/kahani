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
    public function generate(string $topic, string $length = 'short', string $language = 'hindi'): array
    {
        $topic = trim($topic);
        if ($topic === '') {
            throw new \RuntimeException('Topic khaali hai.');
        }

        $langRule = $this->langRule($language);

        $words = match ($length) {
            'long'   => '500-700',
            'medium' => '250-350',
            '1000'   => '900-1100',
            '1500'   => '1400-1600',
            '8000'   => '7500-8500',
            '20000'  => '19000-21000',
            default  => '120-200',
        };

        // Chhoti kahaniyan Shorts/Reel ke liye; badi (1000+ shabd) full-length
        // kahani ke andaaz me — warna framing ulti pad jaati hai.
        $isLong = ! in_array($length, ['short', 'medium', 'long'], true);

        $framing = $isLong
            ? 'ek dilchasp, emotional, detailed Hindi (Devanagari) kahani likho — ek complete full-length story jismein proper shuruaat, madhya aur ant ho.'
            : 'ek dilchasp, emotional Hindi (Devanagari) kahani likho jo YouTube Shorts / Instagram Reel ke liye perfect ho.';

        $lengthRule = $isLong
            ? "Kahani lagभग {$words} shabd ki ho — itni lambi zaroor ho, jaldi mat khatam karo. Kai paragraphs me detail, samvaad (dialogue) aur scene-building ke saath."
            : "Kahani lagभग {$words} shabd ki ho — engaging aur curiosity-driven.";

        $prompt = <<<TXT
        Tum ek expert kahani-lekhak ho. Neeche diye gaye topic par {$framing}

        Rules:
        - {$langRule}
        - {$lengthRule}
        - Chhote paragraphs, saral bhasha, natural kahani-sunane wala andaaz.
        - Ek strong hook se shuru karo, aur ek satisfying moral ya twist ending do.
        - SIRF ek valid JSON object return karo (koi markdown, koi backticks nahi), bilkul is format me:
        {"title": "chhota aakarshak title", "description": "1-2 line ka summary", "body": "poori kahani yahan"}
        - title, description aur body — teeno usi bhasha/script me hon jo upar rule me di gayi hai.

        Topic: {$topic}
        TXT;

        $raw = $this->callAi($prompt);

        return $this->parse($raw);
    }

    /**
     * Language ke hisab se AI ko script/bhasha instruction. Studio service bhi
     * yahi shabdावली use karti hai — consistency ke liye.
     */
    public static function langRule(string $language): string
    {
        return match ($language) {
            'gujarati' => 'Poora content GUJARATI (ગુજરાતી લિપિ) me likho — shuddh Gujarati bhasha, koi Hindi/English nahi.',
            'hinglish' => 'Content HINGLISH me likho — matlab Hindi hi (bolchaal wali), par ANGREZI/Roman (Latin) letters me. '
                . 'Devanagari bilkul mat use karo. Jaise: "Ek chhote gaon mein Ramu naam ka ladka rehta tha."',
            default    => 'Poora content HINDI (Devanagari lipi) me likho — saral, sahaj Hindi.',
        };
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
        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json', // saaf JSON force karo
                'temperature'      => 1.0,
                // Lambi kahaniyan (8000/20000 shabd) beech me na katein.
                'maxOutputTokens'  => 65536,
            ],
        ];

        $lastError = 'Gemini story generation fail.';

        // Free-tier quota har model ki alag hoti hai — 2.5-flash busy (429) ho to
        // 2.0-flash try karo (aksar quota bacha hota hai).
        foreach (['gemini-2.5-flash', 'gemini-2.0-flash'] as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
            $res = Http::timeout(150)
                ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                ->post($url, $payload);

            if ($res->successful()) {
                $text = (string) $res->json('candidates.0.content.parts.0.text');
                if ($text !== '') {
                    return $text;
                }
            }

            $lastError = $res->json('error.message') ?? $lastError;
            Log::warning('Story AI Gemini model fail', ['model' => $model, 'status' => $res->status()]);
        }

        throw new \RuntimeException($lastError);
    }

    protected function callPollinations(string $prompt): string
    {
        $url = 'https://text.pollinations.ai/' . rawurlencode($prompt);
        $status = 0;

        // Ek model busy (429) ho to doosra try karo
        foreach (['openai', 'mistral'] as $i => $model) {
            $res = Http::timeout(120)->get($url, ['model' => $model]);
            $body = trim($res->body());

            if ($res->successful() && $body !== '') {
                return $body;
            }

            $status = $res->status();
            if ($status === 429 && $i === 0) {
                sleep(3); // thoda ruk kar agla model
            }
        }

        throw new \RuntimeException(
            'AI service abhi bahut busy hai (HTTP ' . $status . '). 1-2 minute baad dobara try karein — '
            . 'ya reliable/unlimited ke liye Gemini API billing enable karein.'
        );
    }

    /**
     * AI ke jawab me se JSON nikaal kar {title, description, body} do.
     * Agar JSON na mile to poore text ko body maan lo.
     */
    protected function parse(string $raw): array
    {
        // ```json ... ``` fences hatao
        $clean = preg_replace('/^```(?:json)?|```$/mi', '', trim($raw));

        // Pehla { se aakhri } tak (poora JSON block). Truncated response me
        // closing } na ho to pehle { se aage sab kuch le lo.
        $block = null;
        if (preg_match('/\{.*\}/s', $clean, $m)) {
            $block = $m[0];
        } elseif (($pos = strpos($clean, '{')) !== false) {
            $block = substr($clean, $pos);
        }

        if ($block !== null) {
            // 1) Seedha decode. 2) Agar fail (lambi kahaniyon me aksar body ke
            //    andar raw newlines hote hain jo JSON me invalid hain) to string
            //    ke andar ke control-chars escape karke dobara decode karo.
            foreach ([$block, $this->repairJsonControlChars($block)] as $candidate) {
                $json = json_decode($candidate, true);
                if (is_array($json) && filled($json['body'] ?? null)) {
                    return $this->pack($json['title'] ?? '', $json['description'] ?? '', $json['body']);
                }
            }

            // 3) JSON truncated/malformed — fields regex se seedhe nikaalo taaki
            //    poora raw JSON textarea me na dikhe.
            $fields = $this->extractFields($this->repairJsonControlChars($block));
            if ($fields !== null) {
                return $fields;
            }
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

    /**
     * {title, description, body} ko normalize karke return karo.
     */
    protected function pack(mixed $title, mixed $description, mixed $body): array
    {
        return [
            'title'       => trim((string) $title) ?: 'Nayi Kahani',
            'description' => trim((string) $description),
            'body'        => trim((string) $body),
        ];
    }

    /**
     * JSON string values ke andar ke raw control-characters (newline, tab, etc.)
     * ko escape karo. AI aksar body ke andar seedhe newlines daal deta hai jo
     * standard JSON me invalid hai — isse json_decode fail ho jaata tha.
     * Bytes-wise safe hai: UTF-8 (Devanagari) ke saare bytes >= 0x80 hote hain,
     * kabhi control-char (< 0x20) ya quote/backslash se match nahi karte.
     */
    protected function repairJsonControlChars(string $s): string
    {
        $out = '';
        $inStr = false;
        $esc = false;
        $len = strlen($s);

        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];

            if ($esc) {
                $out .= $c;
                $esc = false;
                continue;
            }
            if ($c === '\\') {
                $out .= $c;
                $esc = true;
                continue;
            }
            if ($c === '"') {
                $inStr = ! $inStr;
                $out .= $c;
                continue;
            }
            if ($inStr && $c < ' ') {
                $out .= match ($c) {
                    "\n"    => '\\n',
                    "\r"    => '\\r',
                    "\t"    => '\\t',
                    default => sprintf('\\u%04x', ord($c)),
                };
                continue;
            }
            $out .= $c;
        }

        return $out;
    }

    /**
     * Repaired (control-chars escaped) JSON block se title/description/body
     * regex se nikaalo — truncated JSON (body ka closing quote missing) bhi
     * handle karta hai.
     */
    protected function extractFields(string $s): ?array
    {
        $val = static function (string $key) use ($s): ?string {
            // "key": "....."  (escaped quotes \" ko allow karo)
            if (preg_match('/"' . $key . '"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/s', $s, $m)) {
                $decoded = json_decode('"' . $m[1] . '"');
                return is_string($decoded) ? $decoded : stripcslashes($m[1]);
            }
            return null;
        };

        $title = $val('title');
        $description = $val('description');
        $body = $val('body');

        // Body ka closing quote na mila (truncated) — "body":" ke baad sab kuch lo
        if ($body === null && preg_match('/"body"\s*:\s*"(.*)$/s', $s, $m)) {
            $tail = preg_replace('/"\s*\}?\s*$/', '', $m[1]); // trailing "}" / quote saaf
            $decoded = json_decode('"' . $tail . '"');
            $body = is_string($decoded) ? $decoded : stripcslashes($tail);
        }

        if ($body === null || trim($body) === '') {
            return null;
        }

        return $this->pack($title ?? '', $description ?? '', $body);
    }
}
