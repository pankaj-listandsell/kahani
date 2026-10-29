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
     * 1. 🔍 PUZZLES: Find the Odd One Out.
     */
    public function generatePuzzles(int $count = 5, string $language = 'gujarati'): array
    {
        $langPrompt = $this->langPrompt($language);

        $prompt = <<<PROMPT
You are a viral Instagram Reels puzzle generator.
Generate {$count} highly engaging "Find the Odd Character / Emoji" puzzle cards for social media reels.
Language: {$langPrompt}.

Rules:
- Choose similar-looking Gujarati/Hindi letters (e.g. 'ક' vs 'ફ', 'ધ' vs 'ઘ', 'દ' vs 'ડ', 'ત' vs 'ન', 'भ' vs 'म', 'घ' vs 'ध') or confusing emoji pairs (e.g. 😃 vs 😀, 🐱 vs 😸, 🍎 vs 🍏).
- Grid dimensions: rows = 7, cols = 8 (total 56 characters).
- Choose a random hidden location (row from 2 to 6, col from 2 to 7) for the odd character.
- `hook`: Catchy viral hook line in {$langPrompt} (e.g. "૫ સેકન્ડમાં છુપાયેલો 'ફ' શોધો! ૯૯% લોકો ફેલ ❌").
- `answer_text`: Reveal explanation (e.g. "સાચો જવાબ: ત્રીજી લાઈનમાં ૫મો અક્ષર 'ફ' છે! ✅").
- `caption`: Must NOT reveal the answer (row/col or position) — only invite viewers to comment their answer.

Return ONLY a raw JSON array of objects:
[
  {
    "base_char": "ક",
    "odd_char": "ફ",
    "grid_rows": 7,
    "grid_cols": 8,
    "odd_row": 3,
    "odd_col": 5,
    "hook": "૫ સેકન્ડમાં છુપાયેલો 'ફ' શોધો!",
    "answer_text": "સાચો જવાબ: ૩જી લાઈનમાં ૫મો અક્ષર 'ફ' છે! ✅",
    "caption": "શું તમે 5 સેકન્ડમાં શોધી શક્યા? તમારો સમય કમેન્ટ કરો! 👇 #puzzle #gujarat #quiz #reels",
    "hashtags": "#puzzle #gujarat #mindgame #viralquiz"
  }
]
PROMPT;

        return $this->callGeminiJson($prompt);
    }

    /**
     * 2. 🔮 MIND READER: Magic Math & Psychology Tricks.
     */
    public function generateMindReader(int $count = 3, string $language = 'gujarati'): array
    {
        $langPrompt = $this->langPrompt($language);

        $prompt = <<<PROMPT
You are a viral mind-reading magic creator for Reels & Shorts.
Generate {$count} interactive 4-step psychological or math mind-reading tricks.
Language: {$langPrompt}.

Structure for each trick:
- `title`: Catchy trick title (e.g. "હું તમારું મન વાંચીશ! 🧠")
- `step1`: First instruction (e.g. "૧ થી ૧૦ વચ્ચેનો કોઈ પણ એક નંબર ધારો...")
- `step2`: Second instruction (e.g. "તેને ૨ વડે ગુણો અને ૮ ઉમેરો...")
- `step3`: Third instruction (e.g. "હવે આવેલા જવાબને ૨ વડે ભાગો અને ધારેલો નંબર બાદ કરો...")
- `final_answer`: The calculated result (e.g. "તમારો છેલ્લો જવાબ ૪ છે! 🎯")
- `call_to_action`: "જો સાચું પડ્યું હોય તો હમણાં જ સબસ્ક્રાઇબ કરો! ✨"
- `caption`: Full viral caption with emojis. Must NOT reveal the final answer — ask viewers to comment their answer.

Return ONLY a raw JSON array of objects with keys: `title`, `step1`, `step2`, `step3`, `final_answer`, `call_to_action`, `caption`, `hashtags`.
PROMPT;

        return $this->callGeminiJson($prompt);
    }

    /**
     * 3. ⚖️ THIS OR THAT: Choose 1 Debate Challenge.
     */
    public function generateThisOrThat(int $count = 5, string $language = 'gujarati', string $category = 'general'): array
    {
        $langPrompt = $this->langPrompt($language);

        $prompt = <<<PROMPT
You are a viral poll and debate reel creator for Instagram & YouTube Shorts.
Generate {$count} highly engaging "This or That / Choose 1 (આ કે તે?)" split-screen choices in {$langPrompt}.
Category: {$category} (Career, Lifestyle, Wealth, Food, Relationships).

Rules:
- Both Option A and Option B must be super exciting, relatable, and hard to choose.
- `title`: Catchy title in {$langPrompt} (e.g. "તમે શું પસંદ કરશો? 🤔")
- `option_a`: Short punchy text for Option A + emoji (e.g. "સરકારી નોકરી (GPSC/Talati) 🏢")
- `option_b`: Short punchy text for Option B + emoji (e.g. "પોતાનો સફળ બિઝનેસ 🚀")
- `hook`: Hook question spoken in voiceover.
- `caption`: Viral caption inviting comments and debate.

Return ONLY a raw JSON array of objects with keys: `title`, `option_a`, `option_b`, `hook`, `caption`, `hashtags`.
PROMPT;

        return $this->callGeminiJson($prompt);
    }

    /**
     * 4. 🔤 NAME SECRETS: First Letter Personality Secrets.
     */
    public function generateNameSecrets(array $letters = ['A', 'S', 'P', 'R', 'K'], string $language = 'gujarati'): array
    {
        $langPrompt = $this->langPrompt($language);
        $lettersStr = implode(', ', $letters);

        $prompt = <<<PROMPT
You are a viral personality & zodiac name secrets creator for Reels.
Generate personality secret cards for these letters: [{$lettersStr}].
Language: {$langPrompt}.

For each letter, provide 3 deep, positive, relatable personality traits that make viewers feel amazed and tag their friends.
- `letter`: The letter (e.g. "A" or "S")
- `title`: (e.g. "'A' અક્ષર વાળા લોકોનું રહસ્ય 👑")
- `trait_1`: First trait (e.g. "દિલના એકદમ સાફ હોય છે, ક્યારેય કોઈનું ખોટું નથી વિચારતા.")
- `trait_2`: Second trait (e.g. "ગુસ્સો જલ્દી આવે પણ થોડી જ વારમાં શાંત થઈ જાય છે.")
- `trait_3`: Third trait (e.g. "દોસ્તી અને પ્રેમ જીવ આપીને નિભાવે છે.")
- `best_match`: Best compatibility letter (e.g. "S, P, K")
- `tag_cta`: "આ અક્ષર વાળા ખાસ દોસ્તને ટેગ કરો! 🤝"
- `caption`: Full viral caption with emojis and hashtags.

Return ONLY a raw JSON array of objects with keys: `letter`, `title`, `trait_1`, `trait_2`, `trait_3`, `best_match`, `tag_cta`, `caption`, `hashtags`.
PROMPT;

        return $this->callGeminiJson($prompt);
    }

    /**
     * 5. 💬 SALAH: Reader ka sawaal + expert jawab + 4 samadhan (advice column card).
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
