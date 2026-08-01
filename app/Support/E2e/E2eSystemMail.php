<?php

namespace App\Support\E2e;

use App\Enums\EmailTemplateSlug;
use App\Enums\SupportedLocale;
use App\Models\User;
use App\Services\Mail\TransactionalMailer;

/**
 * Local Playwright helper: render and send every platform system email to Mailpit.
 */
final class E2eSystemMail
{
    public static function enabled(): bool
    {
        return E2eLocal::enabled();
    }

    /**
     * @return array{
     *     to: string,
     *     locale: string,
     *     sent: list<array{slug: string, subject: string}>
     * }
     */
    public static function sendAll(?string $to = null, ?string $locale = null): array
    {
        $to = $to !== null && $to !== '' ? strtolower(trim($to)) : 'e2e-system-mail@authzio.test';
        $locale = $locale !== null && $locale !== '' && in_array($locale, SupportedLocale::values(), true)
            ? $locale
            : SupportedLocale::En->value;

        User::query()->updateOrCreate(
            ['email' => $to],
            [
                'name' => 'E2E System Mail',
                'password' => 'E2eTestPass123!',
                'email_verified_at' => now(),
                'preferred_locale' => $locale,
            ],
        );

        $mailer = app(TransactionalMailer::class);
        $sent = [];

        foreach (EmailTemplateSlug::cases() as $slug) {
            $mailer->sendPlatform(
                $to,
                $slug,
                self::sampleVariables($slug),
                locale: $locale,
                queue: false,
            );

            $sent[] = [
                'slug' => $slug->value,
                'subject' => $slug->defaultName(),
            ];
        }

        return [
            'to' => $to,
            'locale' => $locale,
            'sent' => $sent,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function sampleVariables(EmailTemplateSlug $slug): array
    {
        $base = [
            'user_name' => 'E2E System Mail',
            'organization_name' => 'E2E Acme',
            'inviter_name' => 'E2E Owner',
            'role' => 'member',
            'invite_url' => 'http://127.0.0.1:8000/console/invitations/demo',
            'expires_at' => now()->addDays(7)->toDayDateTimeString(),
            'magic_link_url' => 'http://127.0.0.1:8000/console/magic/demo',
            'reset_url' => 'http://127.0.0.1:8000/console/reset-password?token=demo',
            'expires_minutes' => '60',
            'verification_code' => '123456',
            'verify_url' => 'http://127.0.0.1:8000/console/verify-email?code=123456',
            'mfa_code' => '654321',
            'application_name' => 'E2E Demo App',
            'otp_code' => '111222',
            'previous_plan_name' => 'Free',
            'plan_name' => 'Starter',
            'mau_limit' => '10',
            'mau_count' => '8',
            'application_limit' => '10',
            'application_count' => '8',
            'usage_count' => '8',
            'usage_limit' => '10',
            'utilization_percent' => '80.0',
            'threshold_percent' => '80',
            'metric_label' => 'mail.metric.daily_platform_email_sends',
            'period_label' => 'mail.metric.period_today',
            'billing_url' => 'http://127.0.0.1:8000/console/demo-org/billing',
            'console_url' => 'http://127.0.0.1:8000/console',
        ];

        return match ($slug) {
            EmailTemplateSlug::MauLimitReached,
            EmailTemplateSlug::ApplicationLimitReached,
            EmailTemplateSlug::EmailUsageLimitReached => array_merge($base, [
                'mau_count' => '10',
                'application_count' => '10',
                'usage_count' => '10',
                'utilization_percent' => '100.0',
                'threshold_percent' => '100',
            ]),
            default => $base,
        };
    }
}
