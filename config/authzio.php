<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Release identity (SemVer + build number)
    |--------------------------------------------------------------------------
    |
    | VERSION file is the SemVer source of truth. CI stamps build / commit into
    | the Docker image (build-info.json + AUTHZIO_* env). package.json may mirror
    | SemVer for tooling but is not authoritative. composer.json has no version.
    |
    */

    'release' => [
        'version' => env('AUTHZIO_VERSION'),
        'build' => env('AUTHZIO_BUILD'),
        'commit' => env('AUTHZIO_COMMIT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    */

    'password' => [
        'min_length' => (int) env('AUTHZIO_PASSWORD_MIN_LENGTH', 12),
        'require_mixed_case' => (bool) env('AUTHZIO_PASSWORD_REQUIRE_MIXED_CASE', true),
        'require_numbers' => (bool) env('AUTHZIO_PASSWORD_REQUIRE_NUMBERS', true),
        'require_symbols' => (bool) env('AUTHZIO_PASSWORD_REQUIRE_SYMBOLS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session
    |--------------------------------------------------------------------------
    */

    'session' => [
        'lifetime_minutes' => (int) env('AUTHZIO_SESSION_LIFETIME', 120),
        'single_device' => (bool) env('AUTHZIO_SESSION_SINGLE_DEVICE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Factor Authentication
    |--------------------------------------------------------------------------
    */

    'mfa' => [
        'enabled' => (bool) env('AUTHZIO_MFA_ENABLED', true),
        'required_for_admins' => (bool) env('AUTHZIO_MFA_REQUIRED_FOR_ADMINS', false),
        'issuer' => env('AUTHZIO_MFA_ISSUER', env('APP_NAME', 'Authzio')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Domains
    |--------------------------------------------------------------------------
    */

    'domains' => [
        'root' => env('AUTHZIO_DOMAIN_ROOT', 'authzio.test'),
        // Live DNS TXT check on Verify when Cloudflare SaaS is not configured.
        'dns_verify' => filter_var(env('AUTHZIO_DOMAIN_DNS_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
        // Shared CNAME target for custom domains (Cloudflare for SaaS).
        'cname_target' => env('AUTHZIO_CUSTOM_DOMAIN_CNAME_TARGET', 'customers.authzio.com'),
    ],

    'cloudflare' => [
        'enabled' => filter_var(env('CLOUDFLARE_CUSTOM_HOSTNAMES_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        // Certificate validation: txt (show DCV TXT records) or http (after CNAME).
        'ssl_method' => env('CLOUDFLARE_CUSTOM_HOSTNAME_SSL_METHOD', 'txt'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Invitations
    |--------------------------------------------------------------------------
    */

    'invitations' => [
        'expires_days' => (int) env('AUTHZIO_INVITE_EXPIRES_DAYS', 7),
    ],

    'otp' => [
        'ttl_minutes' => (int) env('AUTHZIO_OTP_TTL_MINUTES', 10),
        'max_attempts' => (int) env('AUTHZIO_OTP_MAX_ATTEMPTS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenID Connect / OAuth 2.1
    |--------------------------------------------------------------------------
    */

    'oidc' => [
        'scopes_supported' => ['openid', 'profile', 'email', 'offline_access'],
        'access_token_ttl' => (int) env('AUTHZIO_ACCESS_TOKEN_TTL', 3600),
        'id_token_ttl' => (int) env('AUTHZIO_ID_TOKEN_TTL', 3600),
        'refresh_token_ttl' => (int) env('AUTHZIO_REFRESH_TOKEN_TTL', 2592000),
        'auth_code_ttl_minutes' => (int) env('AUTHZIO_AUTH_CODE_TTL_MINUTES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public assets (logos, avatars, …)
    |--------------------------------------------------------------------------
    |
    | Leave AUTHZIO_ASSETS_DISK empty to use the `public` disk locally, or the
    | default filesystem disk when FILESYSTEM_DISK is s3 / Laravel Cloud.
    | On Laravel Cloud, name the attached bucket disk (or set this explicitly).
    |
    */

    'assets' => [
        'disk' => env('AUTHZIO_ASSETS_DISK'),
        'max_kilobytes' => (int) env('AUTHZIO_ASSETS_MAX_KB', 2048),
        'mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    ],

];
