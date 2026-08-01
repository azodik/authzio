<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\EnsuresOrganizationMembership;
use App\Http\Controllers\Controller;
use App\Models\OAuthClient;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverviewController extends Controller
{
    use EnsuresOrganizationMembership;

    public function stats(Request $request, ?Organization $organization = null): JsonResponse
    {
        if ($organization !== null) {
            $this->organizationForUser($request, $organization);
            $userIds = $organization->members()->pluck('user_id');

            return response()->json([
                'data' => [
                    'users' => User::query()->whereIn('id', $userIds)->count(),
                    'organizations' => 1,
                    'applications' => OAuthClient::query()
                        ->where('organization_id', $organization->id)
                        ->whereNull('revoked_at')
                        ->count(),
                    'mfa_enabled_users' => User::query()
                        ->whereIn('id', $userIds)
                        ->where('mfa_enabled', true)
                        ->count(),
                    'end_users' => $organization->applicationUsers()->distinct('user_id')->count('user_id'),
                ],
                'organization' => $organization->only(['id', 'name', 'slug']),
            ]);
        }

        return response()->json([
            'data' => [
                'users' => User::query()->count(),
                'organizations' => $request->user()->organizations()->count(),
                'applications' => OAuthClient::query()->whereNull('revoked_at')->count(),
                'mfa_enabled_users' => User::query()->where('mfa_enabled', true)->count(),
            ],
        ]);
    }
}
