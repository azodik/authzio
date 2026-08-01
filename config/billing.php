<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloud billing mode
    |--------------------------------------------------------------------------
    |
    | Self-hosted installs typically leave billing disabled. Authzio Cloud
    | enables MAU metering and Dodo Payments subscriptions.
    |
    */

    'enabled' => (bool) env('AUTHZIO_BILLING_ENABLED', false),

    'currency' => env('AUTHZIO_BILLING_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | MAU definition
    |--------------------------------------------------------------------------
    |
    | Monthly Active Users = distinct subject_key values with a qualifying
    | authentication event in the current calendar month (UTC): unique people
    | who signed in or received tokens that month.
    |
    */

    'mau' => [
        'timezone' => env('AUTHZIO_MAU_TIMEZONE', 'UTC'),
        'qualifying_events' => [
            'user.authenticated',
            'console.login',
            'token.issued',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage alerts
    |--------------------------------------------------------------------------
    |
    | Email owners/admins (and billing_email) when MAU, application, or
    | platform-email usage crosses each threshold. Each threshold is emailed
    | at most once per organization per period (calendar month for MAU/apps/
    | monthly email; calendar day for daily email).
    |
    | AUTHZIO_USAGE_ALERT_THRESHOLDS=80,90,100
    |
    */

    'alerts' => [
        'thresholds' => array_values(array_filter(array_map(
            static fn (string $value): float => (float) trim($value),
            explode(',', (string) env('AUTHZIO_USAGE_ALERT_THRESHOLDS', '80,90,100')),
        ), static fn (float $value): bool => $value > 0)),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dodo Payments
    |--------------------------------------------------------------------------
    */

    'dodo' => [
        'api_key' => env('DODO_PAYMENTS_API_KEY'),
        'webhook_secret' => env('DODO_PAYMENTS_WEBHOOK_SECRET'),
        // test_mode → https://test.dodopayments.com · live_mode → https://live.dodopayments.com
        'base_url' => env(
            'DODO_PAYMENTS_BASE_URL',
            env('DODO_PAYMENTS_ENVIRONMENT', 'test_mode') === 'live_mode'
                ? 'https://live.dodopayments.com'
                : 'https://test.dodopayments.com',
        ),
        'environment' => env('DODO_PAYMENTS_ENVIRONMENT', 'test_mode'), // test_mode|live_mode
        'return_url' => env('DODO_PAYMENTS_RETURN_URL', env('APP_URL').'/console/{organization_id}/billing'),
        'products' => [
            'starter' => env('DODO_PRODUCT_STARTER'),
            'growth' => env('DODO_PRODUCT_GROWTH'),
            'scale' => env('DODO_PRODUCT_SCALE'),
        ],
    ],

];
