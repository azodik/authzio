<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrganizationInvitation;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizationService,
    ) {}

    /**
     * Pending invitations addressed to the authenticated user's email.
     */
    public function index(Request $request): JsonResponse
    {
        $email = Str::lower((string) $request->user()->email);

        $invitations = OrganizationInvitation::query()
            ->with([
                'organization:id,name,slug',
                'role:id,name,slug',
                'inviter:id,name,email',
            ])
            ->whereRaw('lower(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get()
            ->each(fn (OrganizationInvitation $invitation) => $invitation->makeVisible(['token']));

        return response()->json(['data' => $invitations]);
    }

    public function show(string $token): JsonResponse
    {
        $invitation = OrganizationInvitation::query()
            ->with(['organization:id,name,slug', 'role:id,name,slug'])
            ->where('token', $token)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'email' => $invitation->email,
                'organization' => $invitation->organization,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at,
                'is_pending' => $invitation->isPending(),
            ],
        ]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = OrganizationInvitation::query()
            ->where('token', $token)
            ->firstOrFail();

        $member = $this->organizationService->acceptInvitation($invitation, $request->user());

        return response()->json([
            'data' => $member,
            'organization_id' => $invitation->organization_id,
        ]);
    }
}
