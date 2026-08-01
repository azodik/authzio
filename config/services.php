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

    /*
    |--------------------------------------------------------------------------
    | Console (platform) social login
    |--------------------------------------------------------------------------
    |
    | Leave client_id / client_secret empty to disable a provider for self-host.
    | Register these redirect URIs with the IdP:
    |   {APP_URL}/console/auth/google/callback
    |   {APP_URL}/console/auth/github/callback
    |
    */
    'console_google' => [
        'client_id' => env('CONSOLE_GOOGLE_CLIENT_ID'),
        'client_secret' => env('CONSOLE_GOOGLE_CLIENT_SECRET'),
        'redirect' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/console/auth/google/callback',
    ],

    'console_github' => [
        'client_id' => env('CONSOLE_GITHUB_CLIENT_ID'),
        'client_secret' => env('CONSOLE_GITHUB_CLIENT_SECRET'),
        'redirect' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/console/auth/github/callback',
    ],

];
