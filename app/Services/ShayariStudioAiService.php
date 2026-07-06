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
        $type     = in_array($type, ['shayari', 'joke', 'quote'], true) ? $type : 'shayari';
        $count    = max(1, min(30, $count));
        $category = trim($category) ?: 'general';

        $raw   = $this->callAi($this->prompt($type, $category, $count, $language));
        $items = $this->parseItems($raw, $type);

        if (empty($items)) {
            throw new \RuntimeException('AI se content nahi bana. Dobara try karein.');
        }

        return array_slice($items, 0, $count);
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

        // Ek model busy (429) ho to doosra try karo
        foreach (['openai', 'mistral'] as $i => $model) {
            $res = Http::timeout(120)->get($url, ['model' => $model]);
            $body = trim($res->body());

            if ($res->successful() && $body !== '') {
                return $body;
            }

            $status = $res->status();
            if ($status === 429 && $i === 0) {
                sleep(3);
            }
        }

        throw new \RuntimeException(
            'AI service abhi bahut busy hai (HTTP ' . $status . '). 1-2 minute baad dobara try karein — '
            . 'ya reliable ke liye Gemini API billing enable karein.'
        );
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
