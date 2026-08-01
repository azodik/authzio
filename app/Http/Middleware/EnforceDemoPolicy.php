<?php

namespace App\Http\Middleware;

use App\Exceptions\DemoBoundaryException;
use App\Models\User;
use App\Services\Demo\DemoCapability;
use App\Services\Demo\DemoGate;
use App\Services\Demo\DemoMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceDemoPolicy
{
    public function __construct(
        private readonly DemoGate $gate,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        /** @var User|null $user */
        $user = $request->user();

        if (! $this->gate->isDemo($user)) {
            return $next($request);
        }

        $capability = $this->gate->resolveRouteCapability($request);

        if ($capability === null) {
            throw new DemoBoundaryException(
                DemoCapability::AuthProfile,
                (string) config('demo.messages.default'),
            );
        }

        $mode = $this->gate->mode($user, $capability);

        if ($mode === DemoMode::Deny) {
            throw new DemoBoundaryException($capability);
        }

        if ($mode === DemoMode::Soft) {
            $request->attributes->set('demo_soft_capability', $capability->value);
        }

        return $next($request);
    }
}
