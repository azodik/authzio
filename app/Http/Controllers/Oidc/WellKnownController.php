<?php

namespace App\Http\Controllers\Oidc;

use App\Http\Controllers\Controller;
use App\Services\Oidc\IssuerResolver;
use App\Services\Oidc\SigningKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WellKnownController extends Controller
{
    public function __construct(
        private readonly IssuerResolver $issuerResolver,
        private readonly SigningKeyService $signingKeys,
    ) {}

    public function openidConfiguration(Request $request): JsonResponse
    {
        $organization = $this->issuerResolver->resolveOrganization($request);
        $this->signingKeys->ensureActiveKey($organization);
        $endpoints = $this->issuerResolver->endpoints($organization);

        return response()->json([
            'issuer' => $endpoints['issuer'],
            'authorization_endpoint' => $endpoints['authorization_endpoint'],
            'token_endpoint' => $endpoints['token_endpoint'],
            'userinfo_endpoint' => $endpoints['userinfo_endpoint'],
            'revocation_endpoint' => $endpoints['revocation_endpoint'],
            'introspection_endpoint' => $endpoints['introspection_endpoint'],
            'jwks_uri' => $endpoints['jwks_uri'],
            'end_session_endpoint' => $endpoints['end_session_endpoint'] ?? null,
            'response_types_supported' => ['code'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => config('authzio.oidc.scopes_supported'),
            'prompt_values_supported' => ['none', 'login', 'consent', 'select_account'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'client_secret_basic', 'none'],
            'code_challenge_methods_supported' => ['S256'],
            'grant_types_supported' => ['authorization_code', 'refresh_token', 'client_credentials'],
            'claims_supported' => ['sub', 'iss', 'aud', 'exp', 'iat', 'auth_time', 'nonce', 'email', 'email_verified', 'name'],
        ])->header('Cache-Control', 'public, max-age=300');
    }

    public function jwks(Request $request): JsonResponse
    {
        $organization = $this->issuerResolver->resolveOrganization($request);

        return response()->json(
            $this->signingKeys->jwks($organization),
        )->header('Cache-Control', 'public, max-age=60');
    }
}
