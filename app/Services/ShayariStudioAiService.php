<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ek topic/category par ek saath kai Hindi shayari / jokes / suvichar (quotes)
 * AI se generate karta hai — Studio ke batch generator ke liye. Gemini (agar key)
 * warna Pollinations (free) par gir jaata hai. Har item = ek card banega.
 */
class ShayariStudioAiService
{
    /**
     * @return list<array{text:string, punchline?:string}>
     * @throws \RuntimeException
     */
    public function generateBatch(string $type, string $category, int $count, string $language = 'hindi'): array
    {
        $type     = in_array($type, ['shayari', 'joke', 'quote', 'status', 'fact'], true) ? $type : 'shayari';
        $count    = max(1, min(30, $count));
        $category = trim($category) ?: 'general';

        $raw   = $this->callAi($this->prompt($type, $category, $count, $language));
        $items = $this->parseItems($raw, $type);

        if (empty($items)) {
            throw new \RuntimeException('AI se content nahi bana. Dobara try karein.');
        }

        return array_slice($items, 0, $count);
    }

    /**
     * Quiz (MCQ) generate karo — competitive-exam style.
     *
     * @return list<array{question:string, options:list<string>, answer:string, reason:string, hashtags:string}>
     * @throws \RuntimeException
     */
    public function generateQuiz(string $category, int $count, string $language = 'hindi'): array
    {
        $count    = max(1, min(30, $count));
        $category = trim($category) ?: 'general knowledge';

        $prompt = $this->quizPrompt($category, $count, $language);
        $items  = $this->parseQuiz($this->callAi($prompt));

        // Kabhi model galat format deta hai — ek baar dobara try karo
        if (empty($items)) {
            $raw = $this->callAi($prompt);
            Log::warning('Quiz parse empty, retry', ['raw' => mb_substr($raw, 0, 400)]);
            $items = $this->parseQuiz($raw);
        }

        if (empty($items)) {
            throw new \RuntimeException('AI se quiz nahi bana. Dobara try karein.');
        }

        return array_slice($items, 0, $count);
    }

    protected function quizPrompt(string $category, int $count, string $language): string
    {
        $lang = StoryAiService::langRule($language);

        return <<<TXT
        Tum ek expert quiz-master ho jo competitive exam (jaise "{$category}") ki taiyari karwate ho.
        "{$category}" topic par {$count} multiple-choice questions (MCQ) banao — factual aur accurate.
        {$lang}
        Rules (har question ke liye):
        - EXACTLY 4 options do.
        - "answer" me sahi option ka letter do: "A", "B", "C" ya "D".
        - "reason" me ek chhoti 1-line wajah do (kyun sahi hai).
        - Har item ke saath 6-10 safe, relevant hashtags "hashtags" me (banned/sensitive nahi).
        SIRF ek valid JSON array return karo (koi markdown, koi backticks nahi), bilkul is format me:
        [{"question":"prashn yahan?", "options":["pehla","dusra","teesra","chautha"], "answer":"B", "reason":"chhoti wajah", "hashtags":"#quiz #gk #exam"}]
        TXT;
    }

    protected function prompt(string $type, string $category, int $count, string $language = 'hindi'): string
    {
        // Bhasha/script rule — StoryAiService ke saath consistent
        $lang = StoryAiService::langRule($language);

        // Har item ke saath caption ke liye hashtags — safe & relevant
        $tagRule = 'Har item ke saath "hashtags" bhi do — 6 se 10 relevant, popular hashtags '
            . '(Instagram/YouTube ke liye). SIRF SAFE hashtags — koi banned/sensitive/adult/self-harm '
            . 'wale nahi. Ek string me, space se alag, har tag # se shuru.';

        return match ($type) {
            'joke' => <<<TXT
            Tum ek mazedaar comedy writer ho. "{$category}" topic par {$count} chhote, saaf-suthre (family-friendly) jokes likho.
            {$lang}
            Har joke me ek setup aur ek punchline ho.
            Content ke hisab se 1-2 relevant emoji bhi daalo (jaise 😂🤣😅) — natural tarah se, khaaskar punchline me.
            {$tagRule}
            SIRF ek valid JSON array return karo (koi markdown, koi backticks nahi), bilkul is format me:
            [{"text":"setup line 😅", "punchline":"punchline line 😂", "hashtags":"#jokes #comedy #hindi #funny #viral"}]
            Koi adult/offensive/political content nahi.
            TXT,
            'quote' => <<<TXT
            Tum ek prerak (motivational) lekhak ho. "{$category}" bhaav par {$count} chhote, dil chhoo lene wale original suvichar/quotes likho.
            {$lang}
            Har ek 1-2 line ka ho — powerful aur meaningful.
            Har quote me 1-2 relevant emoji daalo jo bhaav se match kare (jaise ✨🌟💪🙏🔥).
            {$tagRule}
            SIRF ek valid JSON array return karo (koi markdown, koi backticks nahi):
            [{"text":"quote yahan ✨", "hashtags":"#suvichar #motivation #hindi #quotes #life"}]
            TXT,
            'status' => <<<TXT
            Tum ek trendy social-media writer ho. "{$category}" par {$count} chhote, catchy WhatsApp/Instagram STATUS likho.
            {$lang}
            Har status sirf 1 line (max 2 short lines) ka ho — punchy, relatable aur shareable.
            Bhaav ke hisab se 1-2 relevant emoji daalo (zyada nahi).
            {$tagRule}
            SIRF ek valid JSON array return karo (koi markdown, koi backticks nahi):
            [{"text":"status line yahan 🔥", "hashtags":"#status #whatsappstatus #attitude #viral #trending"}]
            TXT,
            'fact' => <<<TXT
            Tum ek rochak-tathya (interesting facts) writer ho. "{$category}" par {$count} chaunkane wale, sacche aur verified facts likho.
            {$lang}
            Har fact 1-2 line ka ho — "Kya aap jaante hain?" style, curiosity jagane wala. Sirf accurate facts.
            Content ke hisab se 1-2 relevant emoji daalo (jaise 🤯🌍🧠🔬).
            {$tagRule}
            SIRF ek valid JSON array return karo (koi markdown, koi backticks nahi):
            [{"text":"rochak fact yahan 🤯", "hashtags":"#facts #didyouknow #gk #amazingfacts #hindi"}]
            TXT,
            default => <<<TXT
            Tum ek behtareen shayar ho. "{$category}" bhaav/mausam par {$count} khoobsurat, original shayari likho.
            {$lang}
            Har shayari 2 se 4 line ki ho — emotional aur gehri. Har line alag ho (line breaks ke saath).
            Bhaav ke hisab se 1-2 relevant emoji bhi daalo (jaise pyaar ❤️🌹, dard 💔😢, chaand 🌙) — natural tarah se, zyada nahi.
            {$tagRule}
            SIRF ek valid JSON array return karo (koi markdown, koi backticks nahi):
            [{"text":"pehli line ❤️\ndusri line", "hashtags":"#shayari #love #hindi #ishq #viral"}]
            TXT,
        };
    }

    /* ===================================================================
     |  AI call (Gemini → Pollinations)
     * =================================================================== */

    protected function callAi(string $prompt): string
    {
        if (filled(config('services.gemini.key'))) {
            try {
                return $this->callGemini($prompt);
            } catch (\Throwable $e) {
                Log::warning('Studio AI: Gemini fail, Pollinations fallback', ['error' => $e->getMessage()]);
            }
        }

        return $this->callPollinations($prompt);
    }

    protected function callGemini(string $prompt): string
    {
        $payload = [
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature'      => 1.15, // thoda creative
                'maxOutputTokens'  => 8192,
            ],
        ];

        $lastError = 'Gemini generation fail.';

        // Free-tier quota har model ki alag — 2.5-flash busy ho to 2.0-flash try karo
        foreach (['gemini-2.5-flash', 'gemini-2.0-flash'] as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
            $res = Http::timeout(120)
                ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                ->post($url, $payload);

            if ($res->successful()) {
                $text = (string) $res->json('candidates.0.content.parts.0.text');
                if ($text !== '') {
                    return $text;
                }
            }

            $lastError = $res->json('error.message') ?? $lastError;
            Log::warning('Studio AI Gemini model fail', ['model' => $model, 'status' => $res->status()]);
        }

        throw new \RuntimeException($lastError);
    }

    protected function callPollinations(string $prompt): string
    {
        $url = 'https://text.pollinations.ai/' . rawurlencode($prompt);
        $status = 0;

        // openai-fast clean JSON deta hai; openai backup (mistral/llama Pollinations ne hata diye)
        foreach (['openai-fast', 'openai'] as $i => $model) {
            $res = Http::timeout(120)->get($url, ['model' => $model]);

            if ($res->successful()) {
                $body = $this->unwrapChat(trim($res->body()));
                // Reasoning/error wrapper na ho — asli usable text
                if ($body !== '' && ! preg_match('/^\{\s*"(role|reasoning|error)"/', $body)) {
                    return $body;
                }
            }

            $status = $res->status() ?: $status;
            if ($status === 429 && $i < 2) {
                sleep(3);
            }
        }

        throw new \RuntimeException(
            'AI service abhi bahut busy hai (HTTP ' . $status . '). 1-2 minute baad dobara try karein — '
            . 'ya reliable ke liye Gemini API billing enable karein.'
        );
    }

    /**
     * Pollinations kabhi plain text ke bajaye chat-object deta hai
     * ({"content":"..."} / {"choices":[...]} / reasoning wrapper) — usme se actual
     * text nikaalo.
     */
    protected function unwrapChat(string $body): string
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

    /* ===================================================================
     |  Parse — JSON array nikaalo (robust)
     * =================================================================== */

    /**
     * AI ke jawab me se JSON array nikaal kar items do. JSON invalid/truncated
     * ho to raw newlines escape karke ya lines ko items maan kar recover karta hai.
     *
     * @return list<array{text:string, punchline?:string}>
     */
    protected function parseItems(string $raw, string $type): array
    {
        $clean = preg_replace('/^```(?:json)?|```$/mi', '', trim($raw));

        // Pehla [ se aakhri ] tak (truncated ho to [ se aage sab)
        $block = null;
        if (preg_match('/\[.*\]/s', $clean, $m)) {
            $block = $m[0];
        } elseif (($p = strpos($clean, '[')) !== false) {
            $block = substr($clean, $p);
        }

        $decoded = null;
        if ($block !== null) {
            foreach ([$block, $this->repairJsonControlChars($block)] as $candidate) {
                $try = json_decode($candidate, true);
                if (is_array($try)) {
                    $decoded = $try;
                    break;
                }
            }
        }

        // JSON na mila — do line-break se alag karke items maan lo
        if (! is_array($decoded)) {
            $decoded = collect(preg_split('/\n{2,}/', $clean))
                ->map(fn ($l) => trim($l))
                ->filter()
                ->map(fn ($l) => ['text' => $l])
                ->values()
                ->all();
        }

        $items = [];
        foreach ($decoded as $row) {
            if (is_string($row)) {
                $text  = trim($row);
                $punch = null;
                $tags  = '';
            } elseif (is_array($row)) {
                $text  = trim((string) ($row['text'] ?? $row['setup'] ?? $row['shayari'] ?? $row['quote'] ?? ''));
                $punch = isset($row['punchline']) ? trim((string) $row['punchline']) : null;
                $tags  = trim((string) ($row['hashtags'] ?? ''));
            } else {
                continue;
            }

            if ($text === '') {
                continue;
            }

            $item = ['text' => $text];
            if ($type === 'joke' && filled($punch)) {
                $item['punchline'] = $punch;
            }
            if (filled($tags)) {
                $item['hashtags'] = $tags;
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Quiz JSON parse — {question, options[4], answer, reason, hashtags}.
     *
     * @return list<array{question:string, options:list<string>, answer:string, reason:string, hashtags:string}>
     */
    protected function parseQuiz(string $raw): array
    {
        $items = [];

        foreach ($this->decodeArray($raw) as $row) {
            if (! is_array($row)) {
                continue;
            }

            // Question — alag-alag models alag keys use karte hain
            $question = $this->asString(
                $row['question'] ?? $row['q'] ?? $row['prashn'] ?? $row['title'] ?? $row['ques'] ?? ''
            );

            // Options — har ek ko safely string banao (AI kabhi object/nested deta hai)
            $rawOpts = $row['options'] ?? $row['choices'] ?? $row['answers'] ?? $row['opts'] ?? [];
            $options = [];
            foreach ((array) $rawOpts as $o) {
                $s = $this->asString($o);
                if ($s !== '') {
                    $options[] = $s;
                }
            }

            if ($question === '' || count($options) < 2) {
                continue;
            }

            // Sirf pehle 4 options; answer letter A–D normalize
            $options = array_slice($options, 0, 4);
            $ans     = strtoupper($this->asString(
                $row['answer'] ?? $row['correct'] ?? $row['correct_answer'] ?? $row['ans'] ?? 'A'
            ));
            if (! preg_match('/^[A-D]$/', $ans)) {
                // number (1-4) ya option-text bhi handle karo
                if (preg_match('/^[1-4]$/', $ans)) {
                    $ans = chr(64 + (int) $ans); // 1->A
                } else {
                    $idx = array_search($ans, array_map('strtoupper', $options), true);
                    $ans = $idx !== false ? chr(65 + $idx) : 'A';
                }
            }
            // Answer index options ki range me ho
            if (ord($ans) - 65 >= count($options)) {
                $ans = 'A';
            }

            $items[] = [
                'question' => $question,
                'options'  => $options,
                'answer'   => $ans,
                'reason'   => $this->asString($row['reason'] ?? $row['explanation'] ?? $row['reasoning'] ?? ''),
                'hashtags' => $this->asString($row['hashtags'] ?? $row['tags'] ?? ''),
            ];
        }

        return $items;
    }

    /**
     * Kisi bhi AI value ko safely string banao — scalar hi, warna array me se
     * text/value nikaalo ya join karo. "Array to string conversion" se bachne ke liye.
     */
    protected function asString(mixed $v): string
    {
        if (is_scalar($v)) {
            return trim((string) $v);
        }

        if (is_array($v)) {
            // Kabhi option {"text": "..."} / {"option": "..."} jaisa aata hai
            foreach (['text', 'value', 'label', 'option', 'answer', 'title'] as $k) {
                if (isset($v[$k]) && is_scalar($v[$k])) {
                    return trim((string) $v[$k]);
                }
            }
            // Flat scalars ko jodo
            $flat = array_filter($v, 'is_scalar');

            return trim(implode(' ', array_map('strval', $flat)));
        }

        return '';
    }

    /** AI ke jawab me se items array nikaalo (repair ke saath, robust). */
    protected function decodeArray(string $raw): array
    {
        $clean = trim(preg_replace('/^```(?:json)?|```$/mi', '', trim($raw)));

        $firstBracket = strpos($clean, '[');
        $firstBrace   = strpos($clean, '{');

        // Response '[' se shuru (array) — seedha list
        if ($firstBracket !== false && ($firstBrace === false || $firstBracket < $firstBrace)) {
            if (preg_match('/\[.*\]/s', $clean, $m)) {
                foreach ([$m[0], $this->repairJsonControlChars($m[0])] as $cand) {
                    $try = json_decode($cand, true);
                    if (is_array($try) && $try !== []) {
                        return $try;
                    }
                }
            }
        }

        // Response '{' se shuru (object) — nested array ya single item
        if ($firstBrace !== false && preg_match('/\{.*\}/s', $clean, $m)) {
            foreach ([$m[0], $this->repairJsonControlChars($m[0])] as $cand) {
                $obj = json_decode($cand, true);
                if (! is_array($obj)) {
                    continue;
                }
                foreach (['questions', 'items', 'quiz', 'data', 'mcqs', 'result'] as $k) {
                    if (isset($obj[$k]) && is_array($obj[$k]) && $obj[$k] !== []) {
                        return $obj[$k];
                    }
                }
                if (isset($obj['question']) || isset($obj['text']) || isset($obj['q'])) {
                    return [$obj];
                }
            }
        }

        // Last resort — koi bhi array block
        if (preg_match('/\[.*\]/s', $clean, $m)) {
            $try = json_decode($this->repairJsonControlChars($m[0]), true);
            if (is_array($try) && $try !== []) {
                return $try;
            }
        }

        return [];
    }

    /**
     * JSON string values ke andar ke raw control-chars (newline/tab) escape karo
     * taaki json_decode fail na ho. Bytes-wise safe (UTF-8 Devanagari bytes >= 0x80).
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
}
