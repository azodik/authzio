<?php

namespace App\Services\Oidc;

use App\Models\OAuthAuthCode;
use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthorizationService
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{client: OAuthClient, scopes: list<string>, redirect_uri: string, state: string|null, nonce: string|null, code_challenge: string|null, code_challenge_method: string|null}
     */
    public function validateAuthorizeRequest(array $params, string $organizationId): array
    {
        $clientId = (string) ($params['client_id'] ?? '');
        $redirectUri = (string) ($params['redirect_uri'] ?? '');
        $responseType = (string) ($params['response_type'] ?? '');
        $scope = (string) ($params['scope'] ?? 'openid');
        $state = isset($params['state']) ? (string) $params['state'] : null;
        $nonce = isset($params['nonce']) ? (string) $params['nonce'] : null;
        $codeChallenge = isset($params['code_challenge']) ? (string) $params['code_challenge'] : null;
        $codeChallengeMethod = isset($params['code_challenge_method']) ? (string) $params['code_challenge_method'] : null;

        if ($responseType !== 'code') {
            throw ValidationException::withMessages([
                'response_type' => ['Only response_type=code is supported.'],
            ]);
        }

        $client = OAuthClient::query()
            ->where('id', $clientId)
            ->where('organization_id', $organizationId)
            ->whereNull('revoked_at')
            ->first();

        if ($client === null) {
            throw ValidationException::withMessages([
                'client_id' => ['Unknown client_id.'],
            ]);
        }

        $allowed = $client->redirect_uris ?? [];
        if ($redirectUri === '' || ! in_array($redirectUri, $allowed, true)) {
            throw ValidationException::withMessages([
                'redirect_uri' => ['redirect_uri is not registered for this client.'],
            ]);
        }

        if (! $client->is_confidential) {
            if ($codeChallenge === null || $codeChallenge === '') {
                throw ValidationException::withMessages([
                    'code_challenge' => ['PKCE code_challenge is required for public clients.'],
                ]);
            }
            if ($codeChallengeMethod !== null && $codeChallengeMethod !== 'S256') {
                throw ValidationException::withMessages([
                    'code_challenge_method' => ['Only S256 is supported.'],
                ]);
            }
            $codeChallengeMethod = 'S256';
        } elseif ($codeChallenge !== null && $codeChallengeMethod !== null && $codeChallengeMethod !== 'S256') {
            throw ValidationException::withMessages([
                'code_challenge_method' => ['Only S256 is supported.'],
            ]);
        }

        $scopes = array_values(array_unique(array_filter(preg_split('/\s+/', trim($scope)) ?: [])));
        if ($scopes === []) {
            $scopes = ['openid'];
        }

        $supported = config('authzio.oidc.scopes_supported', ['openid', 'profile', 'email', 'offline_access']);
        foreach ($scopes as $requested) {
            if (! in_array($requested, $supported, true)) {
                throw ValidationException::withMessages([
                    'scope' => ["Unsupported scope: {$requested}"],
                ]);
            }
        }

        return [
            'client' => $client,
            'scopes' => $scopes,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
        ];
    }

    /**
     * @param  list<string>  $scopes
     */
    public function createAuthorizationCode(
        OAuthClient $client,
        User $user,
        array $scopes,
        string $redirectUri,
        ?string $nonce,
        ?string $codeChallenge,
        ?string $codeChallengeMethod,
    ): OAuthAuthCode {
        return OAuthAuthCode::query()->create([
            'id' => Str::random(64),
            'client_id' => $client->id,
            'user_id' => $user->id,
            'scopes' => $scopes,
            'redirect_uri' => $redirectUri,
            'nonce' => $nonce,
            'revoked' => false,
            'expires_at' => now()->addMinutes((int) config('authzio.oidc.auth_code_ttl_minutes', 10)),
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
        ]);
    }

    public function redirectWithCode(string $redirectUri, string $code, ?string $state): string
    {
        $query = http_build_query(array_filter([
            'code' => $code,
            'state' => $state,
        ], fn ($value) => $value !== null && $value !== ''));

        $separator = str_contains($redirectUri, '?') ? '&' : '?';

        return $redirectUri.$separator.$query;
    }

    public function redirectWithError(string $redirectUri, string $error, string $description, ?string $state): string
    {
        $query = http_build_query(array_filter([
            'error' => $error,
            'error_description' => $description,
            'state' => $state,
        ], fn ($value) => $value !== null && $value !== ''));

        $separator = str_contains($redirectUri, '?') ? '&' : '?';

        return $redirectUri.$separator.$query;
    }
}
