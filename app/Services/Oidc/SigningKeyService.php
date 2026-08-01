<?php

namespace App\Services\Oidc;

use App\Models\Organization;
use App\Models\OrganizationSigningKey;
use App\Services\Billing\PlanEntitlements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SigningKeyService
{
    public function __construct(
        private readonly PlanEntitlements $entitlements,
    ) {}

    public function ensureActiveKey(Organization $organization): OrganizationSigningKey
    {
        $active = OrganizationSigningKey::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->first();

        if ($active !== null) {
            return $active;
        }

        return $this->generate($organization, custom: false);
    }

    public function generate(Organization $organization, bool $custom = false): OrganizationSigningKey
    {
        $keypair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($keypair === false) {
            throw new RuntimeException('Unable to generate RSA key pair.');
        }

        openssl_pkey_export($keypair, $privatePem);
        $details = openssl_pkey_get_details($keypair);

        if ($details === false || ! isset($details['rsa'])) {
            throw new RuntimeException('Unable to read RSA key details.');
        }

        /** @var array{n: string, e: string} $rsa */
        $rsa = $details['rsa'];
        $kid = 'authzio_'.Str::lower(Str::random(16));

        return DB::transaction(function () use ($organization, $privatePem, $rsa, $kid, $custom): OrganizationSigningKey {
            OrganizationSigningKey::query()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'rotated_at' => now(),
                ]);

            return OrganizationSigningKey::query()->create([
                'organization_id' => $organization->id,
                'kid' => $kid,
                'alg' => 'RS256',
                'public_jwk' => $this->rsaToJwk($rsa['n'], $rsa['e'], $kid),
                'private_key' => $privatePem,
                'is_active' => true,
                'is_custom' => $custom,
            ]);
        });
    }

    public function importPem(Organization $organization, string $privatePem, ?string $kid = null): OrganizationSigningKey
    {
        $this->entitlements->assertCustomJwks($organization);

        $privatePem = trim($privatePem);
        $resource = openssl_pkey_get_private($privatePem);

        if ($resource === false) {
            throw ValidationException::withMessages([
                'private_key' => ['Invalid RSA private key PEM.'],
            ]);
        }

        $details = openssl_pkey_get_details($resource);
        if ($details === false || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA || ! isset($details['rsa'])) {
            throw ValidationException::withMessages([
                'private_key' => ['Only RSA private keys are supported (RS256).'],
            ]);
        }

        /** @var array{n: string, e: string} $rsa */
        $rsa = $details['rsa'];
        $resolvedKid = $kid !== null && $kid !== ''
            ? $kid
            : 'custom_'.Str::lower(Str::random(12));

        return DB::transaction(function () use ($organization, $privatePem, $rsa, $resolvedKid): OrganizationSigningKey {
            OrganizationSigningKey::query()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'rotated_at' => now(),
                ]);

            return OrganizationSigningKey::query()->create([
                'organization_id' => $organization->id,
                'kid' => $resolvedKid,
                'alg' => 'RS256',
                'public_jwk' => $this->rsaToJwk($rsa['n'], $rsa['e'], $resolvedKid),
                'private_key' => $privatePem,
                'is_active' => true,
                'is_custom' => true,
            ]);
        });
    }

    /**
     * @return array{keys: list<array<string, mixed>>}
     */
    public function jwks(Organization $organization): array
    {
        $this->ensureActiveKey($organization);

        $keys = OrganizationSigningKey::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (OrganizationSigningKey $key) => $key->public_jwk)
            ->values()
            ->all();

        return ['keys' => $keys];
    }

    /**
     * @return list<array{id: string, kid: string, alg: string, is_active: bool, is_custom: bool, created_at: string|null}>
     */
    public function listForConsole(Organization $organization): array
    {
        $this->ensureActiveKey($organization);

        return OrganizationSigningKey::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (OrganizationSigningKey $key) => [
                'id' => $key->id,
                'kid' => $key->kid,
                'alg' => $key->alg,
                'is_active' => $key->is_active,
                'is_custom' => $key->is_custom,
                'created_at' => $key->created_at?->toIso8601String(),
                'public_jwk' => $key->public_jwk,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function rsaToJwk(string $n, string $e, string $kid): array
    {
        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $kid,
            'n' => $this->base64UrlEncode($n),
            'e' => $this->base64UrlEncode($e),
        ];
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
