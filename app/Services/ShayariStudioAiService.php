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
     * Har item ke hashtags ke saath jodne wale trending/viral hashtags
     * (Instagram reels + YouTube shorts niche). AI ke relevant hashtags ke
     * saath merge hote hain — duplicates hat jaate hain aur total 30 tag ki
     * Instagram limit ke andar rehte hain.
     */
    private const TRENDING_HASHTAGS = [
        '#jantarmantar', '#trending', '#viral', '#reels', '#reelsinstagram',
        '#explore', '#explorepage', '#foryou', '#fyp', '#viralvideo',
        '#trendingreels', '#shorts', '#viralpost', '#instagram', '#india',
    ];

    /**
     * @return list<array{text:string, punchline?:string}>
     * @throws \RuntimeException
     */
    public function generateBatch(string $type, string $category, int $count, string $language = 'hindi'): array
    {
        $type     = in_array($type, ['shayari', 'joke', 'quote', 'status', 'fact', 'ukhana'], true) ? $type : 'shayari';
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
    public function generateQuiz(string $category, int $count, string $language = 'hindi', array $exclude = []): array
    {
        $count    = max(1, min(30, $count));
        $category = trim($category) ?: 'general knowledge';

        $prompt = $this->quizPrompt($category, $count, $language, $exclude);
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

    protected function quizPrompt(string $category, int $count, string $language, array $exclude = []): string
    {
        $lang = StoryAiService::langRule($language);

        // Ek hi topic par kai batch maangne par model wahi sawaal dohra deta hai.
        // Pichhle sawaal dikha dene se naye sawaal aate hain. Sirf aakhri 40 —
        // isse zyada bhejne par prompt bahut lamba ho jaata hai.
        $avoid = '';
        if (! empty($exclude)) {
            $list = collect($exclude)
                ->filter(fn ($q) => is_string($q) && trim($q) !== '')
                ->take(-40)
                ->map(fn ($q) => '- ' . Str::limit(trim($q), 90, ''))
                ->implode("\n");

            if ($list !== '') {
                $avoid = "\nYe sawaal PEHLE SE ban chuke hain — inme se koi bhi dobara mat likhna, "
                    . "na hi inka thoda badla hua roop. Bilkul NAYE sawaal do:\n{$list}\n";
            }
        }

        return <<<TXT
        Tum ek expert quiz-master ho jo competitive exam (jaise "{$category}") ki taiyari karwate ho.
        "{$category}" topic par {$count} multiple-choice questions (MCQ) banao — factual aur accurate.
        {$lang}
        {$avoid}
        Rules (har question ke liye):
        - EXACTLY 4 options do.
        - "answer" me sahi option ka letter do: "A", "B", "C" ya "D".
        - "reason" me ek chhoti 1-line wajah do (kyun sahi hai).
        - EMOJI: "question" me 1-2 topic-relevant emoji daalo (jaise 🤔🧠📚🌍🔬🏆) aur "reason" me
          1 emoji (jaise ✅💡). Options plain rakho — unme emoji mat daalo.
        - "image_query" me 2-4 shabd ANGREZI (English) me do — is sawaal se judi photo dhoondhne ke liye
          (jaise "himalaya mountains", "indian parliament", "solar system", "cricket stadium").
          Sirf aisi cheez likho jiski asli photo milti ho — abstract baat nahi.
        - "caption" me ek chhoti, curiosity/challenge wali HOOK line do — usi bhasha me jo upar rule me
          di gayi hai. Social media par log ruk kar padhein aisi. 1 line, max 12 shabd, 1-2 emoji.
          Jaise: "90% લોકો આ સવાલમાં ખોટા પડે છે! 🤯" / "તમે GPSC ની તૈયારી કરો છો? આ સવાલ ટ્રાય કરો 🎯"
          BAHUT ZAROORI: caption me sawaal ya jawab BILKUL mat likhna — sirf hook.
        - "hashtags" SIRF ANGREZI/Roman letters me — Gujarati/Hindi lipi me ek bhi hashtag nahi.
          6-10 safe, relevant tags (banned/sensitive nahi).
        SIRF ek valid JSON array return karo (koi markdown, koi backticks nahi), bilkul is format me:
        [{"question":"prashn yahan? 🤔", "options":["pehla","dusra","teesra","chautha"], "answer":"B", "reason":"chhoti wajah ✅", "image_query":"himalaya mountains", "caption":"hook line yahan 🤯", "hashtags":"#gpsc #talati #gujaratgk"}]
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
            'ukhana' => <<<TXT
            Tum bachchon ke liye ukhana/paheli (riddle) likhne wale ho — "ઓળખી બતાવો" wali paramparik shaili.
            "{$category}" par {$count} chhoti, mazedaar paheliyan likho.
            {$lang}
            Har paheli ke rules:
            - 1 se 3 line ki ho, laya/tuk (rhyme) ke saath — bachcha sun kar yaad rakh le.
            - Cheez ka naam SEEDHA mat likhna — sirf uske ishaare do (rang, aawaz, kaam, jagah).
            - Jawab ek hi shabd ya do shabd ka ho, aur bilkul saaf ho — do jawab wali paheli nahi.
            - Jawab roz-marra ki cheez ho jo bachcha jaanta ho (jeebh, chaand, aankh, ghadi, aag, kitaab...).
            - "answer" me sirf jawab likho, koi wakya nahi.
            - EMOJI BILKUL NAHI — na paheli me, na jawab me. Koi bhi emoji/symbol/pictograph mat likho.
              (Emoji se jawab ka ishaara mil jaata hai aur paheli ka maza khatam ho jaata hai.)
            {$tagRule}
            SIRF ek valid JSON array return karo (koi markdown, koi backticks nahi):
            [{"text":"paheli ki pehli line\\ndusri line", "answer":"jawab", "hashtags":"#ukhana #paheli #riddle #kids #gujarati"}]
            Koi darawni ya adult cheez nahi — sab bachchon ke layak.
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
                'temperature'      => 1.0,
                'maxOutputTokens'  => 8192,
                'thinkingConfig'   => [
                    'thinkingBudget' => 0,
                ],
            ],
        ];

        $lastError = 'Gemini generation fail.';

        // Fastest models first: gemini-2.5-flash (with budget 0), gemini-2.5-flash-preview
        foreach (['gemini-2.5-flash', 'gemini-2.5-flash-preview', 'gemini-2.0-flash'] as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
            $res = Http::timeout(60)
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
                $ans   = '';
            } elseif (is_array($row)) {
                $text  = trim((string) ($row['text'] ?? $row['setup'] ?? $row['shayari'] ?? $row['quote'] ?? $row['paheli'] ?? ''));
                $punch = isset($row['punchline']) ? trim((string) $row['punchline']) : null;
                $tags  = trim((string) ($row['hashtags'] ?? ''));
                $ans   = $this->asString($row['answer'] ?? $row['jawab'] ?? $row['jawaab'] ?? '');
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
            // Paheli me emoji jawab ka ishaara de deta hai (👅 dekh kar "jeebh"
            // saaf pata chal jaata hai). Prompt me mana kiya hua hai, par model
            // aksar phir bhi daal deta hai — isliye yahan zabardasti hata dete hain.
            if ($type === 'ukhana') {
                $item['text'] = $this->stripEmoji($item['text']);
                if (filled($ans)) {
                    // Jawab card par NAHI aata — caption me jaata hai taaki log
                    // comment me guess karein (isi se engagement aata hai).
                    $item['answer'] = $this->stripEmoji($ans);
                }
            }
            $tags = $this->withTrending($tags);
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
                // Pixabay English me hi theek dhoondhta hai — Gujarati/Hindi sawaal
                // se kuch nahi milta, isliye AI se alag English keyword lete hain
                'image_query' => $this->asString($row['image_query'] ?? $row['imageQuery'] ?? ''),
                // Caption ki hook line (sawaal/jawab nahi — sirf dhyan kheenchne wali line)
                'caption'  => $this->asString($row['caption'] ?? $row['hook'] ?? ''),
                'hashtags' => $this->withQuizTags($this->asString($row['hashtags'] ?? $row['tags'] ?? '')),
            ];
        }

        return $items;
    }

    /**
     * Quiz ke hashtags — Gujarat exam + viral, isi PRIORITY order me.
     *
     * Order maayne rakhta hai: Instagram par 30 tag ki limit hai aur AI ke apne
     * tags pehle jud jaate hain, isliye list ke aakhir wale tags kat sakte hain.
     * Exam ke naam sabse pehle (wahan se sahi audience aati hai), phir viral
     * tags (reach), aur shehar ke naam aakhir me (sabse pehle katne wale).
     */
    private const QUIZ_HASHTAGS = [
        // 1) Exams — sabse targeted
        '#gpsc', '#gsssb', '#talati', '#binsachivalay', '#gujaratpolice',
        '#psi', '#constable', '#forestguard', '#tet', '#tat', '#ojas',
        // 2) Subject / prep
        '#gujaratgk', '#gkquiz', '#currentaffairs', '#competitiveexam', '#examprep',
        // 3) Viral — reach ke liye
        '#viral', '#trending', '#reels', '#explore', '#foryou', '#fyp', '#shorts',
        // 4) State / geo — jagah bache to
        '#gujarat', '#ahmedabad', '#surat', '#rajkot', '#vadodara', '#gandhinagar',
    ];

    /**
     * Quiz item ke hashtags — sirf English tags rakho aur Gujarat exam set jodo.
     * AI kabhi Gujarati lipi ke tag de deta hai; unse reach nahi milti kyunki
     * students English me hi search/follow karte hain.
     */
    protected function withQuizTags(string $tags): string
    {
        // Devanagari/Gujarati wale tags hata do
        preg_match_all('/#[\p{L}\p{N}_]+/u', trim($tags), $m);
        $kept = array_values(array_filter(
            $m[0],
            fn ($t) => ! preg_match('/[^\x00-\x7F]/', $t)
        ));

        $existing = array_map(fn ($t) => Str::lower($t), $kept);

        foreach (self::QUIZ_HASHTAGS as $tag) {
            if (count($kept) >= 30) {
                break;
            }
            if (! in_array(Str::lower($tag), $existing, true)) {
                $kept[]     = $tag;
                $existing[] = Str::lower($tag);
            }
        }

        return implode(' ', $kept);
    }

    /**
     * AI ke diye hashtags ke saath trending/viral hashtags jodo — jo pehle se
     * present nahi hain sirf wahi, aur total 30 tag (Instagram limit) ke andar.
     * Case-insensitive dedup. Tags khaali ho to bhi trending add ho jaate hain.
     */
    protected function withTrending(string $tags): string
    {
        $tags = trim($tags);

        // Pehle se maujood hashtags (case-insensitive)
        preg_match_all('/#[\p{L}\p{N}_]+/u', $tags, $m);
        $existing = array_map(fn ($h) => Str::lower($h), $m[0]);

        $toAdd = array_values(array_filter(
            self::TRENDING_HASHTAGS,
            fn ($tag) => ! in_array(Str::lower($tag), $existing, true)
        ));

        $room  = max(0, 30 - count($existing));
        $toAdd = array_slice($toAdd, 0, $room);

        if (empty($toAdd)) {
            return $tags;
        }

        return trim($tags . ' ' . implode(' ', $toAdd));
    }

    /**
     * Emoji / pictograph / symbol hata do (Devanagari-Gujarati text aur normal
     * punctuation waise hi rehte hain). Same range jo TTS bhi use karta hai.
     */
    protected function stripEmoji(string $text): string
    {
        $clean = preg_replace(
            '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{2300}-\x{23FF}'
            . '\x{2190}-\x{21FF}\x{FE00}-\x{FE0F}\x{200D}\x{20E3}\x{1F1E6}-\x{1F1FF}]/u',
            '',
            $text
        ) ?? $text;

        // Emoji hatne se bache extra space saaf karo (newlines rehne do)
        $clean = preg_replace('/[ \t]{2,}/', ' ', $clean);
        $clean = preg_replace('/[ \t]+(\R)/', '$1', $clean);

        return trim($clean) !== '' ? trim($clean) : trim($text);
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
