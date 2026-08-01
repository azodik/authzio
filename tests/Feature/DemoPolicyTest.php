<?php

namespace Tests\Feature;

use App\Enums\ApplicationType;
use App\Models\OAuthClient;
use App\Models\Organization;
use App\Models\User;
use App\Services\Demo\DemoCapability;
use App\Services\OrganizationService;
use Database\Seeders\BillingPlanSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class DemoPolicyTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BillingPlanSeeder::class);
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    /**
     * @return array{0: User, 1: Organization, 2: OAuthClient}
     */
    private function demoWorkspace(): array
    {
        $user = User::factory()->create([
            'email' => 'demo-policy@authzio.com',
            'password' => Hash::make('AuthzioDemo2026!'),
            'is_demo' => true,
            'email_verified_at' => now(),
        ]);

        $organization = app(OrganizationService::class)->create($user, 'Demo Policy Org', 'demo-policy-org');
        $organization->forceFill(['is_demo' => true])->save();

        $client = OAuthClient::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'name' => 'Demo Policy App',
            'application_type' => ApplicationType::Spa,
            'redirect_uris' => ['https://app.example.com/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'is_confidential' => false,
            'login_layout' => 'form_right',
            'login_theme' => 'light',
        ]);

        return [$user, $organization, $client];
    }

    #[Test]
    public function demo_can_soft_update_application_without_persisting(): void
    {
        [$user, $organization, $client] = $this->demoWorkspace();

        $this->actingAs($user)
            ->putJson("/api/v1/organizations/{$organization->id}/applications/{$client->id}", [
                'login_layout' => 'centered',
                'login_theme' => 'dark',
                'login_headline' => 'Demo headline',
            ])
            ->assertOk()
            ->assertJsonPath('demo_soft', true)
            ->assertJsonPath('data.login_layout', 'centered')
            ->assertJsonPath('data.login_theme', 'dark');

        $client->refresh();
        $this->assertSame('form_right', $client->login_layout);
        $this->assertSame('light', $client->login_theme);

        $this->actingAs($user)
            ->getJson("/api/v1/organizations/{$organization->id}/applications/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.login_layout', 'centered')
            ->assertJsonPath('entitlements.demo_facade', true)
            ->assertJsonPath('entitlements.allows_login_customization', true)
            ->assertJsonPath('entitlements.allows_custom_domains', false);
    }

    #[Test]
    public function demo_domain_mutations_are_denied(): void
    {
        [$user, $organization] = $this->demoWorkspace();

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/domains", [
                'organization_id' => $organization->id,
                'host' => 'evil.example.com',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'demo_boundary')
            ->assertJsonPath('capability', DemoCapability::DomainMutate->value);
    }

    #[Test]
    public function demo_preferences_are_allowed(): void
    {
        [$user] = $this->demoWorkspace();

        $this->actingAs($user)
            ->patchJson('/api/v1/auth/preferences', [
                'theme' => 'dark',
            ])
            ->assertOk()
            ->assertJsonPath('user.theme', 'dark');
    }

    #[Test]
    public function auth_me_exposes_demo_policy(): void
    {
        [$user] = $this->demoWorkspace();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('demo.active', true);

        /** @var array<string, string> $capabilities */
        $capabilities = $response->json('demo.capabilities');
        $this->assertSame('soft', $capabilities[DemoCapability::ApplicationUpdate->value] ?? null);
        $this->assertSame('deny', $capabilities[DemoCapability::DomainMutate->value] ?? null);
    }

    #[Test]
    public function demo_user_cannot_use_hosted_oauth_password_login(): void
    {
        [$user, $organization, $client] = $this->demoWorkspace();

        $challenge = rtrim(strtr(base64_encode(hash('sha256', 'verifier', true)), '+/', '-_'), '=');
        $query = [
            'client_id' => $client->id,
            'redirect_uri' => 'https://app.example.com/callback',
            'response_type' => 'code',
            'scope' => 'openid email',
            'state' => 'demo',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ];

        $this->withHeaders(['X-Authzio-Organization' => $organization->id])
            ->withSession([
                'authzio_authorize_query' => $query,
                'authzio_organization_id' => $organization->id,
            ])
            ->post('/oauth/authorize', [
                ...$query,
                'mode' => 'password',
                'email' => $user->email,
                'password' => 'AuthzioDemo2026!',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function demo_password_reset_is_denied(): void
    {
        [$user] = $this->demoWorkspace();

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'BrandNewPass123!',
            'password_confirmation' => 'BrandNewPass123!',
        ])->assertStatus(422);
    }
}
