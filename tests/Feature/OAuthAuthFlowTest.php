<?php

namespace Tests\Feature;

use App\Enums\ApplicationType;
use App\Models\OAuthAccessToken;
use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use App\Models\OAuthRefreshToken;
use App\Models\Organization;
use App\Models\User;
use App\Services\Oidc\AuthorizationService;
use Database\Seeders\BillingPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class OAuthAuthFlowTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BillingPlanSeeder::class);
    }

    /**
     * @return array{0: User, 1: Organization, 2: OAuthClient, 3: string}
     */
    private function createPublicSpaClient(): array
    {
        [$owner, $organization] = $this->createOwnerWithOrganization();
        unset($owner);

        $client = OAuthClient::query()->create([
            'organization_id' => $organization->id,
            'name' => 'SPA App',
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

        return [$this->createEndUser(), $organization, $client, 'https://app.example.com/callback'];
    }

    /**
     * @return array{0: User, 1: Organization, 2: OAuthClient, 3: string}
     */
    private function createConfidentialWebClient(): array
    {
        [$owner, $organization] = $this->createOwnerWithOrganization();
        unset($owner);

        $plainSecret = 'test-client-secret-'.Str::random(16);
        $client = OAuthClient::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Web App',
            'application_type' => ApplicationType::Web,
            'secret' => Hash::make($plainSecret),
            'redirect_uris' => ['https://web.example.com/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'is_confidential' => true,
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

        return [$this->createEndUser(), $organization, $client, $plainSecret];
    }

    private function createEndUser(): User
    {
        return User::factory()->create([
            'email' => 'enduser@example.com',
            'password' => Hash::make('SecurePass123!'),
        ]);
    }

    /**
     * @return array{verifier: string, challenge: string}
     */
    private function pkcePair(): array
    {
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return ['verifier' => $verifier, 'challenge' => $challenge];
    }

    /**
     * @param  array<string, string|null>  $query
     * @return array<string, string>
     */
    private function orgHeaders(string $organizationId): array
    {
        return ['X-Authzio-Organization' => $organizationId];
    }

    #[Test]
    public function discovery_and_jwks_are_available(): void
    {
        [, $organization] = $this->createOwnerWithOrganization();

        $this->withHeaders($this->orgHeaders($organization->id))
            ->getJson('/.well-known/openid-configuration')
            ->assertOk()
            ->assertJsonPath('response_types_supported.0', 'code')
            ->assertJsonPath('code_challenge_methods_supported.0', 'S256')
            ->assertJsonStructure([
                'issuer',
                'authorization_endpoint',
                'token_endpoint',
                'userinfo_endpoint',
                'revocation_endpoint',
                'introspection_endpoint',
                'jwks_uri',
                'grant_types_supported',
            ]);

        $this->withHeaders($this->orgHeaders($organization->id))
            ->getJson('/.well-known/jwks.json')
            ->assertOk()
            ->assertJsonStructure(['keys']);
    }

    #[Test]
    public function authorize_code_pkce_token_refresh_revoke_userinfo_flow(): void
    {
        [$user, $organization, $client, $redirectUri] = $this->createPublicSpaClient();
        $pkce = $this->pkcePair();

        $authorize = $this->actingAs($user)
            ->withHeaders($this->orgHeaders($organization->id))
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $client->id,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'openid profile email offline_access',
                'state' => 'xyz123',
                'nonce' => 'nonce-1',
                'code_challenge' => $pkce['challenge'],
                'code_challenge_method' => 'S256',
            ]));

        $authorize->assertRedirect();
        $location = $authorize->headers->get('Location');
        $this->assertIsString($location);
        $this->assertStringStartsWith($redirectUri, $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
        $this->assertSame('xyz123', $params['state'] ?? null);
        $this->assertArrayHasKey('code', $params);

        $tokenResponse = $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $client->id,
                'code' => $params['code'],
                'redirect_uri' => $redirectUri,
                'code_verifier' => $pkce['verifier'],
            ])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'refresh_token', 'id_token', 'scope']);

        $accessToken = $tokenResponse->json('access_token');
        $refreshToken = $tokenResponse->json('refresh_token');
        $this->assertIsString($accessToken);
        $this->assertIsString($refreshToken);

        $this->withHeaders([
            ...$this->orgHeaders($organization->id),
            'Authorization' => 'Bearer '.$accessToken,
        ])
            ->getJson('/api/oauth/userinfo')
            ->assertOk()
            ->assertJsonPath('sub', $user->uuid)
            ->assertJsonPath('email', $user->email)
            ->assertJsonPath('name', $user->name);

        $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/introspect', ['token' => $accessToken])
            ->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('sub', $user->uuid);

        $refreshed = $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $client->id,
                'refresh_token' => $refreshToken,
            ])
            ->assertOk();

        $newAccess = $refreshed->json('access_token');
        $newRefresh = $refreshed->json('refresh_token');
        $this->assertIsString($newAccess);
        $this->assertIsString($newRefresh);
        $this->assertNotSame($accessToken, $newAccess);
        $this->assertNotSame($refreshToken, $newRefresh);

        $this->assertTrue((bool) OAuthAccessToken::query()->where('id', $accessToken)->value('revoked'));
        $this->assertTrue((bool) OAuthRefreshToken::query()->where('id', $refreshToken)->value('revoked'));

        $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $client->id,
                'refresh_token' => $refreshToken,
            ])
            ->assertStatus(400);

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

        $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/introspect', ['token' => $newAccess])
            ->assertOk()
            ->assertJsonPath('active', false);
    }

    #[Test]
    public function public_client_requires_pkce_on_authorize(): void
    {
        [$user, $organization, $client, $redirectUri] = $this->createPublicSpaClient();

        $this->actingAs($user)
            ->withHeaders($this->orgHeaders($organization->id))
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $client->id,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'openid',
            ]))
            ->assertRedirect();

        $location = (string) $this->actingAs($user)
            ->withHeaders($this->orgHeaders($organization->id))
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $client->id,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'openid',
            ]))
            ->headers->get('Location');

        $this->assertStringContainsString('error=invalid_request', $location);
        $this->assertStringContainsString('code_challenge', urldecode($location));
    }

    #[Test]
    public function unregistered_redirect_uri_is_rejected_without_open_redirect(): void
    {
        [$user, $organization, $client] = $this->createPublicSpaClient();
        $pkce = $this->pkcePair();

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)
                ->withHeaders($this->orgHeaders($organization->id))
                ->get('/oauth/authorize?'.http_build_query([
                    'client_id' => $client->id,
                    'redirect_uri' => 'https://evil.example.com/steal',
                    'response_type' => 'code',
                    'scope' => 'openid',
                    'code_challenge' => $pkce['challenge'],
                    'code_challenge_method' => 'S256',
                ]));
            $this->fail('Expected ValidationException for unregistered redirect_uri');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('redirect_uri', $exception->errors());
        }
    }

    #[Test]
    public function authorization_code_cannot_be_reused(): void
    {
        [$user, $organization, $client, $redirectUri] = $this->createPublicSpaClient();
        $pkce = $this->pkcePair();

        $code = app(AuthorizationService::class)->createAuthorizationCode(
            $client,
            $user,
            ['openid'],
            $redirectUri,
            null,
            $pkce['challenge'],
            'S256',
        );

        $payload = [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'code' => $code->id,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $pkce['verifier'],
        ];

        $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/token', $payload)
            ->assertOk();

        $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/token', $payload)
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_request');

        $this->assertTrue((bool) OAuthAuthCode::query()->where('id', $code->id)->value('revoked'));
    }

    #[Test]
    public function redirect_uri_mismatch_on_token_exchange_fails(): void
    {
        [$user, $organization, $client, $redirectUri] = $this->createPublicSpaClient();
        $pkce = $this->pkcePair();

        $code = app(AuthorizationService::class)->createAuthorizationCode(
            $client,
            $user,
            ['openid'],
            $redirectUri,
            null,
            $pkce['challenge'],
            'S256',
        );

        $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $client->id,
                'code' => $code->id,
                'redirect_uri' => 'https://other.example.com/callback',
                'code_verifier' => $pkce['verifier'],
            ])
            ->assertStatus(400)
            ->assertJsonFragment(['error_description' => 'redirect_uri mismatch.']);
    }

    #[Test]
    public function invalid_pkce_verifier_is_rejected(): void
    {
        [$user, $organization, $client, $redirectUri] = $this->createPublicSpaClient();
        $pkce = $this->pkcePair();

        $code = app(AuthorizationService::class)->createAuthorizationCode(
            $client,
            $user,
            ['openid'],
            $redirectUri,
            null,
            $pkce['challenge'],
            'S256',
        );

        $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $client->id,
                'code' => $code->id,
                'redirect_uri' => $redirectUri,
                'code_verifier' => 'wrong-verifier-value-that-does-not-match',
            ])
            ->assertStatus(400)
            ->assertJsonFragment(['error_description' => 'Invalid PKCE code_verifier.']);
    }

    #[Test]
    public function confidential_client_requires_valid_secret(): void
    {
        [$user, $organization, $client, $plainSecret] = $this->createConfidentialWebClient();
        $redirectUri = 'https://web.example.com/callback';

        $code = app(AuthorizationService::class)->createAuthorizationCode(
            $client,
            $user,
            ['openid'],
            $redirectUri,
            null,
            null,
            null,
        );

        $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $client->id,
                'client_secret' => 'wrong-secret',
                'code' => $code->id,
                'redirect_uri' => $redirectUri,
            ])
            ->assertStatus(400)
            ->assertJsonFragment(['error_description' => 'Invalid client credentials.']);

        $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $client->id,
                'client_secret' => $plainSecret,
                'code' => $code->id,
                'redirect_uri' => $redirectUri,
            ])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'id_token']);
    }

    #[Test]
    public function client_credentials_grant_works_for_machine_clients(): void
    {
        [, $organization] = $this->createOwnerWithOrganization();
        $plainSecret = 'm2m-secret-'.Str::random(16);

        $client = OAuthClient::query()->create([
            'organization_id' => $organization->id,
            'name' => 'M2M',
            'application_type' => ApplicationType::Machine,
            'secret' => Hash::make($plainSecret),
            'redirect_uris' => [],
            'grant_types' => ['client_credentials'],
            'is_confidential' => true,
        ]);

        $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $client->id,
                'client_secret' => $plainSecret,
                'scope' => '',
            ])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in'])
            ->assertJsonMissing(['refresh_token', 'id_token']);

        $spa = OAuthClient::query()->create([
            'organization_id' => $organization->id,
            'name' => 'SPA no m2m',
            'application_type' => ApplicationType::Spa,
            'redirect_uris' => ['https://app.example.com/cb'],
            'grant_types' => ['authorization_code'],
            'is_confidential' => false,
        ]);

        $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $spa->id,
            ])
            ->assertStatus(400);
    }

    #[Test]
    public function introspect_returns_inactive_for_other_organization_token(): void
    {
        [$userA, $orgA, $clientA, $redirectA] = $this->createPublicSpaClient();
        [, $orgB] = $this->createOwnerWithOrganization('Other Org');
        $pkce = $this->pkcePair();

        $code = app(AuthorizationService::class)->createAuthorizationCode(
            $clientA,
            $userA,
            ['openid'],
            $redirectA,
            null,
            $pkce['challenge'],
            'S256',
        );

        $access = $this->withHeaders($this->orgHeaders($orgA->id))
            ->postJson('/api/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $clientA->id,
                'code' => $code->id,
                'redirect_uri' => $redirectA,
                'code_verifier' => $pkce['verifier'],
            ])
            ->assertOk()
            ->json('access_token');

        $this->withHeaders($this->orgHeaders($orgB->id))
            ->postJson('/api/oauth/introspect', ['token' => $access])
            ->assertOk()
            ->assertJsonPath('active', false);
    }

    #[Test]
    public function expired_authorization_code_is_rejected(): void
    {
        [$user, $organization, $client, $redirectUri] = $this->createPublicSpaClient();
        $pkce = $this->pkcePair();

        $code = app(AuthorizationService::class)->createAuthorizationCode(
            $client,
            $user,
            ['openid'],
            $redirectUri,
            null,
            $pkce['challenge'],
            'S256',
        );
        $code->update(['expires_at' => now()->subMinute()]);

        $this->withHeaders($this->orgHeaders($organization->id))
            ->postJson('/api/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $client->id,
                'code' => $code->id,
                'redirect_uri' => $redirectUri,
                'code_verifier' => $pkce['verifier'],
            ])
            ->assertStatus(400)
            ->assertJsonFragment(['error_description' => 'Invalid or expired authorization code.']);
    }
}
