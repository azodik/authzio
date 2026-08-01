<?php

namespace Tests\Feature;

use App\Models\OrganizationSsoConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class SsoConnectionTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBillingPlans();
    }

    public function test_growth_plan_can_create_oidc_sso_connection(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $growth = $this->plan('growth');
        $organization->subscription()->update(['billing_plan_id' => $growth->id]);

        Http::fake([
            'https://login.example.com/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://login.example.com',
                'authorization_endpoint' => 'https://login.example.com/authorize',
                'token_endpoint' => 'https://login.example.com/token',
                'userinfo_endpoint' => 'https://login.example.com/userinfo',
                'jwks_uri' => 'https://login.example.com/jwks',
            ], 200),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/organizations/{$organization->id}/sso-connections", [
            'organization_id' => $organization->id,
            'name' => 'Acme Okta',
            'issuer' => 'https://login.example.com',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
            'email_domains' => ['acme.com'],
            'discover' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Acme Okta')
            ->assertJsonPath('data.issuer', 'https://login.example.com')
            ->assertJsonPath('data.authorization_endpoint', 'https://login.example.com/authorize')
            ->assertJsonPath('data.email_domains.0', 'acme.com');

        $this->assertDatabaseHas('organization_sso_connections', [
            'organization_id' => $organization->id,
            'slug' => 'acme-okta',
            'client_id' => 'client-123',
        ]);
    }

    public function test_starter_plan_cannot_create_sso_connection(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $starter = $this->plan('starter');
        $organization->subscription()->update(['billing_plan_id' => $starter->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/organizations/{$organization->id}/sso-connections", [
            'organization_id' => $organization->id,
            'name' => 'Acme Okta',
            'issuer' => 'https://login.example.com',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['sso']);
    }

    public function test_sso_index_returns_entitlement_flag(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/organizations/{$organization->id}/sso-connections")
            ->assertOk()
            ->assertJsonPath('entitlements.allows_sso', false)
            ->assertJsonPath('data', []);
    }

    public function test_billing_plans_match_new_pricing(): void
    {
        $this->assertSame(500, $this->plan('starter')->price_cents_monthly);
        $this->assertSame(5_000, $this->plan('starter')->mau_limit);
        $this->assertSame(5, $this->plan('starter')->application_limit);
        $this->assertFalse((bool) $this->plan('starter')->allows_custom_jwks);
        $this->assertFalse((bool) $this->plan('starter')->allows_sso);

        $this->assertSame(2_000, $this->plan('growth')->price_cents_monthly);
        $this->assertFalse((bool) $this->plan('growth')->allows_custom_jwks);
        $this->assertTrue((bool) $this->plan('growth')->allows_sso);

        $this->assertSame(9_900, $this->plan('scale')->price_cents_monthly);
        $this->assertTrue((bool) $this->plan('scale')->allows_custom_jwks);
        $this->assertTrue((bool) $this->plan('scale')->allows_sso);
    }

    public function test_can_delete_sso_connection(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $growth = $this->plan('growth');
        $organization->subscription()->update(['billing_plan_id' => $growth->id]);

        $connection = OrganizationSsoConnection::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Acme',
            'slug' => 'acme',
            'protocol' => 'oidc',
            'issuer' => 'https://login.example.com',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
            'authorization_endpoint' => 'https://login.example.com/authorize',
            'token_endpoint' => 'https://login.example.com/token',
            'userinfo_endpoint' => 'https://login.example.com/userinfo',
            'enabled' => true,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/organizations/{$organization->id}/sso-connections/{$connection->id}")
            ->assertOk();

        $this->assertDatabaseMissing('organization_sso_connections', ['id' => $connection->id]);
    }
}
