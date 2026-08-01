<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\DodoWebhookEvent;
use App\Models\OrganizationSubscription;
use App\Services\Billing\BillingNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class DodoWebhookTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(BillingNotifier::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyPlanChange')->zeroOrMoreTimes();
            $mock->shouldReceive('checkUsageThresholds')->zeroOrMoreTimes();
            $mock->shouldReceive('checkApplicationThresholds')->zeroOrMoreTimes();
            $mock->shouldReceive('checkEmailUsageThresholds')->zeroOrMoreTimes();
        });
    }

    #[Test]
    public function it_rejects_unsigned_webhooks_when_secret_is_configured(): void
    {
        config(['billing.dodo.webhook_secret' => 'whsec_dGVzdF9zZWNyZXQ=']);

        $this->postJson('/api/v1/webhooks/dodo', [
            'type' => 'subscription.active',
            'data' => [],
        ])->assertStatus(400);
    }

    #[Test]
    public function it_rejects_invalid_signatures(): void
    {
        config(['billing.dodo.webhook_secret' => 'whsec_dGVzdF9zZWNyZXQ=']);

        $payload = json_encode(['type' => 'subscription.active', 'data' => []], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/v1/webhooks/dodo',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_WEBHOOK_ID' => 'msg_bad',
                'HTTP_WEBHOOK_TIMESTAMP' => (string) time(),
                'HTTP_WEBHOOK_SIGNATURE' => 'v1,invalid',
            ],
            $payload,
        )->assertStatus(401);
    }

    #[Test]
    public function it_activates_subscription_from_signed_webhook(): void
    {
        $this->seedBillingPlans();
        [$user, $organization] = $this->createOwnerWithOrganization();
        unset($user);

        $starter = $this->plan('starter');
        $starter->update(['dodo_product_id' => 'pdt_test_starter']);

        $secret = 'whsec_dGVzdF9zZWNyZXQ=';
        config(['billing.dodo.webhook_secret' => $secret]);

        $body = [
            'type' => 'subscription.active',
            'data' => [
                'subscription_id' => 'sub_test_123',
                'customer_id' => 'cus_test_123',
                'product_id' => 'pdt_test_starter',
                'metadata' => [
                    'organization_id' => $organization->id,
                    'billing_plan_id' => $starter->id,
                    'billing_plan_slug' => 'starter',
                ],
            ],
        ];
        $payload = json_encode($body, JSON_THROW_ON_ERROR);
        $headers = $this->signedWebhookHeaders($payload, $secret);

        $this->call(
            'POST',
            '/api/v1/webhooks/dodo',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_WEBHOOK_ID' => $headers['webhook-id'],
                'HTTP_WEBHOOK_TIMESTAMP' => $headers['webhook-timestamp'],
                'HTTP_WEBHOOK_SIGNATURE' => $headers['webhook-signature'],
            ],
            $payload,
        )->assertOk()->assertJson(['received' => true]);

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->first();

        $this->assertNotNull($subscription);
        $this->assertSame($starter->id, $subscription->billing_plan_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame('sub_test_123', $subscription->dodo_subscription_id);
        $this->assertSame('cus_test_123', $subscription->dodo_customer_id);

        $this->assertNotNull(
            DodoWebhookEvent::query()->where('webhook_id', $headers['webhook-id'])->whereNotNull('processed_at')->first(),
        );
    }

    #[Test]
    public function it_is_idempotent_for_duplicate_webhook_ids(): void
    {
        $this->seedBillingPlans();
        [, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $starter->update(['dodo_product_id' => 'pdt_test_starter']);

        $secret = 'whsec_dGVzdF9zZWNyZXQ=';
        config(['billing.dodo.webhook_secret' => $secret]);

        $body = [
            'type' => 'subscription.active',
            'data' => [
                'subscription_id' => 'sub_once',
                'product_id' => 'pdt_test_starter',
                'metadata' => [
                    'organization_id' => $organization->id,
                    'billing_plan_id' => $starter->id,
                ],
            ],
        ];
        $payload = json_encode($body, JSON_THROW_ON_ERROR);
        $headers = $this->signedWebhookHeaders($payload, $secret, 'msg_duplicate');

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_WEBHOOK_ID' => $headers['webhook-id'],
            'HTTP_WEBHOOK_TIMESTAMP' => $headers['webhook-timestamp'],
            'HTTP_WEBHOOK_SIGNATURE' => $headers['webhook-signature'],
        ];

        $this->call('POST', '/api/v1/webhooks/dodo', [], [], [], $server, $payload)->assertOk();
        $this->call('POST', '/api/v1/webhooks/dodo', [], [], [], $server, $payload)->assertOk();

        $this->assertSame(1, DodoWebhookEvent::query()->where('webhook_id', 'msg_duplicate')->count());
    }

    #[Test]
    public function failed_checkout_does_not_upgrade_plan(): void
    {
        $this->seedBillingPlans();
        [, $organization] = $this->createOwnerWithOrganization();
        $free = $this->plan('free');
        $starter = $this->plan('starter');
        $starter->update(['dodo_product_id' => 'pdt_test_starter']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'metadata' => ['pending_plan_id' => $starter->id],
            'dodo_checkout_session_id' => 'cks_pending',
        ]);

        $secret = 'whsec_dGVzdF9zZWNyZXQ=';
        config(['billing.dodo.webhook_secret' => $secret]);

        $body = [
            'type' => 'subscription.failed',
            'data' => [
                'subscription_id' => 'sub_failed_1',
                'product_id' => 'pdt_test_starter',
                'metadata' => [
                    'organization_id' => $organization->id,
                    'billing_plan_id' => $starter->id,
                    'billing_plan_slug' => 'starter',
                ],
            ],
        ];
        $payload = json_encode($body, JSON_THROW_ON_ERROR);
        $headers = $this->signedWebhookHeaders($payload, $secret, 'msg_failed_checkout');

        $this->call(
            'POST',
            '/api/v1/webhooks/dodo',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_WEBHOOK_ID' => $headers['webhook-id'],
                'HTTP_WEBHOOK_TIMESTAMP' => $headers['webhook-timestamp'],
                'HTTP_WEBHOOK_SIGNATURE' => $headers['webhook-signature'],
            ],
            $payload,
        )->assertOk();

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame($free->id, $subscription->billing_plan_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->metadata['pending_plan_id'] ?? null);
        $this->assertNull($subscription->dodo_checkout_session_id);
    }

    #[Test]
    public function failed_renewal_marks_past_due_but_keeps_paid_plan(): void
    {
        $this->seedBillingPlans();
        [, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $starter->update(['dodo_product_id' => 'pdt_test_starter']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_existing',
        ]);

        $secret = 'whsec_dGVzdF9zZWNyZXQ=';
        config(['billing.dodo.webhook_secret' => $secret]);

        $body = [
            'type' => 'subscription.failed',
            'data' => [
                'subscription_id' => 'sub_existing',
                'product_id' => 'pdt_test_starter',
                'metadata' => [
                    'organization_id' => $organization->id,
                    'billing_plan_id' => $starter->id,
                ],
            ],
        ];
        $payload = json_encode($body, JSON_THROW_ON_ERROR);
        $headers = $this->signedWebhookHeaders($payload, $secret, 'msg_failed_renewal');

        $this->call(
            'POST',
            '/api/v1/webhooks/dodo',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_WEBHOOK_ID' => $headers['webhook-id'],
                'HTTP_WEBHOOK_TIMESTAMP' => $headers['webhook-timestamp'],
                'HTTP_WEBHOOK_SIGNATURE' => $headers['webhook-signature'],
            ],
            $payload,
        )->assertOk();

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame($starter->id, $subscription->billing_plan_id);
        $this->assertSame(SubscriptionStatus::PastDue, $subscription->status);
    }

    #[Test]
    public function cancelled_subscription_moves_org_to_free(): void
    {
        $this->seedBillingPlans();
        [, $organization] = $this->createOwnerWithOrganization();
        $free = $this->plan('free');
        $starter = $this->plan('starter');
        $starter->update(['dodo_product_id' => 'pdt_test_starter']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_existing',
            'dodo_customer_id' => 'cus_existing',
            'metadata' => [
                'cancel_at_period_end' => true,
                'pending_plan_slug' => 'free',
            ],
        ]);

        $secret = 'whsec_dGVzdF9zZWNyZXQ=';
        config(['billing.dodo.webhook_secret' => $secret]);

        $body = [
            'type' => 'subscription.cancelled',
            'data' => [
                'subscription_id' => 'sub_existing',
                'customer_id' => 'cus_existing',
                'product_id' => 'pdt_test_starter',
                'metadata' => [
                    'organization_id' => $organization->id,
                    'billing_plan_id' => $starter->id,
                ],
            ],
        ];
        $payload = json_encode($body, JSON_THROW_ON_ERROR);
        $headers = $this->signedWebhookHeaders($payload, $secret, 'msg_cancelled');

        $this->call(
            'POST',
            '/api/v1/webhooks/dodo',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_WEBHOOK_ID' => $headers['webhook-id'],
                'HTTP_WEBHOOK_TIMESTAMP' => $headers['webhook-timestamp'],
                'HTTP_WEBHOOK_SIGNATURE' => $headers['webhook-signature'],
            ],
            $payload,
        )->assertOk();

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame($free->id, $subscription->billing_plan_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->dodo_subscription_id);
        $this->assertFalse((bool) ($subscription->metadata['cancel_at_period_end'] ?? false));
        $this->assertSame('cus_existing', $subscription->dodo_customer_id);
    }

    #[Test]
    public function cancel_of_replaced_subscription_is_ignored_after_upgrade_checkout(): void
    {
        $this->seedBillingPlans();
        [, $organization] = $this->createOwnerWithOrganization();
        $growth = $this->plan('growth');
        $growth->update(['dodo_product_id' => 'pdt_test_growth']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $growth->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_new_growth',
            'dodo_customer_id' => 'cus_existing',
            'metadata' => [
                'previous_dodo_subscription_id' => null,
            ],
        ]);

        $secret = 'whsec_dGVzdF9zZWNyZXQ=';
        config(['billing.dodo.webhook_secret' => $secret]);

        $body = [
            'type' => 'subscription.cancelled',
            'data' => [
                'subscription_id' => 'sub_old_starter',
                'customer_id' => 'cus_existing',
                'product_id' => 'pdt_test_starter',
                'metadata' => [
                    'organization_id' => $organization->id,
                    'billing_plan_id' => $this->plan('starter')->id,
                ],
            ],
        ];
        $payload = json_encode($body, JSON_THROW_ON_ERROR);
        $headers = $this->signedWebhookHeaders($payload, $secret, 'msg_cancel_old');

        $this->call(
            'POST',
            '/api/v1/webhooks/dodo',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_WEBHOOK_ID' => $headers['webhook-id'],
                'HTTP_WEBHOOK_TIMESTAMP' => $headers['webhook-timestamp'],
                'HTTP_WEBHOOK_SIGNATURE' => $headers['webhook-signature'],
            ],
            $payload,
        )->assertOk();

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame($growth->id, $subscription->billing_plan_id);
        $this->assertSame('sub_new_growth', $subscription->dodo_subscription_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
    }

    #[Test]
    public function expired_subscription_moves_org_to_free(): void
    {
        $this->seedBillingPlans();
        [, $organization] = $this->createOwnerWithOrganization();
        $free = $this->plan('free');
        $starter = $this->plan('starter');
        $starter->update(['dodo_product_id' => 'pdt_test_starter']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_expire',
            'metadata' => [
                'cancel_at_period_end' => true,
                'pending_plan_slug' => 'free',
            ],
        ]);

        $secret = 'whsec_dGVzdF9zZWNyZXQ=';
        config(['billing.dodo.webhook_secret' => $secret]);

        $body = [
            'type' => 'subscription.expired',
            'data' => [
                'subscription_id' => 'sub_expire',
                'product_id' => 'pdt_test_starter',
                'metadata' => [
                    'organization_id' => $organization->id,
                    'billing_plan_id' => $starter->id,
                ],
            ],
        ];
        $payload = json_encode($body, JSON_THROW_ON_ERROR);
        $headers = $this->signedWebhookHeaders($payload, $secret, 'msg_expired');

        $this->call(
            'POST',
            '/api/v1/webhooks/dodo',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_WEBHOOK_ID' => $headers['webhook-id'],
                'HTTP_WEBHOOK_TIMESTAMP' => $headers['webhook-timestamp'],
                'HTTP_WEBHOOK_SIGNATURE' => $headers['webhook-signature'],
            ],
            $payload,
        )->assertOk();

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame($free->id, $subscription->billing_plan_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->dodo_subscription_id);
    }

    #[Test]
    public function plan_changed_cancel_flag_keeps_paid_plan_until_period_end(): void
    {
        $this->seedBillingPlans();
        [, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $starter->update(['dodo_product_id' => 'pdt_test_starter']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
            'current_period_end' => now()->addDays(14),
        ]);

        $secret = 'whsec_dGVzdF9zZWNyZXQ=';
        config(['billing.dodo.webhook_secret' => $secret]);

        $body = [
            'type' => 'subscription.plan_changed',
            'data' => [
                'subscription_id' => 'sub_live',
                'product_id' => 'pdt_test_starter',
                'cancel_at_next_billing_date' => true,
                'next_billing_date' => now()->addDays(14)->toIso8601String(),
                'metadata' => [
                    'organization_id' => $organization->id,
                    'billing_plan_id' => $starter->id,
                ],
            ],
        ];
        $payload = json_encode($body, JSON_THROW_ON_ERROR);
        $headers = $this->signedWebhookHeaders($payload, $secret, 'msg_plan_cancel_flag');

        $this->call(
            'POST',
            '/api/v1/webhooks/dodo',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_WEBHOOK_ID' => $headers['webhook-id'],
                'HTTP_WEBHOOK_TIMESTAMP' => $headers['webhook-timestamp'],
                'HTTP_WEBHOOK_SIGNATURE' => $headers['webhook-signature'],
            ],
            $payload,
        )->assertOk();

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame($starter->id, $subscription->billing_plan_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue((bool) ($subscription->metadata['cancel_at_period_end'] ?? false));
        $this->assertSame('free', $subscription->metadata['pending_plan_slug'] ?? null);
    }

    #[Test]
    public function on_hold_keeps_paid_plan(): void
    {
        $this->seedBillingPlans();
        [, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $starter->update(['dodo_product_id' => 'pdt_test_starter']);

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'dodo_subscription_id' => 'sub_live',
        ]);

        $secret = 'whsec_dGVzdF9zZWNyZXQ=';
        config(['billing.dodo.webhook_secret' => $secret]);

        $body = [
            'type' => 'subscription.on_hold',
            'data' => [
                'subscription_id' => 'sub_live',
                'product_id' => 'pdt_test_starter',
                'metadata' => [
                    'organization_id' => $organization->id,
                    'billing_plan_id' => $starter->id,
                ],
            ],
        ];
        $payload = json_encode($body, JSON_THROW_ON_ERROR);
        $headers = $this->signedWebhookHeaders($payload, $secret, 'msg_on_hold');

        $this->call(
            'POST',
            '/api/v1/webhooks/dodo',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_WEBHOOK_ID' => $headers['webhook-id'],
                'HTTP_WEBHOOK_TIMESTAMP' => $headers['webhook-timestamp'],
                'HTTP_WEBHOOK_SIGNATURE' => $headers['webhook-signature'],
            ],
            $payload,
        )->assertOk();

        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertSame($starter->id, $subscription->billing_plan_id);
        $this->assertSame(SubscriptionStatus::OnHold, $subscription->status);
        $this->assertSame('sub_live', $subscription->dodo_subscription_id);
    }

    #[Test]
    public function it_rejects_unsigned_webhooks_even_in_local(): void
    {
        config([
            'billing.dodo.webhook_secret' => null,
        ]);

        $this->postJson('/api/v1/webhooks/dodo', [
            'type' => 'subscription.active',
            'data' => [],
        ])->assertStatus(400);
    }
}
