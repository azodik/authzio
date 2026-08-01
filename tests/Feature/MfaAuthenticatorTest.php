<?php

namespace Tests\Feature;

use App\Enums\ApplicationType;
use App\Models\MfaRecoveryCode;
use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use App\Models\User;
use App\Services\Auth\MfaService;
use Database\Seeders\BillingPlanSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class MfaAuthenticatorTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BillingPlanSeeder::class);
        config(['authzio.mfa.enabled' => true]);
    }

    private function currentTotp(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    private function seedRecoveryCode(User $user, string $plainCode): void
    {
        MfaRecoveryCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make(strtoupper(str_replace([' ', '-'], '', $plainCode))),
        ]);
    }

    #[Test]
    public function user_can_enroll_authenticator_and_receive_recovery_codes(): void
    {
        $user = User::factory()->create();

        $setup = $this->actingAs($user)
            ->postJson('/api/v1/auth/mfa/setup')
            ->assertOk()
            ->assertJsonStructure(['secret', 'otpauth_url', 'qr_svg']);

        $secret = $setup->json('secret');
        $this->assertIsString($secret);

        $confirm = $this->actingAs($user->fresh())
            ->postJson('/api/v1/auth/mfa/confirm', [
                'code' => $this->currentTotp($secret),
            ])
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonStructure(['recovery_codes']);

        $codes = $confirm->json('recovery_codes');
        $this->assertIsArray($codes);
        $this->assertCount(10, $codes);

        $user->refresh();
        $this->assertTrue($user->mfa_enabled);
        $this->assertNotNull($user->mfa_confirmed_at);
        $this->assertNotNull($user->mfa_secret);
        $this->assertSame(10, MfaRecoveryCode::query()->where('user_id', $user->id)->whereNull('used_at')->count());
    }

    #[Test]
    public function console_login_requires_mfa_challenge_when_enabled(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $user = User::factory()->create([
            'email' => 'mfa@example.com',
            'password' => Hash::make('SecurePass123!'),
            'mfa_enabled' => true,
            'mfa_secret' => $secret,
            'mfa_confirmed_at' => now(),
        ]);
        $this->seedRecoveryCode($user, 'ABCD1234');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'mfa@example.com',
            'password' => 'SecurePass123!',
        ])
            ->assertOk()
            ->assertJsonPath('mfa_required', true)
            ->assertJsonMissing(['user']);

        $this->assertGuest();

        $this->postJson('/api/v1/auth/mfa/challenge', [
            'code' => $this->currentTotp($secret),
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'mfa@example.com');

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function recovery_code_can_complete_console_mfa_challenge_and_is_consumed(): void
    {
        $plain = 'ZXCV9876';
        $user = User::factory()->create([
            'email' => 'recover@example.com',
            'password' => Hash::make('SecurePass123!'),
            'mfa_enabled' => true,
            'mfa_secret' => app(Google2FA::class)->generateSecretKey(32),
            'mfa_confirmed_at' => now(),
        ]);
        $this->seedRecoveryCode($user, $plain);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'recover@example.com',
            'password' => 'SecurePass123!',
        ])->assertOk()->assertJsonPath('mfa_required', true);

        $this->postJson('/api/v1/auth/mfa/challenge', [
            'code' => 'ZXCV-9876',
        ])->assertOk();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, MfaRecoveryCode::query()->where('user_id', $user->id)->whereNull('used_at')->count());
        $this->assertSame(1, MfaRecoveryCode::query()->where('user_id', $user->id)->whereNotNull('used_at')->count());
    }

    #[Test]
    public function disable_mfa_requires_valid_code(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $user = User::factory()->create([
            'mfa_enabled' => true,
            'mfa_secret' => $secret,
            'mfa_confirmed_at' => now(),
        ]);
        $this->seedRecoveryCode($user, 'AAAA1111');

        $this->actingAs($user)
            ->postJson('/api/v1/auth/mfa/disable', ['code' => '000000'])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson('/api/v1/auth/mfa/disable', [
                'code' => $this->currentTotp($secret),
            ])
            ->assertOk()
            ->assertJsonPath('enabled', false);

        $user->refresh();
        $this->assertFalse($user->mfa_enabled);
        $this->assertNull($user->mfa_secret);
        $this->assertSame(0, MfaRecoveryCode::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function hosted_authorize_challenges_mfa_before_issuing_code(): void
    {
        [$owner, $organization] = $this->createOwnerWithOrganization();
        unset($owner);

        $secret = app(Google2FA::class)->generateSecretKey(32);
        $endUser = User::factory()->create([
            'email' => 'end-mfa@example.com',
            'password' => Hash::make('SecurePass123!'),
            'mfa_enabled' => true,
            'mfa_secret' => $secret,
            'mfa_confirmed_at' => now(),
        ]);

        $client = OAuthClient::query()->create([
            'organization_id' => $organization->id,
            'name' => 'MFA App',
            'application_type' => ApplicationType::Spa,
            'redirect_uris' => ['https://app.example.com/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'is_confidential' => false,
            'login_methods' => [
                'password' => true,
                'require_verified_email' => false,
            ],
        ]);

        $pkceChallenge = rtrim(strtr(base64_encode(hash('sha256', 'verifier-aaaaaaaa', true)), '+/', '-_'), '=');
        $query = [
            'client_id' => $client->id,
            'redirect_uri' => 'https://app.example.com/callback',
            'response_type' => 'code',
            'scope' => 'openid',
            'code_challenge' => $pkceChallenge,
            'code_challenge_method' => 'S256',
            'state' => 'mfa-state',
        ];

        $this->actingAs($endUser)
            ->withHeaders(['X-Authzio-Organization' => $organization->id])
            ->get('/oauth/authorize?'.http_build_query($query))
            ->assertRedirect(route('oauth.mfa', $query));

        $this->assertSame(0, OAuthAuthCode::query()->count());

        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($endUser)
            ->withHeaders(['X-Authzio-Organization' => $organization->id])
            ->withSession([
                'authzio_pending_mfa' => true,
                'authzio_authorize_query' => $query,
                'authzio_organization_id' => $organization->id,
            ])
            ->post('/oauth/mfa', [
                ...$query,
                'code' => $this->currentTotp($secret),
            ])
            ->assertRedirect();

        $this->assertSame(1, OAuthAuthCode::query()->count());
    }

    #[Test]
    public function app_mfa_required_blocks_users_without_authenticator(): void
    {
        [, $organization] = $this->createOwnerWithOrganization();
        $endUser = User::factory()->create([
            'mfa_enabled' => false,
            'password' => Hash::make('SecurePass123!'),
        ]);

        $client = OAuthClient::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Strict App',
            'application_type' => ApplicationType::Spa,
            'redirect_uris' => ['https://app.example.com/callback'],
            'grant_types' => ['authorization_code'],
            'is_confidential' => false,
            'security_policy' => [
                'mfa_required' => true,
                'session_lifetime_minutes' => 120,
                'single_device' => false,
            ],
            'login_methods' => [
                'password' => true,
                'require_verified_email' => false,
            ],
        ]);

        $pkceChallenge = rtrim(strtr(base64_encode(hash('sha256', 'verifier-bbbbbbbb', true)), '+/', '-_'), '=');
        $query = [
            'client_id' => $client->id,
            'redirect_uri' => 'https://app.example.com/callback',
            'response_type' => 'code',
            'scope' => 'openid',
            'code_challenge' => $pkceChallenge,
            'code_challenge_method' => 'S256',
            'state' => 'need-mfa',
        ];

        $location = (string) $this->actingAs($endUser)
            ->withHeaders(['X-Authzio-Organization' => $organization->id])
            ->get('/oauth/authorize?'.http_build_query($query))
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith('https://app.example.com/callback', $location);
        $this->assertStringContainsString('error=access_denied', $location);
        $this->assertStringContainsString('authenticator', urldecode($location));
        $this->assertGuest();
    }

    #[Test]
    public function regenerating_recovery_codes_replaces_previous_set(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $user = User::factory()->create([
            'mfa_enabled' => true,
            'mfa_secret' => $secret,
            'mfa_confirmed_at' => now(),
        ]);
        $this->seedRecoveryCode($user, 'OLDCODE1');

        $response = $this->actingAs($user)
            ->postJson('/api/v1/auth/mfa/recovery-codes', [
                'code' => $this->currentTotp($secret),
            ])
            ->assertOk();

        $codes = $response->json('recovery_codes');
        $this->assertIsArray($codes);
        $this->assertCount(10, $codes);
        $this->assertSame(10, MfaRecoveryCode::query()->where('user_id', $user->id)->whereNull('used_at')->count());

        $this->assertFalse(app(MfaService::class)->verify($user->fresh(), 'OLDCODE1'));
    }
}
