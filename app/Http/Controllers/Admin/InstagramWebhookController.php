<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\InstagramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramWebhookController extends Controller
{
    /**
     * Webhook verification for Meta App (GET request).
     */
    public function verify(Request $request)
    {
        $mode        = $request->query('hub_mode');
        $token       = $request->query('hub_verify_token');
        $challenge   = $request->query('hub_challenge');
        $secretToken = Setting::get('ig_webhook_verify_token') ?: 'kahani_auto_engagement_token';

        if ($mode === 'subscribe' && $token === $secretToken) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Receive and auto-reply to Instagram comments (POST request).
     */
    public function handle(Request $request, InstagramService $instagram)
    {
        $enabled = Setting::get('ig_auto_reply_enabled', '0') === '1';
        if (! $enabled) {
            return response()->json(['status' => 'disabled']);
        }

        $payload = $request->all();
        Log::info('Instagram Webhook Event', ['payload' => $payload]);

        $entries = $payload['entry'] ?? [];
        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                if (($change['field'] ?? '') === 'comments') {
                    $value = $change['value'] ?? [];
                    $commentId = $value['id'] ?? null;
                    $text      = trim((string) ($value['text'] ?? ''));
                    $from      = $value['from'] ?? [];
                    $fromId    = $from['id'] ?? null;

                    // Do not reply to self
                    $myId = Setting::get('ig_user_id');
                    if ($fromId && $myId && $fromId === $myId) {
                        continue;
                    }

                    if ($commentId && $text !== '') {
                        $this->replyToComment($commentId, $text, $instagram);
                    }
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Post reply to user comment.
     */
    protected function replyToComment(string $commentId, string $userText, InstagramService $instagram): void
    {
        $token = $instagram->token();
        if (! $token) {
            return;
        }

        $template = Setting::get(
            'ig_auto_reply_template',
            '✅ Aapka jawab note ho gaya hai! Sahi jawab janne ke liye reel ko end tak dekhein 🎯'
        );

        // Customize reply if option detected
        $replyText = $template;
        $upper = strtoupper(trim($userText));
        if (in_array($upper, ['A', 'B', 'C', 'D', 'OPTION A', 'OPTION B', 'OPTION C', 'OPTION D'])) {
            $replyText = "🎯 Option {$upper} note kar liya gaya hai! Sahi answer dekhne ke liye reel check karein! 💡";
        }

        try {
            $res = Http::post("https://graph.facebook.com/v22.0/{$commentId}/replies", [
                'message'      => $replyText,
                'access_token' => $token,
            ]);

            Log::info('Instagram auto-reply sent', ['comment_id' => $commentId, 'res' => $res->json()]);
        } catch (\Throwable $e) {
            Log::error('Instagram auto-reply failed', ['error' => $e->getMessage()]);
        }
    }
}
