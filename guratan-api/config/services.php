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

    'llm' => [
        'provider' => env('LLM_PROVIDER', 'none'),
        'api_key' => env('LLM_API_KEY'),
        'endpoint' => env('LLM_API_ENDPOINT'),
        'model' => env('LLM_MODEL'),
        'selfhosted_url' => env('LLM_SELFHOSTED_URL', 'http://localhost:11434'),
        'selfhosted_model' => env('LLM_SELFHOSTED_MODEL', 'qwen2.5:14b'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Xendit Payment Gateway
    |--------------------------------------------------------------------------
    |
    | secret_key comes from Xendit Dashboard - sandbox vs production is
    | determined by the key's OWN prefix (xnd_development_... vs
    | xnd_production_...), not a separate host/flag like the old DOKU
    | integration had - both modes share the same base_url. callback_token
    | is the static X-Callback-Token value configured in Xendit Dashboard
    | (Settings > Webhooks), used to verify incoming webhook calls. See
    | guratan-api/CLAUDE.md "Payment (Xendit)" section for where these are
    | actually used.
    |
    */
    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
        'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
        // Ke mana browser klien diarahkan balik setelah bayar di halaman
        // Invoice Xendit (success_redirect_url). Bukan webhook - itu
        // Callback URL, dikonfigurasi di Xendit Dashboard, bukan di sini.
        'success_redirect_url' => env('XENDIT_SUCCESS_REDIRECT_URL', rtrim((string) env('FRONTEND_URL', config('app.url')), '/').'/dashboard'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fonnte (WhatsApp gateway)
    |--------------------------------------------------------------------------
    |
    | Token perangkat dari https://fonnte.com (Perangkat > pilih perangkat >
    | Token). Kosong = WhatsAppService diam-diam skip pengiriman (lihat
    | catatan class-nya) - tidak menggagalkan alur utama yang memanggilnya.
    |
    */
    'fonnte' => [
        'token' => env('FONNTE_TOKEN'),
    ],

];
