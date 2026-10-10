<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\InstagramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
    public const DEFAULT_OPTION_REPLIES = "🎯 Option {option} note ho gaya! Sahi jawab reel ke end me 💡\n"
        . "✅ {option} lock kar diya! Dekhte hain sahi hai ya nahi 🤔\n"
        . '🔥 Aapne {option} chuna! Answer janne ke liye reel dobara dekho 👀';

    /** Baaki comments ke default replies. Har line = ek message. */
    public const DEFAULT_REPLIES = '✅ Aapka jawab note ho gaya hai! Sahi jawab janne ke liye reel ko end tak dekhein 🎯';

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

            if (! $userId || Setting::getFor($userId, 'ig_auto_reply_enabled', '0') !== '1') {
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

                // Meta same event dobara bhej sakta hai — ek comment ko ek hi reply
                if (! Cache::add("ig_webhook_reply.{$commentId}", true, now()->addDay())) {
                    continue;
                }

                $this->replyToComment($instagram->forUser($userId), $userId, $commentId, $text);
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
