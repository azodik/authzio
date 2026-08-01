<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(
        private readonly Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        AuditAction $action,
        ?User $actor = null,
        ?Organization $organization = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
        array $metadata = [],
    ): AuditLog {
        return AuditLog::create([
            'organization_id' => $organization?->id,
            'actor_user_id' => $actor?->id,
            'action' => $action->value,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'metadata' => $metadata !== [] ? $metadata : null,
        ]);
    }
}
