<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Services\Demo\DemoCapability;
use App\Services\Demo\DemoGate;
use App\Services\Demo\DemoMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait HandlesDemoSoftMutations
{
    protected function demoGate(): DemoGate
    {
        return app(DemoGate::class);
    }

    protected function isDemoSoft(Request $request, DemoCapability $capability): bool
    {
        /** @var User|null $user */
        $user = $request->user();

        return $this->demoGate()->mode($user, $capability) === DemoMode::Soft;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function demoSoftResponse(array $payload, int $status = 200): JsonResponse
    {
        return response()->json(array_merge($payload, [
            'demo_soft' => true,
            'message' => $payload['message'] ?? 'Saved for this demo session.',
        ]), $status);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function demoSoftAck(
        Request $request,
        DemoCapability $capability,
        array $payload = [],
        int $status = 200,
    ): ?JsonResponse {
        if (! $this->isDemoSoft($request, $capability)) {
            return null;
        }

        return $this->demoSoftResponse($payload === [] ? [
            'data' => true,
        ] : $payload, $status);
    }
}
