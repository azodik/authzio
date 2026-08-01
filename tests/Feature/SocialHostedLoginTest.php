<?php

namespace Tests\Feature;

use App\Enums\ApplicationType;
use App\Enums\SocialProvider;
use App\Models\OAuthClient;
use App\Models\Organization;
use App\Models\OrganizationSocialProvider;
use App\Models\UserIdentity;
use App\Services\Auth\SocialIdentityService;
use App\Services\Oidc\IssuerResolver;
use Database\Seeders\BillingPlanSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class SocialHostedLoginTest extends TestCase
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
     * @return array{0: Organization, 1: OAuthClient, 2: array<string, string>}
     */
    private function appWithGoogle(): array
    {
        [, $organization] = $this->createOwnerWithOrganization();

        OrganizationSocialProvider::query()->create([
            'organization_id' => $organization->id,
            'provider' => SocialProvider::Google,
            'client_id' => 'google-client',
            'client_secret' => 'google-secret',
            'enabled' => true,
            'scopes' => SocialProvider::Google->defaultScopes(),
        ]);

        $client = OAuthClient::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Social App',
            'application_type' => ApplicationType::Spa,
            'redirect_uris' => ['https://app.example.com/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'is_confidential' => false,
            'login_methods' => [
                'password' => true,
                'google' => true,
                'github' => false,
                'passkey' => false,
                'email_otp' => false,
                'sync_profile' => true,
                'require_verified_email' => false,
                'allow_unverified_email_with_otp' => false,
            ],
        ]);

        $challenge = rtrim(strtr(base64_encode(hash('sha256', 'verifier-social', true)), '+/', '-_'), '=');
        $query = [
            'client_id' => $client->id,
            'redirect_uri' => 'https://app.example.com/callback',
            'response_type' => 'code',
            'scope' => 'openid email',
            'state' => 'social-state',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ];

        return [$organization, $client, $query];
    }

    #[Test]
    public function authorize_page_shows_social_button_when_org_and_app_enabled(): void
    {
        [$organization, , $query] = $this->appWithGoogle();

        $this->withHeaders(['X-Authzio-Organization' => $organization->id])
            ->get('/oauth/authorize?'.http_build_query($query))
            ->assertOk()
            ->assertSee('Continue with Google', false)
            ->assertSee('/oauth/social/google/redirect', false);
    }

    #[Test]
    public function authorize_page_hides_social_when_app_toggle_off(): void
    {
        [$organization, $client, $query] = $this->appWithGoogle();
        $methods = $client->login_methods;
        $methods['google'] = false;
        $client->update(['login_methods' => $methods]);

        $this->withHeaders(['X-Authzio-Organization' => $organization->id])
            ->get('/oauth/authorize?'.http_build_query($query))
            ->assertOk()
            ->assertDontSee('Continue with Google', false);
    }

    #[Test]
    public function callback_url_uses_organization_issuer_host(): void
    {
        [$organization] = $this->appWithGoogle();

        $expected = rtrim(app(IssuerResolver::class)->issuerUrl($organization), '/').'/oauth/social/google/callback';

        $this->assertSame(
            $expected,
            app(SocialIdentityService::class)->callbackUrl($organization, SocialProvider::Google),
        );
    }

    #[Test]
    public function social_callback_creates_user_and_completes_authorize(): void
    {
        [$organization, , $query] = $this->appWithGoogle();

        $socialUser = (new SocialiteUser)->setRaw([
            'sub' => 'google-user-1',
            'email' => 'alice@gmail.com',
            'email_verified' => true,
            'name' => 'Alice Google',
        ])->map([
            'id' => 'google-user-1',
            'nickname' => 'alice',
            'name' => 'Alice Google',
            'email' => 'alice@gmail.com',
            'avatar' => null,
        ]);

        $driver = Mockery::mock(AbstractProvider::class);
        $driver->shouldReceive('user')->once()->andReturn($socialUser);

        $service = Mockery::mock(SocialIdentityService::class, [app(IssuerResolver::class)])->makePartial();
        $service->shouldReceive('configureDriver')
            ->once()
            ->andReturn($driver);
        $this->app->instance(SocialIdentityService::class, $service);

        $location = (string) $this->withHeaders(['X-Authzio-Organization' => $organization->id])
            ->withSession([
                'authzio_authorize_query' => $query,
                'authzio_organization_id' => $organization->id,
            ])
            ->get('/oauth/social/google/callback?code=fake-code&state=social-state')
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith('https://app.example.com/callback', $location);
        $this->assertStringContainsString('code=', $location);

        $this->assertDatabaseHas('users', [
            'email' => 'alice@gmail.com',
            'name' => 'Alice Google',
        ]);

        $this->assertTrue(
            UserIdentity::query()
                ->where('provider', 'google')
                ->where('provider_user_id', 'google-user-1')
                ->exists(),
        );
    }
}
