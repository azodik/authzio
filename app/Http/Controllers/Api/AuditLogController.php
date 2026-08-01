<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrgPermission;
use App\Http\Controllers\Concerns\AuthorizesOrganization;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use AuthorizesOrganization;

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request, $organization, OrgPermission::AuditLogsRead);

        $logs = AuditLog::query()
            ->with(['actor:id,name,email,uuid', 'organization:id,name,slug'])
            ->where('organization_id', $organization->id)
            ->when(
                $request->filled('action'),
                fn ($query) => $query->where('action', $request->string('action')),
            )
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json($logs);
    }
}
