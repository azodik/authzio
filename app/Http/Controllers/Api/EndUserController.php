<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrgPermission;
use App\Http\Controllers\Concerns\AuthorizesOrganization;
use App\Http\Controllers\Controller;
use App\Models\ApplicationUser;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EndUserController extends Controller
{
    use AuthorizesOrganization;

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request, $organization, OrgPermission::EndUsersRead);

        $query = ApplicationUser::query()
            ->where('organization_id', $organization->id)
            ->with([
                'user:id,uuid,name,email,email_verified_at,is_active,last_login_at,preferred_locale',
                'oauthClient:id,name,application_type',
            ]);

        $applicationId = $request->string('application_id')->toString();
        if ($applicationId !== '') {
            $query->where('oauth_client_id', $applicationId);
        }

        $search = $request->string('q')->toString();
        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $query->whereHas('user', function ($builder) use ($like): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
            });
        }

        if ($request->has('verified')) {
            $verified = $request->boolean('verified');
            $query->whereHas('user', function ($builder) use ($verified): void {
                if ($verified) {
                    $builder->whereNotNull('email_verified_at');
                } else {
                    $builder->whereNull('email_verified_at');
                }
            });
        }

        $rows = $query
            ->orderByDesc('last_seen_at')
            ->paginate(min(100, max(1, $request->integer('per_page', 25))));

        // Group apps per user for the current page.
        $userIds = collect($rows->items())->pluck('user_id')->unique()->values();
        $appsByUser = ApplicationUser::query()
            ->where('organization_id', $organization->id)
            ->whereIn('user_id', $userIds)
            ->with('oauthClient:id,name,application_type')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($items) => $items->map(fn (ApplicationUser $row) => [
                'id' => $row->oauth_client_id,
                'name' => $row->oauthClient?->name,
                'application_type' => $row->oauthClient?->application_type,
                'last_login_at' => $row->last_login_at,
                'sign_in_count' => $row->sign_in_count,
            ])->values());

        $data = collect($rows->items())->map(function (ApplicationUser $row) use ($appsByUser) {
            return [
                'id' => $row->user_id,
                'uuid' => $row->user?->uuid,
                'name' => $row->user?->name,
                'email' => $row->user?->email,
                'email_verified_at' => $row->user?->email_verified_at,
                'is_active' => $row->user?->is_active,
                'preferred_locale' => $row->user?->preferred_locale,
                'last_login_at' => $row->last_login_at,
                'last_seen_at' => $row->last_seen_at,
                'applications' => $appsByUser->get($row->user_id, collect())->unique('id')->values(),
            ];
        })->unique('id')->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }
}
