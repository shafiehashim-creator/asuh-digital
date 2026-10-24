<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // CHIP Collect (https://docs.chip-in.asia). Test and live use the same
    // host; the secret key decides the mode.
    'chip' => [
        'base_url' => env('CHIP_BASE_URL', 'https://gate.chip-in.asia/api/v1'),
        'brand_id' => env('CHIP_BRAND_ID'),
        'secret_key' => env('CHIP_API_KEY'),
        'test_mode' => (bool) env('CHIP_TEST_MODE', true),
        // Only for webhooks created in the CHIP portal; success callbacks are
        // verified with the company key from GET /public_key/.
        'webhook_public_key' => env('CHIP_WEBHOOK_PUBLIC_KEY'),
    ],

];
