<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\OrganizationSubscription;
use App\Services\Billing\BillingNotifier;
use App\Services\Billing\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class BillingCheckoutTest extends TestCase
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
    public function it_shows_billing_dashboard_for_org_owner(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();

        $this->actingAs($user)
            ->getJson("/api/v1/organizations/{$organization->id}/billing")
            ->assertOk()
            ->assertJsonPath('data.plan.slug', 'free')
            ->assertJsonPath('data.dodo_configured', true)
            ->assertJsonPath('data.billing_enabled', true);
    }

    #[Test]
    public function it_rejects_checkout_when_already_on_the_same_paid_plan(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $starter->update(['dodo_product_id' => 'pdt_starter']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/checkout", [
                'plan_slug' => 'starter',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['You are already on the Starter plan.']);

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/preview-change", [
                'plan_slug' => 'starter',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['You are already on the Starter plan.']);
    }

    #[Test]
    public function it_rejects_second_hosted_checkout_while_one_is_in_progress(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $this->plan('starter')->update(['dodo_product_id' => 'pdt_test_starter']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'dodo_checkout_session_id' => 'sess_already_open',
            'metadata' => ['pending_plan_id' => $this->plan('starter')->id],
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/checkout", [
                'plan_slug' => 'starter',
            ])
            ->assertStatus(422)
            ->assertJsonFragment([
                'A checkout is already in progress. Finish that payment or wait a few minutes before starting another.',
            ]);
    }

    #[Test]
    public function it_does_not_start_a_second_change_plan_while_upgrade_payment_is_open(): void
    {
        Http::fake([
            'test.dodopayments.com/payments/pay_open' => Http::response([
                'payment_id' => 'pay_open',
                'status' => 'processing',
                'total_amount' => 1500,
                'currency' => 'USD',
            ], 200),
            'test.dodopayments.com/subscriptions/sub_live/change-plan' => Http::response([
                'status' => 'active',
                'payment_id' => 'pay_should_not_create',
            ], 200),
        ]);

        [$user, $organization] = $this->createOwnerWithOrganization();
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

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/checkout", [
                'plan_slug' => 'growth',
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'plan_change_pending');

        Http::assertNotSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/subscriptions/sub_live/change-plan');
        });
    }

    #[Test]
    public function it_blocks_plan_changes_while_upgrade_payment_is_pending(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
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
                'pending_payment_id' => 'pay_pending',
            ],
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/checkout", [
                'plan_slug' => 'free',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'An upgrade payment is still processing. Wait for it to finish before changing plans.');

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/preview-change", [
                'plan_slug' => 'scale',
            ])
            ->assertStatus(422);

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame('starter', $subscription->plan->slug);
        $this->assertSame('growth', $subscription->metadata['pending_plan_slug'] ?? null);
    }

    #[Test]
    public function it_schedules_dodo_cancel_at_period_end_when_switching_to_free(): void
    {
        Http::fake([
            'test.dodopayments.com/subscriptions/sub_old' => Http::response([
                'subscription_id' => 'sub_old',
                'cancel_at_next_billing_date' => true,
                'next_billing_date' => '2026-08-15T00:00:00Z',
                'status' => 'active',
            ], 200),
            'test.dodopayments.com/checkouts' => Http::response([
                'checkout_url' => 'https://test.dodopayments.com/checkout/sess_test',
                'session_id' => 'sess_test',
            ], 200),
        ]);

        [$user, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $starter->update(['dodo_product_id' => 'pdt_test_starter']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_old',
            'dodo_customer_id' => 'cus_old',
            'current_period_end' => '2026-08-15 00:00:00',
        ]);

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
        $this->assertSame('sub_old', $subscription->dodo_subscription_id);
        $this->assertTrue((bool) ($subscription->metadata['cancel_at_period_end'] ?? false));

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'PATCH'
                && str_ends_with($request->url(), '/subscriptions/sub_old')
                && ($request['cancel_at_next_billing_date'] ?? null) === true;
        });
    }

    #[Test]
    public function it_schedules_free_at_period_end_without_dodo_subscription(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $periodEnd = now()->addDays(12);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => null,
            'current_period_end' => $periodEnd,
        ]);

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
        $this->assertNotNull($subscription->current_period_end);
        $this->assertTrue($subscription->current_period_end->greaterThan(now()->addDays(10)));
    }

    #[Test]
    public function it_applies_due_cancellations_after_period_end(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        unset($user);
        $starter = $this->plan('starter');
        $free = $this->plan('free');

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_old',
            'current_period_end' => now()->subMinute(),
            'metadata' => [
                'cancel_at_period_end' => true,
                'pending_plan_slug' => 'free',
                'pending_plan_id' => $free->id,
            ],
        ]);

        $applied = app(BillingService::class)->applyDueCancellations();

        $this->assertSame(1, $applied);

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame('free', $subscription->plan->slug);
        $this->assertNull($subscription->dodo_subscription_id);
        $this->assertFalse((bool) ($subscription->metadata['cancel_at_period_end'] ?? false));
    }

    #[Test]
    public function it_lists_and_downloads_invoices_for_linked_customer(): void
    {
        Http::fake([
            'test.dodopayments.com/payments*' => Http::response([
                'items' => [
                    [
                        'payment_id' => 'pay_abc',
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
                        'invoice_url' => null,
                    ],
                ],
            ], 200),
            'test.dodopayments.com/invoices/payments/pay_abc' => Http::response('%PDF-1.4 invoice', 200, [
                'Content-Type' => 'application/pdf',
            ]),
        ]);

        [$user, $organization] = $this->createOwnerWithOrganization();

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'dodo_customer_id' => 'cus_1',
            'dodo_subscription_id' => 'sub_1',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/organizations/{$organization->id}/billing/invoices")
            ->assertOk()
            ->assertJsonPath('data.0.payment_id', 'pay_abc')
            ->assertJsonPath('data.0.amount_cents', 500);

        $this->actingAs($user)
            ->get("/api/v1/organizations/{$organization->id}/billing/invoices/pay_abc")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function it_previews_upgrade_charge_before_change_plan(): void
    {
        Http::fake([
            'test.dodopayments.com/subscriptions/sub_live/change-plan/preview' => Http::response([
                'immediate_charge' => [
                    'summary' => [
                        'total_amount' => 1500,
                        'currency' => 'USD',
                        'settlement_amount' => 1500,
                        'settlement_currency' => 'USD',
                        'customer_credits' => 0,
                    ],
                    'line_items' => [],
                    'effective_at' => '2026-07-29T00:00:00Z',
                ],
                'new_plan' => [
                    'subscription_id' => 'sub_live',
                    'product_id' => 'pdt_growth',
                ],
            ], 200),
        ]);

        [$user, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $growth = $this->plan('growth');
        $starter->update(['dodo_product_id' => 'pdt_starter']);
        $growth->update(['dodo_product_id' => 'pdt_growth']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/preview-change", [
                'plan_slug' => 'growth',
            ])
            ->assertOk()
            ->assertJsonPath('data.requires_checkout', false)
            ->assertJsonPath('data.is_upgrade', true)
            ->assertJsonPath('data.effective_at', 'immediately')
            ->assertJsonPath('data.immediate_charge_cents', 1500)
            ->assertJsonPath('data.to_plan.slug', 'growth');

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame('starter', $subscription->plan->slug);
    }

    #[Test]
    public function it_upgrades_existing_subscription_via_change_plan_immediately(): void
    {
        Http::fake([
            'test.dodopayments.com/subscriptions/sub_live/change-plan' => Http::response([
                'status' => 'active',
                'subscription_id' => 'sub_live',
                'payment_id' => 'pay_upgrade',
            ], 200),
            'test.dodopayments.com/subscriptions/sub_live' => Http::response([
                'subscription_id' => 'sub_live',
                'product_id' => 'pdt_starter',
                'status' => 'active',
            ], 200),
            'test.dodopayments.com/payments/pay_upgrade' => Http::response([
                'payment_id' => 'pay_upgrade',
                'status' => 'succeeded',
                'total_amount' => 1500,
                'currency' => 'USD',
            ], 200),
        ]);

        [$user, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $growth = $this->plan('growth');
        $starter->update(['dodo_product_id' => 'pdt_starter']);
        $growth->update(['dodo_product_id' => 'pdt_growth']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
            'dodo_customer_id' => 'cus_live',
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/checkout", [
                'plan_slug' => 'growth',
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'plan_change_pending')
            ->assertJsonPath('session_id', 'plan-change-pending');

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        // Local plan must stay Starter until payment.succeeded webhook applies Growth.
        $this->assertSame('starter', $subscription->plan->slug);
        $this->assertSame('growth', $subscription->metadata['pending_plan_slug'] ?? null);
        $this->assertTrue((bool) ($subscription->metadata['pending_requires_payment'] ?? false));
        $this->assertFalse((bool) ($subscription->metadata['cancel_at_period_end'] ?? false));

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/subscriptions/sub_live/change-plan')
                && ($request['product_id'] ?? null) === 'pdt_growth'
                && ($request['proration_billing_mode'] ?? null) === 'difference_immediately'
                && ($request['effective_at'] ?? null) === 'immediately'
                && ($request['on_payment_failure'] ?? null) === 'prevent_change';
        });

        Http::assertNotSent(function (Request $request): bool {
            return $request->method() === 'POST' && str_ends_with($request->url(), '/checkouts');
        });
    }

    #[Test]
    public function it_opens_difference_payment_link_when_upgrade_needs_authentication(): void
    {
        Http::fake([
            'test.dodopayments.com/subscriptions/sub_live/change-plan' => Http::response([
                'status' => 'on_hold',
                'subscription_id' => 'sub_live',
                'payment_id' => 'pay_auth',
            ], 200),
            'test.dodopayments.com/subscriptions/sub_live' => Http::response([
                'subscription_id' => 'sub_live',
                'product_id' => 'pdt_growth',
                'status' => 'on_hold',
            ], 200),
            'test.dodopayments.com/payments/pay_auth' => Http::response([
                'payment_id' => 'pay_auth',
                'status' => 'processing',
                'error_message' => 'authentication Failed',
                'total_amount' => 1500,
                'currency' => 'USD',
                'payment_link' => 'https://test.checkout.dodopayments.com/dues-session',
            ], 200),
            'test.dodopayments.com/checkouts' => Http::response([
                'checkout_url' => 'https://test.checkout.dodopayments.com/full-plan-should-not-be-used',
                'session_id' => 'sess_full',
            ], 200),
        ]);

        [$user, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $growth = $this->plan('growth');
        $starter->update(['dodo_product_id' => 'pdt_starter']);
        $growth->update(['dodo_product_id' => 'pdt_growth']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/checkout", [
                'plan_slug' => 'growth',
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'checkout')
            ->assertJsonPath('checkout_url', 'https://test.checkout.dodopayments.com/dues-session')
            ->assertJsonPath('session_id', 'pay_auth');

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame('starter', $subscription->plan->slug);
        $this->assertSame('growth', $subscription->metadata['pending_plan_slug'] ?? null);
        $this->assertSame('upgrade', $subscription->metadata['pending_plan_kind'] ?? null);
        $this->assertTrue((bool) ($subscription->metadata['pending_requires_payment'] ?? false));
        $this->assertFalse((bool) ($subscription->metadata['upgrade_via_checkout'] ?? false));
        $this->assertSame('upgrade_auth_required', $subscription->metadata['last_plan_change'] ?? null);
        $this->assertNull($subscription->metadata['scheduled_plan_change_at'] ?? null);

        Http::assertNotSent(function (Request $request): bool {
            return $request->method() === 'POST' && str_ends_with($request->url(), '/checkouts');
        });
    }

    #[Test]
    public function it_opens_on_hold_dues_session_when_payment_has_no_link(): void
    {
        Http::fake([
            'test.dodopayments.com/subscriptions/sub_live/change-plan' => Http::response([
                'status' => 'on_hold',
                'subscription_id' => 'sub_live',
                'payment_id' => 'pay_auth',
            ], 200),
            'test.dodopayments.com/subscriptions/sub_live' => Http::response([
                'subscription_id' => 'sub_live',
                'product_id' => 'pdt_growth',
                'status' => 'on_hold',
            ], 200),
            'test.dodopayments.com/payments/pay_auth' => Http::response([
                'payment_id' => 'pay_auth',
                'status' => 'failed',
                'total_amount' => 7900,
                'currency' => 'USD',
            ], 200),
            'test.dodopayments.com/subscriptions/sub_live/update-payment-method' => Http::response([
                'payment_id' => 'pay_dues',
                'payment_link' => 'https://test.checkout.dodopayments.com/on-hold-dues',
                'client_secret' => 'secret',
            ], 200),
            'test.dodopayments.com/payments/pay_dues' => Http::response([
                'payment_id' => 'pay_dues',
                'status' => 'processing',
                'total_amount' => 7900,
                'currency' => 'USD',
            ], 200),
        ]);

        [$user, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $growth = $this->plan('growth');
        $starter->update(['dodo_product_id' => 'pdt_starter']);
        $growth->update(['dodo_product_id' => 'pdt_growth']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/checkout", [
                'plan_slug' => 'growth',
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'checkout')
            ->assertJsonPath('checkout_url', 'https://test.checkout.dodopayments.com/on-hold-dues');

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/subscriptions/sub_live/update-payment-method')
                && ($request['type'] ?? null) === 'new';
        });

        Http::assertNotSent(function (Request $request): bool {
            return $request->method() === 'POST' && str_ends_with($request->url(), '/checkouts');
        });
    }

    #[Test]
    public function it_schedules_paid_downgrade_for_next_billing_date(): void
    {
        Http::fake([
            'test.dodopayments.com/subscriptions/sub_live/change-plan' => Http::response([
                'status' => 'active',
                'subscription_id' => 'sub_live',
            ], 200),
        ]);

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
            ->postJson("/api/v1/organizations/{$organization->id}/billing/checkout", [
                'plan_slug' => 'starter',
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'plan_change_scheduled');

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame('growth', $subscription->plan->slug);
        $this->assertSame('starter', $subscription->metadata['pending_plan_slug'] ?? null);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/subscriptions/sub_live/change-plan')
                && ($request['product_id'] ?? null) === 'pdt_starter'
                && ($request['effective_at'] ?? null) === 'next_billing_date'
                && ($request['proration_billing_mode'] ?? null) === 'full_immediately';
        });
    }

    #[Test]
    public function it_resumes_then_upgrades_when_cancel_was_scheduled(): void
    {
        Http::fake([
            'test.dodopayments.com/subscriptions/sub_live/change-plan' => Http::response([
                'status' => 'active',
                'subscription_id' => 'sub_live',
                'payment_id' => 'pay_upgrade',
            ], 200),
            'test.dodopayments.com/subscriptions/sub_live' => Http::response([
                'subscription_id' => 'sub_live',
                'product_id' => 'pdt_starter',
                'status' => 'active',
                'cancel_at_next_billing_date' => false,
            ], 200),
            'test.dodopayments.com/payments/pay_upgrade' => Http::response([
                'payment_id' => 'pay_upgrade',
                'status' => 'succeeded',
                'total_amount' => 1500,
                'currency' => 'USD',
            ], 200),
        ]);

        [$user, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $growth = $this->plan('growth');
        $starter->update(['dodo_product_id' => 'pdt_starter']);
        $growth->update(['dodo_product_id' => 'pdt_growth']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
            'metadata' => [
                'cancel_at_period_end' => true,
                'pending_plan_slug' => 'free',
            ],
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/checkout", [
                'plan_slug' => 'growth',
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'plan_change_pending');

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'PATCH'
                && str_ends_with($request->url(), '/subscriptions/sub_live')
                && ($request['cancel_at_next_billing_date'] ?? null) === false;
        });

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame('starter', $subscription->plan->slug);
        $this->assertSame('growth', $subscription->metadata['pending_plan_slug'] ?? null);
        $this->assertTrue((bool) ($subscription->metadata['pending_requires_payment'] ?? false));
        $this->assertFalse((bool) ($subscription->metadata['cancel_at_period_end'] ?? false));
    }

    #[Test]
    public function it_starts_dodo_checkout_for_paid_plans(): void
    {
        $this->dodoHttpFakeForProductCreate();

        [$user, $organization] = $this->createOwnerWithOrganization();
        $this->plan('starter')->update(['dodo_product_id' => 'pdt_test_starter']);

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/checkout", [
                'plan_slug' => 'starter',
            ])
            ->assertOk()
            ->assertJsonPath('checkout_url', 'https://test.dodopayments.com/checkout/sess_test')
            ->assertJsonPath('session_id', 'sess_test')
            ->assertJsonPath('mode', 'checkout');

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame('sess_test', $subscription->dodo_checkout_session_id);
        $this->assertSame($this->plan('starter')->id, $subscription->metadata['pending_plan_id'] ?? null);
        $this->assertSame('free', $subscription->plan->slug);

        Http::assertSent(function (Request $request) use ($organization): bool {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/checkouts')) {
                return false;
            }

            $cart = $request['product_cart'][0] ?? [];
            $returnUrl = (string) ($request['return_url'] ?? '');

            return ($cart['product_id'] ?? null) === 'pdt_test_starter'
                && ($request['metadata']['organization_id'] ?? null) === $organization->id
                && str_contains($returnUrl, '/console/'.$organization->id.'/billing')
                && str_contains($returnUrl, 'checkout=pending');
        });
    }

    #[Test]
    public function it_requires_dodo_product_id_for_paid_checkout(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $this->plan('starter')->update(['dodo_product_id' => null]);

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/billing/checkout", [
                'plan_slug' => 'starter',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function it_marks_dodo_unconfigured_when_api_key_missing(): void
    {
        config(['billing.dodo.api_key' => null]);

        [$user, $organization] = $this->createOwnerWithOrganization();

        $this->actingAs($user)
            ->getJson("/api/v1/organizations/{$organization->id}/billing")
            ->assertOk()
            ->assertJsonPath('data.dodo_configured', false);
    }
}
