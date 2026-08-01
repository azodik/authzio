<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrgPermission;
use App\Http\Controllers\Concerns\AuthorizesOrganization;
use App\Http\Controllers\Concerns\EnsuresOrganizationMembership;
use App\Http\Controllers\Concerns\HandlesDemoSoftMutations;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationSsoConnection;
use App\Services\Auth\SsoIdentityService;
use App\Services\Billing\PlanEntitlements;
use App\Services\Demo\DemoCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SsoConnectionController extends Controller
{
    use AuthorizesOrganization;
    use EnsuresOrganizationMembership;
    use HandlesDemoSoftMutations;

    public function __construct(
        private readonly PlanEntitlements $entitlements,
        private readonly SsoIdentityService $sso,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organization = $this->resolveOrganization($request);
        $this->authorizeOrg($request, $organization, OrgPermission::SsoRead);

        $connections = OrganizationSsoConnection::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->get()
            ->map(fn (OrganizationSsoConnection $connection): array => $this->serialize($connection));

        return response()->json([
            'organization' => $organization->only(['id', 'name', 'slug']),
            'entitlements' => $this->entitlements->forOrganization($organization),
            'data' => $connections,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // demo-soft:DemoCapability::SsoMutate
        if ($response = $this->demoSoftAck($request, DemoCapability::SsoMutate)) {
            return $response;
        }

        $validated = $request->validate([
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:80', 'alpha_dash'],
            'issuer' => ['required', 'url', 'max:500'],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:2000'],
            'scopes' => ['sometimes', 'array'],
            'scopes.*' => ['string', 'max:100'],
            'email_domains' => ['sometimes', 'array'],
            'email_domains.*' => ['string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
            'discover' => ['sometimes', 'boolean'],
        ]);

        $organization = $this->organizationForUser(
            $request,
            Organization::query()->findOrFail($validated['organization_id']),
        );
        $this->authorizeOrg($request, $organization, OrgPermission::SsoWrite);
        $this->entitlements->assertSso($organization);

        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        if ($slug === '') {
            $slug = 'sso';
        }

        $slugTaken = OrganizationSsoConnection::query()
            ->where('organization_id', $organization->id)
            ->where('slug', $slug)
            ->exists();

        if ($slugTaken) {
            $slug = $slug.'-'.Str::lower(Str::random(4));
        }

        $endpoints = [
            'authorization_endpoint' => null,
            'token_endpoint' => null,
            'userinfo_endpoint' => null,
            'jwks_uri' => null,
            'issuer' => rtrim($validated['issuer'], '/'),
        ];

        if ($validated['discover'] ?? true) {
            $discovered = $this->sso->discover($validated['issuer']);
            $endpoints = [
                'issuer' => $discovered['issuer'],
                'authorization_endpoint' => $discovered['authorization_endpoint'],
                'token_endpoint' => $discovered['token_endpoint'],
                'userinfo_endpoint' => $discovered['userinfo_endpoint'],
                'jwks_uri' => $discovered['jwks_uri'],
            ];
        }

        $connection = OrganizationSsoConnection::query()->create([
            'organization_id' => $organization->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'protocol' => 'oidc',
            'client_id' => $validated['client_id'],
            'client_secret' => $validated['client_secret'],
            'scopes' => $validated['scopes'] ?? ['openid', 'profile', 'email'],
            'email_domains' => $this->normalizeDomains($validated['email_domains'] ?? []),
            'enabled' => $validated['enabled'] ?? true,
            ...$endpoints,
        ]);

        return response()->json(['data' => $this->serialize($connection)], 201);
    }

    public function update(Request $request, Organization $organization, OrganizationSsoConnection $ssoConnection): JsonResponse
    {
        $organization = $this->organizationForUser($request, $organization);
        $this->authorizeOrg($request, $organization, OrgPermission::SsoWrite);
        $this->entitlements->assertSso($organization);
        $this->assertConnectionBelongs($organization, $ssoConnection);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'slug' => ['sometimes', 'string', 'max:80', 'alpha_dash', Rule::unique('organization_sso_connections', 'slug')
                ->where(fn ($query) => $query->where('organization_id', $organization->id))
                ->ignore($ssoConnection->id)],
            'issuer' => ['sometimes', 'url', 'max:500'],
            'client_id' => ['sometimes', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:2000'],
            'scopes' => ['sometimes', 'array'],
            'scopes.*' => ['string', 'max:100'],
            'email_domains' => ['sometimes', 'array'],
            'email_domains.*' => ['string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
            'discover' => ['sometimes', 'boolean'],
        ]);

        $payload = [];

        foreach (['name', 'slug', 'client_id', 'enabled', 'scopes'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (array_key_exists('email_domains', $validated)) {
            $payload['email_domains'] = $this->normalizeDomains($validated['email_domains']);
        }

        if (filled($validated['client_secret'] ?? null)) {
            $payload['client_secret'] = $validated['client_secret'];
        }

        if (isset($validated['issuer'])) {
            $issuer = rtrim($validated['issuer'], '/');
            $payload['issuer'] = $issuer;

            if ($validated['discover'] ?? true) {
                $discovered = $this->sso->discover($issuer);
                $payload['issuer'] = $discovered['issuer'];
                $payload['authorization_endpoint'] = $discovered['authorization_endpoint'];
                $payload['token_endpoint'] = $discovered['token_endpoint'];
                $payload['userinfo_endpoint'] = $discovered['userinfo_endpoint'];
                $payload['jwks_uri'] = $discovered['jwks_uri'];
            }
        }

        $ssoConnection->fill($payload)->save();

        return response()->json(['data' => $this->serialize($ssoConnection->fresh() ?? $ssoConnection)]);
    }

    public function destroy(Request $request, Organization $organization, OrganizationSsoConnection $ssoConnection): JsonResponse
    {
        $organization = $this->organizationForUser($request, $organization);
        $this->authorizeOrg($request, $organization, OrgPermission::SsoWrite);
        $this->assertConnectionBelongs($organization, $ssoConnection);

        $ssoConnection->delete();

        return response()->json(['message' => __('SSO connection deleted.')]);
    }

    public function discover(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'issuer' => ['required', 'url', 'max:500'],
        ]);

        $organization = $this->organizationForUser(
            $request,
            Organization::query()->findOrFail($validated['organization_id']),
        );
        $this->authorizeOrg($request, $organization, OrgPermission::SsoWrite);
        $this->entitlements->assertSso($organization);

        return response()->json(['data' => $this->sso->discover($validated['issuer'])]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(OrganizationSsoConnection $connection): array
    {
        return [
            'id' => $connection->id,
            'name' => $connection->name,
            'slug' => $connection->slug,
            'protocol' => $connection->protocol,
            'issuer' => $connection->issuer,
            'client_id' => $connection->client_id,
            'has_client_secret' => filled($connection->client_secret),
            'authorization_endpoint' => $connection->authorization_endpoint,
            'token_endpoint' => $connection->token_endpoint,
            'userinfo_endpoint' => $connection->userinfo_endpoint,
            'jwks_uri' => $connection->jwks_uri,
            'scopes' => $connection->scopes ?? ['openid', 'profile', 'email'],
            'email_domains' => $connection->normalizedEmailDomains(),
            'enabled' => $connection->enabled,
            'callback_url' => $this->sso->callbackUrl($connection),
        ];
    }

    /**
     * @param  list<string>  $domains
     * @return list<string>
     */
    private function normalizeDomains(array $domains): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $domain): string => strtolower(trim($domain)),
            $domains,
        ))));
    }

    private function assertConnectionBelongs(Organization $organization, OrganizationSsoConnection $connection): void
    {
        if ($connection->organization_id !== $organization->id) {
            abort(404);
        }
    }
}
