<?php

namespace App\Http\Controllers\Api;

use App\Enums\SocialProvider;
use App\Http\Controllers\Concerns\EnsuresOrganizationMembership;
use App\Http\Controllers\Concerns\HandlesDemoSoftMutations;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationSocialProvider;
use App\Services\Auth\SocialIdentityService;
use App\Services\Billing\PlanEntitlements;
use App\Services\Demo\DemoCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SocialProviderController extends Controller
{
    use EnsuresOrganizationMembership;
    use HandlesDemoSoftMutations;

    public function __construct(
        private readonly PlanEntitlements $entitlements,
        private readonly SocialIdentityService $social,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organization = $this->resolveOrganization($request);

        $providers = OrganizationSocialProvider::query()
            ->where('organization_id', $organization->id)
            ->get()
            ->keyBy(fn (OrganizationSocialProvider $provider) => $provider->provider->value);

        $catalog = collect(SocialProvider::cases())->map(function (SocialProvider $provider) use ($providers, $organization) {
            $configured = $providers->get($provider->value);

            return [
                'provider' => $provider->value,
                'label' => $provider->label(),
                'description' => $provider->description(),
                'configured' => $configured !== null,
                'enabled' => $configured?->enabled ?? false,
                'client_id' => $configured?->client_id,
                'has_client_secret' => $configured !== null && filled($configured->client_secret),
                'scopes' => $configured?->scopes ?? $provider->defaultScopes(),
                'callback_url' => $this->social->callbackUrl($organization, $provider),
            ];
        });

        return response()->json([
            'organization' => $organization->only(['id', 'name', 'slug']),
            'entitlements' => $this->entitlements->forOrganization($organization),
            'data' => $catalog,
        ]);
    }

    public function upsert(Request $request): JsonResponse
    {
        // demo-soft:DemoCapability::SocialProviderMutate
        if ($response = $this->demoSoftAck($request, DemoCapability::SocialProviderMutate)) {
            return $response;
        }

        $validated = $request->validate([
            'organization_id' => ['required', 'uuid', 'exists:organizations,id'],
            'provider' => ['required', 'string', Rule::enum(SocialProvider::class)],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:2000'],
            'enabled' => ['sometimes', 'boolean'],
            'scopes' => ['sometimes', 'array'],
            'scopes.*' => ['string', 'max:100'],
        ]);

        $organization = $this->organizationForUser(
            $request,
            Organization::query()->findOrFail($validated['organization_id']),
        );

        $provider = SocialProvider::from($validated['provider']);
        $existing = OrganizationSocialProvider::query()
            ->where('organization_id', $organization->id)
            ->where('provider', $provider->value)
            ->first();

        if ($existing === null && blank($validated['client_secret'] ?? null)) {
            return response()->json([
                'message' => 'Client secret is required when configuring a provider for the first time.',
                'errors' => ['client_secret' => ['Client secret is required.']],
            ], 422);
        }

        $payload = [
            'client_id' => $validated['client_id'],
            'enabled' => $validated['enabled'] ?? true,
            'scopes' => $validated['scopes'] ?? $provider->defaultScopes(),
        ];

        if (filled($validated['client_secret'] ?? null)) {
            $payload['client_secret'] = $validated['client_secret'];
        }

        $record = OrganizationSocialProvider::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'provider' => $provider->value,
            ],
            $payload,
        );

        return response()->json([
            'data' => [
                'provider' => $record->provider->value,
                'label' => $record->provider->label(),
                'configured' => true,
                'enabled' => $record->enabled,
                'client_id' => $record->client_id,
                'has_client_secret' => true,
                'scopes' => $record->scopes,
                'callback_url' => $this->social->callbackUrl($organization, $record->provider),
            ],
        ]);
    }
}
