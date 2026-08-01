<?php

namespace App\Http\Controllers\Api;

use App\Enums\SupportedLocale;
use App\Http\Controllers\Concerns\EnsuresOrganizationMembership;
use App\Http\Controllers\Controller;
use App\Models\OAuthClient;
use App\Services\Authorization\PermissionChecker;
use App\Services\Billing\PlanEntitlements;
use App\Services\Demo\DemoOverlay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    use EnsuresOrganizationMembership;

    public function __construct(
        private readonly PlanEntitlements $entitlements,
        private readonly PermissionChecker $permissions,
        private readonly DemoOverlay $demoOverlay,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $organizations = $user->organizations()
            ->with(['roles' => fn ($query) => $query->select('id', 'organization_id', 'name', 'slug', 'is_owner', 'is_system')])
            ->withPivot(['role_id', 'status', 'joined_at'])
            ->orderBy('name')
            ->get();

        $organization = null;
        $requestedId = $request->string('organization_id')->toString();

        if ($requestedId !== '') {
            $organization = $organizations->firstWhere('id', $requestedId);
            // Stale localStorage (e.g. demo org from a previous session) must not 403.
            if ($organization === null) {
                $organization = $organizations->first();
            }
        } else {
            $organization = $organizations->first();
        }

        $applications = [];
        $entitlements = null;
        $application = null;
        $permissionSlugs = [];

        if ($organization !== null) {
            $applications = OAuthClient::query()
                ->where('organization_id', $organization->id)
                ->whereNull('revoked_at')
                ->orderBy('name')
                ->get()
                ->makeHidden(['secret'])
                ->map(function (OAuthClient $client) use ($request) {
                    /** @var array<string, mixed> $base */
                    $base = $client->toArray();

                    return $this->demoOverlay->merge(
                        $request,
                        $this->demoOverlay->applicationKey($client->id),
                        $base,
                    );
                })
                ->values();

            $entitlements = $this->entitlements->forOrganization($organization, $user);
            $permissionSlugs = $this->permissions->permissionSlugs($user, $organization);

            $applicationId = $request->string('application_id')->toString();
            if ($applicationId !== '') {
                $application = $applications->firstWhere('id', $applicationId);
            }
        }

        return response()->json([
            'organizations' => $organizations,
            'organization' => $organization,
            'applications' => $applications,
            'application' => $application,
            'entitlements' => $entitlements,
            'permissions' => $permissionSlugs,
            'locales' => SupportedLocale::values(),
            'domain_root' => (string) config('authzio.domains.root', 'authzio.test'),
            'user_preferences' => [
                'preferred_locale' => $user->preferred_locale ?? 'en',
                'theme' => $user->theme ?? 'system',
            ],
        ]);
    }
}
