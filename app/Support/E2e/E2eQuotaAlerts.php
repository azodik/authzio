<?php

namespace App\Support\E2e;

use App\Enums\ApplicationType;
use App\Enums\UsageEventType;
use App\Models\OAuthClient;
use App\Models\Organization;
use App\Models\UsageEvent;
use App\Services\Auth\LoginMethods;
use App\Services\Billing\BillingNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Local Playwright helpers to force MAU / application / email quota thresholds
 * and send real platform alert emails (Mailpit).
 */
final class E2eQuotaAlerts
{
    public static function enabled(): bool
    {
        return E2eLocal::enabled();
    }

    /**
     * @return array{
     *     organization_id: string,
     *     mau_limit: int,
     *     application_limit: int,
     *     email_daily_limit: int
     * }
     */
    public static function prepare(Organization $organization): array
    {
        $organization->loadMissing('subscription.plan');
        $plan = $organization->subscription?->plan;
        abort_if($plan === null, 404, 'E2E organization has no plan.');

        $plan->forceFill([
            'mau_limit' => 10,
            'application_limit' => 10,
            'email_daily_limit' => 10,
            'email_monthly_limit' => 10_000,
        ])->save();

        self::clearAlertState($organization);

        return [
            'organization_id' => $organization->id,
            'mau_limit' => 10,
            'application_limit' => 10,
            'email_daily_limit' => 10,
        ];
    }

    public static function clearAlertState(Organization $organization): void
    {
        $subscription = $organization->subscription;
        if ($subscription !== null) {
            $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];
            unset($metadata['usage_alerts']);
            $subscription->forceFill(['metadata' => $metadata])->save();
        }

        UsageEvent::query()->where('organization_id', $organization->id)->delete();

        DB::table('email_usage_daily')->where('organization_id', $organization->id)->delete();
        DB::table('email_usage_monthly')->where('organization_id', $organization->id)->delete();

        // Keep the seeded OIDC client; remove extras created by quota E2E.
        OAuthClient::query()
            ->where('organization_id', $organization->id)
            ->where('name', 'like', 'E2E Quota App %')
            ->delete();
    }

    /**
     * @return array{mau_count: int, emails_triggered: bool}
     */
    public static function seedMau(Organization $organization, int $count): array
    {
        abort_if($count < 0 || $count > 10_000, 422, 'Invalid MAU count.');

        UsageEvent::query()->where('organization_id', $organization->id)->delete();

        $day = CarbonImmutable::now(config('billing.mau.timezone', 'UTC'))->toDateString();

        for ($i = 1; $i <= $count; $i++) {
            UsageEvent::query()->create([
                'id' => (string) Str::uuid(),
                'organization_id' => $organization->id,
                'subject_key' => 'e2e-mau-'.$i,
                'event_type' => UsageEventType::UserAuthenticated,
                'occurred_on' => $day,
                'metadata' => ['source' => 'e2e'],
            ]);
        }

        app(BillingNotifier::class)->checkUsageThresholds($organization->fresh(['subscription.plan']));

        return ['mau_count' => $count, 'emails_triggered' => true];
    }

    /**
     * @return array{application_count: int, emails_triggered: bool}
     */
    public static function seedApplications(Organization $organization, int $count): array
    {
        abort_if($count < 0 || $count > 50, 422, 'Invalid application count.');

        OAuthClient::query()
            ->where('organization_id', $organization->id)
            ->where('name', 'like', 'E2E Quota App %')
            ->delete();

        $existing = OAuthClient::query()
            ->where('organization_id', $organization->id)
            ->whereNull('revoked_at')
            ->count();

        for ($i = $existing + 1; $i <= $count; $i++) {
            OAuthClient::query()->create([
                'organization_id' => $organization->id,
                'name' => 'E2E Quota App '.$i,
                'application_type' => ApplicationType::Spa,
                'redirect_uris' => ['https://app.example.com/callback'],
                'grant_types' => ['authorization_code'],
                'is_confidential' => false,
                'login_methods' => LoginMethods::defaults(),
            ]);
        }

        // If we need fewer than existing (only seeded OIDC), revoke is not required for count math.
        $current = OAuthClient::query()
            ->where('organization_id', $organization->id)
            ->whereNull('revoked_at')
            ->count();

        abort_if($current < $count, 500, 'Could not reach requested application count.');

        app(BillingNotifier::class)->checkApplicationThresholds($organization->fresh(['subscription.plan']));

        return ['application_count' => $current, 'emails_triggered' => true];
    }

    /**
     * @return array{daily_count: int, emails_triggered: bool}
     */
    public static function seedEmailDaily(Organization $organization, int $count): array
    {
        abort_if($count < 0 || $count > 10_000, 422, 'Invalid email count.');

        $day = now()->toDateString();
        $month = now()->format('Y-m');

        DB::table('email_usage_daily')->where('organization_id', $organization->id)->delete();
        DB::table('email_usage_monthly')->where('organization_id', $organization->id)->delete();

        if ($count > 0) {
            DB::table('email_usage_daily')->insert([
                'organization_id' => $organization->id,
                'day' => $day,
                'count' => $count,
            ]);
            DB::table('email_usage_monthly')->insert([
                'organization_id' => $organization->id,
                'year_month' => $month,
                'count' => $count,
            ]);
        }

        app(BillingNotifier::class)->checkEmailUsageThresholds($organization->fresh(['subscription.plan']));

        return ['daily_count' => $count, 'emails_triggered' => true];
    }
}
