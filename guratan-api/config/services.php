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

    // Pemantauan biaya AI (2026-10-28). Harga USD per 1 juta token per model - ISI/CEK ULANG dari halaman
    // harga Google (Gemini API pricing); angka di sini hanya perkiraan awal, bukan tagihan resmi.
    'llm_cost' => [
        'pricing' => [
            'gemini-2.5-flash' => ['input' => (float) env('LLM_PRICE_INPUT_USD', 0.30), 'output' => (float) env('LLM_PRICE_OUTPUT_USD', 2.50)],
        ],
        'default_pricing' => ['input' => (float) env('LLM_PRICE_INPUT_USD', 0.30), 'output' => (float) env('LLM_PRICE_OUTPUT_USD', 2.50)],
        'usd_idr' => (float) env('LLM_USD_IDR', 16500),
        // Anggaran bulanan (Rp). Kosong = tanpa anggaran. Peringatan admin di 80% dan 100%.
        'monthly_budget_idr' => env('LLM_MONTHLY_BUDGET_IDR') !== null && env('LLM_MONTHLY_BUDGET_IDR') !== '' ? (int) env('LLM_MONTHLY_BUDGET_IDR') : null,
    ],

    // LLM_PROVIDER=gemini: sampai 10 key, dirotasi otomatis saat limit
    // (GeminiKeyPool). Cooldown dalam detik.
    'gemini' => [
        'keys' => [
            1 => env('GEMINI_API_KEY_1'),
            2 => env('GEMINI_API_KEY_2'),
            3 => env('GEMINI_API_KEY_3'),
            4 => env('GEMINI_API_KEY_4'),
            5 => env('GEMINI_API_KEY_5'),
            6 => env('GEMINI_API_KEY_6'),
            7 => env('GEMINI_API_KEY_7'),
            8 => env('GEMINI_API_KEY_8'),
            9 => env('GEMINI_API_KEY_9'),
            10 => env('GEMINI_API_KEY_10'),
        ],
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'max_output_tokens' => env('GEMINI_MAX_OUTPUT_TOKENS', 2048),
        'timeout' => env('GEMINI_TIMEOUT', 60),
        'cooldown' => env('GEMINI_COOLDOWN_SECONDS', 60),
        'daily_cooldown' => env('GEMINI_DAILY_COOLDOWN_SECONDS', 3600),
        'invalid_cooldown' => env('GEMINI_INVALID_COOLDOWN_SECONDS', 86400),
    ],

    /*
    |--------------------------------------------------------------------------
    | DOKU Payment Gateway
    |--------------------------------------------------------------------------
    |
    | client_id & secret_key come from DOKU Back Office (Sandbox and
    | Production have SEPARATE credentials - don't reuse sandbox keys in
    | production or vice versa). See guratan-api/CLAUDE.md "Payment (DOKU)"
    | section for where these are actually used.
    |
    */
    'doku' => [
        'client_id' => env('DOKU_CLIENT_ID'),
        'secret_key' => env('DOKU_SECRET_KEY'),
        'is_production' => env('DOKU_IS_PRODUCTION', false),
        'base_url' => env('DOKU_IS_PRODUCTION', false)
            ? 'https://api.doku.com'
            : 'https://api-sandbox.doku.com',
        // Ke mana browser klien diarahkan balik setelah bayar di halaman
        // DOKU (auto_redirect). Bukan webhook - itu Notification URL,
        // dikonfigurasi di DOKU Back Office, bukan di sini.
        'callback_url' => env('DOKU_CALLBACK_URL', rtrim((string) env('FRONTEND_URL', config('app.url')), '/').'/dashboard'),
    ],


    /*
    |--------------------------------------------------------------------------
    | Xendit Payment Gateway (alternatif DOKU)
    |--------------------------------------------------------------------------
    |
    | secret_key dari Xendit Dashboard - sandbox vs produksi ditentukan prefix key
    | (xnd_development_... / xnd_production_...), base_url sama. callback_token = nilai
    | X-Callback-Token statis (Settings > Webhooks) untuk memverifikasi webhook.
    |
    */
    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
        'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
        'success_redirect_url' => env('XENDIT_SUCCESS_REDIRECT_URL', rtrim((string) env('FRONTEND_URL', config('app.url')), '/').'/dashboard'),
    ],


    /*
    |--------------------------------------------------------------------------
    | Fonnte (WhatsApp gateway)
    |--------------------------------------------------------------------------
    |
    | Token perangkat dari https://fonnte.com (Perangkat > pilih perangkat > Token).
    | Kosong = notifikasi WhatsApp tidak dikirim sama sekali (email tetap jalan).
    |
    */
    'fonnte' => [
        'token' => env('FONNTE_TOKEN'),
    ],

];
