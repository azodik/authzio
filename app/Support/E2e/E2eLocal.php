<?php

namespace App\Support\E2e;

/**
 * Shared gate for Playwright fixture routes (__e2e/*).
 * Only when APP_ENV=e2e — never local/production/testing by accident.
 */
final class E2eLocal
{
    public static function enabled(): bool
    {
        return app()->environment('e2e');
    }
}
