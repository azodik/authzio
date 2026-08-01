<?php

namespace App\Services\Authorization;

use App\Enums\OrgPermission;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PermissionChecker
{
    public function membership(User $user, Organization $organization): ?OrganizationMember
    {
        return OrganizationMember::query()
            ->with(['role.permissions'])
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
    }

    public function can(User $user, Organization $organization, OrgPermission|string $permission): bool
    {
        $slug = $permission instanceof OrgPermission ? $permission->value : $permission;
        $membership = $this->membership($user, $organization);

        if ($membership === null || $membership->role === null) {
            return false;
        }

        if ($membership->role->is_owner) {
            return true;
        }

        return $membership->role->permissions->contains('slug', $slug);
    }

    /**
     * @return list<string>
     */
    public function permissionSlugs(User $user, Organization $organization): array
    {
        $cacheKey = "org_perms:{$organization->id}:{$user->id}";

        return Cache::remember($cacheKey, 60, function () use ($user, $organization): array {
            $membership = $this->membership($user, $organization);

            if ($membership === null || $membership->role === null) {
                return [];
            }

            if ($membership->role->is_owner) {
                return OrgPermission::allSlugs();
            }

            return $membership->role->permissions->pluck('slug')->values()->all();
        });
    }

    public function forget(User $user, Organization $organization): void
    {
        Cache::forget("org_perms:{$organization->id}:{$user->id}");
    }

    public function authorize(User $user, Organization $organization, OrgPermission|string $permission): void
    {
        abort_unless(
            $this->can($user, $organization, $permission),
            403,
            __('You do not have permission to perform this action.'),
        );
    }
}
