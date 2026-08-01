<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\OrganizationSubscription;
use App\Models\User;
use App\Services\Billing\BillingNotifier;
use App\Services\Billing\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class BillingCriticalTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.enabled' => true,
            'billing.dodo.api_key' => 'test_api_key',
            'billing.dodo.base_url' => 'https://test.dodopayments.com',
            'billing.dodo.return_url' => 'https://authzio.test/console/{organization_id}/billing',
        ]);

        $this->seedBillingPlans();

        $this->mock(BillingNotifier::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyPlanChange')->zeroOrMoreTimes();
            $mock->shouldReceive('checkUsageThresholds')->zeroOrMoreTimes();
            $mock->shouldReceive('checkApplicationThresholds')->zeroOrMoreTimes();
            $mock->shouldReceive('checkEmailUsageThresholds')->zeroOrMoreTimes();
        });
    }

    #[Test]
    public function dashboard_exposes_downgrade_preview_and_scheduled_cancel_fields(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
            'current_period_end' => now()->addDays(20),
            'metadata' => [
                'cancel_at_period_end' => true,
                'cancels_at' => now()->addDays(20)->toIso8601String(),
                'pending_plan_slug' => 'free',
            ],
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/organizations/{$organization->id}/billing")
            ->assertOk()
            ->assertJsonPath('data.plan.slug', 'starter')
            ->assertJsonPath('data.subscription.cancel_at_period_end', true)
            ->assertJsonPath('data.downgrade.from_plan', 'Starter')
            ->assertJsonPath('data.downgrade.to_plan', 'Free')
            ->assertJsonStructure([
                'data' => [
                    'downgrade' => ['losses'],
                    'subscription' => ['cancels_at', 'current_period_end'],
                ],
            ]);
    }

    #[Test]
    public function switching_to_free_twice_is_idempotent_and_keeps_paid_plan(): void
    {
        Http::fake([
            'test.dodopayments.com/subscriptions/sub_live' => Http::response([
                'subscription_id' => 'sub_live',
                'cancel_at_next_billing_date' => true,
                'next_billing_date' => now()->addDays(10)->toIso8601String(),
                'status' => 'active',
            ], 200),
        ]);

        [$user, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
            'current_period_end' => now()->addDays(10),
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/checkout", [
                'plan_slug' => 'free',
            ])
            ->assertOk()
            ->assertJsonPath('session_id', 'cancel-at-period-end');

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/checkout", [
                'plan_slug' => 'free',
            ])
            ->assertOk()
            ->assertJsonPath('session_id', 'cancel-at-period-end');

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame('starter', $subscription->plan->slug);
        $this->assertTrue((bool) ($subscription->metadata['cancel_at_period_end'] ?? false));

        // Second call must not PATCH Dodo again.
        Http::assertSentCount(1);
    }

    #[Test]
    public function apply_cancellations_skips_active_paid_subs_without_schedule_flag(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        unset($user);
        $starter = $this->plan('starter');

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
            'current_period_end' => now()->subMinute(),
            'metadata' => [],
        ]);

        $applied = app(BillingService::class)->applyDueCancellations();

        $this->assertSame(0, $applied);
        $this->assertSame(
            'starter',
            OrganizationSubscription::query()->where('organization_id', $organization->id)->firstOrFail()->plan->slug,
        );
    }

    #[Test]
    public function apply_cancellations_skips_scheduled_subs_before_period_end(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        unset($user);
        $starter = $this->plan('starter');
        $free = $this->plan('free');

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addDays(5),
            'metadata' => [
                'cancel_at_period_end' => true,
                'pending_plan_id' => $free->id,
            ],
        ]);

        $applied = app(BillingService::class)->applyDueCancellations();

        $this->assertSame(0, $applied);
        $this->assertSame(
            'starter',
            OrganizationSubscription::query()->where('organization_id', $organization->id)->firstOrFail()->plan->slug,
        );
    }

    #[Test]
    public function invoice_download_rejects_payments_not_owned_by_organization(): void
    {
        Http::fake([
            'test.dodopayments.com/payments*' => Http::response([
                'items' => [
                    [
                        'payment_id' => 'pay_owned',
                        'total_amount' => 500,
                        'currency' => 'USD',
                        'status' => 'succeeded',
                        'created_at' => '2026-07-01T10:00:00Z',
                        'customer' => ['customer_id' => 'cus_1', 'name' => 'A', 'email' => 'a@b.com'],
                        'brand_id' => 'brand_1',
                        'digital_products_delivered' => false,
                        'has_license_key' => false,
                        'metadata' => [],
                        'payment_provider' => 'dodo',
                    ],
                ],
            ], 200),
        ]);

        [$user, $organization] = $this->createOwnerWithOrganization();

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'dodo_customer_id' => 'cus_1',
            'dodo_subscription_id' => 'sub_1',
        ]);

        $this->actingAs($user)
            ->get("/api/v1/organizations/{$organization->id}/billing/invoices/pay_other")
            ->assertStatus(422);
    }

    #[Test]
    public function non_member_cannot_access_billing_or_invoices(): void
    {
        [, $organization] = $this->createOwnerWithOrganization();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson("/api/v1/organizations/{$organization->id}/billing")
            ->assertForbidden();

        $this->actingAs($stranger)
            ->getJson("/api/v1/organizations/{$organization->id}/billing/invoices")
            ->assertForbidden();

        $this->actingAs($stranger)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/checkout", [
                'plan_slug' => 'free',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function enterprise_plan_cannot_be_checked_out_self_serve(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/checkout", [
                'plan_slug' => 'enterprise',
            ])
            ->assertStatus(422);
    }
}
