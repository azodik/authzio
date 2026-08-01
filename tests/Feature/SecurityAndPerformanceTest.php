<?php

namespace Tests\Feature;

use App\Enums\ApplicationType;
use App\Enums\SubscriptionStatus;
use App\Enums\UsageEventType;
use App\Models\OAuthClient;
use App\Models\OrganizationSubscription;
use App\Models\User;
use App\Services\Billing\BillingNotifier;
use App\Services\Billing\UsageTracker;
use Database\Seeders\BillingPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class SecurityAndPerformanceTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BillingPlanSeeder::class);
    }

    #[Test]
    public function security_headers_are_present_on_api_responses(): void
    {
        $response = $this->getJson('/up');

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotEmpty($response->headers->get('Content-Security-Policy'));
        $this->assertNotEmpty($response->headers->get('Permissions-Policy'));
    }

    #[Test]
    public function non_json_api_mutations_are_rejected(): void
    {
        $this->call(
            'POST',
            '/api/v1/auth/login',
            [
                'email' => 'a@example.com',
                'password' => 'SecurePass123!',
            ],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
                'HTTP_ACCEPT' => 'application/json',
            ],
        )->assertStatus(415);
    }

    #[Test]
    public function oauth_token_endpoint_allows_form_posts(): void
    {
        [, $organization] = $this->createOwnerWithOrganization();
        $plainSecret = 'secret-'.bin2hex(random_bytes(8));

        $client = OAuthClient::query()->create([
            'organization_id' => $organization->id,
            'name' => 'M2M',
            'application_type' => ApplicationType::Machine,
            'secret' => Hash::make($plainSecret),
            'redirect_uris' => [],
            'grant_types' => ['client_credentials'],
            'is_confidential' => true,
        ]);

        $this->call(
            'POST',
            '/api/oauth/token',
            [
                'grant_type' => 'client_credentials',
                'client_id' => $client->id,
                'client_secret' => $plainSecret,
            ],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_AUTHZIO_ORGANIZATION' => $organization->id,
            ],
        )->assertOk();
    }

    #[Test]
    public function auth_endpoints_are_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'limited@example.com',
            'password' => Hash::make('SecurePass123!'),
        ]);

        RateLimiter::clear('auth');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'limited@example.com',
                'password' => 'WrongPassword1!',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'limited@example.com',
            'password' => 'WrongPassword1!',
        ])->assertStatus(429);
    }

    #[Test]
    public function webhook_endpoint_rejects_stale_timestamps(): void
    {
        config(['billing.dodo.webhook_secret' => 'whsec_dGVzdF9zZWNyZXQ=']);

        $payload = json_encode([
            'type' => 'subscription.active',
            'data' => [],
        ], JSON_THROW_ON_ERROR);

        $webhookId = 'msg_stale';
        $timestamp = (string) (time() - 600);
        $secretKey = base64_decode(substr('whsec_dGVzdF9zZWNyZXQ=', 6), true);
        $signature = base64_encode(hash_hmac(
            'sha256',
            $webhookId.'.'.$timestamp.'.'.$payload,
            (string) $secretKey,
            true,
        ));

        $this->call(
            'POST',
            '/api/v1/webhooks/dodo',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_WEBHOOK_ID' => $webhookId,
                'HTTP_WEBHOOK_TIMESTAMP' => $timestamp,
                'HTTP_WEBHOOK_SIGNATURE' => 'v1,'.$signature,
            ],
            $payload,
        )->assertStatus(401);
    }

    #[Test]
    public function billing_requires_org_membership(): void
    {
        $this->mock(BillingNotifier::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyPlanChange')->zeroOrMoreTimes();
            $mock->shouldReceive('checkUsageThresholds')->zeroOrMoreTimes();
            $mock->shouldReceive('checkApplicationThresholds')->zeroOrMoreTimes();
            $mock->shouldReceive('checkEmailUsageThresholds')->zeroOrMoreTimes();
        });

        config(['billing.enabled' => true]);

        [, $organization] = $this->createOwnerWithOrganization();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->getJson("/api/v1/organizations/{$organization->id}/billing")
            ->assertStatus(403);
    }

    #[Test]
    public function usage_events_are_deduplicated_per_subject_per_day(): void
    {
        config(['billing.enabled' => true]);

        [, $organization] = $this->createOwnerWithOrganization();
        $user = User::factory()->create();
        $tracker = app(UsageTracker::class);

        $tracker->record($organization, UsageEventType::TokenIssued, $user->uuid, $user);
        $tracker->record($organization, UsageEventType::TokenIssued, $user->uuid, $user);
        $tracker->record($organization, UsageEventType::TokenIssued, $user->uuid, $user);

        $this->assertDatabaseCount('usage_events', 1);
        $this->assertSame(1, $tracker->monthlyActiveUsers($organization));
    }

    #[Test]
    public function mau_computation_stays_fast_with_many_events(): void
    {
        config(['billing.enabled' => true]);

        [, $organization] = $this->createOwnerWithOrganization();
        $tracker = app(UsageTracker::class);

        for ($i = 0; $i < 50; $i++) {
            $user = User::factory()->create();
            $tracker->record($organization, UsageEventType::UserAuthenticated, $user->uuid, $user);
        }

        $started = hrtime(true);
        $mau = $tracker->monthlyActiveUsers($organization);
        $elapsedMs = (hrtime(true) - $started) / 1_000_000;

        $this->assertSame(50, $mau);
        $this->assertLessThan(500, $elapsedMs, 'MAU query should stay under 500ms for 50 subjects in tests');
    }

    #[Test]
    public function billing_dashboard_responds_quickly_for_active_subscription(): void
    {
        $this->mock(BillingNotifier::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyPlanChange')->zeroOrMoreTimes();
            $mock->shouldReceive('checkUsageThresholds')->zeroOrMoreTimes();
            $mock->shouldReceive('checkApplicationThresholds')->zeroOrMoreTimes();
            $mock->shouldReceive('checkEmailUsageThresholds')->zeroOrMoreTimes();
        });

        config([
            'billing.enabled' => true,
            'billing.dodo.api_key' => 'test_api_key',
            'billing.dodo.base_url' => 'https://test.dodopayments.com',
        ]);

        [$user, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $started = hrtime(true);
        $this->actingAs($user)
            ->getJson("/api/v1/organizations/{$organization->id}/billing")
            ->assertOk()
            ->assertJsonPath('data.plan.slug', 'starter');
        $elapsedMs = (hrtime(true) - $started) / 1_000_000;

        $this->assertLessThan(2000, $elapsedMs, 'Billing dashboard should respond under 2s in tests');
    }

    #[Test]
    public function sso_is_blocked_on_free_plan(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/sso-connections", [
                'name' => 'Acme Okta',
                'protocol' => 'oidc',
                'issuer' => 'https://example.okta.com',
                'client_id' => 'oidc-client',
                'client_secret' => 'oidc-secret',
            ])
            ->assertStatus(422);
    }
}
