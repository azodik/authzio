<?php

namespace App\Services\Oidc;

use App\Models\Organization;
use App\Models\OrganizationSigningKey;
use App\Models\User;
use Firebase\JWT\JWT;
use RuntimeException;

class IdTokenIssuer
{
    public function __construct(
        private readonly SigningKeyService $signingKeys,
        private readonly IssuerResolver $issuerResolver,
    ) {}

    /**
     * @param  list<string>  $scopes
     */
    public function issue(
        Organization $organization,
        User $user,
        string $clientId,
        array $scopes,
        ?string $nonce = null,
        int $ttlSeconds = 3600,
    ): string {
        $key = $this->signingKeys->ensureActiveKey($organization);
        $now = time();

        $payload = [
            'iss' => $this->issuerResolver->issuerUrl($organization),
            'sub' => $user->uuid,
            'aud' => $clientId,
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
            'auth_time' => $now,
        ];

        if ($nonce !== null && $nonce !== '') {
            $payload['nonce'] = $nonce;
        }

        if (in_array('email', $scopes, true)) {
            $payload['email'] = $user->email;
            $payload['email_verified'] = $user->email_verified_at !== null;
        }

        if (in_array('profile', $scopes, true)) {
            $payload['name'] = $user->name;
        }

        return $this->encode($key, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function encode(OrganizationSigningKey $key, array $payload): string
    {
        $privateKey = openssl_pkey_get_private((string) $key->private_key);

        if ($privateKey === false) {
            throw new RuntimeException('Active signing key is invalid.');
        }

        return JWT::encode($payload, $privateKey, 'RS256', $key->kid);
    }
}
