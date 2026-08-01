<?php

namespace App\Services\Oidc;

use App\Enums\UsageEventType;
use App\Models\OAuthAccessToken;
use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use App\Models\OAuthRefreshToken;
use App\Models\Organization;
use App\Models\User;
use App\Services\Billing\UsageTracker;
use App\Services\Users\ApplicationUserTracker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OAuthTokenService
{
    public function __construct(
        private readonly IdTokenIssuer $idTokens,
        private readonly UsageTracker $usageTracker,
        private readonly ApplicationUserTracker $applicationUsers,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function issue(Organization $organization, array $input): array
    {
        $grantType = (string) ($input['grant_type'] ?? '');

        return match ($grantType) {
            'authorization_code' => $this->authorizationCode($organization, $input),
            'refresh_token' => $this->refreshToken($organization, $input),
            'client_credentials' => $this->clientCredentials($organization, $input),
            default => throw ValidationException::withMessages([
                'grant_type' => ['Unsupported grant_type.'],
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function authorizationCode(Organization $organization, array $input): array
    {
        $client = $this->authenticateClient($organization, $input, requireConfidential: false);
        $code = (string) ($input['code'] ?? '');
        $redirectUri = (string) ($input['redirect_uri'] ?? '');
        $codeVerifier = (string) ($input['code_verifier'] ?? '');

        $authCode = OAuthAuthCode::query()->where('id', $code)->first();

        if ($authCode === null
            || $authCode->revoked
            || $authCode->expires_at->isPast()
            || $authCode->client_id !== $client->id
        ) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired authorization code.'],
            ]);
        }

        if ($authCode->redirect_uri !== $redirectUri) {
            throw ValidationException::withMessages([
                'redirect_uri' => ['redirect_uri mismatch.'],
            ]);
        }

        if ($authCode->code_challenge !== null) {
            if ($codeVerifier === '') {
                throw ValidationException::withMessages([
                    'code_verifier' => ['PKCE code_verifier is required.'],
                ]);
            }

            $method = $authCode->code_challenge_method ?: 'S256';
            $computed = $method === 'plain'
                ? $codeVerifier
                : rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

            if (! hash_equals($authCode->code_challenge, $computed)) {
                throw ValidationException::withMessages([
                    'code_verifier' => ['Invalid PKCE code_verifier.'],
                ]);
            }
        }

        $authCode->update(['revoked' => true]);

        /** @var User $user */
        $user = $authCode->user;
        $scopes = $authCode->scopes ?? [];

        return $this->createTokenResponse($organization, $client, $user, $scopes, $authCode->nonce);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function refreshToken(Organization $organization, array $input): array
    {
        $client = $this->authenticateClient($organization, $input, requireConfidential: false);
        $refreshId = (string) ($input['refresh_token'] ?? '');

        $refresh = OAuthRefreshToken::query()->where('id', $refreshId)->first();
        $access = $refresh?->accessToken;

        if ($refresh === null
            || $refresh->revoked
            || ($refresh->expires_at !== null && $refresh->expires_at->isPast())
            || $access === null
            || $access->revoked
            || $access->client_id !== $client->id
        ) {
            throw ValidationException::withMessages([
                'refresh_token' => ['Invalid refresh token.'],
            ]);
        }

        $refresh->update(['revoked' => true]);
        $access->update(['revoked' => true]);

        $user = $access->user;
        abort_if($user === null, 400, 'Refresh token is not bound to a user.');

        return $this->createTokenResponse($organization, $client, $user, $access->scopes ?? []);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function clientCredentials(Organization $organization, array $input): array
    {
        $client = $this->authenticateClient($organization, $input, requireConfidential: true);

        if (! in_array('client_credentials', $client->grant_types ?? [], true)) {
            throw ValidationException::withMessages([
                'grant_type' => ['Client is not allowed to use client_credentials.'],
            ]);
        }

        $scopes = $this->parseScopes((string) ($input['scope'] ?? ''));
        $access = $this->storeAccessToken($client, null, $scopes, name: 'client_credentials');

        return [
            'access_token' => $access->id,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('authzio.oidc.access_token_ttl', 3600),
            'scope' => implode(' ', $scopes),
        ];
    }

    /**
     * @param  list<string>  $scopes
     * @return array<string, mixed>
     */
    private function createTokenResponse(
        Organization $organization,
        OAuthClient $client,
        User $user,
        array $scopes,
        ?string $nonce = null,
    ): array {
        $access = $this->storeAccessToken($client, $user, $scopes, name: 'authorization_code');
        $refresh = null;

        if (in_array('offline_access', $scopes, true) || in_array('refresh_token', $client->grant_types ?? [], true)) {
            $refresh = OAuthRefreshToken::query()->create([
                'id' => Str::random(80),
                'access_token_id' => $access->id,
                'revoked' => false,
                'expires_at' => now()->addSeconds((int) config('authzio.oidc.refresh_token_ttl', 2_592_000)),
            ]);
        }

        $this->usageTracker->record(
            $organization,
            UsageEventType::TokenIssued,
            $user->uuid,
            $user,
            ['client_id' => $client->id],
        );
        $this->usageTracker->recordUserAuthenticated($organization, $user);
        $this->applicationUsers->record($client, $user);

        $response = [
            'access_token' => $access->id,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('authzio.oidc.access_token_ttl', 3600),
            'scope' => implode(' ', $scopes),
        ];

        if ($refresh !== null) {
            $response['refresh_token'] = $refresh->id;
        }

        if (in_array('openid', $scopes, true)) {
            $response['id_token'] = $this->idTokens->issue(
                $organization,
                $user,
                $client->id,
                $scopes,
                $nonce,
                (int) config('authzio.oidc.id_token_ttl', 3600),
            );
        }

        return $response;
    }

    /**
     * @param  list<string>  $scopes
     */
    private function storeAccessToken(
        OAuthClient $client,
        ?User $user,
        array $scopes,
        string $name,
    ): OAuthAccessToken {
        return OAuthAccessToken::query()->create([
            'id' => Str::random(80),
            'user_id' => $user?->id,
            'client_id' => $client->id,
            'name' => $name,
            'scopes' => $scopes,
            'revoked' => false,
            'expires_at' => now()->addSeconds((int) config('authzio.oidc.access_token_ttl', 3600)),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function authenticateClient(
        Organization $organization,
        array $input,
        bool $requireConfidential,
    ): OAuthClient {
        $clientId = (string) ($input['client_id'] ?? '');
        $clientSecret = (string) ($input['client_secret'] ?? '');

        $client = OAuthClient::query()
            ->where('id', $clientId)
            ->where('organization_id', $organization->id)
            ->whereNull('revoked_at')
            ->first();

        if ($client === null) {
            throw ValidationException::withMessages([
                'client_id' => ['Unknown client_id.'],
            ]);
        }

        if ($client->is_confidential || $requireConfidential) {
            if ($client->secret === null || $clientSecret === '' || ! Hash::check($clientSecret, $client->secret)) {
                throw ValidationException::withMessages([
                    'client_secret' => ['Invalid client credentials.'],
                ]);
            }
        }

        return $client;
    }

    /**
     * @return list<string>
     */
    private function parseScopes(string $scope): array
    {
        if (trim($scope) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(preg_split('/\s+/', trim($scope)) ?: [])));
    }

    public function revoke(?string $token, ?string $hint = null): void
    {
        if ($token === null || $token === '') {
            return;
        }

        if ($hint === 'refresh_token' || OAuthRefreshToken::query()->where('id', $token)->exists()) {
            $refresh = OAuthRefreshToken::query()->where('id', $token)->first();
            if ($refresh !== null) {
                $refresh->update(['revoked' => true]);
                $refresh->accessToken?->update(['revoked' => true]);
            }

            return;
        }

        OAuthAccessToken::query()->where('id', $token)->update(['revoked' => true]);
        OAuthRefreshToken::query()->where('access_token_id', $token)->update(['revoked' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    public function introspect(Organization $organization, string $token): array
    {
        $access = OAuthAccessToken::query()->where('id', $token)->first();

        if ($access === null
            || $access->revoked
            || ($access->expires_at !== null && $access->expires_at->isPast())
            || $access->client?->organization_id !== $organization->id
        ) {
            return ['active' => false];
        }

        return [
            'active' => true,
            'scope' => implode(' ', $access->scopes ?? []),
            'client_id' => $access->client_id,
            'token_type' => 'Bearer',
            'exp' => $access->expires_at?->timestamp,
            'iat' => $access->created_at?->timestamp,
            'sub' => $access->user?->uuid,
            'username' => $access->user?->email,
        ];
    }
}
