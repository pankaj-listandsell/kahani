<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Kids Yoga cards ke liye content generator.
 *
 * Aasan ki list AI se NAHI aati — wo {@see YogaPoseLibrary} me curated hai
 * (safety). AI sirf har aasan ke 3 aasan-se steps, ek fayda aur hashtags likhta
 * hai, chuni hui bhasha me.
 *
 * AI plumbing (Gemini → Pollinations fallback, JSON repair/parse) parent se
 * reuse hoti hai — dobara likhne ki zaroorat nahi.
 */
class YogaAiService extends ShayariStudioAiService
{
    /** Kids-yoga niche ke trending tags (parent ke general viral tags ki jagah). */
    private const YOGA_HASHTAGS = [
        '#kidsyoga', '#yogaforkids', '#yoga', '#yogaeveryday', '#kidsfitness',
        '#healthykids', '#bacchonkeliye', '#yogaathome', '#shorts', '#reels',
        '#viral', '#trending', '#parenting', '#india', '#fitkids',
    ];

    /**
     * Chune hue aasan ke liye steps/fayde generate karo.
     *
     * @param  list<string>  $poseKeys  YogaPoseLibrary ki keys
     * @return list<array{key:string, name:string, name_en:string, emoji:string, steps:list<string>, benefit:string, hashtags:string}>
     *
     * @throws \RuntimeException
     */
    public function generatePoses(array $poseKeys, string $language = 'hindi'): array
    {
        $poseKeys = array_slice(YogaPoseLibrary::filterKeys($poseKeys), 0, 20);

        if (empty($poseKeys)) {
            throw new \RuntimeException('Kam se kam ek aasan chuno.');
        }

        $prompt = $this->posePrompt($poseKeys, $language);
        $items  = $this->parsePoses($this->callAi($prompt), $poseKeys);

        // Model kabhi format bigaad deta hai — ek retry
        if (empty($items)) {
            $raw = $this->callAi($prompt);
            Log::warning('Yoga parse empty, retry', ['raw' => mb_substr($raw, 0, 400)]);
            $items = $this->parsePoses($raw, $poseKeys);
        }

        if (empty($items)) {
            throw new \RuntimeException('AI se yoga content nahi bana. Dobara try karein.');
        }

        return $items;
    }

    protected function posePrompt(array $poseKeys, string $language): string
    {
        $lang = StoryAiService::langRule($language);

        $list = '';
        foreach ($poseKeys as $key) {
            $pose = YogaPoseLibrary::get($key);
            $list .= "- key: {$key} | naam: {$pose['hi']} ({$pose['en']}) | pose: {$pose['scene']}\n";
        }

        return <<<TXT
        Tum bachchon (6-12 saal) ke liye ek friendly yoga teacher ho. Neeche di gayi aasan
        list me se HAR aasan ke liye chhota, aasan content likho jo ek social-media card par aaye.
        {$lang}

        Aasan list:
        {$list}
        Har aasan ke liye rules:
        - "key" bilkul wahi rakho jo upar di gayi hai (badalna nahi).
        - "name" me aasan ka naam usi bhasha/script me likho jo upar rule me di gayi hai.
        - "steps" me EXACTLY 3 steps — har step max 6 shabd, bachche ko samajh aaye aisi saral bhasha.
        - "benefit" me ek chhoti line (max 8 shabd) — bachche ko kya fayda hoga.
        - EMOJI: har step ke aakhir me 1 relevant emoji, aur "benefit" me 1 emoji (jaise ✨💪🧘🌈).
        - "hashtags" me 6-10 safe, relevant hashtags (kids yoga niche).

        BAHUT ZAROORI (in par koi chhoot nahi):
        - Koi MEDICAL claim nahi. "bimari theek karta hai", "ilaaj", "rog door", "dawa" jaise shabd
          bilkul mat likho. Sirf general fayde likho — jaise "ekagrata badhti hai",
          "shareer lachila banta hai", "neend achhi aati hai", "mann shaant rehta hai".
        - Koi darr/pressure wali baat nahi — sab kuch khush aur khel jaisa lage.

        SIRF ek valid JSON array return karo (koi markdown, koi backticks nahi), bilkul is format me:
        [{"key":"vrikshasana", "name":"वृक्षासन", "steps":["सीधे खड़े हो जाओ 🧍","एक पैर जांघ पर रखो 🦵","दोनों हाथ ऊपर जोड़ो 🙏"], "benefit":"बैलेंस और ध्यान बढ़ता है ✨", "hashtags":"#kidsyoga #yogaforkids #vrikshasana"}]
        TXT;
    }

    /**
     * AI ke jawab ko pose-library ke saath merge karo. Naam/emoji library se aate
     * hain (guaranteed sahi), steps/benefit AI se. Jo aasan AI chhod de, uske liye
     * bhi card banta hai — bas steps khaali rehte hain.
     *
     * @param  list<string>  $poseKeys
     * @return list<array<string, mixed>>
     */
    protected function parsePoses(string $raw, array $poseKeys): array
    {
        // key => AI row (jo mila)
        $byKey = [];
        foreach ($this->decodeArray($raw) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = $this->asString($row['key'] ?? $row['pose'] ?? $row['id'] ?? '');
            if ($key !== '' && in_array($key, $poseKeys, true)) {
                $byKey[$key] = $row;
            }
        }

        // AI ne key hi na di ho to order-wise maan lo (aksar order sahi hota hai)
        if (empty($byKey)) {
            $rows = array_values(array_filter($this->decodeArray($raw), 'is_array'));
            foreach ($poseKeys as $i => $key) {
                if (isset($rows[$i])) {
                    $byKey[$key] = $rows[$i];
                }
            }
        }

        if (empty($byKey)) {
            return [];
        }

        $items = [];
        foreach ($poseKeys as $key) {
            $pose = YogaPoseLibrary::get($key);
            $row  = $byKey[$key] ?? null;

            $steps = [];
            foreach ((array) ($row['steps'] ?? $row['instructions'] ?? []) as $s) {
                $s = $this->asString($s);
                if ($s !== '') {
                    $steps[] = $s;
                }
            }

            // Steps hi na mile to ye card skip — aadha card post karne se behtar hai
            if (empty($steps)) {
                continue;
            }

            $items[] = [
                'key'      => $key,
                'name'     => $this->asString($row['name'] ?? '') ?: $pose['hi'],
                'name_en'  => $pose['en'],
                'emoji'    => $pose['emoji'],
                'steps'    => array_slice($steps, 0, 3),
                'benefit'  => $this->stripMedicalClaims(
                    $this->asString($row['benefit'] ?? $row['fayda'] ?? $row['benefits'] ?? '')
                ),
                'hashtags' => $this->withTrending($this->asString($row['hashtags'] ?? $row['tags'] ?? '')),
                'image'    => null, // browser baad me bhar deta hai
                'fallback' => YogaPoseLibrary::fallbackSvg($key),
            ];
        }

        return $items;
    }

    /**
     * Prompt me mana karne ke baad bhi model kabhi medical claim likh deta hai —
     * aisi line ko safe generic line se badal do. YouTube/Meta dono health claims
     * par action lete hain, isliye ye last line of defence hai.
     */
    protected function stripMedicalClaims(string $benefit): string
    {
        if ($benefit === '') {
            return '';
        }

        $banned = [
            'बीमारी', 'रोग', 'इलाज', 'दवा', 'ठीक कर', 'ठीक हो', 'निदान', 'उपचार',
            'બીમારી', 'રોગ', 'ઈલાજ', 'દવા',
            'bimari', 'bimaari', 'rog ', 'ilaaj', 'ilaj', 'dawa', 'cure', 'treat', 'disease', 'heal ',
        ];

        foreach ($banned as $word) {
            if (Str::contains(Str::lower($benefit), Str::lower($word))) {
                Log::info('Yoga benefit me medical claim mila, replace kiya', ['text' => $benefit]);

                return 'शरीर और मन दोनों तंदुरुस्त रहते हैं ✨';
            }
        }

        return $benefit;
    }

    /**
     * Parent ke general viral tags ki jagah kids-yoga tags jodo. Baaki logic wahi —
     * case-insensitive dedup, Instagram ki 30-tag limit ke andar.
     */
    protected function withTrending(string $tags): string
    {
        $tags = trim($tags);

        preg_match_all('/#[\p{L}\p{N}_]+/u', $tags, $m);
        $existing = array_map(fn ($h) => Str::lower($h), $m[0]);

        $toAdd = array_values(array_filter(
            self::YOGA_HASHTAGS,
            fn ($tag) => ! in_array(Str::lower($tag), $existing, true)
        ));

        $toAdd = array_slice($toAdd, 0, max(0, 30 - count($existing)));

        return empty($toAdd) ? $tags : trim($tags . ' ' . implode(' ', $toAdd));
    }
}
