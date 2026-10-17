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

    'apple' => [
        'issuer_id' => env('APPLE_ISSUER_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key_path' => env('APPLE_PRIVATE_KEY_PATH'),
        'bundle_id' => env('APPLE_BUNDLE_ID'),
        'root_ca_path' => env('APPLE_ROOT_CA_PATH'),
        'environment' => env('APPLE_STORE_ENVIRONMENT', 'Sandbox'),
        'product_ids' => array_values(array_filter(explode(',', env('APPLE_PRODUCT_IDS', '')))),
    ],

    'google_play' => [
        'package_name' => env('GOOGLE_PLAY_PACKAGE_NAME'),
        'service_account_path' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_PATH'),
        'product_ids' => array_values(array_filter(explode(',', env('GOOGLE_PLAY_PRODUCT_IDS', '')))),
        'pubsub_token' => env('GOOGLE_PLAY_PUBSUB_TOKEN'),
        'pubsub_audience' => env('GOOGLE_PUBSUB_AUDIENCE'),
        'pubsub_service_account' => env('GOOGLE_PUBSUB_SERVICE_ACCOUNT'),
    ],

];
