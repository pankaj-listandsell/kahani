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
        $langPrompt = $language === 'gujarati' ? 'Gujarati (ગુજરાતી)' : 'Hindi (हिंदी)';

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
        $langPrompt = $language === 'gujarati' ? 'Gujarati (ગુજરાતી)' : 'Hindi (हिंदी)';

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
- `caption`: Full viral caption with emojis

Return ONLY a raw JSON array of objects with keys: `title`, `step1`, `step2`, `step3`, `final_answer`, `call_to_action`, `caption`, `hashtags`.
PROMPT;

        return $this->callGeminiJson($prompt);
    }

    /**
     * 3. ⚖️ THIS OR THAT: Choose 1 Debate Challenge.
     */
    public function generateThisOrThat(int $count = 5, string $language = 'gujarati', string $category = 'general'): array
    {
        $langPrompt = $language === 'gujarati' ? 'Gujarati (ગુજરાતી)' : 'Hindi (हिंदी)';

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
        $langPrompt = $language === 'gujarati' ? 'Gujarati (ગુજરાતી)' : 'Hindi (हिंदी)';
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
     * Gemini JSON Helper
     */
    protected function callGeminiJson(string $prompt): array
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

        $lastError = 'AI generation failed.';
        $raw = '';

        foreach (['gemini-2.5-flash', 'gemini-2.5-flash-preview'] as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->apiKey;
            $res = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post($url, $payload);

            if ($res->successful()) {
                $raw = (string) $res->json('candidates.0.content.parts.0.text');
                if ($raw !== '') {
                    break;
                }
            }
            $lastError = $res->json('error.message') ?? $lastError;
            Log::warning('ViralStudio Gemini model failed', ['model' => $model, 'status' => $res->status()]);
        }

        if ($raw === '') {
            throw new \RuntimeException('Gemini AI API Error: ' . $lastError);
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
