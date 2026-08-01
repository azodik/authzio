<?php

namespace App\Services\Billing;

use App\Enums\EmailTemplateSlug;
use App\Enums\SubscriptionStatus;
use App\Models\BillingPlan;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Services\Mail\EmailUsageTracker;
use App\Services\Mail\TransactionalMailer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BillingNotifier
{
    public function __construct(
        private readonly TransactionalMailer $mailer,
    ) {}

    public function notifyPlanChange(
        Organization $organization,
        ?BillingPlan $previousPlan,
        BillingPlan $newPlan,
        SubscriptionStatus $status,
    ): void {
        if (! config('billing.enabled', true)) {
            return;
        }

        if ($status === SubscriptionStatus::Cancelled || $status === SubscriptionStatus::Expired) {
            $this->sendToBillingContacts($organization, EmailTemplateSlug::PlanCancelled, [
                'organization_name' => $organization->name,
                'plan_name' => $previousPlan?->name ?? $newPlan->name,
                'billing_url' => $this->billingUrl($organization),
            ]);

            return;
        }

        if ($previousPlan === null || $previousPlan->id === $newPlan->id) {
            if ($previousPlan === null && $newPlan->slug !== 'free') {
                $this->sendToBillingContacts($organization, EmailTemplateSlug::PlanUpgraded, [
                    'organization_name' => $organization->name,
                    'previous_plan_name' => 'Free',
                    'plan_name' => $newPlan->name,
                    'mau_limit' => number_format($newPlan->mau_limit),
                    'billing_url' => $this->billingUrl($organization),
                ]);
            }

            return;
        }

        $upgraded = $newPlan->mau_limit > $previousPlan->mau_limit
            || $newPlan->sort_order > $previousPlan->sort_order;

        $slug = $upgraded ? EmailTemplateSlug::PlanUpgraded : EmailTemplateSlug::PlanDowngraded;

        $this->sendToBillingContacts($organization, $slug, [
            'organization_name' => $organization->name,
            'previous_plan_name' => $previousPlan->name,
            'plan_name' => $newPlan->name,
            'mau_limit' => number_format($newPlan->mau_limit),
            'billing_url' => $this->billingUrl($organization),
        ]);
    }

    /**
     * Evaluate MAU / application / platform-email quotas and email at each crossed threshold.
     */
    public function checkUsageThresholds(Organization $organization): void
    {
        if (! config('billing.enabled', true)) {
            return;
        }

        $organization->loadMissing('subscription.plan');
        $subscription = $organization->subscription;

        if ($subscription === null || $subscription->plan === null) {
            return;
        }

        $this->checkMauThresholds($organization, $subscription);
        $this->checkApplicationThresholds($organization, $subscription);
        $this->checkEmailUsageThresholds($organization, $subscription);
    }

    public function checkApplicationThresholds(
        Organization $organization,
        ?OrganizationSubscription $subscription = null,
    ): void {
        if (! config('billing.enabled', true)) {
            return;
        }

        $organization->loadMissing('subscription.plan');
        $subscription ??= $organization->subscription;

        if ($subscription === null || $subscription->plan === null) {
            return;
        }

        $limit = $subscription->plan->application_limit;
        if ($limit === null || (int) $limit <= 0) {
            return;
        }

        $count = (int) $organization->oauthClients()->count();
        $periodKey = CarbonImmutable::now(config('billing.mau.timezone', 'UTC'))->format('Y-m');

        $this->dispatchThresholdEmails(
            $organization,
            $subscription,
            $periodKey,
            'applications',
            $count,
            (int) $limit,
            EmailTemplateSlug::ApplicationWarning,
            EmailTemplateSlug::ApplicationLimitReached,
            [
                'organization_name' => $organization->name,
                'plan_name' => $subscription->plan->name,
                'application_count' => number_format($count),
                'application_limit' => number_format((int) $limit),
                'billing_url' => $this->billingUrl($organization),
            ],
        );
    }

    public function checkEmailUsageThresholds(
        Organization $organization,
        ?OrganizationSubscription $subscription = null,
    ): void {
        if (! config('billing.enabled', true)) {
            return;
        }

        $organization->loadMissing('subscription.plan');
        $subscription ??= $organization->subscription;

        if ($subscription === null || $subscription->plan === null) {
            return;
        }

        /** @var EmailUsageTracker $tracker */
        $tracker = app(EmailUsageTracker::class);
        $snapshot = $tracker->snapshot($organization);
        $tz = config('billing.mau.timezone', 'UTC');
        $now = CarbonImmutable::now($tz);

        if ($snapshot['daily_limit'] !== null && $snapshot['daily_limit'] > 0) {
            $this->dispatchThresholdEmails(
                $organization,
                $subscription,
                $now->format('Y-m-d'),
                'email_daily',
                $snapshot['daily_count'],
                $snapshot['daily_limit'],
                EmailTemplateSlug::EmailUsageWarning,
                EmailTemplateSlug::EmailUsageLimitReached,
                [
                    'organization_name' => $organization->name,
                    'plan_name' => $subscription->plan->name,
                    'metric_label' => 'mail.metric.daily_platform_email_sends',
                    'usage_count' => number_format($snapshot['daily_count']),
                    'usage_limit' => number_format($snapshot['daily_limit']),
                    'period_label' => 'mail.metric.period_today',
                    'billing_url' => $this->billingUrl($organization),
                ],
            );
        }

        if ($snapshot['monthly_limit'] !== null && $snapshot['monthly_limit'] > 0) {
            $this->dispatchThresholdEmails(
                $organization,
                $subscription,
                $now->format('Y-m'),
                'email_monthly',
                $snapshot['monthly_count'],
                $snapshot['monthly_limit'],
                EmailTemplateSlug::EmailUsageWarning,
                EmailTemplateSlug::EmailUsageLimitReached,
                [
                    'organization_name' => $organization->name,
                    'plan_name' => $subscription->plan->name,
                    'metric_label' => 'mail.metric.monthly_platform_email_sends',
                    'usage_count' => number_format($snapshot['monthly_count']),
                    'usage_limit' => number_format($snapshot['monthly_limit']),
                    'period_label' => 'mail.metric.period_this_month',
                    'billing_url' => $this->billingUrl($organization),
                ],
            );
        }
    }

    private function checkMauThresholds(
        Organization $organization,
        OrganizationSubscription $subscription,
    ): void {
        /** @var UsageTracker $tracker */
        $tracker = app(UsageTracker::class);
        $mau = $tracker->monthlyActiveUsers($organization);
        $limit = (int) $subscription->plan->mau_limit;

        if ($limit <= 0) {
            return;
        }

        $periodKey = CarbonImmutable::now(config('billing.mau.timezone', 'UTC'))->format('Y-m');

        $this->dispatchThresholdEmails(
            $organization,
            $subscription,
            $periodKey,
            'mau',
            $mau,
            $limit,
            EmailTemplateSlug::MauWarning,
            EmailTemplateSlug::MauLimitReached,
            [
                'organization_name' => $organization->name,
                'plan_name' => $subscription->plan->name,
                'mau_count' => number_format($mau),
                'mau_limit' => number_format($limit),
                'billing_url' => $this->billingUrl($organization),
            ],
        );
    }

    /**
     * Send one email per crossed threshold (default 80 / 90 / 100), once per period key.
     *
     * @param  array<string, string>  $baseVariables
     */
    private function dispatchThresholdEmails(
        Organization $organization,
        OrganizationSubscription $subscription,
        string $periodKey,
        string $metric,
        int $count,
        int $limit,
        EmailTemplateSlug $warningSlug,
        EmailTemplateSlug $limitSlug,
        array $baseVariables,
    ): void {
        $utilization = ($count / $limit) * 100;

        foreach ($this->thresholds() as $threshold) {
            if ($utilization + 0.0001 < $threshold) {
                continue;
            }

            $slug = $threshold >= 100 ? $limitSlug : $warningSlug;
            $variables = array_merge($baseVariables, [
                'utilization_percent' => number_format($utilization, 1),
                'threshold_percent' => (string) (int) $threshold,
            ]);

            $this->sendUsageAlertOnce(
                $organization,
                $subscription,
                $periodKey,
                $metric.'_'.(int) $threshold,
                $slug,
                $variables,
            );
        }
    }

    /**
     * @return list<float>
     */
    private function thresholds(): array
    {
        $configured = config('billing.alerts.thresholds', [80, 90, 100]);

        if (! is_array($configured) || $configured === []) {
            return [80.0, 90.0, 100.0];
        }

        $thresholds = array_map(
            static fn (mixed $value): float => (float) $value,
            $configured,
        );

        sort($thresholds);

        return array_values(array_unique($thresholds));
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function sendUsageAlertOnce(
        Organization $organization,
        OrganizationSubscription $subscription,
        string $periodKey,
        string $kind,
        EmailTemplateSlug $slug,
        array $variables,
    ): void {
        $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];
        $alerts = is_array($metadata['usage_alerts'] ?? null) ? $metadata['usage_alerts'] : [];
        $periodAlerts = is_array($alerts[$periodKey] ?? null) ? $alerts[$periodKey] : [];

        if (! empty($periodAlerts[$kind.'_sent_at'])) {
            return;
        }

        $this->sendToBillingContacts($organization, $slug, $variables);

        $periodAlerts[$kind.'_sent_at'] = now()->toIso8601String();
        $alerts[$periodKey] = $periodAlerts;
        $metadata['usage_alerts'] = $alerts;

        $subscription->forceFill(['metadata' => $metadata])->save();
    }

    /**
     * Authzio system / console emails (billing, invites to the console, etc.).
     * Always sent via the platform mailer — never the organization's BYO provider.
     *
     * @param  array<string, string>  $variables
     */
    private function sendToBillingContacts(
        Organization $organization,
        EmailTemplateSlug $slug,
        array $variables,
    ): void {
        $recipients = $this->recipients($organization);

        if ($recipients === []) {
            Log::info('Billing email skipped — no recipients', [
                'organization_id' => $organization->id,
                'slug' => $slug->value,
            ]);

            return;
        }

        foreach ($recipients as $email) {
            $this->mailer->sendPlatform($email, $slug, $variables);
        }
    }

    /**
     * @return list<string>
     */
    private function recipients(Organization $organization): array
    {
        $emails = [];

        if (filled($organization->billing_email)) {
            $emails[] = Str::lower((string) $organization->billing_email);
        }

        $memberEmails = $organization->members()
            ->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->where('is_owner', true)->orWhere('slug', 'admin'))
            ->with('user')
            ->get()
            ->map(fn ($member) => $member->user?->email)
            ->filter(fn ($email) => is_string($email) && $email !== '')
            ->map(fn (string $email) => Str::lower($email))
            ->all();

        return array_values(array_unique([...$emails, ...$memberEmails]));
    }

    private function billingUrl(Organization $organization): string
    {
        return rtrim((string) config('app.url'), '/').'/console/'.$organization->id.'/billing';
    }
}
