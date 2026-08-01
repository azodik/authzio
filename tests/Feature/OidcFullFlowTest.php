<?php

namespace Tests\Feature;

use App\Enums\ApplicationType;
use App\Models\OAuthAccessToken;
use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use App\Models\OAuthRefreshToken;
use App\Models\Organization;
use App\Models\Passkey;
use App\Models\User;
use App\Services\Auth\PasskeyService;
use Database\Seeders\BillingPlanSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

/**
 * Full OIDC / hosted-login verification with a dedicated end-user.
 */
class OidcFullFlowTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    private const TEST_EMAIL = 'oidc-e2e@authzio.test';

    private const TEST_PASSWORD = 'SecurePass123!';

    private const REDIRECT_URI = 'https://rp.example.com/oidc/callback';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BillingPlanSeeder::class);
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    /**
     * @return array{0: User, 1: Organization, 2: OAuthClient, 3: array{verifier: string, challenge: string}}
     */
    private function createOidcFixture(bool $passkeyEnabled = false): array
    {
        [, $organization] = $this->createOwnerWithOrganization('OIDC E2E Org');

        $user = User::factory()->create([
            'name' => 'OIDC E2E User',
            'email' => self::TEST_EMAIL,
            'password' => Hash::make(self::TEST_PASSWORD),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $client = OAuthClient::query()->create([
            'organization_id' => $organization->id,
            'name' => 'OIDC E2E RP',
            'application_type' => ApplicationType::Spa,
            'redirect_uris' => [self::REDIRECT_URI],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'is_confidential' => false,
            'login_methods' => [
                'password' => true,
                'passkey' => $passkeyEnabled,
                'email_otp' => false,
                'google' => false,
                'github' => false,
                'sync_profile' => true,
                'require_verified_email' => false,
                'allow_unverified_email_with_otp' => false,
            ],
        ]);

        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return [$user, $organization, $client, ['verifier' => $verifier, 'challenge' => $challenge]];
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, string>
     */
    private function orgHeaders(string $organizationId): array
    {
        return ['X-Authzio-Organization' => $organizationId];
    }

    #[Test]
    public function hosted_password_login_completes_full_oidc_lifecycle_for_test_user(): void
    {
        [$user, $organization, $client, $pkce] = $this->createOidcFixture();

        $query = [
            'client_id' => $client->id,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => 'openid profile email offline_access',
            'state' => 'e2e-state',
            'nonce' => 'e2e-nonce',
            'code_challenge' => $pkce['challenge'],
            'code_challenge_method' => 'S256',
        ];

        // Guest sees hosted login.
        $this->withHeaders($this->orgHeaders($organization->id))
            ->get('/oauth/authorize?'.http_build_query($query))
            ->assertOk()
            ->assertSee('OIDC E2E RP', false);

        // Password login → authorization code redirect.
        $location = (string) $this->withHeaders($this->orgHeaders($organization->id))
            ->withSession([
                'authzio_authorize_query' => $query,
                'authzio_organization_id' => $organization->id,
            ])
            ->post('/oauth/authorize', [
                ...$query,
                'mode' => 'password',
                'email' => self::TEST_EMAIL,
                'password' => self::TEST_PASSWORD,
            ])
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith(self::REDIRECT_URI, $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
        $this->assertSame('e2e-state', $params['state'] ?? null);
        $this->assertArrayHasKey('code', $params);
        $this->assertAuthenticatedAs($user);

        // Token endpoint.
        $tokens = $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $client->id,
                'code' => $params['code'],
                'redirect_uri' => self::REDIRECT_URI,
                'code_verifier' => $pkce['verifier'],
            ])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token', 'id_token', 'token_type', 'expires_in', 'scope']);

        $access = $tokens->json('access_token');
        $refresh = $tokens->json('refresh_token');
        $idToken = $tokens->json('id_token');
        $this->assertIsString($access);
        $this->assertIsString($refresh);
        $this->assertIsString($idToken);
        $this->assertSame(2, substr_count($idToken, '.'));

        // Userinfo + introspect.
        $this->withHeaders([
            ...$this->orgHeaders($organization->id),
            'Authorization' => 'Bearer '.$access,
        ])
            ->getJson('/api/oauth/userinfo')
            ->assertOk()
            ->assertJsonPath('sub', $user->uuid)
            ->assertJsonPath('email', self::TEST_EMAIL)
            ->assertJsonPath('name', 'OIDC E2E User');

        $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/introspect', ['token' => $access])
            ->assertOk()
            ->assertJsonPath('active', true);

        // Refresh rotation.
        $rotated = $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $client->id,
                'refresh_token' => $refresh,
            ])
            ->assertOk();

        $newAccess = $rotated->json('access_token');
        $this->assertIsString($newAccess);
        $this->assertNotSame($access, $newAccess);
        $this->assertTrue((bool) OAuthAccessToken::query()->where('id', $access)->value('revoked'));
        $this->assertTrue((bool) OAuthRefreshToken::query()->where('id', $refresh)->value('revoked'));

        // Revoke + userinfo fails.
        $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/revoke', [
                'token' => $newAccess,
                'token_type_hint' => 'access_token',
            ])
            ->assertOk();

        $this->withHeaders([
            ...$this->orgHeaders($organization->id),
            'Authorization' => 'Bearer '.$newAccess,
        ])
            ->getJson('/api/oauth/userinfo')
            ->assertStatus(401);
    }

    #[Test]
    public function passkey_authenticate_rejects_expired_challenge_and_unknown_credential(): void
    {
        $user = User::factory()->create(['email' => self::TEST_EMAIL]);
        Passkey::query()->create([
            'user_id' => $user->id,
            'name' => 'Test Key',
            'credential_id' => 'cred-known',
            'public_key' => '-----BEGIN PUBLIC KEY-----\nMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE\n-----END PUBLIC KEY-----',
            'sign_count' => 0,
        ]);

        $service = app(PasskeyService::class);

        try {
            $service->authenticate([
                'id' => 'cred-known',
                'rawId' => 'cred-known',
                'type' => 'public-key',
                'response' => [
                    'clientDataJSON' => base64_encode('{}'),
                    'authenticatorData' => base64_encode('adata'),
                    'signature' => base64_encode('sig'),
                ],
            ], 'localhost');
            $this->fail('Expected validation for missing challenge');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('passkey', $exception->errors());
            $this->assertStringContainsString('challenge', strtolower($exception->errors()['passkey'][0] ?? ''));
        }

        session(['webauthn_login_challenge' => 'challenge-value']);

        try {
            $service->authenticate([
                'id' => 'cred-unknown',
                'rawId' => 'cred-unknown',
                'type' => 'public-key',
                'response' => [
                    'clientDataJSON' => base64_encode('{}'),
                    'authenticatorData' => base64_encode('adata'),
                    'signature' => base64_encode('sig'),
                ],
            ], 'localhost');
            $this->fail('Expected validation for unknown passkey');
        } catch (ValidationException $exception) {
            $this->assertSame(['Unknown passkey.'], $exception->errors()['passkey'] ?? null);
        }
    }

    #[Test]
    public function passkey_verify_endpoint_issues_authorization_code_after_successful_assert(): void
    {
        [$user, $organization, $client, $pkce] = $this->createOidcFixture(passkeyEnabled: true);

        $query = [
            'client_id' => $client->id,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => 'openid profile email offline_access',
            'state' => 'passkey-state',
            'code_challenge' => $pkce['challenge'],
            'code_challenge_method' => 'S256',
        ];

        // Cryptographic WebAuthn assertion is device-bound; assert the hosted verify path
        // completes OIDC once PasskeyService accepts a credential.
        $this->mock(PasskeyService::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('authenticate')
                ->once()
                ->andReturnUsing(function () use ($user): User {
                    Auth::login($user);

                    return $user;
                });
        });

        $location = (string) $this->withHeaders([
            ...$this->orgHeaders($organization->id),
            'Accept' => 'text/html',
        ])
            ->withSession([
                'authzio_authorize_query' => $query,
                'authzio_organization_id' => $organization->id,
                'webauthn_login_challenge' => 'test-challenge',
            ])
            ->post('/oauth/passkey/verify', [
                'id' => 'credential-id',
                'rawId' => 'credential-id',
                'type' => 'public-key',
                'response' => [
                    'clientDataJSON' => 'eyJ0eXBlIjoid2ViYXV0aG4uZ2V0In0',
                    'authenticatorData' => 'YQ',
                    'signature' => 'YQ',
                ],
            ])
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith(self::REDIRECT_URI, $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
        $this->assertSame('passkey-state', $params['state'] ?? null);
        $this->assertArrayHasKey('code', $params);
        $this->assertSame(1, OAuthAuthCode::query()->count());

        $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $client->id,
                'code' => $params['code'],
                'redirect_uri' => self::REDIRECT_URI,
                'code_verifier' => $pkce['verifier'],
            ])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'id_token']);
    }

    #[Test]
    public function discovery_document_matches_token_and_authorize_endpoints(): void
    {
        [, $organization] = $this->createOwnerWithOrganization();

        $discovery = $this->withHeaders($this->orgHeaders($organization->id))
            ->getJson('/.well-known/openid-configuration')
            ->assertOk()
            ->json();

        $this->assertIsArray($discovery);
        $this->assertStringContainsString('/oauth/authorize', (string) ($discovery['authorization_endpoint'] ?? ''));
        $this->assertStringContainsString('/api/oauth/token', (string) ($discovery['token_endpoint'] ?? ''));
        $this->assertStringContainsString('/api/oauth/userinfo', (string) ($discovery['userinfo_endpoint'] ?? ''));
        $this->assertContains('S256', $discovery['code_challenge_methods_supported'] ?? []);
        $this->assertContains('authorization_code', $discovery['grant_types_supported'] ?? []);
    }
}
