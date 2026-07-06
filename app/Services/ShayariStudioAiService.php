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
    public function generateBatch(string $type, string $category, int $count): array
    {
        $type     = in_array($type, ['shayari', 'joke', 'quote'], true) ? $type : 'shayari';
        $count    = max(1, min(30, $count));
        $category = trim($category) ?: 'general';

        $raw   = $this->callAi($this->prompt($type, $category, $count));
        $items = $this->parseItems($raw, $type);

        if (empty($items)) {
            throw new \RuntimeException('AI se content nahi bana. Dobara try karein.');
        }

        return array_slice($items, 0, $count);
    }

    protected function prompt(string $type, string $category, int $count): string
    {
        return match ($type) {
            'joke' => <<<TXT
            Tum ek mazedaar Hindi comedy writer ho. "{$category}" topic par {$count} chhote, saaf-suthre (family-friendly) Hindi jokes likho.
            Har joke me ek setup aur ek punchline ho — dono Devanagari me.
            Content ke hisab se 1-2 relevant emoji bhi daalo (jaise 😂🤣😅) — natural tarah se, khaaskar punchline me.
            SIRF ek valid JSON array return karo (koi markdown, koi backticks nahi), bilkul is format me:
            [{"text":"setup line 😅", "punchline":"punchline line 😂"}]
            Koi adult/offensive/political content nahi.
            TXT,
            'quote' => <<<TXT
            Tum ek prerak (motivational) Hindi lekhak ho. "{$category}" bhaav par {$count} chhote, dil chhoo lene wale original Hindi suvichar/quotes likho.
            Har ek 1-2 line ka ho — powerful aur meaningful.
            Har quote me 1-2 relevant emoji daalo jo bhaav se match kare (jaise ✨🌟💪🙏🔥).
            SIRF ek valid JSON array return karo (koi markdown, koi backticks nahi):
            [{"text":"quote yahan ✨ (Devanagari)"}]
            TXT,
            default => <<<TXT
            Tum ek behtareen Hindi shayar ho. "{$category}" bhaav/mausam par {$count} khoobsurat, original Hindi shayari likho.
            Har shayari 2 se 4 line ki ho — emotional aur gehri. Har line alag ho (line breaks ke saath).
            Bhaav ke hisab se 1-2 relevant emoji bhi daalo (jaise pyaar ❤️🌹, dard 💔😢, chaand 🌙, aankhein 👀) — natural tarah se, zyada nahi.
            SIRF ek valid JSON array return karo (koi markdown, koi backticks nahi):
            [{"text":"pehli line ❤️\ndusri line (Devanagari)"}]
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
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

        $res = Http::timeout(120)
            ->retry(2, 2000, throw: false)
            ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->post($url, [
                'contents'         => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature'      => 1.15, // thoda creative
                    'maxOutputTokens'  => 8192,
                ],
            ]);

        if (! $res->successful()) {
            Log::error('Studio AI Gemini failed', ['status' => $res->status(), 'body' => $res->json() ?: $res->body()]);
            throw new \RuntimeException($res->json('error.message') ?? 'Gemini generation fail.');
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
            throw new \RuntimeException('AI service ne error diya (HTTP ' . $res->status() . '). Thodi der baad try karein.');
        }

        $body = trim($res->body());
        if ($body === '') {
            throw new \RuntimeException('AI se khaali jawab aaya. Dobara try karein.');
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
            } elseif (is_array($row)) {
                $text  = trim((string) ($row['text'] ?? $row['setup'] ?? $row['shayari'] ?? $row['quote'] ?? ''));
                $punch = isset($row['punchline']) ? trim((string) $row['punchline']) : null;
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
