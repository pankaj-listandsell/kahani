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
    /** Salah ke patr me sheher — poore Gujarat se (AI warna har baar Ahmedabad/Surat hi likhta hai). */
    public const GUJARAT_CITIES = [
        'અમદાવાદ', 'સુરત', 'વડોદરા', 'રાજકોટ', 'ભાવનગર', 'જામનગર', 'જૂનાગઢ', 'ગાંધીનગર', 'આણંદ', 'નડિયાદ',
        'ભરૂચ', 'અંકલેશ્વર', 'વાપી', 'વલસાડ', 'નવસારી', 'મહેસાણા', 'પાટણ', 'પાલનપુર', 'ડીસા', 'હિંમતનગર',
        'મોડાસા', 'ગોધરા', 'દાહોદ', 'પોરબંદર', 'ગોંડલ', 'જેતપુર', 'મોરબી', 'સુરેન્દ્રનગર', 'અમરેલી', 'બોટાદ',
        'ભુજ', 'ગાંધીધામ', 'અંજાર', 'માંડવી', 'દ્વારકા', 'વેરાવળ', 'કેશોદ', 'ઉના', 'મહુવા', 'પાલિતાણા',
        'વિસનગર', 'કલોલ', 'ઊંઝા', 'સિદ્ધપુર', 'ખંભાત', 'પેટલાદ', 'બારડોલી', 'વ્યારા', 'રાજપીપળા', 'ધોરાજી',
        'ઉપલેટા', 'વાંકાનેર', 'લીંબડી', 'ધ્રાંગધ્રા', 'સાવરકુંડલા', 'ધંધુકા', 'વિરમગામ', 'ઝાલોદ', 'છોટા ઉદેપુર', 'લુણાવાડા',
    ];

    public function generateSalah(int $count = 3, string $language = 'gujarati', string $category = 'Parenting', string $topic = '', string $style = 'normal'): array
    {
        $langPrompt = $this->langPrompt($language);
        $category = trim($category);
        // Category khaali = sirf topic par (topic hi poora vishay hai)
        if ($category === '') {
            $category = $topic !== '' ? "Whatever fits the topic \"{$topic}\" — stay strictly on this topic" : 'Parenting';
        }
        $topicLine = $topic !== '' ? "Specific topic: {$topic}. Every card must be about this topic (different angles)." : 'Pick a different, common real-life problem for each card.';

        // confession = lambi, dil kholkar likhi kahani (age, sheher, kya hua, kya mehsoos hua) + chhota jawab
        if ($style === 'kahani') {
            return $this->generateKahaniCards($count, $langPrompt, $category, $topic);
        }

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

        // Har card ka alag sheher — poore Gujarat se random
        $cities   = collect(self::GUJARAT_CITIES)->shuffle()->take(max(1, $count))->values();
        $cityLine = 'Cities for `asker` (use them in this order, one per card; write the name in the output language/script): '
            . $cities->map(fn ($c, $i) => 'card ' . ($i + 1) . ' = ' . $c)->implode(', ')
            . '. Use the same city inside the letter (`question`) where a city is mentioned. Do NOT default to Ahmedabad/Surat.';

        $prompt = <<<PROMPT
You write a popular "reader asks, expert answers" advice column that is posted as Instagram Reels text cards.
Generate {$count} cards in {$langPrompt}.
Category: {$category}.
{$topicLine}
{$cityLine}

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
     * 💕 1-CARD KAHANI: ek hi 9:16 card me poori romantic / affair / pehli-raat wali kahani.
     * Intimacy sirf ishaare me (kiss/aalingan tak, phir fade-to-black) — IG/FB par chalne layak.
     */
    protected function generateKahaniCards(int $count, string $langPrompt, string $category, string $topic): array
    {
        $topicLine = $topic !== '' ? "Specific topic: {$topic}. Every card must be a different story on this topic." : 'Each card must be a completely different situation and couple.';

        $cities   = collect(self::GUJARAT_CITIES)->shuffle()->take(max(1, $count))->values();
        $cityLine = 'Cities (one per card, in this order, written in the output language/script): '
            . $cities->map(fn ($c, $i) => 'card ' . ($i + 1) . ' = ' . $c)->implode(', ') . '.';

        $prompt = <<<PROMPT
You write viral one-card romantic short stories ("sachchi ghatna se prerit") for a Gujarati Instagram/Facebook page.
Each story must be COMPLETE on a single 9:16 text card. Generate {$count} cards in {$langPrompt}.
Theme / category: {$category}.
{$topicLine}
{$cityLine}

How to make it feel REAL:
- Ordinary middle-class Gujarati life: real places (society, office, college canteen, bus stand, garba ground, monsoon evening), common first names, ages 22-40, small concrete details (chai, rain, phone notification, saree, scooter).
- Show feelings through small moments and dialogue: nervous hands, a long glance, heartbeat, hesitation, a whispered line.
- Clear arc: hook in the first line → build-up of attraction/tension → the key moment → an ending (sweet, painful or a twist) that stays with the reader.

Make it BOLD, ROMANTIC and full of MOOD (the most sensual version that is still non-explicit):
- Describe how she looks, sensually but tastefully: her figure (curves, slim waist, the way the saree / kurti / wet dupatta drapes her body), long open hair, kajal eyes, soft lips, glowing skin, her perfume, her walk, the bangles and anklets. One or two lines about him too (broad shoulders, his cologne, strong hands).
- Build desire slowly: lingering eye contact, quickened breath, racing heartbeat, fingers brushing, a hand resting on her waist a moment too long, a whisper near the ear, goosebumps, the silence before the first move.
- The intimate scene is THE moment — write it slowly, step by step, with sensory detail (breath, warmth, touch, scent, sound of rain, dim light):
  first a soft kiss on the forehead or cheek → a long, deep kiss on the lips → kisses on her neck, behind the ear, on the shoulder → his hands on her waist, back and hair, her fingers in his hair, bodies pressed close in an embrace → her shy smile, closed eyes, trembling voice.
- Use warm, mood-setting words in the output language (e.g. Gujarati: "ધબકારા", "શ્વાસ", "સ્પર્શ", "ગરમાવો", "ધ્રુજારી", "નજીક", "આલિંગન", "હોઠ", "ગરદન" …).
- Title and first line must be a daring, curiosity-driven hook (forbidden, secret, "that night…").
- End right at the peak with a strong cut-away ("darwajo bandh thayo…", "light bujhai gai…", "e raate badhi maryadao tooti gai — baaki fakt e banne jaane chhe") and then the next-morning aftermath (a secret smile, guilt, a changed relationship).

Hard limits (strict — posted on Instagram/Facebook):
- All characters are adults (22+). Consent is clear and mutual.
- Kisses on lips / neck / shoulder and touching the waist, back, hair and hands are fine. Never describe sexual acts, nudity, undressing, breasts/nipples or private body parts — not directly, not with euphemisms, slang or coded words. Everything beyond the kiss and embrace happens off-page (cut-away).
- No vulgar words. Affairs can be daring but show honest feelings (temptation, guilt, consequence).

Fields (all text in {$langPrompt}):
- `title`: 6-10 word curiosity hook headline (no quotes) — short, it must fit in 2 lines.
- `story`: the full story, 185-205 words (count them — it must fill exactly one card at a large font; never more than 210), 5-6 short paragraphs separated by \\n\\n, with 2-3 lines of dialogue (whispered, teasing, shy). Keep every sentence rich — fewer words, more mood.
- Write every word only in the output language's own script — never mix letters from another script inside a word.
- `asker`: "— first name, city" style signature of the narrator or the couple.
- `takeaway`: one closing line (12-22 words) — a feeling, lesson or twist.
- `cta_question`: question for comments, 10-20 words.
- `caption`: 2-4 short lines with a few emojis, ends with "(Kaalpanik kahani)" in the output language.
- `hashtags`: 8-12 relevant hashtags in one string.

Return ONLY a raw JSON array of objects with keys: `title`, `story`, `asker`, `takeaway`, `cta_question`, `caption`, `hashtags`.
PROMPT;

        $items = $this->callGeminiJson($prompt, 90);

        // AI kabhi Gujarati shabd ke beech doosri lipi ke akshar daal deta hai ("દુ७७ો", "ક॔ઁઈ") — card par
        // ajeeb dikhte hain. Gujarati kahani me Devanagari / Hebrew / Arabic wagairah ke akshar hatao.
        if (str_contains($langPrompt, 'Gujarati')) {
            array_walk_recursive($items, function (&$v) {
                if (is_string($v)) {
                    $v = preg_replace('/[\p{Devanagari}\p{Hebrew}\p{Arabic}\p{Bengali}\p{Gurmukhi}\p{Tamil}\p{Telugu}]+/u', '', $v) ?? $v;
                }
            });
        }

        return array_map(fn ($item) => is_array($item) ? $item + ['style' => 'kahani'] : $item, $items);
    }

    /**
     * 🔯 RASHIFAL: Ek din ka 12 rashi ka bhavishya (Mesh → Meen, isi kram me).
     */
    /**
     * 🪔 Festival rashifal — key => [English naam (AI ke liye), extra rule].
     * 'navu_varsh' = poore Gujarati naye saal (Vikram Samvat) ka varshik rashifal.
     */
    public const RASHIFAL_FESTIVALS = [
        'dhanteras'    => ['Dhanteras', 'Weave Dhanteras naturally into each sign (what is auspicious to buy — gold, silver, utensils, a new item — Lakshmi-Kuber blessings, money matters).'],
        'kali_chaudas' => ['Kali Chaudas (Narak Chaturdashi)', 'Weave Kali Chaudas naturally (removing negativity, cleaning the home, self-care, protection prayers). Keep it positive, no fear.'],
        'diwali'       => ['Diwali (Lakshmi Puja)', 'Weave Diwali naturally into each sign (Lakshmi puja, diyas, family, sweets, money and new beginnings). Festive, joyful tone; start the prediction with a short Diwali wish.'],
        'navu_varsh'   => ['Gujarati New Year (Bestu Varas, Vikram Samvat 2083)', 'This is a YEARLY horoscope for the whole new Vikram Samvat year, not one day: describe how the full year will go (best months, money, family, career). Start with a short "Saal Mubarak / Nutan Varshabhinandan" style wish. lucky_number and lucky_color are for the whole year.'],
        'bhai_beej'    => ['Bhai Beej', 'Weave Bhai Beej naturally (brother-sister bond, gifts, family love) into each sign.'],
        'labh_pancham' => ['Labh Pancham', 'Weave Labh Pancham naturally (starting business/shop again after Diwali, new account books, profit, Saraswati and Lakshmi blessings).'],
        'dev_diwali'   => ['Dev Diwali (Kartik Purnima)', 'Weave Dev Diwali / Kartik Purnima naturally (diyas, holy dip, charity, devotion) into each sign.'],
    ];

    public function generateRashifal(string $date, string $language = 'gujarati', string $festival = '', ?int $moonIdx = null): array
    {
        $langPrompt = $this->langPrompt($language);
        $day = \Illuminate\Support\Carbon::parse($date)->locale('en')->isoFormat('dddd, D MMMM YYYY');

        [$festName, $festRule] = self::RASHIFAL_FESTIVALS[$festival] ?? [null, null];
        $festLine = $festName ? "\nFESTIVAL SPECIAL: {$day} is {$festName}. {$festRule}\n" : '';

        // 🌙 Chandra gochar (ganit se) — card par badge bhi isi se lagta hai, bhavishya usse mel khaye
        $signs = ['Mesh', 'Vrishabh', 'Mithun', 'Kark', 'Simha', 'Kanya', 'Tula', 'Vrishchik', 'Dhanu', 'Makar', 'Kumbh', 'Meen'];
        if ($moonIdx !== null && $festival !== 'navu_varsh' && isset($signs[$moonIdx])) {
            $moon  = $signs[$moonIdx];
            $eighth = $signs[($moonIdx + 5) % 12]; // jiske liye Chandra 8th bhaav me hai
            $festLine .= "\nASTRO FACT: Today the Moon transits {$moon}. For {$moon}: Moon in their own sign — emotions and intuition are strong, mention it gently. "
                . "For {$eighth}: Chandrashtama (Moon in 8th) — advise patience and avoiding big decisions today, in a calm, non-scary way; its rating is at most 3.\n";
        }

        $prompt = <<<PROMPT
You are a popular, positive daily horoscope (rashifal) writer for Gujarati/Hindi Instagram audiences.
Write the horoscope for {$day} for all 12 Vedic moon signs, in {$langPrompt}.
{$festLine}

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
- `short`: ONE punchy line for a YouTube Short, 8-14 words, the day's main message (no emoji).
- `rating`: how good the day is, integer 1-5. Vary it honestly across signs: exactly 3 signs get 5, the rest 2-4 (never 1).
- `love_score`, `career_score`, `health_score`: integers 3-10, matching the tone of `love`/`career`/`health` and the rating (shown as % meters).
- `do`: one thing to DO today, 4-9 words. `dont`: one thing to AVOID today, 4-9 words.
- `upay`: one FREE, simple remedy, 5-12 words (a short prayer, feeding animals/birds, small charity, wearing a colour). Never anything to buy, gemstones or paid puja.

Return ONLY a raw JSON array of 12 objects with keys: `rashi`, `prediction`, `love`, `career`, `health`, `lucky_number`, `lucky_color`, `caption`, `hashtags`, `short`, `rating`, `love_score`, `career_score`, `health_score`, `do`, `dont`, `upay`.
PROMPT;

        return $this->callGeminiJson($prompt, 120);
    }

    /**
     * 🎬 SAPTAHIK RASHIFAL (YouTube ka lamba 16:9 video): Somvar–Ravivar ka 12 rashi ka bhavishya.
     * $weekStart = us hafte ka Somvar ('Y-m-d').
     */
    public function generateWeeklyRashifal(string $weekStart, string $language = 'gujarati'): array
    {
        $langPrompt = $this->langPrompt($language);
        $from = \Illuminate\Support\Carbon::parse($weekStart)->locale('en');
        $range = $from->isoFormat('dddd D MMMM') . ' to ' . $from->copy()->addDays(6)->isoFormat('dddd D MMMM YYYY');

        $prompt = <<<PROMPT
You are a popular, positive WEEKLY horoscope (saptahik rashifal) writer for a Gujarati/Hindi YouTube channel.
Write the horoscope for the week {$range} (Monday to Sunday) for all 12 Vedic moon signs, in {$langPrompt}.
This text is read aloud as a voice-over in a long YouTube video, so write natural, flowing spoken sentences.

Rules:
- Exactly 12 objects, in this exact order: Mesh, Vrishabh, Mithun, Kark, Simha, Kanya, Tula, Vrishchik, Dhanu, Makar, Kumbh, Meen.
- Each sign must feel different (no repeated sentences). Practical, hopeful, relatable tone. Talk about the WHOLE week (start, middle, weekend).
- No fear-mongering, no death/accident/disease predictions, no paid remedies, no gemstone selling, no harmful superstition.
- `rashi`: the English key exactly as in the list above.
- `overview`: 55-75 words — how the week goes overall, the best part of the week and one thing to be careful about.
- `love`, `career`, `money`, `health`: 12-20 words each, about this week.
- `love_score`, `career_score`, `money_score`, `health_score`: integers 3-10 matching the text (shown as % meters).
- `rating`: how good the week is, integer 1-5. Exactly 3 signs get 5, the rest 2-4 (never 1).
- `lucky_days`: array of 1-2 English weekday names (e.g. ["Tuesday","Friday"]). `careful_day`: one English weekday name, different from lucky_days.
- `lucky_number`: one number 1-9 (as string). `lucky_color`: one colour name in {$langPrompt}.
- `upay`: one FREE, simple remedy for the week, 6-14 words (a short prayer, feeding animals/birds, small charity, wearing a colour). Never anything to buy.
- `short`: ONE punchy line summarising the week, 8-14 words (no emoji).

Return ONLY a raw JSON array of 12 objects with keys: `rashi`, `overview`, `love`, `career`, `money`, `health`, `love_score`, `career_score`, `money_score`, `health_score`, `rating`, `lucky_days`, `careful_day`, `lucky_number`, `lucky_color`, `upay`, `short`.
PROMPT;

        return $this->callGeminiJson($prompt, 150);
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

    /**
     * 🙏 Dharmik Card categories: key → [label (dropdown), about (AI ke liye), anukool templates].
     * Mix mode me har card alag category se. Nayi category bas yahan jodo — dropdown apne aap.
     */
    public const DHARMIK_CATEGORIES = [
        'mantra'    => ['🕉️ Mantra', 'Mantras and stotra lines (Lakshmi, Shiv, Ganesh, Hanuman, Durga, Vishnu, Navgrah, Kuber, Saraswati) — what to chant for what, and how.', ['list']],
        'upay'      => ['🪔 Upay / Totka', 'Simple traditional remedies / upay (diya, tulsi, daan, puja vidhi, what to do on which day) for money, peace at home, career, peace of mind.', ['list']],
        'vastu'     => ['🏠 Vastu Tips', 'Vastu tips for home, main door, kitchen, bedroom, puja room, shop and office — what to keep, where, and what to avoid.', ['list', 'grid']],
        'kem'       => ['❓ Aavu kem karvama aave che?', 'Why do we follow this tradition? (e.g. why touch feet, why ring the temple bell, why tilak, why break a coconut, why tie a raksha sutra) — reasons from scriptures and belief.', ['article']],
        'niyam'     => ['📜 Safalta na niyam', 'Life and success rules / good habits — motivational, practical, wisdom of elders.', ['grid']],
        'chanakya'  => ['🧠 Chanakya Niti', 'Teachings of Chanakya Niti — about money, people to avoid, friendship, success, family (state them as Chanakya\'s teaching, not invented quotes).', ['grid', 'list']],
        'gita'      => ['📖 Bhagavad Gita updesh', 'Teachings of Shri Krishna in the Bhagavad Gita — life lessons, karma, peace of mind (stay true to the Gita; no invented verse numbers).', ['list', 'article']],
        'dev'       => ['🛕 Dev-Devi ni vaato', 'Facts about Gods and Goddesses (Shiv, Krishna, Ram, Hanuman, Ganesh, Mataji, Vishnu) — their symbols, vahan, favourite bhog, names and meanings.', ['article', 'list']],
        'katha'     => ['📜 Pauranik katha', 'A short well-known story from Puranas, Ramayan or Mahabharat with its lesson (Shiv-Parvati, Ganesh, Hanuman, Krishna leela, Prahlad, Dhruv).', ['article']],
        'mandir'    => ['🚩 Prasiddh mandir', 'Famous temples of Gujarat and India (Somnath, Dwarka, Ambaji, Pavagadh, Chotila, Bahucharaji, Shamlaji, Salangpur, Virpur, Dakor, Kashi, Kedarnath) — history, belief, darshan facts.', ['article', 'list']],
        'sant'      => ['🙏 Sant-Mahatma', 'Saints and devotees (Narsinh Mehta, Jalaram Bapa, Mirabai, Bajrang Das Bapa, Sant Tulsidas, Kabir, Sai Baba) — life story and teachings.', ['article', 'list']],
        'graha'     => ['🪐 Navgrah', 'Navgrah beliefs — what each planet gives, its day, colour, daan, mantra, simple ways to make it favourable.', ['list']],
        'rashi'     => ['♈ Rashi swabhav', 'Rashi (zodiac) nature: strengths, lucky colour, lucky number, which deity to worship, which rashi is most loving / hard-working etc. Positive only.', ['grid', 'list']],
        'ank'       => ['🔢 Ank jyotish', 'Numerology (mulank 1-9) — nature, lucky day, colour, deity for each number. Positive only.', ['grid', 'list']],
        'ratna'     => ['💎 Ratna / Rudraksha', 'Gemstones and rudraksha beliefs — which for what, rules of wearing (always advise asking a learned astrologer first; no selling).', ['list']],
        'vruksh'    => ['🌿 Pavitra vruksh-chhod', 'Sacred plants and trees (tulsi, pipal, vad, bili, shami, kela, aanbo) — significance, puja rules, what to do and not do.', ['list', 'article']],
        'puja'      => ['🪷 Puja vidhi / Samagri', 'Puja vidhi and the meaning of puja items (kalash, diya, shankh, nariyal, kumkum, akshat, supari, ghanti, aarti) — how to do puja correctly at home.', ['list', 'article']],
        'vrat'      => ['🌸 Vrat / Tahevar', 'Vrat and festivals — rules, what to do and not do, significance, katha in brief (Ekadashi, Purnima, Pradosh, Navratri, Shravan, Janmashtami).', ['list', 'grid']],
        'daan'      => ['🤲 Daan-Punya', 'Charity — what to donate on which day / for which planet / on which festival and its punya.', ['list', 'grid']],
        'swapna'    => ['💭 Swapna shastra', 'Dream meanings according to Swapna Shastra — auspicious dreams (positive signs only, no fear).', ['list', 'grid']],
        'shubh'     => ['✨ Shubh sanket', 'Auspicious signs and shakun in daily life (what is considered lucky to see / hear / happen). Positive only, no fear of bad omens.', ['list', 'grid']],
        'rasoi'     => ['🍲 Rasoi ane Annapurna', 'Kitchen and food traditions — Annapurna blessings, rules of cooking and eating from tradition, bhog to offer gods.', ['list', 'grid']],
        'sanskar'   => ['👪 Sanskar / Parampara', 'Hindu sanskar and family traditions (16 sanskar, morning routine of elders, greeting elders, rules at home) and their meaning.', ['grid', 'list']],
        'suvichar'  => ['💬 Dharmik suvichar', 'Short devotional / spiritual thoughts for daily life (one line each) — faith, karma, patience, gratitude.', ['grid']],
    ];

    /** Vaar → dev (Somvar=1 … Ravivar=7, Carbon dayOfWeekIso). */
    public const DHARMIK_WEEKDAY_DEV = [
        1 => 'Monday — Lord Shiv (Mahadev)',
        2 => 'Tuesday — Lord Hanuman and Mataji',
        3 => 'Wednesday — Lord Ganesh',
        4 => 'Thursday — Lord Vishnu, Guru and Sai Baba',
        5 => 'Friday — Goddess Lakshmi and Mataji',
        6 => 'Saturday — Shani Dev and Hanuman',
        7 => 'Sunday — Surya Dev',
    ];

    /**
     * 🙏 DHARMIK CARDS: "11 Dhan Akarshan Mantra" (list), "20 Niyam" (grid),
     * "Shivling par mathu kem?" (article) jaisa roj navo dharmik content.
     *
     * @param  string  $template  list | grid | article | mix
     * @param  array   $avoid     pehle bane titles — AI inhe repeat na kare
     */
    public function generateDharmik(int $count = 3, string $language = 'gujarati', string $template = 'mix', string $category = 'auto', string $topic = '', array $avoid = []): array
    {
        $langPrompt = $this->langPrompt($language);
        $count = max(1, min(10, $count));

        $templates = ['list', 'article', 'grid'];
        $fixedTpl = in_array($template, $templates, true);
        $dev = self::DHARMIK_WEEKDAY_DEV[now()->dayOfWeekIso];

        if ($category === 'mix') {
            // 🔀 Dar card alag category — ek card aaj na vaar na dev nu, baaki random;
            // template category ne anukool (mantra → list, niyam → grid, kem → article)
            $keys = array_keys(self::DHARMIK_CATEGORIES);
            shuffle($keys);
            $cats = array_slice(array_merge(['vaar'], $keys, $keys), 0, $count);
            shuffle($cats);

            $assigned = [];
            $planLines = [];
            foreach ($cats as $i => $cat) {
                $tpl = $fixedTpl ? $template : $this->dharmikTemplateFor($cat);
                $assigned[] = $tpl;
                $about = $cat === 'vaar'
                    ? "today's deity ({$dev}) — mantra, upay or a fact"
                    : self::DHARMIK_CATEGORIES[$cat][1];
                $planLines[] = 'Card ' . ($i + 1) . ": template `{$tpl}` — category: {$about}";
            }
            $catLine = 'Every card is from a DIFFERENT category as given in the plan. Inside each category pick a specific, interesting sub-topic (not always the most obvious one).';
            $plan = implode("\n", $planLines);
        } else {
            $assigned = $fixedTpl
                ? array_fill(0, $count, $template)
                : array_map(fn ($i) => $templates[$i % 3], range(0, $count - 1));
            if (! $fixedTpl) {
                shuffle($assigned);
            }
            $catLine = ($category === 'auto' || ! isset(self::DHARMIK_CATEGORIES[$category]))
                ? "Today is {$dev}. Make every card about this day's deity (mix of mantra, upay, why-traditions and facts)."
                : 'Category: ' . self::DHARMIK_CATEGORIES[$category][1];
            $plan = collect($assigned)->map(fn ($t, $i) => 'Card ' . ($i + 1) . ": template `{$t}`")->implode("\n");
        }
        $topicLine = $topic !== '' ? "Specific topic from the page owner: {$topic}. Every card must be about this topic (different angles)." : 'Each card must be a different, popular topic.';

        $avoid = array_values(array_filter(array_map('trim', $avoid)));
        $avoidLine = $avoid
            ? "These titles were already posted — do NOT repeat these topics, pick fresh ones:\n- " . implode("\n- ", array_slice($avoid, 0, 80))
            : '';

        $prompt = <<<PROMPT
You create viral devotional (dharmik) information cards for a Gujarati Facebook/Instagram page about astrology, religion and daily rashifal.
Generate exactly {$count} cards in {$langPrompt}, one per line of this plan, in the same order:
{$plan}

{$catLine}
{$topicLine}
{$avoidLine}

TEMPLATES:
- `list` — a numbered list card, like "11 ધન આકર્ષણ મંત્ર" or "7 ઉપાય જે ઘરમાં સુખ-શાંતિ લાવે".
  `items`: 5-11 entries, each 2-12 words (one mantra / one upay / one point per entry, no numbering).
  `note`: 25-45 words closing paragraph (how to do it with faith and what blessing it brings).
- `grid` — a two-column checklist of short rules, like "ગરીબમાંથી અમીર બનવાનાં 20 નિયમ".
  `items`: an EVEN number between 10 and 20 entries, each only 2-6 words, punchy (no numbering).
  `note`: "" (empty) or max 20 words.
- `article` — a question-and-answer explainer, like "શિવલિંગ પર માથું કેમ ટેકવવામાં આવે છે?".
  `title` must be a curiosity question.
  `intro`: 40-60 words — describe the tradition and ask the question again.
  `body`: 90-140 words, 2-3 paragraphs separated by \\n\\n — the reason from scriptures / belief, ends with a devotional line.
  `items` and `note` may be empty.

RULES:
- MANTRAS: use only authentic, widely known mantras exactly as found in scriptures (e.g. "ॐ नमः शिवाय", "ॐ श्रीं महालक्ष्म्यै नमः", "ॐ गं गणपतये नमः"). Never invent or alter a mantra. Write mantras in the output script (Gujarati script for Gujarati).
- Use soft belief language ("માનવામાં આવે છે", "શાસ્ત્રો અનુસાર") — never guarantee money, cure, job or marriage.
- No fear-mongering, no curses, no black magic / vashikaran / tantra, no paid remedies, no gemstone selling, no medicines or health claims.
- No real living people, brands, apps, phone numbers or website names.
- `title`: 3-9 words, bold hook, may contain a number (e.g. "11 ધન આકર્ષણ મંત્ર"). No quotes, no emoji.
- `cta`: one line asking viewers to comment a devotional phrase, 6-14 words (e.g. "કોમેન્ટમાં શ્રદ્ધાપૂર્વક \"જય મહાલક્ષ્મી માં\" જરૂર લખો.").
- `caption`: 2-4 short lines with a few emojis, asks to share with family and follow the page.
- `hashtags`: 8-12 relevant hashtags in one string.
- `template`: exactly the template from the plan.

Return ONLY a raw JSON array of objects with keys: `template`, `title`, `items`, `intro`, `body`, `note`, `cta`, `caption`, `hashtags`.
PROMPT;

        $items = $this->callGeminiJson($prompt, 120);

        $out = [];
        foreach (array_values(array_filter($items, 'is_array')) as $i => $item) {
            if (! in_array($item['template'] ?? '', $templates, true)) {
                $item['template'] = $assigned[$i] ?? 'list';
            }
            $item['items'] = array_values(array_filter(array_map(
                fn ($x) => trim(is_array($x) ? implode(' ', $x) : (string) $x),
                is_array($item['items'] ?? null) ? $item['items'] : []
            )));
            $out[] = $item;
        }

        return $out;
    }

    /** Category ne anukool template (mix mode me). */
    protected function dharmikTemplateFor(string $cat): string
    {
        // 'vaar' (aaj na dev) → list ke article
        $options = self::DHARMIK_CATEGORIES[$cat][2] ?? ['list', 'article'];

        return $options[array_rand($options)];
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

        // 🚫 Ashleel shabd har text field me badlo (list + AI, poore jawab ke liye ek AI call)
        return \App\Support\WordFilter::cleanArray($data);
    }
}
