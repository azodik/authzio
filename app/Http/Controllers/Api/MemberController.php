<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\OrgPermission;
use App\Http\Controllers\Concerns\AuthorizesOrganization;
use App\Http\Controllers\Concerns\HandlesDemoSoftMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvitationRequest;
use App\Http\Requests\UpdateMemberRoleRequest;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionChecker;
use App\Services\Demo\DemoCapability;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    use AuthorizesOrganization;
    use HandlesDemoSoftMutations;

    public function __construct(
        private readonly OrganizationService $organizationService,
        private readonly AuditLogger $auditLogger,
        private readonly PermissionChecker $permissionChecker,
    ) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request, $organization, OrgPermission::MembersRead);

        $members = $organization->members()
            ->with([
                'user:id,uuid,name,email,email_verified_at,mfa_enabled,last_login_at,is_active',
                'role:id,name,slug,is_owner,is_system',
            ])
            ->orderBy('created_at')
            ->get();

        $invitations = $organization->invitations()
            ->with(['inviter:id,name,email', 'role:id,name,slug'])
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->orderByDesc('created_at')
            ->get();

        $invitationHistory = $organization->invitations()
            ->with(['inviter:id,name,email', 'role:id,name,slug'])
            ->where(function ($query): void {
                $query->whereNotNull('accepted_at')
                    ->orWhereNotNull('revoked_at');
            })
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        return response()->json([
            'organization' => $organization->only(['id', 'name', 'slug', 'subdomain', 'primary_domain']),
            'members' => $members,
            'invitations' => $invitations,
            'invitation_history' => $invitationHistory,
        ]);
    }

    public function invite(StoreInvitationRequest $request, Organization $organization): JsonResponse
    {
        // demo-soft:DemoCapability::MemberInvite
        if ($response = $this->demoSoftAck($request, DemoCapability::MemberInvite)) {
            return $response;
        }

        $this->authorizeOrg($request, $organization, OrgPermission::MembersInvite);

        $role = Role::query()
            ->where('organization_id', $organization->id)
            ->whereKey($request->string('role_id')->toString())
            ->firstOrFail();

        $invitation = $this->organizationService->invite(
            $organization,
            $request->user(),
            $request->string('email')->toString(),
            $role,
        );

        return response()->json(['data' => $invitation], 201);
    }

    public function updateRole(
        UpdateMemberRoleRequest $request,
        Organization $organization,
        OrganizationMember $member,
    ): JsonResponse {
        // demo-soft:DemoCapability::MemberUpdate
        if ($response = $this->demoSoftAck($request, DemoCapability::MemberUpdate)) {
            return $response;
        }

        $this->authorizeOrg($request, $organization, OrgPermission::MembersManageRoles);
        abort_unless($member->organization_id === $organization->id, 404);

        $role = Role::query()
            ->where('organization_id', $organization->id)
            ->whereKey($request->string('role_id')->toString())
            ->firstOrFail();

        if ($member->role?->is_owner || $role->is_owner) {
            return response()->json(['message' => __('Owner role cannot be reassigned this way.')], 422);
        }

        $member->update(['role_id' => $role->id]);
        $this->permissionChecker->forget($member->user, $organization);

        return response()->json(['data' => $member->fresh(['role', 'user'])]);
    }

    public function resendInvitation(
        Request $request,
        Organization $organization,
        OrganizationInvitation $invitation,
    ): JsonResponse {
        if ($response = $this->demoSoftAck($request, DemoCapability::MemberInvite)) {
            return $response;
        }

        $this->authorizeOrg($request, $organization, OrgPermission::MembersInvite);
        abort_unless($invitation->organization_id === $organization->id, 404);

        $invitation = $this->organizationService->resendInvitation($invitation, $request->user());

        return response()->json([
            'message' => __('Invitation resent.'),
            'data' => $invitation->load(['inviter:id,name,email', 'role:id,name,slug']),
        ]);
    }

    public function revokeInvitation(
        Request $request,
        Organization $organization,
        OrganizationInvitation $invitation,
    ): JsonResponse {
        if ($response = $this->demoSoftAck($request, DemoCapability::MemberInvite)) {
            return $response;
        }

        $this->authorizeOrg($request, $organization, OrgPermission::MembersInvite);
        abort_unless($invitation->organization_id === $organization->id, 404);

        if ($invitation->accepted_at !== null) {
            return response()->json(['message' => __('Accepted invitations cannot be revoked.')], 422);
        }

        if ($invitation->revoked_at !== null) {
            return response()->json(['message' => __('Invitation already revoked.')], 422);
        }

        $invitation->update(['revoked_at' => now()]);

        return response()->json(['message' => __('Invitation revoked.')]);
    }

    public function destroy(Request $request, Organization $organization, OrganizationMember $member): JsonResponse
    {
        // demo-soft:DemoCapability::MemberDestroy
        if ($response = $this->demoSoftAck($request, DemoCapability::MemberDestroy)) {
            return $response;
        }

        $this->authorizeOrg($request, $organization, OrgPermission::MembersRemove);
        abort_unless($member->organization_id === $organization->id, 404);

        if ($member->role?->is_owner) {
            return response()->json(['message' => __('Owner cannot be removed.')], 422);
        }

        if ($member->user_id === $request->user()->id) {
            return response()->json(['message' => __('You cannot remove yourself.')], 422);
        }

        $this->auditLogger->log(
            AuditAction::MemberRemoved,
            $request->user(),
            $organization,
            OrganizationMember::class,
            $member->id,
        );

        $this->permissionChecker->forget($member->user, $organization);
        $member->delete();

        return response()->json(['message' => __('Member removed.')]);
    }
}
