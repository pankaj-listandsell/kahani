<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ViralStudioAiService
{
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.key');
    }

    /**
     * 💬 SALAH: Reader ka sawaal + expert jawab + 4 samadhan (advice column card).
     */
    public function generateSalah(int $count = 3, string $language = 'gujarati', string $category = 'Parenting', string $topic = '', string $style = 'normal'): array
    {
        $langPrompt = $this->langPrompt($language);
        $topicLine = $topic !== '' ? "Specific topic: {$topic}. Every card must be about this topic (different angles)." : 'Pick a different, common real-life problem for each card.';

        // confession = lambi, dil kholkar likhi kahani (age, sheher, kya hua, kya mehsoos hua) + chhota jawab
        [$questionRule, $askerRule, $answerRule, $solutionsRule, $limit] = $style === 'confession'
            ? [
                '150-200 words, a heartfelt first-person confession told like a story: age, city, living situation, how it started, what happened step by step, how they felt (loneliness, attraction, guilt, confusion), what they tried, ends asking "what should I do?". Bold, gossip-worthy, emotionally honest, but never explicit.',
                'First name + city (e.g. "રિયા, અમદાવાદ").',
                '45-60 words: name the psychology behind the feeling (e.g. validation craving, loneliness, emotional dependence) without judging.',
                'Array of exactly 2 objects {"heading": 3-6 word step title, "text": 20-35 word explanation}.',
                320,
            ]
            : [
                '55-80 words, first person, starts with a greeting (e.g. "નમસ્તે,"), realistic detail, ends asking for advice.',
                'Short anonymous signature: who + city (e.g. "એક ચિંતિત માતા, વડોદરા").',
                '50-70 words: validate the feeling, explain the reason simply.',
                'Array of exactly 4 objects {"heading": 3-7 word bold step title, "text": 12-25 word explanation}.',
                260,
            ];

        $prompt = <<<PROMPT
You write a popular "reader asks, expert answers" advice column that is posted as Instagram Reels text cards.
Generate {$count} cards in {$langPrompt}.
Category: {$category}.
{$topicLine}

Tone & safety:
- Warm, respectful, practical — like a trusted elder sister / family counsellor. Relatable to Indian (Gujarati/Hindi) families.
- Sensitive topics (health, puberty, marriage, mental health) must be handled tastefully and non-explicitly.
- For health / medical / legal issues, never diagnose or prescribe medicine — recommend consulting a doctor or professional as one of the steps.
- No shaming, no superstition, no fear-mongering.
- Never mention, recommend or name any app, Telegram/WhatsApp bot, website, social media handle or brand — advice must point to real-life solutions (family, friends, hobbies, counsellor).
- Characters are always adults unless the category is about children/parenting; never describe minors in any romantic or sexual context.

Fields (all text in {$langPrompt}):
- `title`: Headline, 10-22 words, emotional and curiosity-driven, describes the situation (no quotes around it).
- `question`: The reader's letter, {$questionRule}
- `asker`: {$askerRule}
- `answer`: Empathetic expert reply, {$answerRule}
- `solutions`: {$solutionsRule}
- `takeaway`: One closing line of wisdom, 15-25 words.
- `cta_question`: One question asking viewers to share their opinion in comments, 12-25 words.
- `caption`: Instagram caption, 2-4 short lines with a few emojis, asks people to comment / share with someone who needs it.
- `hashtags`: 8-12 relevant hashtags in one string.

Keep total text per card under ~{$limit} words so it fits on one 9:16 card.

Return ONLY a raw JSON array of objects with keys: `title`, `question`, `asker`, `answer`, `solutions`, `takeaway`, `cta_question`, `caption`, `hashtags`.
PROMPT;

        return $this->callGeminiJson($prompt, 90);
    }

    /**
     * 🔯 RASHIFAL: Ek din ka 12 rashi ka bhavishya (Mesh → Meen, isi kram me).
     */
    public function generateRashifal(string $date, string $language = 'gujarati'): array
    {
        $langPrompt = $this->langPrompt($language);
        $day = \Illuminate\Support\Carbon::parse($date)->locale('en')->isoFormat('dddd, D MMMM YYYY');

        $prompt = <<<PROMPT
You are a popular, positive daily horoscope (rashifal) writer for Gujarati/Hindi Instagram audiences.
Write the horoscope for {$day} for all 12 Vedic moon signs, in {$langPrompt}.

Rules:
- Exactly 12 objects, in this exact order: Mesh, Vrishabh, Mithun, Kark, Simha, Kanya, Tula, Vrishchik, Dhanu, Makar, Kumbh, Meen.
- Each sign must feel different (do not repeat the same sentences). Practical, hopeful, relatable daily-life tone.
- No fear-mongering, no death/accident/disease predictions, no paid remedies, no gemstone selling, no superstition that harms anyone.
- `rashi`: the English key exactly as in the list above.
- `prediction`: 35-50 words — overall day, one opportunity and one thing to be careful about.
- `love`, `career`, `health`: 8-15 words each.
- `lucky_number`: one number 1-9 (as string). `lucky_color`: one colour name in {$langPrompt}.
- `caption`: 1-2 short lines with emojis asking followers to share with someone of this rashi.
- `hashtags`: 6-10 hashtags (include the rashi name and #rashifal).

Return ONLY a raw JSON array of 12 objects with keys: `rashi`, `prediction`, `love`, `career`, `health`, `lucky_number`, `lucky_color`, `caption`, `hashtags`.
PROMPT;

        return $this->callGeminiJson($prompt, 120);
    }

    /**
     * 🌿 NUSKHA / RECIPE: Gharelu nuskhe ya recipe ke save-worthy cards.
     */
    public function generateNuskha(int $count = 3, string $language = 'gujarati', string $mode = 'nuskha', string $category = '', string $topic = ''): array
    {
        $langPrompt = $this->langPrompt($language);
        $topicLine = $topic !== '' ? "Specific topic: {$topic}. Every card must be about this topic (different variations)." : 'Pick a different, popular item for each card.';

        $modeRules = $mode === 'recipe'
            ? <<<R
Content type: traditional Gujarati / Indian home RECIPES. Category: {$category}.
- `title`: Dish name + a hook, 4-10 words (e.g. "ઘરે બનાવો હોટેલ જેવા ખમણ 😋").
- `intro`: 15-25 words — why this recipe is special, prep time and serves (e.g. "૨૦ મિનિટ · ૪ વ્યક્તિ").
- `items`: 5-10 ingredients with quantity (each 2-8 words).
- `steps`: 4-7 clear cooking steps (each 8-20 words).
- `tip`: one secret tip for perfect taste (10-20 words).
R
            : <<<R
Content type: safe, traditional GHARELU NUSKHE (home remedies / kitchen tips / beauty tips). Category: {$category}.
- Only for minor everyday issues (e.g. cold, cough, acidity, dandruff, tanning, cleaning hacks). Use common, safe kitchen ingredients.
- Never claim to cure serious diseases (diabetes, cancer, BP, thyroid, etc.), never say "stop medicine", no dosages for babies or pregnant women.
- `title`: Problem + solution hook, 5-10 words (e.g. "શરદી-ખાંસીમાં તરત રાહત આપતો દેશી ઉકાળો ☕").
- `intro`: 15-25 words — what it helps with and why it works.
- `items`: 3-7 ingredients/things needed with quantity (each 2-8 words).
- `steps`: 3-6 steps how to make/use (each 8-20 words).
- `tip`: one caution line — e.g. allergy test, and to consult a doctor if the problem lasts more than 2-3 days (10-20 words).
R;

        $prompt = <<<PROMPT
You create highly save-worthy Instagram text cards in {$langPrompt}.
Generate {$count} cards.
{$topicLine}

{$modeRules}
- `caption`: 2-3 short lines with emojis, ask people to SAVE the post and share with family.
- `hashtags`: 8-12 relevant hashtags in one string.

Return ONLY a raw JSON array of objects with keys: `title`, `intro`, `items`, `steps`, `tip`, `caption`, `hashtags`.
PROMPT;

        return $this->callGeminiJson($prompt, 90);
    }

    /**
     * 💌 Prem Patra ki love themes (key → AI ke liye matlab). Card ka design (rang/emoji)
     * view me isi key se chunta hai — key badlo to patra/index.blade.php ka THEMES bhi.
     */
    public const PATRA_THEMES = [
        'pehlo_prem'    => 'First love — innocent first love from college days (both adults), shy feelings, the first meeting and first conversation.',
        'pati_patni'    => 'Husband/wife letter — gratitude and deep love for the life partner after years of marriage, small everyday moments together.',
        'long_distance' => 'Long-distance love — partners living in different cities or countries, missing each other, late-night calls, counting days to meet.',
        'judaai'        => 'Separation / breakup — a letter to a love that has ended; pain with dignity, no blame, wishing them happiness, moving on with strength.',
        'ek_tarfi'      => 'One-sided love — a letter never sent, to someone who never knew about this silent love.',
        'maafi'         => 'Sorry letter — apologising to the partner after a fight, admitting the mistake with honesty and promising to change.',
        'proposal'      => 'Confession / proposal — finally admitting love to a close friend and asking them to become the life partner.',
        'anniversary'   => 'Anniversary — celebrating years together, memories from the wedding day until today.',
        'budhapa'       => 'Old-age love — an elderly husband or wife after 40-50 years together; companionship, grey hair, holding hands, still in love.',
        'fauji'         => "Soldier's love — an army man posted at the border writing to his wife (or the wife writing to him); duty, pride and longing.",
        'yaad'          => 'Missing you / memories — remembering beautiful moments together: monsoon rain, old songs, the first gift, a shared cup of tea.',
        'sagai'         => 'Engagement / newly married — dreams for the new life together, nervous excitement, promises for the future.',
    ];

    /**
     * Love letter cards — har card ko ALAG theme. $theme = 'mix' ho to $count alag
     * themes random chuni jaati hain; warna sab cards usi ek theme me (alag kahani).
     */
    public function generatePremPatra(int $count = 3, string $language = 'gujarati', string $theme = 'mix', string $topic = ''): array
    {
        $langPrompt = $this->langPrompt($language);
        $count = max(1, min(12, $count));

        if (isset(self::PATRA_THEMES[$theme])) {
            $assigned = array_fill(0, $count, $theme);
        } else {
            $keys = array_keys(self::PATRA_THEMES);
            shuffle($keys);
            $assigned = array_slice($keys, 0, $count);
        }

        $plan = collect($assigned)
            ->map(fn ($key, $i) => 'Card ' . ($i + 1) . ": theme `{$key}` — " . self::PATRA_THEMES[$key])
            ->implode("\n");
        $topicLine = $topic !== '' ? "Extra direction from the page owner: {$topic}." : '';

        $prompt = <<<PROMPT
You write heart-touching, emotional LOVE LETTERS (prem patra) for Instagram cards in {$langPrompt}.
Generate exactly {$count} letters, one per line of this plan, in the same order. Each letter must clearly feel like its theme:
{$plan}
{$topicLine}

Rules for every letter:
- Written in first person, directly to the beloved ("તું/તમે" or "तुम/आप"), like a real handwritten letter — warm, specific, vivid little details (places, smells, songs, seasons), not generic lines.
- Different story, names and situation in every letter. Vary between a man writing and a woman writing.
- Both people are adults. Never involve minors. Nothing sexual or explicit — romantic and emotional only.
- No real or famous people, no brands, apps, social media handles or phone numbers.
- Sad themes stay hopeful and dignified: no self-harm, no threats, no stalking or obsession.
- `theme`: the theme key from the plan, exactly.
- `hook`: a short line shown above the letter, 4-9 words, makes people stop scrolling (e.g. "એક પત્ર, જે ક્યારેય મોકલી ન શક્યો 💔"). Max 1 emoji, never a flag emoji (flags show as letters on many phones).
- `salutation`: opening address, 2-4 words (e.g. "મારી વહાલી નેહા,").
- `body`: the letter, 90-130 words, 2-3 short paragraphs separated by "\\n\\n".
- `closing`: 2-4 words before the name (e.g. "હંમેશા તારો જ,").
- `signature`: just a common first name (e.g. "આકાશ").
- `caption`: 2-3 short lines with emojis — ask people to tag or send this letter to the person they love, and to save it.
- `hashtags`: 8-12 relevant hashtags (love letter, prem patra, language-specific) in one string.

Return ONLY a raw JSON array of objects with keys: `theme`, `hook`, `salutation`, `body`, `closing`, `signature`, `caption`, `hashtags`.
PROMPT;

        $items = $this->callGeminiJson($prompt, 120);

        // Design theme par chalta hai — AI galat/khaali key de to plan wali key lagao
        foreach ($items as $i => &$item) {
            if (! is_array($item)) {
                continue;
            }
            if (! isset(self::PATRA_THEMES[$item['theme'] ?? ''])) {
                $item['theme'] = $assigned[$i] ?? 'pehlo_prem';
            }
        }
        unset($item);

        return array_values(array_filter($items, 'is_array'));
    }

    /** Prompt ke liye language ka naam — hinglish = Hindi words, Roman (English) letters me. */
    protected function langPrompt(string $language): string
    {
        return match ($language) {
            'gujarati' => 'Gujarati (ગુજરાતી)',
            'hinglish' => 'Hinglish (Hindi words written in Roman/English letters, e.g. "Aap kya chunoge?")',
            default    => 'Hindi (हिंदी)',
        };
    }

    /**
     * Gemini JSON Helper
     */
    protected function callGeminiJson(string $prompt, int $timeout = 30): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Gemini API Key missing (.env me GEMINI_API_KEY).');
        }

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature'      => 0.7,
                'thinkingConfig'   => ['thinkingBudget' => 0],
                'responseMimeType' => 'application/json',
            ],
        ];

        $errors = [];
        $raw = '';

        // 503 (Google busy) aksar kuch second me theek ho jaata hai — har model ek baar retry, fir agla model
        foreach (config('services.gemini.text_models') as $model) {
            // thinkingBudget sirf 2.5 models samajhte hain — Gemini 3+ apna default use kare
            $body = $payload;
            if (! str_starts_with($model, 'gemini-2.5')) {
                unset($body['generationConfig']['thinkingConfig']);
            }
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->apiKey;

            foreach ([0, 2] as $wait) {
                sleep($wait);
                $res = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout($timeout)
                    ->post($url, $body);

                if ($res->successful()) {
                    $raw = (string) $res->json('candidates.0.content.parts.0.text');
                    if ($raw !== '') {
                        break 2;
                    }
                }
                Log::warning('ViralStudio Gemini model failed', ['model' => $model, 'status' => $res->status()]);
                if ($res->status() !== 503) {
                    break;
                }
            }
            $errors[] = $model . ': ' . ($res->status() === 503
                ? 'busy (503) — 1-2 minute baad dobara try karo'
                : ($res->json('error.message') ?? 'HTTP ' . $res->status()));
        }

        if ($raw === '') {
            throw new \RuntimeException('Gemini AI API Error: ' . implode(' | ', $errors));
        }
        $clean = trim($raw);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
        $clean = preg_replace('/\s*```$/', '', $clean);
        $clean = trim($clean);

        $data = json_decode($clean, true);
        if (! is_array($data)) {
            throw new \RuntimeException('Invalid JSON response from AI.');
        }

        return $data;
    }
}
