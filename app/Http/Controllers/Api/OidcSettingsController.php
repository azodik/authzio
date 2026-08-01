<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\EnsuresOrganizationMembership;
use App\Http\Controllers\Concerns\HandlesDemoSoftMutations;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Billing\PlanEntitlements;
use App\Services\Demo\DemoCapability;
use App\Services\Oidc\IssuerResolver;
use App\Services\Oidc\SigningKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OidcSettingsController extends Controller
{
    use EnsuresOrganizationMembership;
    use HandlesDemoSoftMutations;

    public function __construct(
        private readonly SigningKeyService $signingKeys,
        private readonly IssuerResolver $issuerResolver,
        private readonly PlanEntitlements $entitlements,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $organization = $this->resolveOrganization($request);
        $endpoints = $this->issuerResolver->endpoints($organization);

        return response()->json([
            'organization' => $organization->only(['id', 'name', 'slug', 'subdomain', 'primary_domain']),
            'entitlements' => $this->entitlements->forOrganization($organization),
            'issuer' => $endpoints['issuer'],
            'endpoints' => $endpoints,
            'discovery_url' => $endpoints['issuer'].'/.well-known/openid-configuration',
            'keys' => $this->signingKeys->listForConsole($organization),
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        // demo-soft:DemoCapability::OidcKeys
        if ($response = $this->demoSoftAck($request, DemoCapability::OidcKeys)) {
            return $response;
        }

        $validated = $request->validate([
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
        ]);

        $organization = $this->organizationForUser(
            $request,
            Organization::query()->findOrFail($validated['organization_id']),
        );

        $key = $this->signingKeys->generate($organization, custom: false);

        return response()->json([
            'message' => 'New RS256 signing key activated.',
            'key' => [
                'id' => $key->id,
                'kid' => $key->kid,
                'alg' => $key->alg,
                'is_active' => $key->is_active,
                'is_custom' => $key->is_custom,
                'public_jwk' => $key->public_jwk,
            ],
            'keys' => $this->signingKeys->listForConsole($organization),
        ], 201);
    }

    public function import(Request $request): JsonResponse
    {
        if ($response = $this->demoSoftAck($request, DemoCapability::OidcKeys, [
            'message' => 'Saved for this demo session.',
            'keys' => [],
        ])) {
            return $response;
        }

        $validated = $request->validate([
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'private_key' => ['required', 'string'],
            'kid' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/'],
        ]);

        $organization = $this->organizationForUser(
            $request,
            Organization::query()->findOrFail($validated['organization_id']),
        );

        $key = $this->signingKeys->importPem(
            $organization,
            $validated['private_key'],
            $validated['kid'] ?? null,
        );

        return response()->json([
            'message' => 'Custom signing key imported and activated.',
            'key' => [
                'id' => $key->id,
                'kid' => $key->kid,
                'alg' => $key->alg,
                'is_active' => $key->is_active,
                'is_custom' => $key->is_custom,
                'public_jwk' => $key->public_jwk,
            ],
            'keys' => $this->signingKeys->listForConsole($organization),
        ], 201);
    }
}
