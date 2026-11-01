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

    'navora_admin' => [
        'email' => env('NAVORA_ADMIN_EMAIL', 'Navora@gmail.com'),
        'password' => env('NAVORA_ADMIN_PASSWORD'),
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'base_url' => env(
            'MIDTRANS_BASE_URL',
            env('MIDTRANS_IS_PRODUCTION', false)
                ? 'https://app.midtrans.com'
                : 'https://app.sandbox.midtrans.com',
        ),
        'api_base_url' => env(
            'MIDTRANS_API_BASE_URL',
            env('MIDTRANS_IS_PRODUCTION', false)
                ? 'https://api.midtrans.com'
                : 'https://api.sandbox.midtrans.com',
        ),
    ],

    'rajaongkir' => [
        'api_key' => env('RAJAONGKIR_API_KEY'),
        'base_url' => env('RAJAONGKIR_BASE_URL', 'https://rajaongkir.komerce.id/api/v1'),
        'couriers' => array_filter(array_map(
            'trim',
            explode(',', env('RAJAONGKIR_COURIERS', 'jne,pos,tiki,jnt,sicepat')),
        )),
    ],

    'navora' => [
        'vendor_subscription_amount' => (int) env('NAVORA_VENDOR_SUBSCRIPTION_AMOUNT', 500000),
        'vendor_subscription_days' => (int) env('NAVORA_VENDOR_SUBSCRIPTION_DAYS', 30),
        'platform_fee_percent' => (int) env('NAVORA_PLATFORM_FEE_PERCENT', 2),
        'free_vendor_monthly_rfq_limit' => (int) env('NAVORA_FREE_VENDOR_MONTHLY_RFQ_LIMIT', 10),
    ],

];
