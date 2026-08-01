<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\OrganizationDomain;
use App\Models\OrganizationSubscription;
use App\Services\DomainDnsVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class CustomDomainTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBillingPlans();
        config([
            'authzio.cloudflare.enabled' => false,
            'authzio.domains.cname_target' => 'customers.authzio.com',
        ]);
    }

    #[Test]
    public function free_plan_cannot_add_custom_domain(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/domains", [
                'organization_id' => $organization->id,
                'host' => 'auth.example.com',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['domain']);
    }

    #[Test]
    public function paid_plan_can_add_and_verify_custom_domain(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $create = $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/domains", [
                'organization_id' => $organization->id,
                'host' => 'auth.example.com',
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('auth.example.com', $create['host']);
        $this->assertNotEmpty($create['verification_token']);
        $this->assertNull($create['verified_at']);
        $this->assertIsArray($create['dns_records']);
        $this->assertTrue(
            collect($create['dns_records'])->contains(
                fn (array $record): bool => ($record['type'] ?? '') === 'CNAME'
                    && ($record['value'] ?? '') === 'customers.authzio.com',
            ),
        );

        $token = $create['verification_token'];
        $domainId = $create['id'];

        $this->app->instance(
            DomainDnsVerifier::class,
            (new DomainDnsVerifier)->usingTxtLookup(
                fn (string $host): array => $host === 'auth.example.com' ? [$token] : [],
            ),
        );

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/domains/{$domainId}/verify")
            ->assertOk()
            ->assertJsonPath('data.verified_at', fn ($value): bool => is_string($value) && $value !== '');

        $this->assertNotNull(
            OrganizationDomain::query()->findOrFail($domainId)->verified_at,
        );
    }

    #[Test]
    public function verify_fails_when_dns_txt_missing(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');

        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $domain = $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/domains", [
                'organization_id' => $organization->id,
                'host' => 'login.acme.test',
            ])
            ->assertCreated()
            ->json('data');

        $this->app->instance(
            DomainDnsVerifier::class,
            (new DomainDnsVerifier)->usingTxtLookup(fn (): array => []),
        );

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/domains/{$domain['id']}/verify")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['host']);
    }

    #[Test]
    public function authzio_subdomain_cannot_be_deleted(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();

        $subdomain = OrganizationDomain::query()
            ->where('organization_id', $organization->id)
            ->where('type', 'subdomain')
            ->firstOrFail();

        $this->actingAs($user)
            ->deleteJson("/api/v1/organizations/{$organization->id}/domains/{$subdomain->id}")
            ->assertStatus(422);
    }

    #[Test]
    public function domains_index_rewrites_subdomain_host_to_current_root(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();

        config(['authzio.domains.root' => 'authzio.com']);

        OrganizationDomain::query()
            ->where('organization_id', $organization->id)
            ->where('type', 'subdomain')
            ->update(['host' => $organization->subdomain.'.authzio.test']);

        $organization->update([
            'primary_domain' => $organization->subdomain.'.authzio.test',
        ]);

        $expected = $organization->subdomain.'.authzio.com';

        $this->actingAs($user)
            ->getJson("/api/v1/organizations/{$organization->id}/domains")
            ->assertOk()
            ->assertJsonPath('root_domain', 'authzio.com')
            ->assertJsonPath('cname_target', $expected)
            ->assertJsonPath('cloudflare_saas', false)
            ->assertJsonPath('domains.0.host', $expected);

        $this->assertSame(
            $expected,
            OrganizationDomain::query()
                ->where('organization_id', $organization->id)
                ->where('type', 'subdomain')
                ->value('host'),
        );
        $this->assertSame($expected, $organization->fresh()?->primary_domain);
    }

    #[Test]
    public function cloudflare_saas_provisions_hostname_and_verifies_when_active(): void
    {
        config([
            'authzio.cloudflare.enabled' => true,
            'authzio.cloudflare.api_token' => 'test-token',
            'authzio.cloudflare.zone_id' => 'zone-123',
            'authzio.domains.cname_target' => 'customers.authzio.com',
        ]);

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/zone-123/custom_hostnames' => Http::response([
                'success' => true,
                'result' => [
                    'id' => 'cf-host-1',
                    'hostname' => 'id.example.com',
                    'status' => 'pending',
                    'ownership_verification' => [
                        'type' => 'txt',
                        'name' => '_cf-custom-hostname.id.example.com',
                        'value' => 'own-token',
                    ],
                    'ssl' => [
                        'status' => 'pending_validation',
                        'validation_records' => [
                            [
                                'txt_name' => '_acme-challenge.id.example.com',
                                'txt_value' => 'ssl-token',
                            ],
                        ],
                    ],
                    'verification_errors' => ['custom hostname does not CNAME to this zone.'],
                ],
            ], 200),
            'https://api.cloudflare.com/client/v4/zones/zone-123/custom_hostnames/cf-host-1' => Http::sequence()
                ->push([
                    'success' => true,
                    'result' => [
                        'id' => 'cf-host-1',
                        'hostname' => 'id.example.com',
                        'status' => 'pending',
                        'ownership_verification' => [
                            'type' => 'txt',
                            'name' => '_cf-custom-hostname.id.example.com',
                            'value' => 'own-token',
                        ],
                        'ssl' => ['status' => 'pending_validation', 'validation_records' => []],
                        'verification_errors' => ['waiting'],
                    ],
                ], 200)
                ->push([
                    'success' => true,
                    'result' => [
                        'id' => 'cf-host-1',
                        'hostname' => 'id.example.com',
                        'status' => 'active',
                        'ownership_verification' => [
                            'type' => 'txt',
                            'name' => '_cf-custom-hostname.id.example.com',
                            'value' => 'own-token',
                        ],
                        'ssl' => ['status' => 'active', 'validation_records' => []],
                        'verification_errors' => [],
                    ],
                ], 200),
            'https://api.cloudflare.com/client/v4/zones/zone-123/custom_hostnames/cf-host-1*' => Http::response([
                'success' => true,
            ], 200),
        ]);

        [$user, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        OrganizationSubscription::query()->where('organization_id', $organization->id)->update([
            'billing_plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $create = $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/domains", [
                'organization_id' => $organization->id,
                'host' => 'id.example.com',
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('cf-host-1', $create['cloudflare_hostname_id']);
        $this->assertSame('pending', $create['cloudflare_status']);
        $this->assertTrue(
            collect($create['dns_records'])->contains(
                fn (array $record): bool => ($record['purpose'] ?? '') === 'ownership'
                    && ($record['value'] ?? '') === 'own-token',
            ),
        );

        $domainId = $create['id'];

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/domains/{$domainId}/verify")
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/domains/{$domainId}/verify")
            ->assertOk()
            ->assertJsonPath('data.verified_at', fn ($value): bool => is_string($value) && $value !== '')
            ->assertJsonPath('data.cloudflare_status', 'active');

        $this->actingAs($user)
            ->getJson("/api/v1/organizations/{$organization->id}/domains")
            ->assertOk()
            ->assertJsonPath('cloudflare_saas', true)
            ->assertJsonPath('cname_target', 'customers.authzio.com');

        $this->actingAs($user)
            ->deleteJson("/api/v1/organizations/{$organization->id}/domains/{$domainId}")
            ->assertOk();

        $this->assertDatabaseMissing('organization_domains', ['id' => $domainId]);
    }
}
