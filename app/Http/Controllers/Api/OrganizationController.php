<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Models\Organization;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organizations = $request->user()
            ->organizations()
            ->withPivot(['role_id', 'status', 'joined_at'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $organizations,
        ]);
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $organization = $this->organizationService->create(
            $request->user(),
            $request->validated('name'),
            $request->validated('slug'),
        );

        if ($request->filled('billing_email')) {
            $organization->update([
                'billing_email' => $request->validated('billing_email'),
            ]);
        }

        return response()->json([
            'data' => $organization->fresh(),
        ], 201);
    }

    public function show(Request $request, Organization $organization): JsonResponse
    {
        $this->ensureMember($request, $organization);

        $organization->load(['members.user', 'roles']);

        return response()->json([
            'data' => $organization,
        ]);
    }

    private function ensureMember(Request $request, Organization $organization): void
    {
        $isMember = $request->user()
            ->organizationMemberships()
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->exists();

        abort_unless($isMember, 403, 'You are not a member of this organization.');
    }
}
