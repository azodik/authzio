<?php

namespace Tests\Feature;

use App\Enums\ApplicationType;
use App\Enums\EmailOtpPurpose;
use App\Models\EmailOtp;
use App\Models\OAuthClient;
use App\Models\Organization;
use App\Models\User;
use App\Services\Auth\PasskeyService;
use App\Services\Mail\TransactionalMailer;
use Database\Seeders\BillingPlanSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class HostedLoginModesTest extends TestCase
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
    private function spaClientWithMethods(array $loginMethods): array
    {
        [, $organization] = $this->createOwnerWithOrganization();

        $client = OAuthClient::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Hosted Modes App',
            'application_type' => ApplicationType::Spa,
            'redirect_uris' => ['https://app.example.com/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'is_confidential' => false,
            'login_methods' => array_merge([
                'password' => false,
                'google' => false,
                'github' => false,
                'passkey' => false,
                'email_otp' => false,
                'sync_profile' => true,
                'require_verified_email' => false,
                'allow_unverified_email_with_otp' => false,
            ], $loginMethods),
        ]);

        $challenge = rtrim(strtr(base64_encode(hash('sha256', 'verifier-hosted-login', true)), '+/', '-_'), '=');
        $query = [
            'client_id' => $client->id,
            'redirect_uri' => 'https://app.example.com/callback',
            'response_type' => 'code',
            'scope' => 'openid email',
            'state' => 'hosted',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ];

        return [$organization, $client, $query];
    }

    #[Test]
    public function password_login_issues_authorization_code(): void
    {
        [$organization, , $query] = $this->spaClientWithMethods(['password' => true]);
        $user = User::factory()->create([
            'email' => 'hosted@example.com',
            'password' => Hash::make('SecurePass123!'),
        ]);

        $location = (string) $this->withHeaders(['X-Authzio-Organization' => $organization->id])
            ->withSession([
                'authzio_authorize_query' => $query,
                'authzio_organization_id' => $organization->id,
            ])
            ->post('/oauth/authorize', [
                ...$query,
                'mode' => 'password',
                'email' => $user->email,
                'password' => 'SecurePass123!',
            ])
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith('https://app.example.com/callback', $location);
        $this->assertStringContainsString('code=', $location);
    }

    #[Test]
    public function email_otp_login_send_and_verify_flow(): void
    {
        $capturedCode = null;
        $this->mock(TransactionalMailer::class, function (MockInterface $mock) use (&$capturedCode): void {
            $mock->shouldReceive('sendOrganization')
                ->once()
                ->andReturnUsing(function (...$args) use (&$capturedCode): void {
                    $vars = $args[3] ?? [];
                    $capturedCode = is_array($vars) ? ($vars['otp_code'] ?? null) : null;
                });
        });

        [$organization, , $query] = $this->spaClientWithMethods(['email_otp' => true]);

        $this->withHeaders(['X-Authzio-Organization' => $organization->id])
            ->withSession([
                'authzio_authorize_query' => $query,
                'authzio_organization_id' => $organization->id,
            ])
            ->post('/oauth/authorize', [
                ...$query,
                'mode' => 'email_otp_send',
                'email' => 'otp-user@example.com',
            ])
            ->assertRedirect();

        $this->assertNotNull($capturedCode);
        $this->assertDatabaseHas('email_otps', [
            'email' => 'otp-user@example.com',
            'purpose' => EmailOtpPurpose::Login->value,
        ]);

        $this->withHeaders(['X-Authzio-Organization' => $organization->id])
            ->withSession([
                'authzio_authorize_query' => $query,
                'authzio_organization_id' => $organization->id,
                'authzio_otp_login_email' => 'otp-user@example.com',
            ])
            ->post('/oauth/authorize', [
                ...$query,
                'mode' => 'email_otp_verify',
                'email' => 'otp-user@example.com',
                'code' => $capturedCode,
            ])
            ->assertRedirect();

        $this->assertNotNull(User::query()->where('email', 'otp-user@example.com')->first());
        $this->assertNotNull(EmailOtp::query()->where('email', 'otp-user@example.com')->whereNotNull('consumed_at')->first());
    }

    #[Test]
    public function email_otp_rejects_invalid_code(): void
    {
        [$organization, , $query] = $this->spaClientWithMethods(['email_otp' => true]);

        EmailOtp::query()->create([
            'email' => 'otp-user@example.com',
            'code_hash' => Hash::make('123456'),
            'purpose' => EmailOtpPurpose::Login,
            'organization_id' => $organization->id,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        $this->withHeaders(['X-Authzio-Organization' => $organization->id])
            ->withSession([
                'authzio_authorize_query' => $query,
                'authzio_organization_id' => $organization->id,
            ])
            ->from('/oauth/authorize?'.http_build_query($query))
            ->post('/oauth/authorize', [
                ...$query,
                'mode' => 'email_otp_verify',
                'email' => 'otp-user@example.com',
                'code' => '000000',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('code');
    }

    #[Test]
    public function passkey_options_return_webauthn_challenge(): void
    {
        [$organization, , $query] = $this->spaClientWithMethods(['passkey' => true]);

        $this->withHeaders(['X-Authzio-Organization' => $organization->id])
            ->withSession([
                'authzio_authorize_query' => $query,
                'authzio_organization_id' => $organization->id,
            ])
            ->getJson('/oauth/passkey/options?'.http_build_query($query))
            ->assertOk()
            ->assertJsonStructure(['publicKey' => ['challenge', 'rpId']]);
    }

    #[Test]
    public function social_redirect_404s_when_provider_disabled_on_app(): void
    {
        [$organization, , $query] = $this->spaClientWithMethods([
            'password' => true,
            'google' => false,
        ]);

        $this->withHeaders(['X-Authzio-Organization' => $organization->id])
            ->get('/oauth/social/google/redirect?'.http_build_query($query))
            ->assertNotFound();
    }

    #[Test]
    public function disabled_login_mode_is_rejected(): void
    {
        [$organization, , $query] = $this->spaClientWithMethods([
            'password' => false,
            'email_otp' => true,
        ]);

        $this->withHeaders(['X-Authzio-Organization' => $organization->id])
            ->withSession([
                'authzio_authorize_query' => $query,
                'authzio_organization_id' => $organization->id,
            ])
            ->from('/oauth/authorize?'.http_build_query($query))
            ->post('/oauth/authorize', [
                ...$query,
                'mode' => 'password',
                'email' => 'x@example.com',
                'password' => 'SecurePass123!',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('email');
    }

    #[Test]
    public function passkey_service_builds_authentication_options(): void
    {
        $options = app(PasskeyService::class)->authenticationOptions('authzio.test');

        $this->assertArrayHasKey('publicKey', $options);
        $this->assertIsArray($options['publicKey']);
        $this->assertArrayHasKey('challenge', $options['publicKey']);
    }
}
