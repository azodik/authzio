<?php

namespace App\Services\Demo;

use App\Exceptions\DemoBoundaryException;
use App\Models\User;
use Illuminate\Http\Request;

class DemoGate
{
    public function isDemo(?User $user): bool
    {
        return $user !== null && $user->isDemo();
    }

    public function mode(?User $user, DemoCapability|string $capability): DemoMode
    {
        if (! $this->isDemo($user)) {
            return DemoMode::Allow;
        }

        $key = $capability instanceof DemoCapability ? $capability->value : $capability;
        /** @var array<string, string> $capabilities */
        $capabilities = config('demo.capabilities', []);
        $configured = $capabilities[$key] ?? null;

        if (! is_string($configured)) {
            return DemoMode::Deny;
        }

        return DemoMode::tryFrom($configured) ?? DemoMode::Deny;
    }

    public function assert(?User $user, DemoCapability|string $capability): void
    {
        $capabilityEnum = $capability instanceof DemoCapability
            ? $capability
            : DemoCapability::from($capability);

        if ($this->mode($user, $capabilityEnum) === DemoMode::Deny) {
            throw new DemoBoundaryException($capabilityEnum);
        }
    }

    public function isSoft(?User $user, DemoCapability|string $capability): bool
    {
        return $this->mode($user, $capability) === DemoMode::Soft;
    }

    public function resolveRouteCapability(Request $request): ?DemoCapability
    {
        $method = strtoupper($request->method());
        $path = trim($request->path(), '/');
        $candidates = [$path];
        if (! str_starts_with($path, 'api/')) {
            $candidates[] = 'api/'.$path;
        }

        /** @var list<array{methods: list<string>, uri: string, capability: string}> $routes */
        $routes = config('demo.routes', []);

        foreach ($routes as $route) {
            $methods = array_map('strtoupper', $route['methods'] ?? []);
            if ($methods !== [] && ! in_array($method, $methods, true)) {
                continue;
            }

            foreach ($candidates as $candidate) {
                if ($this->uriMatches($route['uri'] ?? '', $candidate)) {
                    return DemoCapability::tryFrom((string) $route['capability']);
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public function capabilityMapFor(?User $user): array
    {
        if (! $this->isDemo($user)) {
            return [];
        }

        /** @var array<string, string> $capabilities */
        $capabilities = config('demo.capabilities', []);

        return $capabilities;
    }

    private function uriMatches(string $pattern, string $path): bool
    {
        $pattern = trim($pattern, '/');
        $regex = '#^'.implode('[^/]+', array_map(
            static fn (string $part): string => preg_quote($part, '#'),
            explode('*', $pattern),
        )).'$#';

        return (bool) preg_match($regex, $path);
    }
}
