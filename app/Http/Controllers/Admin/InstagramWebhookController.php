<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartCard;
use App\Models\Setting;
use App\Services\InstagramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Meta webhook (public route — koi logged-in user nahi hota). Isliye har setting
 * Setting::getFor(user) se padhi jaati hai; user webhook entry ke IG account id se milta hai.
 *
 * .env:
 *   IG_WEBHOOK_VERIFY_TOKEN = Meta App dashboard me daala gaya verify token
 *   IG_APP_SECRET           = App secret — har POST ka X-Hub-Signature-256 isi se check hota hai
 */
class InstagramWebhookController extends Controller
{
    /** A/B/C/D comment ke default replies — {option} ki jagah user ka option aata hai. Har line = ek message. */
    public const DEFAULT_OPTION_REPLIES = "🎯 Option {option} lock ho gaya! Dekhte hain kitne log sahi hain 🤔\n"
        . "✅ {option} note kar liya! Dekhte hain sahi hai ya nahi 🤔\n"
        . '🔥 Aapne {option} chuna! Confident ho ya guess kiya? 😄';

    /** Baaki comments ke default replies. Har line = ek message. */
    public const DEFAULT_REPLIES = '✅ Aapka jawab note ho gaya hai! Sahi jawab janne ke liye reel ko end tak dekhein 🎯';

    /**
     * Comment karne wale ko DM me sahi jawab — {answer} ki jagah card ka answer
     * (alag paragraph me) aata hai. Har line = ek message.
     */
    public const DEFAULT_DMS = "📩 Comment karne ke liye shukriya! Ye raha sahi jawab 👇 {answer} Kitna sahi tha aapka? 😄\n"
        . "🎯 Aapke liye special 🤫 {answer} Agle quiz ke liye ready raho! 🔥\n"
        . '🧠 Ye raha is reel ka jawab 👇 {answer} Dosto ko bhi challenge karo 😎';

    /**
     * Webhook verification for Meta App (GET request).
     */
    public function verify(Request $request)
    {
        $expected = (string) config('services.instagram_webhook.verify_token');
        $token    = (string) $request->query('hub_verify_token', '');

        if ($request->query('hub_mode') === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Receive and auto-reply to Instagram comments (POST request).
     */
    public function handle(Request $request, InstagramService $instagram)
    {
        if (! $this->validSignature($request)) {
            Log::warning('Instagram webhook: invalid/missing signature');

            return response('Forbidden', 403);
        }

        $entries = $request->input('entry', []);
        Log::info('Instagram webhook event', ['entries' => is_array($entries) ? count($entries) : 0]);

        foreach ((array) $entries as $entry) {
            $accountId = (string) ($entry['id'] ?? '');
            $userId = $this->userForAccount($accountId);

            if (! $userId) {
                continue;
            }

            $replyOn = Setting::getFor($userId, 'ig_auto_reply_enabled', '0') === '1';
            $dmOn    = Setting::getFor($userId, 'ig_auto_dm_enabled', '0') === '1'
                || Setting::getFor($userId, 'ig_keyword_dm_enabled', '0') === '1'
                || Setting::getFor($userId, 'ig_all_dm_enabled', '0') === '1';
            if (! $replyOn && ! $dmOn) {
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? '') !== 'comments') {
                    continue;
                }

                $value     = $change['value'] ?? [];
                $commentId = (string) ($value['id'] ?? '');
                $text      = trim((string) ($value['text'] ?? ''));
                $fromId    = (string) ($value['from']['id'] ?? '');

                // Apne hi comment/reply ko reply mat karo
                if ($fromId !== '' && $fromId === $accountId) {
                    continue;
                }

                // Comment id sirf digits — Graph URL path me kuch aur na ghuse
                if (! ctype_digit($commentId) || $text === '') {
                    continue;
                }

                // Meta same event dobara bhej sakta hai — ek comment ko ek hi reply/DM
                if (! Cache::add("ig_webhook_reply.{$commentId}", true, now()->addDay())) {
                    continue;
                }

                $instagram->forUser($userId);

                if ($replyOn) {
                    $this->replyToComment($instagram, $userId, $commentId, $text);
                }

                if ($dmOn) {
                    $this->sendDm($instagram, $userId, $commentId, $fromId, (string) ($value['media']['id'] ?? ''), $text);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /** X-Hub-Signature-256 = "sha256=" . HMAC-SHA256(raw body, app secret). */
    protected function validSignature(Request $request): bool
    {
        $secret = (string) config('services.instagram_webhook.app_secret');
        if ($secret === '') {
            Log::error('Instagram webhook: IG_APP_SECRET .env me set nahi — events reject ho rahe hain.');

            return false;
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return $header !== '' && hash_equals($expected, $header);
    }

    /**
     * Webhook entry ka IG account id → hamara user. Pehle saved ig_user_id se match;
     * na mile (Instagram Login wale users aksar id khaali chhodte hain) aur sirf ek
     * user ne auto-reply ON kiya ho to wahi.
     */
    protected function userForAccount(string $accountId): ?int
    {
        if ($accountId !== '') {
            $match = Setting::where('key', 'ig_user_id')->where('value', $accountId)->value('user_id');
            if ($match) {
                return (int) $match;
            }
        }

        $enabled = Setting::where('key', 'ig_auto_reply_enabled')->where('value', '1')->pluck('user_id');

        return $enabled->count() === 1 ? (int) $enabled->first() : null;
    }

    /**
     * Comment ke hisaab se reply chuno. A/B/C/D (ya "Option B", "b)", "B.") → option wale
     * messages, warna general messages. Har textbox me kai lines ho sakti hain — har baar
     * random ek line, taaki har comment par bilkul same reply na jaaye (Instagram spam maanta hai).
     */
    protected function pickReply(int $userId, string $userText): string
    {
        if (preg_match('/^\s*(?:option\s*)?([a-d])\s*[.)!:]*\s*$/i', $userText, $m)) {
            $option = strtoupper($m[1]);
            $lines = $this->lines(Setting::getFor($userId, 'ig_auto_reply_option_template'), self::DEFAULT_OPTION_REPLIES);

            return str_replace('{option}', $option, $lines[array_rand($lines)]);
        }

        $lines = $this->lines(Setting::getFor($userId, 'ig_auto_reply_template'), self::DEFAULT_REPLIES);

        return $lines[array_rand($lines)];
    }

    /**
     * Commenter ko DM (Instagram "private reply" — ek comment par sirf ek, 7 din ke andar).
     *  1) Keyword DM ON aur comment me koi keyword (jaise LINK) → us keyword ka message
     *  2) warna Answer DM ON aur reel ka card answer wala hai → sahi jawab
     *  3) "Har DM ke upar link" ON → ye text sabse upar (aur 1/2 kuch na ho to sirf yahi)
     * Ek user ko ek reel par ek hi DM (kai comment kare to bhi) — spam na lage.
     */
    protected function sendDm(InstagramService $instagram, int $userId, string $commentId, string $fromId, string $mediaId, string $text): void
    {
        $answer = $this->answerForMedia($userId, $mediaId);

        $message = null;
        if (Setting::getFor($userId, 'ig_keyword_dm_enabled', '0') === '1') {
            $message = $this->keywordMessage((string) Setting::getFor($userId, 'ig_keyword_dm_rules'), $text, $answer);
        }
        if ($message === null && $answer !== null && Setting::getFor($userId, 'ig_auto_dm_enabled', '0') === '1') {
            $lines = $this->lines(Setting::getFor($userId, 'ig_auto_dm_template'), self::DEFAULT_DMS);
            $line = $lines[array_rand($lines)];
            // {answer} likhna bhool gaye to bhi jawab jaye — message ke neeche jod do
            if (! str_contains($line, '{answer}')) {
                $line .= ' {answer}';
            }
            $message = $this->fillAnswer($line, $answer);
        }
        // 3) "Har DM ke upar link" — ON ho to har comment karne wale ko DM jaata hai (sab reels):
        //    upar ye link/text, neeche keyword ya answer wala message (ho to).
        if (Setting::getFor($userId, 'ig_all_dm_enabled', '0') === '1') {
            $lines = $this->lines(Setting::getFor($userId, 'ig_all_dm_template'), '');
            if ($lines) {
                $top = $lines[array_rand($lines)];
                $message = $message === null ? $top : $top . "\n\n" . $message;
            }
        }
        if ($message === null) {
            Log::info('Instagram DM skipped', [
                'comment_id' => $commentId,
                'media_id'   => $mediaId,
                'reason'     => $answer === null
                    ? 'is reel ka answer save nahi (ya admin panel se post nahi hui) aur koi keyword match nahi'
                    : 'answer DM switch OFF aur koi keyword match nahi',
            ]);

            return;
        }

        $key = 'ig_dm.' . ($mediaId !== '' ? $mediaId : 'x') . '.' . ($fromId !== '' ? $fromId : $commentId);
        if (! Cache::add($key, true, now()->addDays(7))) {
            Log::info('Instagram DM skipped', [
                'comment_id' => $commentId,
                'media_id'   => $mediaId,
                'reason'     => 'is user ko is reel par pehle hi DM ja chuka (7 din me ek)',
            ]);

            return;
        }

        try {
            $res = Http::withToken((string) $instagram->token())->timeout(20)
                ->post($instagram->apiBase() . '/' . $instagram->nodeId() . '/messages', [
                    'recipient' => ['comment_id' => $commentId],
                    'message'   => ['text' => Str::limit($message, 950)],
                ]);

            Log::info('Instagram DM sent', [
                'comment_id'   => $commentId,
                'to_user'      => $fromId,
                'ok'           => $res->successful(),
                'message_id'   => $res->json('message_id'),
                'recipient_id' => $res->json('recipient_id'),
                'error'        => $res->json('error.message'),
            ]);

            if (! $res->successful()) {
                Cache::forget($key); // fail hua — user agla comment kare to dobara try
            }
        } catch (\Throwable $e) {
            Cache::forget($key);
            Log::error('Instagram DM failed', ['comment_id' => $commentId, 'error' => $e->getMessage()]);
        }
    }

    /** Is reel (IG media id) wale card ka answer — sirf isi user ka card, answer ho tab. */
    protected function answerForMedia(int $userId, string $mediaId): ?string
    {
        if (! ctype_digit($mediaId)) {
            return null;
        }

        $card = PartCard::with('part.story')->where('ig_media_id', $mediaId)->first();
        $answer = trim((string) $card?->answer_text);

        return $answer !== '' && (int) $card->part?->story?->user_id === $userId ? $answer : null;
    }

    /**
     * Keyword rules — har line "KEYWORD = message", ek line me kai keyword comma se
     * ("LINK, LINKS = …"). Same keyword ki kai lines = random me se ek. Comment me
     * keyword poora shabd hona chahiye (case ignore). {answer} wala rule sirf answer
     * wali reel par chalta hai.
     */
    protected function keywordMessage(string $rules, string $comment, ?string $answer): ?string
    {
        $matches = [];

        foreach (preg_split('/\R/', $rules) as $line) {
            if (! str_contains($line, '=')) {
                continue;
            }
            [$keys, $msg] = array_map('trim', explode('=', $line, 2));
            if ($msg === '' || (str_contains($msg, '{answer}') && $answer === null)) {
                continue;
            }

            foreach (array_filter(array_map('trim', explode(',', $keys)), 'strlen') as $kw) {
                if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($kw, '/') . '(?![\p{L}\p{N}])/iu', $comment)) {
                    $matches[] = $msg;
                    break;
                }
            }
        }

        return $matches ? $this->fillAnswer($matches[array_rand($matches)], (string) $answer) : null;
    }

    /** {answer} ko alag paragraph me daalo, taaki DM me saaf dikhe. */
    protected function fillAnswer(string $message, string $answer): string
    {
        $text = str_replace('{answer}', "\n\n{$answer}\n\n", $message);
        $text = preg_replace('/[ \t]*\n[ \t]*/', "\n", $text); // line ke aage/peeche ke space

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /** Textbox ki non-empty lines; khaali ho to default ki lines. */
    protected function lines(?string $text, string $default): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text)), 'strlen'));

        return $lines ?: array_values(array_filter(array_map('trim', preg_split('/\R/', $default)), 'strlen'));
    }

    /**
     * Post reply to user comment.
     */
    protected function replyToComment(InstagramService $instagram, int $userId, string $commentId, string $userText): void
    {
        $token = $instagram->token();
        if (! $token) {
            return;
        }

        $replyText = $this->pickReply($userId, $userText);

        try {
            $res = Http::asForm()->timeout(20)->post($instagram->apiBase() . "/{$commentId}/replies", [
                'message'      => $replyText,
                'access_token' => $token,
            ]);

            Log::info('Instagram auto-reply sent', ['comment_id' => $commentId, 'ok' => $res->successful(), 'error' => $res->json('error.message')]);
        } catch (\Throwable $e) {
            Log::error('Instagram auto-reply failed', ['comment_id' => $commentId, 'error' => $e->getMessage()]);
        }
    }
}
