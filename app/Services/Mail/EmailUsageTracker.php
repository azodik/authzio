<?php

namespace App\Services\Mail;

use App\Models\Organization;
use App\Services\Billing\BillingNotifier;
use App\Services\Billing\PlanEntitlements;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmailUsageTracker
{
    private function entitlements(): PlanEntitlements
    {
        return app(PlanEntitlements::class);
    }

    /**
     * @return array{daily_count: int, monthly_count: int, daily_limit: int|null, monthly_limit: int|null, can_send: bool}
     */
    public function snapshot(Organization $organization): array
    {
        $entitlements = $this->entitlements()->forOrganization($organization);
        $day = now()->toDateString();
        $month = now()->format('Y-m');

        $daily = (int) DB::table('email_usage_daily')
            ->where('organization_id', $organization->id)
            ->where('day', $day)
            ->value('count');

        $monthly = (int) DB::table('email_usage_monthly')
            ->where('organization_id', $organization->id)
            ->where('year_month', $month)
            ->value('count');

        $dailyLimit = $entitlements['email_daily_limit'];
        $monthlyLimit = $entitlements['email_monthly_limit'];

        $canSend = ($dailyLimit === null || $daily < $dailyLimit)
            && ($monthlyLimit === null || $monthly < $monthlyLimit);

        return [
            'daily_count' => $daily,
            'monthly_count' => $monthly,
            'daily_limit' => $dailyLimit,
            'monthly_limit' => $monthlyLimit,
            'can_send' => $canSend,
        ];
    }

    public function assertCanSend(Organization $organization): void
    {
        $snapshot = $this->snapshot($organization);

        if ($snapshot['can_send']) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => [__('Daily or monthly email sending limit reached for this organization.')],
        ]);
    }

    public function increment(Organization $organization): void
    {
        $day = now()->toDateString();
        $month = now()->format('Y-m');

        DB::table('email_usage_daily')->upsert(
            [
                'organization_id' => $organization->id,
                'day' => $day,
                'count' => 1,
            ],
            ['organization_id', 'day'],
            ['count' => DB::raw('email_usage_daily.count + 1')],
        );

        DB::table('email_usage_monthly')->upsert(
            [
                'organization_id' => $organization->id,
                'year_month' => $month,
                'count' => 1,
            ],
            ['organization_id', 'year_month'],
            ['count' => DB::raw('email_usage_monthly.count + 1')],
        );

        app(BillingNotifier::class)->checkEmailUsageThresholds($organization);
    }
}
