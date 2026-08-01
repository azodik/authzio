<?php

namespace App\Http\Controllers\Oidc;

use App\Http\Controllers\Controller;
use App\Models\OAuthAccessToken;
use App\Services\Oidc\IssuerResolver;
use App\Services\Oidc\OAuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    public function __construct(
        private readonly IssuerResolver $issuerResolver,
        private readonly OAuthTokenService $tokens,
    ) {}

    public function token(Request $request): JsonResponse
    {
        $organization = $this->issuerResolver->resolveOrganization($request);
        $input = $this->normalizeInput($request);

        try {
            $response = $this->tokens->issue($organization, $input);
        } catch (ValidationException $exception) {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => collect($exception->errors())->flatten()->first(),
            ], 400);
        }

        return response()->json($response);
    }

    public function userinfo(Request $request): JsonResponse
    {
        $organization = $this->issuerResolver->resolveOrganization($request);
        $token = $this->bearerToken($request);

        if ($token === null) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $access = OAuthAccessToken::query()->where('id', $token)->first();

        if ($access === null
            || $access->revoked
            || ($access->expires_at !== null && $access->expires_at->isPast())
            || $access->client?->organization_id !== $organization->id
            || $access->user === null
        ) {
            return response()->json(['error' => 'invalid_token'], 401);
        }

        $user = $access->user;
        $scopes = $access->scopes ?? [];
        $claims = [
            'sub' => $user->uuid,
        ];

        if (in_array('email', $scopes, true)) {
            $claims['email'] = $user->email;
            $claims['email_verified'] = $user->email_verified_at !== null;
        }

        if (in_array('profile', $scopes, true)) {
            $claims['name'] = $user->name;
        }

        return response()->json($claims);
    }

    public function revoke(Request $request): JsonResponse
    {
        $input = $this->normalizeInput($request);
        $this->tokens->revoke(
            isset($input['token']) ? (string) $input['token'] : null,
            isset($input['token_type_hint']) ? (string) $input['token_type_hint'] : null,
        );

        return response()->json(new \stdClass);
    }

    public function introspect(Request $request): JsonResponse
    {
        $organization = $this->issuerResolver->resolveOrganization($request);
        $input = $this->normalizeInput($request);
        $token = (string) ($input['token'] ?? '');

        return response()->json($this->tokens->introspect($organization, $token));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeInput(Request $request): array
    {
        $input = $request->all();

        $auth = $request->header('Authorization');
        if (is_string($auth) && str_starts_with($auth, 'Basic ')) {
            $decoded = base64_decode(substr($auth, 6), true);
            if (is_string($decoded) && str_contains($decoded, ':')) {
                [$id, $secret] = explode(':', $decoded, 2);
                $input['client_id'] ??= $id;
                $input['client_secret'] ??= $secret;
            }
        }

        return $input;
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if (is_string($header) && preg_match('/^Bearer\s+(\S+)$/i', $header, $matches) === 1) {
            return $matches[1];
        }

        $token = $request->query('access_token');

        return is_string($token) && $token !== '' ? $token : null;
    }
}
