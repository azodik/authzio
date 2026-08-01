<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\OrganizationSubscription;
use App\Services\Billing\BillingNotifier;
use App\Services\Billing\PlanEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class BillingPlanChangeScenariosTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_dGVzdF9zZWNyZXQ=';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.enabled' => true,
            'billing.dodo.api_key' => 'test_api_key',
            'billing.dodo.base_url' => 'https://test.dodopayments.com',
            'billing.dodo.return_url' => 'https://authzio.test/console/{organization_id}/billing',
            'billing.dodo.webhook_secret' => $this->webhookSecret,
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
    public function payment_succeeded_finalizes_pending_upgrade_to_growth(): void
    {
        Http::fake([
            'test.dodopayments.com/payments/pay_upgrade' => Http::response([
                'payment_id' => 'pay_upgrade',
                'status' => 'succeeded',
                'total_amount' => 1500,
                'currency' => 'USD',
            ], 200),
        ]);

        [, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $growth = $this->plan('growth');
        $starter->update(['dodo_product_id' => 'pdt_starter']);
        $growth->update(['dodo_product_id' => 'pdt_growth']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
            'dodo_customer_id' => 'cus_live',
            'metadata' => [
                'pending_plan_id' => $growth->id,
                'pending_plan_slug' => 'growth',
                'pending_plan_kind' => 'upgrade',
                'pending_requires_payment' => true,
                'pending_payment_id' => 'pay_upgrade',
            ],
        ]);

        $this->postSignedDodoWebhook([
            'type' => 'payment.succeeded',
            'data' => [
                'payment_id' => 'pay_upgrade',
                'status' => 'succeeded',
                'total_amount' => 1500,
                'metadata' => [
                    'organization_id' => $organization->id,
                ],
            ],
        ], $this->webhookSecret, 'msg_pay_ok')->assertOk();

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame($growth->id, $subscription->billing_plan_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame('upgrade', $subscription->metadata['last_plan_change'] ?? null);
        $this->assertFalse((bool) ($subscription->metadata['pending_requires_payment'] ?? false));
        $this->assertNull($subscription->metadata['pending_plan_slug'] ?? null);
        $this->assertSame('pay_upgrade', $subscription->metadata['upgrade_finalized_for_payment'] ?? null);
    }

    #[Test]
    public function payment_succeeded_is_idempotent_for_same_payment_id(): void
    {
        Http::fake([
            'test.dodopayments.com/payments/pay_upgrade' => Http::response([
                'payment_id' => 'pay_upgrade',
                'status' => 'succeeded',
                'total_amount' => 1500,
                'currency' => 'USD',
            ], 200),
        ]);

        [, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $growth = $this->plan('growth');
        $starter->update(['dodo_product_id' => 'pdt_starter']);
        $growth->update(['dodo_product_id' => 'pdt_growth']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
            'metadata' => [
                'pending_plan_id' => $growth->id,
                'pending_plan_slug' => 'growth',
                'pending_plan_kind' => 'upgrade',
                'pending_requires_payment' => true,
                'pending_payment_id' => 'pay_upgrade',
            ],
        ]);

        $body = [
            'type' => 'payment.succeeded',
            'data' => [
                'payment_id' => 'pay_upgrade',
                'metadata' => ['organization_id' => $organization->id],
            ],
        ];

        $this->postSignedDodoWebhook($body, $this->webhookSecret, 'msg_pay_1')->assertOk();
        $this->postSignedDodoWebhook($body, $this->webhookSecret, 'msg_pay_2')->assertOk();

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame($growth->id, $subscription->billing_plan_id);
        $this->assertSame('pay_upgrade', $subscription->metadata['upgrade_finalized_for_payment'] ?? null);
    }

    #[Test]
    public function payment_failed_keeps_current_plan_and_marks_upgrade_payment_failed(): void
    {
        Http::fake([
            'test.dodopayments.com/payments/pay_fail' => Http::response([
                'payment_id' => 'pay_fail',
                'status' => 'failed',
                'total_amount' => 1500,
                'currency' => 'USD',
            ], 200),
        ]);

        [, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $growth = $this->plan('growth');
        $starter->update(['dodo_product_id' => 'pdt_starter']);
        $growth->update(['dodo_product_id' => 'pdt_growth']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
            'metadata' => [
                'pending_plan_id' => $growth->id,
                'pending_plan_slug' => 'growth',
                'pending_plan_kind' => 'upgrade',
                'pending_requires_payment' => true,
                'pending_payment_id' => 'pay_fail',
            ],
        ]);

        $this->postSignedDodoWebhook([
            'type' => 'payment.failed',
            'data' => [
                'payment_id' => 'pay_fail',
                'status' => 'failed',
                'total_amount' => 1500,
                'metadata' => ['organization_id' => $organization->id],
            ],
        ], $this->webhookSecret, 'msg_pay_fail')->assertOk();

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame($starter->id, $subscription->billing_plan_id);
        $this->assertSame('growth', $subscription->metadata['pending_plan_slug'] ?? null);
        $this->assertTrue((bool) ($subscription->metadata['pending_requires_payment'] ?? false));
        $this->assertSame('upgrade_payment_failed', $subscription->metadata['last_plan_change'] ?? null);
    }

    #[Test]
    public function zero_amount_payment_does_not_finalize_upgrade(): void
    {
        Http::fake([
            'test.dodopayments.com/payments/pay_zero' => Http::response([
                'payment_id' => 'pay_zero',
                'status' => 'succeeded',
                'total_amount' => 0,
                'currency' => 'USD',
            ], 200),
        ]);

        [, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $growth = $this->plan('growth');
        $starter->update(['dodo_product_id' => 'pdt_starter']);
        $growth->update(['dodo_product_id' => 'pdt_growth']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
            'metadata' => [
                'pending_plan_id' => $growth->id,
                'pending_plan_slug' => 'growth',
                'pending_plan_kind' => 'upgrade',
                'pending_requires_payment' => true,
                'pending_payment_id' => 'pay_zero',
            ],
        ]);

        $this->postSignedDodoWebhook([
            'type' => 'payment.succeeded',
            'data' => [
                'payment_id' => 'pay_zero',
                'metadata' => ['organization_id' => $organization->id],
            ],
        ], $this->webhookSecret, 'msg_pay_zero')->assertOk();

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame($starter->id, $subscription->billing_plan_id);
        $this->assertSame('growth', $subscription->metadata['pending_plan_slug'] ?? null);
        $this->assertTrue((bool) ($subscription->metadata['pending_requires_payment'] ?? false));
    }

    #[Test]
    public function subscription_plan_changed_is_deferred_while_upgrade_payment_pending(): void
    {
        [, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $growth = $this->plan('growth');
        $starter->update(['dodo_product_id' => 'pdt_starter']);
        $growth->update(['dodo_product_id' => 'pdt_growth']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
            'metadata' => [
                'pending_plan_id' => $growth->id,
                'pending_plan_slug' => 'growth',
                'pending_plan_kind' => 'upgrade',
                'pending_requires_payment' => true,
                'pending_payment_id' => 'pay_open',
            ],
        ]);

        $this->postSignedDodoWebhook([
            'type' => 'subscription.plan_changed',
            'data' => [
                'subscription_id' => 'sub_live',
                'product_id' => 'pdt_growth',
                'metadata' => [
                    'organization_id' => $organization->id,
                    'billing_plan_id' => $growth->id,
                ],
            ],
        ], $this->webhookSecret, 'msg_defer_plan')->assertOk();

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame($starter->id, $subscription->billing_plan_id);
        $this->assertSame('growth', $subscription->metadata['pending_plan_slug'] ?? null);
        $this->assertTrue((bool) ($subscription->metadata['pending_requires_payment'] ?? false));
    }

    #[Test]
    public function paid_downgrade_applies_when_plan_changed_webhook_arrives_with_new_product(): void
    {
        [, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $growth = $this->plan('growth');
        $starter->update(['dodo_product_id' => 'pdt_starter']);
        $growth->update(['dodo_product_id' => 'pdt_growth']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $growth->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
            'metadata' => [
                'pending_plan_id' => $starter->id,
                'pending_plan_slug' => 'starter',
                'pending_plan_kind' => 'downgrade',
                'scheduled_plan_change_at' => now()->addDays(10)->toIso8601String(),
            ],
        ]);

        $this->postSignedDodoWebhook([
            'type' => 'subscription.plan_changed',
            'data' => [
                'subscription_id' => 'sub_live',
                'product_id' => 'pdt_starter',
                'cancel_at_next_billing_date' => false,
                'metadata' => [
                    'organization_id' => $organization->id,
                    'billing_plan_id' => $starter->id,
                ],
            ],
        ], $this->webhookSecret, 'msg_downgrade_apply')->assertOk();

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame($starter->id, $subscription->billing_plan_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
    }

    #[Test]
    public function checkout_same_paid_plan_resumes_cancel_at_period_end(): void
    {
        Http::fake([
            'test.dodopayments.com/subscriptions/sub_live' => Http::response([
                'subscription_id' => 'sub_live',
                'product_id' => 'pdt_starter',
                'status' => 'active',
                'cancel_at_next_billing_date' => false,
            ], 200),
        ]);

        [$user, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $starter->update(['dodo_product_id' => 'pdt_starter']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
            'metadata' => [
                'cancel_at_period_end' => true,
                'cancels_at' => now()->addDays(10)->toIso8601String(),
                'pending_plan_slug' => 'free',
                'pending_plan_kind' => 'cancel',
            ],
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/checkout", [
                'plan_slug' => 'starter',
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'plan_changed')
            ->assertJsonPath('session_id', 'plan-resumed');

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame($starter->id, $subscription->billing_plan_id);
        $this->assertFalse((bool) ($subscription->metadata['cancel_at_period_end'] ?? false));
        $this->assertNull($subscription->metadata['pending_plan_slug'] ?? null);
        $this->assertNull($subscription->cancelled_at);
    }

    #[Test]
    public function preview_shows_downgrade_and_free_scheduling_fields(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $growth = $this->plan('growth');
        $starter->update(['dodo_product_id' => 'pdt_starter']);
        $growth->update(['dodo_product_id' => 'pdt_growth']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $growth->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
            'current_period_end' => '2026-08-15 00:00:00',
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/preview-change", [
                'plan_slug' => 'starter',
            ])
            ->assertOk()
            ->assertJsonPath('data.is_upgrade', false)
            ->assertJsonPath('data.effective_at', 'next_billing_date')
            ->assertJsonPath('data.to_plan.slug', 'starter')
            ->assertJsonPath('data.from_plan.slug', 'growth');

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/preview-change", [
                'plan_slug' => 'free',
            ])
            ->assertOk()
            ->assertJsonPath('data.is_upgrade', false)
            ->assertJsonPath('data.effective_at', 'next_billing_date')
            ->assertJsonPath('data.to_plan.slug', 'free')
            ->assertJsonPath('data.requires_checkout', false);
    }

    #[Test]
    public function free_plan_after_cancel_enforces_application_limit(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $free = $this->plan('free');
        $starter = $this->plan('starter');

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/applications", [
                'name' => 'App One',
                'application_type' => 'spa',
                'redirect_uris' => ['https://app.test/callback'],
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/applications", [
                'name' => 'App Two',
                'application_type' => 'spa',
                'redirect_uris' => ['https://app.test/callback2'],
            ])
            ->assertCreated();

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $free->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => null,
            'metadata' => [],
        ]);

        $entitlements = app(PlanEntitlements::class)->forOrganization($organization->fresh());
        $this->assertTrue($entitlements['is_free']);
        $this->assertSame(1, $entitlements['application_limit']);
        $this->assertFalse($entitlements['can_create_application']);

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/applications", [
                'name' => 'App Three',
                'application_type' => 'spa',
                'redirect_uris' => ['https://app.test/callback3'],
            ])
            ->assertStatus(422);
    }
}
