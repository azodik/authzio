<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrgPermission;
use App\Enums\PermissionGroup;
use App\Http\Controllers\Concerns\AuthorizesOrganization;
use App\Http\Controllers\Concerns\HandlesDemoSoftMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Authorization\PermissionChecker;
use App\Services\Demo\DemoCapability;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    use AuthorizesOrganization;
    use HandlesDemoSoftMutations;

    public function __construct(
        private readonly OrganizationService $organizationService,
        private readonly PermissionChecker $permissionChecker,
    ) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request, $organization, OrgPermission::RolesRead);
        $this->organizationService->syncPermissionCatalog();

        $roles = $organization->roles()
            ->with(['permissions:id,slug,name,group'])
            ->withCount('members')
            ->orderByDesc('is_owner')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $roles,
            'permissions' => Permission::query()->orderBy('group')->orderBy('name')->get(['id', 'slug', 'name', 'group', 'description']),
            'groups' => PermissionGroup::options(),
        ]);
    }

    public function store(StoreRoleRequest $request, Organization $organization): JsonResponse
    {
        // demo-soft:DemoCapability::RoleMutate
        if ($response = $this->demoSoftAck($request, DemoCapability::RoleMutate)) {
            return $response;
        }

        $this->authorizeOrg($request, $organization, OrgPermission::RolesWrite);

        $slug = Str::slug($request->string('name')->toString());
        if ($slug === '') {
            $slug = 'role';
        }

        if (Role::query()->where('organization_id', $organization->id)->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'name' => [__('A role with this name already exists.')],
            ]);
        }

        $role = Role::create([
            'organization_id' => $organization->id,
            'name' => $request->string('name')->toString(),
            'slug' => $slug,
            'description' => $request->input('description'),
            'is_system' => false,
            'is_owner' => false,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('slug', $request->input('permissions', []))
            ->pluck('id')
            ->all();
        $role->permissions()->sync($permissionIds);

        return response()->json(['data' => $role->load('permissions')], 201);
    }

    public function update(
        UpdateRoleRequest $request,
        Organization $organization,
        Role $role,
    ): JsonResponse {
        $this->authorizeOrg($request, $organization, OrgPermission::RolesWrite);
        abort_unless($role->organization_id === $organization->id, 404);

        if ($role->is_owner) {
            throw ValidationException::withMessages([
                'role' => [__('The owner role cannot be modified.')],
            ]);
        }

        if ($request->filled('name') && ! $role->is_system) {
            $role->name = $request->string('name')->toString();
        }

        if ($request->exists('description')) {
            $role->description = $request->input('description');
        }

        $role->save();

        if ($request->exists('permissions')) {
            $permissionIds = Permission::query()
                ->whereIn('slug', $request->input('permissions', []))
                ->pluck('id')
                ->all();
            $role->permissions()->sync($permissionIds);

            foreach ($role->members as $member) {
                $this->permissionChecker->forget($member->user, $organization);
            }
        }

        return response()->json(['data' => $role->fresh('permissions')]);
    }

    public function destroy(Request $request, Organization $organization, Role $role): JsonResponse
    {
        $this->authorizeOrg($request, $organization, OrgPermission::RolesWrite);
        abort_unless($role->organization_id === $organization->id, 404);

        if ($role->is_system || $role->is_owner) {
            throw ValidationException::withMessages([
                'role' => [__('System roles cannot be deleted.')],
            ]);
        }

        if ($role->members()->exists()) {
            throw ValidationException::withMessages([
                'role' => [__('Reassign members before deleting this role.')],
            ]);
        }

        $role->delete();

        return response()->json(['message' => __('Role deleted.')]);
    }
}
