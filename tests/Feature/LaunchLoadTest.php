<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Enums\UsageEventType;
use App\Models\OrganizationSubscription;
use App\Models\User;
use App\Services\Billing\BillingNotifier;
use App\Services\Billing\UsageTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class LaunchLoadTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBillingPlans();
        config([
            'billing.enabled' => true,
            'billing.dodo.webhook_secret' => 'whsec_dGVzdF9zZWNyZXQ=',
        ]);

        $this->mock(BillingNotifier::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyPlanChange')->zeroOrMoreTimes();
            $mock->shouldReceive('checkUsageThresholds')->zeroOrMoreTimes();
            $mock->shouldReceive('checkApplicationThresholds')->zeroOrMoreTimes();
            $mock->shouldReceive('checkEmailUsageThresholds')->zeroOrMoreTimes();
        });
    }

    #[Test]
    public function webhook_ingress_handles_burst_of_signed_events(): void
    {
        [, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $starter->update(['dodo_product_id' => 'pdt_starter']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_burst',
        ]);

        $started = hrtime(true);

        for ($i = 0; $i < 25; $i++) {
            $this->postSignedDodoWebhook([
                'type' => 'subscription.on_hold',
                'data' => [
                    'subscription_id' => 'sub_burst',
                    'product_id' => 'pdt_starter',
                    'metadata' => [
                        'organization_id' => $organization->id,
                        'billing_plan_id' => $starter->id,
                    ],
                ],
            ], 'whsec_dGVzdF9zZWNyZXQ=', 'msg_burst_'.$i)->assertOk();
        }

        $elapsedMs = (hrtime(true) - $started) / 1_000_000;
        $this->assertLessThan(5000, $elapsedMs, '25 signed webhooks should process under 5s in tests');

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();
        $this->assertSame(SubscriptionStatus::OnHold, $subscription->status);
    }

    #[Test]
    public function mau_recompute_scales_to_hundreds_of_subjects(): void
    {
        [, $organization] = $this->createOwnerWithOrganization();
        $tracker = app(UsageTracker::class);

        for ($i = 0; $i < 200; $i++) {
            $user = User::factory()->create();
            $tracker->record($organization, UsageEventType::UserAuthenticated, $user->uuid, $user);
        }

        // Duplicate day events should not inflate MAU.
        $again = User::factory()->create();
        $tracker->record($organization, UsageEventType::UserAuthenticated, $again->uuid, $again);
        $tracker->record($organization, UsageEventType::UserAuthenticated, $again->uuid, $again);

        $started = hrtime(true);
        $mau = $tracker->monthlyActiveUsers($organization);
        $summary = $tracker->recomputeMonthlySummary($organization);
        $elapsedMs = (hrtime(true) - $started) / 1_000_000;

        $this->assertSame(201, $mau);
        $this->assertSame(201, $summary->mau_count);
        $this->assertLessThan(1500, $elapsedMs, 'MAU recompute for ~200 subjects should stay under 1.5s');
    }
}
