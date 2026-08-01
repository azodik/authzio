<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Organization;
use Illuminate\Http\Request;

trait EnsuresOrganizationMembership
{
    protected function organizationForUser(Request $request, Organization $organization): Organization
    {
        $isMember = $request->user()
            ->organizationMemberships()
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->exists();

        abort_unless($isMember, 403, __('You are not a member of this organization.'));

        return $organization;
    }

    protected function resolveOrganization(Request $request): Organization
    {
        $routeOrganization = $request->route('organization');
        if ($routeOrganization instanceof Organization) {
            return $this->organizationForUser($request, $routeOrganization);
        }

        $organizationId = $request->string('organization_id')->toString();

        if ($organizationId === '') {
            $organization = $request->user()->organizations()->orderBy('name')->first();
            abort_if($organization === null, 422, __('Create an organization first.'));

            return $organization;
        }

        $organization = Organization::query()->findOrFail($organizationId);

        return $this->organizationForUser($request, $organization);
    }
}
