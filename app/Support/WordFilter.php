<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 🚫 AI ke text me ashleel shabd badlo:
 *  1) config/word_filter.php ki list + AI ki pehle seekhi list (storage/app/word_filter_ai.json)
 *  2) phir (config 'ai' on ho to) Gemini se baaki ashleel / sharirik ang wale shabd dhoondho —
 *     AI poora text dobara NAHI likhta, sirf {galat: saaf} jodi deta hai; badalna yahin hota hai.
 * Sirf poore shabd badalte hain (Gujarati/Hindi me \b nahi chalta — akshar+matra se boundary).
 */
class WordFilter
{
    private const LEARNED = 'word_filter_ai.json';
    /** Admin page (Stories → 🚫 Shabd List) se saved list — na ho to config/word_filter.php ki 'words' */
    private const WORDS = 'word_filter_words.json';

    public static function clean(string $text): string
    {
        return self::cleanArray([$text])[0];
    }

    /** Array ke har string (nested bhi) me badlo — AI call poore array ke liye ek hi. */
    public static function cleanArray(array $data): array
    {
        self::$hits = 0;
        self::$aiStatus = 'off';

        $map = self::map();
        array_walk_recursive($data, function (&$v) use ($map) {
            if (is_string($v)) {
                $v = self::apply($v, $map);
            }
        });

        if (! config('word_filter.ai')) {
            return $data;
        }

        $all = [];
        array_walk_recursive($data, function ($v) use (&$all) {
            if (is_string($v) && trim($v) !== '') {
                $all[] = $v;
            }
        });
        $found = self::aiFind(implode("\n", $all)); // null = AI fail
        self::$aiStatus = $found === null ? 'fail' : ($found ? 'ok' : 'none');
        if (! $found) {
            return $data;
        }

        array_walk_recursive($data, function (&$v) use ($found) {
            if (is_string($v)) {
                $v = self::apply($v, $found);
            }
        });
        // Yaad sirf ekla shabd — AI ke phrase ("હલાવી રહી હતી") is kahani me badle, par hamesha ke liye nahi
        self::learn(array_filter($found, fn ($bad) => ! preg_match('/\s/u', (string) $bad), ARRAY_FILTER_USE_KEY));

        return $data;
    }

    /** Sirf list (config + AI ki seekhi) — bina AI call, turant. Har Part save par lagta hai. */
    public static function cleanLocal(string $text): string
    {
        return self::apply($text, self::map());
    }

    /** Hamari list + AI ki seekhi list (hamari upar). */
    private static function map(): array
    {
        return self::words() + self::learned();
    }

    /** Hamari list — admin page se saved file, warna config/word_filter.php. */
    public static function words(): array
    {
        $saved = self::readJson(self::WORDS);

        return $saved ?? (array) config('word_filter.words', []);
    }

    /** AI ke dhoondhe shabd. */
    public static function learned(): array
    {
        // Haath se galat lipi wali jodi aa jaaye to bhi mat lagao
        return array_filter(
            self::readJson(self::LEARNED) ?? [],
            fn ($good, $bad) => self::script((string) $bad) === self::script((string) $good),
            ARRAY_FILTER_USE_BOTH
        );
    }

    public static function saveWords(array $map): void
    {
        self::writeJson(self::WORDS, self::tidy($map));
    }

    public static function saveLearned(array $map): void
    {
        self::writeJson(self::LEARNED, self::tidy($map));
    }

    /** Khaali / ek jaise / bahut lambe jodi hatao. */
    private static function tidy(array $map): array
    {
        $out = [];
        foreach ($map as $bad => $good) {
            $bad = trim((string) $bad);
            $good = trim((string) $good);
            if ($bad !== '' && $good !== '' && $bad !== $good && mb_strlen($bad) <= 60) {
                $out[$bad] = $good;
            }
        }

        return $out;
    }

    /** null = file nahi hai. */
    private static function readJson(string $file): ?array
    {
        try {
            if (! Storage::disk('local')->exists($file)) {
                return null;
            }
            $arr = json_decode((string) Storage::disk('local')->get($file), true);

            return is_array($arr) ? $arr : [];
        } catch (\Throwable) {
            return null;
        }
    }

    private static function writeJson(string $file, array $data): void
    {
        Storage::disk('local')->put($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private static function learn(array $found): void
    {
        try {
            $all = self::learned();
            foreach ($found as $bad => $good) {
                $all[$bad] ??= $good;
            }
            self::writeJson(self::LEARNED, $all);
        } catch (\Throwable $e) {
            Log::warning('WordFilter: seekhi list save nahi hui', ['error' => $e->getMessage()]);
        }
    }

    /** Shabd ki lipi: gu / hi / latin. */
    private static function script(string $s): string
    {
        return match (true) {
            (bool) preg_match('/\p{Gujarati}/u', $s)   => 'gu',
            (bool) preg_match('/\p{Devanagari}/u', $s) => 'hi',
            default                                     => 'latin',
        };
    }

    private static function apply(string $text, array $map): string
    {
        if ($text === '' || ! $map) {
            return $text;
        }
        // Lamba shabd pehle — taaki chhota shabd uske andar ka hissa na badal de
        uksort($map, fn ($a, $b) => mb_strlen((string) $b) <=> mb_strlen((string) $a));

        foreach ($map as $bad => $good) {
            $bad = trim((string) $bad);
            if ($bad === '') {
                continue;
            }
            $good = (string) $good;
            // Gujarati me vibhakti shabd se judi aati hai (ભોસડીમાં, બોબલાને) — woh bhi pakdo aur
            // saaf shabd ke saath wahi pratyay lagao. "ગાંડો" ka "ો" matra hai, pratyay nahi — bacha rehta hai.
            $suffix = self::script($bad) === 'gu' ? '(' . self::GU_SUFFIX . ')?' : '()';
            // Story AI ka jawab kachcha JSON hota hai — "\nગાંડ" me shabd se pehle 'n' akshar hai,
            // isliye \n \r \t ke baad wala shabd bhi poora shabd maano
            $re = '/(?:(?<![\p{L}\p{M}])|(?<=\\\\[nrt]))' . preg_quote($bad, '/') . $suffix . '(?![\p{L}\p{M}])/u'
                . (self::script($bad) === 'latin' ? 'i' : ''); // Lund / LUND / lund
            $out = preg_replace_callback($re, function ($m) use ($good) {
                self::$hits++;

                return $good . ($m[1] ?? '');
            }, $text);
            if ($out !== null) { // galat UTF-8 par preg null deta hai — tab text jaisa tha waisa
                $text = $out;
            }
        }

        return $text;
    }

    /** Gujarati vibhakti / pratyay jo shabd se jud ke aate hain (lambe pehle). */
    private const GU_SUFFIX = 'માંથી|માંનો|માંની|માં|નાથી|થી|ને|નો|ની|નાં|ના|નું|એ|ઓ';

    /* ---- Pichhli cleanArray() ka hisaab — save ke message me dikhane ke liye ---- */
    private static int $hits = 0;
    private static string $aiStatus = 'off';

    /** ['words' => kitni jagah badla, 'ai' => ok | none | fail | off] */
    public static function lastReport(): array
    {
        return ['words' => self::$hits, 'ai' => self::$aiStatus];
    }

    /** Save ke baad dikhane layak ek line. */
    public static function lastSummary(): string
    {
        $r = self::lastReport();
        $ai = ['ok' => 'AI ne bhi tapasa', 'none' => 'AI ko naya shabd nahi mila', 'fail' => '⚠ AI abhi nahi chala — sirf list lagi', 'off' => 'AI band hai'][$r['ai']] ?? '';

        return "🚫 {$r['words']} jagah shabd badle ({$ai}).";
    }

    /**
     * Gemini se {galat shabd: saaf shabd} lo. [] = kuch nahi mila, null = AI fail (kahani rukti nahi).
     * Sirf woh jodi maani jaati hai jiska shabd sach me text me hai.
     */
    private static function aiFind(string $text): ?array
    {
        if (trim($text) === '') {
            return [];
        }
        $key = config('services.gemini.key');
        if (! filled($key)) {
            Log::warning('WordFilter AI fail: GEMINI_API_KEY nahi hai');

            return null;
        }

        $prompt = <<<PROMPT
You are a content-safety editor for a Gujarati/Hindi Instagram story page.
Find every word or short phrase in the TEXT below that is:
- an obscene, vulgar or abusive word / gaali (in any script: Gujarati, Hindi/Devanagari, Hinglish/Latin),
- a crude or explicit word for private/sexual body parts,
- a crude or explicit word for a sexual act.
For each one give a decent, non-explicit replacement in the SAME language and script that keeps the sentence natural.
The replacement must be a REAL, common, everyday word — prefer a soft, vague word (e.g. a crude word for breasts → "છાતી",
for male organ → "શરીર", for sex → "શારીરિક સંબંધ"); keep the same grammatical suffix (e.g. …ને → …ને). Never invent words.
Do NOT include normal or already-decent words (e.g. ગાંડો = crazy, પ્રેમ, ચુંબન, આલિંગન, અંગ, શરીર, છાતી, સ્તન), names.
Copy each found word EXACTLY as it appears in the text (same spelling and suffix).

Return ONLY a raw JSON object {"found word": "replacement", ...}. Return {} if there is nothing.

TEXT:
{$text}
PROMPT;

        foreach (config('services.gemini.text_models') as $model) {
            $gen = ['temperature' => 0, 'responseMimeType' => 'application/json'];
            if (str_starts_with($model, 'gemini-2.5')) {
                $gen['thinkingConfig'] = ['thinkingBudget' => 0];
            }
            try {
                $res = Http::timeout(120) // lambi kahani
                    ->withHeaders(['x-goog-api-key' => $key])
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                        'contents'         => [['parts' => [['text' => $prompt]]]],
                        'generationConfig' => $gen,
                        // Kaam hi ashleel text SAAF karna hai — Gemini ka safety block us text ko padhne hi na de
                        // to filter bekaar. Isliye block band (jawab sirf shabdon ki jodi hai).
                        'safetySettings'   => array_map(fn ($c) => ['category' => $c, 'threshold' => 'BLOCK_NONE'], [
                            'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'HARM_CATEGORY_HARASSMENT',
                            'HARM_CATEGORY_HATE_SPEECH', 'HARM_CATEGORY_DANGEROUS_CONTENT',
                        ]),
                    ]);
            } catch (\Throwable $e) {
                Log::warning('WordFilter AI fail', ['model' => $model, 'error' => $e->getMessage()]);
                continue;
            }
            if (! $res->successful()) {
                Log::warning('WordFilter AI fail', ['model' => $model, 'status' => $res->status()]);
                continue;
            }

            $raw = trim((string) $res->json('candidates.0.content.parts.0.text'));
            $raw = preg_replace(['/^```(?:json)?\s*/i', '/\s*```$/'], '', $raw);
            $arr = json_decode((string) $raw, true);
            if (! is_array($arr)) {
                // Khaali jawab = aksar safety block (promptFeedback / finishReason) — agla model try karo
                Log::warning('WordFilter AI: jawab samajh nahi aaya', [
                    'model'  => $model,
                    'block'  => $res->json('promptFeedback.blockReason'),
                    'finish' => $res->json('candidates.0.finishReason'),
                ]);
                continue;
            }
            // AI kabhi [{"word": …}] jaisa array de — use {shabd: saaf} me badlo
            if (array_is_list($arr)) {
                $arr = collect($arr)->filter(fn ($r) => is_array($r) && count($r) >= 2)
                    ->mapWithKeys(fn ($r) => [(string) array_values($r)[0] => (string) array_values($r)[1]])->all();
            }

            // Hamari list ke saaf shabd (અંગ, સ્તન …) AI dobara na badle — warna woh normal shabd hamesha ke liye badal jaate
            $decent = array_map('strval', array_values(self::words()));

            $out = [];
            foreach ($arr as $bad => $good) {
                if (in_array(trim((string) $bad), $decent, true)) {
                    continue;
                }
                $bad = trim((string) $bad);
                $good = trim((string) $good);
                if ($bad === '' || $good === '' || $bad === $good || mb_strlen($bad) > 40 || ! str_contains($text, $bad)
                    || self::script($bad) !== self::script($good)) { // Hindi shabd ki jagah Gujarati lipi nahi chalegi
                    continue;
                }
                $out[$bad] = $good;
            }
            if ($out) {
                Log::info('WordFilter AI ne shabd badle', $out);
            }

            return $out;
        }

        return null;
    }
}
