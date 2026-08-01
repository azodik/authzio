<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\OrgPermission;
use App\Models\Organization;
use App\Services\Authorization\PermissionChecker;
use Illuminate\Http\Request;

trait AuthorizesOrganization
{
    use EnsuresOrganizationMembership;

    protected function permissionChecker(): PermissionChecker
    {
        return app(PermissionChecker::class);
    }

    protected function authorizeOrg(
        Request $request,
        Organization $organization,
        OrgPermission|string $permission,
    ): Organization {
        $this->organizationForUser($request, $organization);
        $this->permissionChecker()->authorize($request->user(), $organization, $permission);

        return $organization;
    }

    protected function resolveOrganizationWithPermission(
        Request $request,
        OrgPermission|string $permission,
    ): Organization {
        $organization = $this->resolveOrganization($request);
        $this->permissionChecker()->authorize($request->user(), $organization, $permission);

        return $organization;
    }
}
