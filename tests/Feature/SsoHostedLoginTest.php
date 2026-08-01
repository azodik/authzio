<?php

namespace Tests\Feature;

use App\Enums\ApplicationType;
use App\Models\OAuthClient;
use App\Models\Organization;
use App\Models\OrganizationSsoConnection;
use App\Models\UserIdentity;
use App\Services\Auth\GenericOidcProvider;
use App\Services\Auth\SsoIdentityService;
use App\Services\Oidc\IssuerResolver;
use Database\Seeders\BillingPlanSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class SsoHostedLoginTest extends TestCase
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
     * @return array{0: Organization, 1: OAuthClient, 2: OrganizationSsoConnection, 3: array<string, string>}
     */
    private function growthOrgWithSso(): array
    {
        [, $organization] = $this->createOwnerWithOrganization();
        $organization->subscription()->update([
            'billing_plan_id' => $this->plan('growth')->id,
        ]);

        $client = OAuthClient::query()->create([
            'organization_id' => $organization->id,
            'name' => 'SSO App',
            'application_type' => ApplicationType::Spa,
            'redirect_uris' => ['https://app.example.com/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'is_confidential' => false,
            'login_methods' => [
                'password' => true,
                'google' => false,
                'github' => false,
                'passkey' => false,
                'email_otp' => false,
                'sync_profile' => true,
                'require_verified_email' => false,
                'allow_unverified_email_with_otp' => false,
            ],
        ]);

        $connection = OrganizationSsoConnection::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Acme Okta',
            'slug' => 'acme-okta',
            'protocol' => 'oidc',
            'issuer' => 'https://login.example.com',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
            'authorization_endpoint' => 'https://login.example.com/authorize',
            'token_endpoint' => 'https://login.example.com/token',
            'userinfo_endpoint' => 'https://login.example.com/userinfo',
            'jwks_uri' => 'https://login.example.com/jwks',
            'scopes' => ['openid', 'profile', 'email'],
            'email_domains' => ['acme.com'],
            'enabled' => true,
        ]);

        $challenge = rtrim(strtr(base64_encode(hash('sha256', 'verifier-sso', true)), '+/', '-_'), '=');
        $query = [
            'client_id' => $client->id,
            'redirect_uri' => 'https://app.example.com/callback',
            'response_type' => 'code',
            'scope' => 'openid email',
            'state' => 'sso-state',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ];

        return [$organization, $client, $connection, $query];
    }

    #[Test]
    public function authorize_page_shows_sso_button_on_growth_plan(): void
    {
        [$organization, , $connection, $query] = $this->growthOrgWithSso();

        $this->withHeaders(['X-Authzio-Organization' => $organization->id])
            ->get('/oauth/authorize?'.http_build_query($query))
            ->assertOk()
            ->assertSee('Continue with Acme Okta', false)
            ->assertSee('/oauth/sso/'.$connection->id.'/redirect', false);
    }

    #[Test]
    public function authorize_page_hides_sso_on_free_plan(): void
    {
        [$organization, , $connection, $query] = $this->growthOrgWithSso();
        $organization->subscription()->update([
            'billing_plan_id' => $this->plan('free')->id,
        ]);

        $this->withHeaders(['X-Authzio-Organization' => $organization->id])
            ->get('/oauth/authorize?'.http_build_query($query))
            ->assertOk()
            ->assertDontSee('Continue with Acme Okta', false)
            ->assertDontSee('/oauth/sso/'.$connection->id.'/redirect', false);
    }

    #[Test]
    public function callback_url_uses_organization_issuer_host(): void
    {
        [$organization, , $connection] = $this->growthOrgWithSso();

        $expected = rtrim(app(IssuerResolver::class)->issuerUrl($organization), '/').'/oauth/sso/'.$connection->id.'/callback';

        $this->assertSame($expected, app(SsoIdentityService::class)->callbackUrl($connection));
    }

    #[Test]
    public function sso_callback_creates_user_and_completes_authorize(): void
    {
        [$organization, , $connection, $query] = $this->growthOrgWithSso();

        $socialUser = (new SocialiteUser)->setRaw([
            'sub' => 'okta-user-1',
            'email' => 'alice@acme.com',
            'email_verified' => true,
            'name' => 'Alice Acme',
        ])->map([
            'id' => 'okta-user-1',
            'nickname' => 'alice',
            'name' => 'Alice Acme',
            'email' => 'alice@acme.com',
            'avatar' => null,
        ]);

        $driver = Mockery::mock(GenericOidcProvider::class);
        $driver->shouldReceive('user')->once()->andReturn($socialUser);

        $service = Mockery::mock(SsoIdentityService::class, [app(IssuerResolver::class)])->makePartial();
        $service->shouldReceive('configureDriver')
            ->once()
            ->andReturn($driver);
        $this->app->instance(SsoIdentityService::class, $service);

        $location = (string) $this->withHeaders(['X-Authzio-Organization' => $organization->id])
            ->withSession([
                'authzio_authorize_query' => $query,
                'authzio_organization_id' => $organization->id,
            ])
            ->get('/oauth/sso/'.$connection->id.'/callback?code=fake-code&state=sso-state')
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith('https://app.example.com/callback', $location);
        $this->assertStringContainsString('code=', $location);

        $this->assertDatabaseHas('users', [
            'email' => 'alice@acme.com',
            'name' => 'Alice Acme',
        ]);

        $this->assertTrue(
            UserIdentity::query()
                ->where('provider', 'sso:'.$connection->id)
                ->where('provider_user_id', 'okta-user-1')
                ->exists(),
        );
    }
}
