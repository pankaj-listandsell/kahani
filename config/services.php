<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // ffmpeg binary path (card image -> reel video conversion)
    'ffmpeg' => [
        'path' => env('FFMPEG_PATH', 'ffmpeg'),
    ],

    // Google AI Studio (Gemini) — TTS voice-over for reels/shorts.
    // Free API key: https://aistudio.google.com/apikey
    'gemini' => [
        'key'         => env('GEMINI_API_KEY'),
        'tts_model'   => env('GEMINI_TTS_MODEL', 'gemini-2.5-flash-preview-tts'),
        'tts_voice'   => env('GEMINI_TTS_VOICE', 'Kore'), // default voice (Hindi supported)
        'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image'), // "Nano Banana"
    ],

    // Google / YouTube Data API v3 (OAuth) — Shorts auto-upload
    // client id/secret Google Cloud Console se aate hain (app-level, sabhi users share karte hain).
    // Har user apna channel OAuth se connect karta hai (refresh token per-user settings me).
    'youtube' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        // Google Console me isi exact URL ko "Authorized redirect URI" me daalna hai.
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

];
