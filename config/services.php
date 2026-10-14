<?php

declare(strict_types=1);

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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'repair' => [
        'root' => env('REPAIR_ROOT', storage_path('app/private/repair-data/repair')),
        'archive_root' => env('REPAIR_ARCHIVE_ROOT', storage_path('app/private/repair-data/archive')),
    ],

    'telegram' => [
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'max_age_seconds' => (int) env('TELEGRAM_LOGIN_MAX_AGE', 86400),
        'dev_login' => (bool) env('TELEGRAM_DEV_LOGIN', false),
    ],

    'yookassa' => [
        'shop_id' => env('YOOKASSA_SHOP_ID'),
        'secret_key' => env('YOOKASSA_SECRET_KEY'),
        'api_url' => env('YOOKASSA_API_URL', 'https://api.yookassa.ru/v3'),
        'webhook_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('YOOKASSA_WEBHOOK_IPS', ''))))),
    ],

];
