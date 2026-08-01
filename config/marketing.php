<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public site URL for SEO / ads (falls back to APP_URL)
    |--------------------------------------------------------------------------
    */
    'url' => rtrim((string) env('MARKETING_URL', env('APP_URL', 'http://localhost')), '/'),

    'brand' => 'Authzio',
    'tagline' => 'Open-source identity and access management',
    'organization' => 'Azodik Consulting Private Limited',
    'organization_url' => 'https://azodik.com',
    'github' => 'https://github.com/azodik/authzio',
    'linkedin' => env('MARKETING_LINKEDIN', 'https://www.linkedin.com/company/azodik'),
    'instagram' => env('MARKETING_INSTAGRAM', 'https://www.instagram.com/azodikhq'),
    'twitter' => env('MARKETING_TWITTER', '@azodikhq'),
    'locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Primary keywords (used in copy + schema; not stuffed into every meta)
    |--------------------------------------------------------------------------
    */
    'keywords' => [
        'open source identity provider',
        'self-hosted OIDC',
        'OAuth 2.1',
        'OpenID Connect',
        'Auth0 alternative',
        'Keycloak alternative',
        'Laravel authentication',
        'IAM platform',
        'passkeys MFA',
        'self-hosted auth',
        'SSO for SaaS',
        'RBAC organizations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ads / analytics (leave empty until ready — scripts only load when set)
    |--------------------------------------------------------------------------
    */
    'google_site_verification' => env('GOOGLE_SITE_VERIFICATION', ''),
    'facebook_domain_verification' => env('FACEBOOK_DOMAIN_VERIFICATION', ''),
    'bing_site_verification' => env('BING_SITE_VERIFICATION', ''),

    'gtm_id' => env('GOOGLE_TAG_MANAGER_ID', ''),
    'ga4_id' => env('GOOGLE_ANALYTICS_ID', ''),
    'meta_pixel_id' => env('META_PIXEL_ID', ''),
    'linkedin_partner_id' => env('LINKEDIN_PARTNER_ID', ''),
    'reddit_pixel_id' => env('REDDIT_PIXEL_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Default social share image (1200×630)
    |--------------------------------------------------------------------------
    */
    'og_image' => '/images/og-image.jpg',
    'og_image_width' => 1200,
    'og_image_height' => 630,

];
