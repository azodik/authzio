<?php

namespace App\Enums;

use App\Services\Mail\EmailHtml;

enum EmailTemplateSlug: string
{
    case Welcome = 'welcome';
    case InviteMember = 'invite_member';
    case MagicLink = 'magic_link';
    case PasswordReset = 'password_reset';
    case PasswordChanged = 'password_changed';
    case EmailVerification = 'email_verification';
    case MfaCode = 'mfa_code';
    case EmailOtp = 'email_otp';
    case PlanUpgraded = 'plan_upgraded';
    case PlanDowngraded = 'plan_downgraded';
    case PlanCancelled = 'plan_cancelled';
    case MauWarning = 'mau_warning';
    case MauLimitReached = 'mau_limit_reached';
    case ApplicationWarning = 'application_warning';
    case ApplicationLimitReached = 'application_limit_reached';
    case EmailUsageWarning = 'email_usage_warning';
    case EmailUsageLimitReached = 'email_usage_limit_reached';

    public function defaultName(): string
    {
        return match ($this) {
            self::Welcome => 'Welcome',
            self::InviteMember => 'Member invitation',
            self::MagicLink => 'Magic link sign-in',
            self::PasswordReset => 'Password reset',
            self::PasswordChanged => 'Password changed',
            self::EmailVerification => 'Email verification',
            self::MfaCode => 'MFA verification code',
            self::EmailOtp => 'Email sign-in code',
            self::PlanUpgraded => 'Plan upgraded',
            self::PlanDowngraded => 'Plan downgraded',
            self::PlanCancelled => 'Plan cancelled',
            self::MauWarning => 'MAU usage warning',
            self::MauLimitReached => 'MAU limit reached',
            self::ApplicationWarning => 'Application usage warning',
            self::ApplicationLimitReached => 'Application limit reached',
            self::EmailUsageWarning => 'Platform email usage warning',
            self::EmailUsageLimitReached => 'Platform email limit reached',
        };
    }

    public function defaultSubject(): string
    {
        return match ($this) {
            self::Welcome => 'Welcome to {{product_name}}',
            self::InviteMember => 'Join {{organization_name}} on {{product_name}}',
            self::MagicLink => 'Your {{product_name}} sign-in link',
            self::PasswordReset => 'Reset your {{product_name}} password',
            self::PasswordChanged => 'Your {{product_name}} password was changed',
            self::EmailVerification => 'Verify your {{product_name}} email',
            self::MfaCode => 'Your {{product_name}} security code',
            self::EmailOtp => 'Your {{application_name}} sign-in code',
            self::PlanUpgraded => '{{organization_name}} is now on {{plan_name}}',
            self::PlanDowngraded => '{{organization_name}} moved to {{plan_name}}',
            self::PlanCancelled => '{{organization_name}} subscription cancelled',
            self::MauWarning => '{{organization_name}} is at {{threshold_percent}}% of its MAU limit',
            self::MauLimitReached => '{{organization_name}} reached its MAU limit',
            self::ApplicationWarning => '{{organization_name}} is at {{threshold_percent}}% of its application limit',
            self::ApplicationLimitReached => '{{organization_name}} reached its application limit',
            self::EmailUsageWarning => '{{organization_name}} is at {{threshold_percent}}% of its {{metric_label}} limit',
            self::EmailUsageLimitReached => '{{organization_name}} reached its {{metric_label}} limit',
        };
    }

    /**
     * HTML body for the current app locale (set by TransactionalMailer before calling).
     */
    public function defaultHtml(): string
    {
        $t = static fn (string $key): string => __('mail.'.$key);

        return match ($this) {
            self::Welcome => EmailHtml::heading($t('welcome.heading'))
                .EmailHtml::paragraph($t('welcome.hi'))
                .EmailHtml::paragraph($t('welcome.body'))
                .EmailHtml::button('{{console_url}}', $t('welcome.cta'))
                .EmailHtml::muted($t('welcome.muted')),

            self::InviteMember => EmailHtml::heading($t('invite_member.heading'))
                .EmailHtml::paragraph($t('invite_member.body'))
                .EmailHtml::button('{{invite_url}}', $t('invite_member.cta'))
                .EmailHtml::muted($t('invite_member.muted')),

            self::MagicLink => EmailHtml::heading($t('magic_link.heading'))
                .EmailHtml::paragraph($t('magic_link.body'))
                .EmailHtml::button('{{magic_link_url}}', $t('magic_link.cta'))
                .EmailHtml::muted($t('magic_link.muted')),

            self::PasswordReset => EmailHtml::heading($t('password_reset.heading'))
                .EmailHtml::paragraph($t('password_reset.hi'))
                .EmailHtml::paragraph($t('password_reset.body'))
                .EmailHtml::button('{{reset_url}}', $t('password_reset.cta'))
                .EmailHtml::muted($t('password_reset.muted')),

            self::PasswordChanged => EmailHtml::heading($t('password_changed.heading'))
                .EmailHtml::paragraph($t('password_changed.hi'))
                .EmailHtml::paragraph($t('password_changed.body'))
                .EmailHtml::muted($t('password_changed.muted')),

            self::EmailVerification => EmailHtml::heading($t('email_verification.heading'))
                .EmailHtml::paragraph($t('email_verification.hi'))
                .EmailHtml::paragraph($t('email_verification.body'))
                .EmailHtml::code('{{verification_code}}')
                .EmailHtml::paragraph($t('email_verification.link'))
                .EmailHtml::muted($t('email_verification.muted')),

            self::MfaCode => EmailHtml::heading($t('mfa_code.heading'))
                .EmailHtml::paragraph($t('mfa_code.body'))
                .EmailHtml::code('{{mfa_code}}')
                .EmailHtml::muted($t('mfa_code.muted')),

            self::EmailOtp => EmailHtml::heading($t('email_otp.heading'))
                .EmailHtml::paragraph($t('email_otp.body'))
                .EmailHtml::code('{{otp_code}}')
                .EmailHtml::muted($t('email_otp.muted')),

            self::PlanUpgraded => EmailHtml::heading($t('plan_upgraded.heading'))
                .EmailHtml::paragraph($t('plan_upgraded.body'))
                .EmailHtml::paragraph($t('plan_upgraded.mau'))
                .EmailHtml::button('{{billing_url}}', $t('plan_upgraded.cta'))
                .EmailHtml::muted($t('plan_upgraded.muted')),

            self::PlanDowngraded => EmailHtml::heading($t('plan_downgraded.heading'))
                .EmailHtml::paragraph($t('plan_downgraded.body'))
                .EmailHtml::paragraph($t('plan_downgraded.mau'))
                .EmailHtml::button('{{billing_url}}', $t('plan_downgraded.cta'))
                .EmailHtml::muted($t('plan_downgraded.muted')),

            self::PlanCancelled => EmailHtml::heading($t('plan_cancelled.heading'))
                .EmailHtml::paragraph($t('plan_cancelled.body'))
                .EmailHtml::paragraph($t('plan_cancelled.help'))
                .EmailHtml::button('{{billing_url}}', $t('plan_cancelled.cta')),

            self::MauWarning => EmailHtml::heading($t('mau_warning.heading'))
                .EmailHtml::paragraph($t('mau_warning.body'))
                .EmailHtml::paragraph($t('mau_warning.threshold'))
                .EmailHtml::button('{{billing_url}}', $t('mau_warning.cta'))
                .EmailHtml::muted($t('mau_warning.muted')),

            self::MauLimitReached => EmailHtml::heading($t('mau_limit_reached.heading'))
                .EmailHtml::paragraph($t('mau_limit_reached.body'))
                .EmailHtml::paragraph($t('mau_limit_reached.usage'))
                .EmailHtml::button('{{billing_url}}', $t('mau_limit_reached.cta'))
                .EmailHtml::muted($t('mau_limit_reached.muted')),

            self::ApplicationWarning => EmailHtml::heading($t('application_warning.heading'))
                .EmailHtml::paragraph($t('application_warning.body'))
                .EmailHtml::paragraph($t('application_warning.threshold'))
                .EmailHtml::button('{{billing_url}}', $t('application_warning.cta'))
                .EmailHtml::muted($t('application_warning.muted')),

            self::ApplicationLimitReached => EmailHtml::heading($t('application_limit_reached.heading'))
                .EmailHtml::paragraph($t('application_limit_reached.body'))
                .EmailHtml::paragraph($t('application_limit_reached.usage'))
                .EmailHtml::button('{{billing_url}}', $t('application_limit_reached.cta'))
                .EmailHtml::muted($t('application_limit_reached.muted')),

            self::EmailUsageWarning => EmailHtml::heading($t('email_usage_warning.heading'))
                .EmailHtml::paragraph($t('email_usage_warning.body'))
                .EmailHtml::paragraph($t('email_usage_warning.threshold'))
                .EmailHtml::button('{{billing_url}}', $t('email_usage_warning.cta'))
                .EmailHtml::muted($t('email_usage_warning.muted')),

            self::EmailUsageLimitReached => EmailHtml::heading($t('email_usage_limit_reached.heading'))
                .EmailHtml::paragraph($t('email_usage_limit_reached.body'))
                .EmailHtml::paragraph($t('email_usage_limit_reached.usage'))
                .EmailHtml::button('{{billing_url}}', $t('email_usage_limit_reached.cta'))
                .EmailHtml::muted($t('email_usage_limit_reached.muted')),
        };
    }

    /**
     * Auth / end-user templates that organizations can customize in the console.
     * Platform templates (welcome, billing, MAU) stay system-owned.
     */
    public function isOrganizationCustomizable(): bool
    {
        return match ($this) {
            self::MagicLink,
            self::PasswordReset,
            self::PasswordChanged,
            self::EmailVerification,
            self::MfaCode,
            self::EmailOtp => true,
            self::Welcome,
            self::InviteMember,
            self::PlanUpgraded,
            self::PlanDowngraded,
            self::PlanCancelled,
            self::MauWarning,
            self::MauLimitReached,
            self::ApplicationWarning,
            self::ApplicationLimitReached,
            self::EmailUsageWarning,
            self::EmailUsageLimitReached => false,
        };
    }

    /**
     * @return list<self>
     */
    public static function organizationCustomizable(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $slug): bool => $slug->isOrganizationCustomizable(),
        ));
    }

    /**
     * @return list<string>
     */
    public function variables(): array
    {
        return match ($this) {
            self::Welcome => ['user_name', 'product_name', 'console_url'],
            self::InviteMember => ['organization_name', 'inviter_name', 'role', 'invite_url', 'expires_at', 'product_name'],
            self::MagicLink => ['product_name', 'magic_link_url'],
            self::PasswordReset => ['user_name', 'product_name', 'reset_url', 'expires_minutes'],
            self::PasswordChanged => ['user_name', 'product_name'],
            self::EmailVerification => ['user_name', 'product_name', 'verification_code', 'verify_url'],
            self::MfaCode => ['product_name', 'mfa_code'],
            self::EmailOtp => ['application_name', 'otp_code'],
            self::PlanUpgraded, self::PlanDowngraded => [
                'organization_name',
                'previous_plan_name',
                'plan_name',
                'mau_limit',
                'billing_url',
            ],
            self::PlanCancelled => ['organization_name', 'plan_name', 'billing_url'],
            self::MauWarning => [
                'organization_name',
                'plan_name',
                'mau_count',
                'mau_limit',
                'utilization_percent',
                'threshold_percent',
                'billing_url',
            ],
            self::MauLimitReached => [
                'organization_name',
                'plan_name',
                'mau_count',
                'mau_limit',
                'utilization_percent',
                'threshold_percent',
                'billing_url',
            ],
            self::ApplicationWarning => [
                'organization_name',
                'plan_name',
                'application_count',
                'application_limit',
                'utilization_percent',
                'threshold_percent',
                'billing_url',
            ],
            self::ApplicationLimitReached => [
                'organization_name',
                'plan_name',
                'application_count',
                'application_limit',
                'utilization_percent',
                'threshold_percent',
                'billing_url',
            ],
            self::EmailUsageWarning, self::EmailUsageLimitReached => [
                'organization_name',
                'plan_name',
                'metric_label',
                'usage_count',
                'usage_limit',
                'period_label',
                'utilization_percent',
                'threshold_percent',
                'billing_url',
            ],
        };
    }
}
