<?php

namespace App\Services\Demo;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class DemoOverlay
{
    private const SESSION_KEY = 'demo_overrides';

    public function __construct(
        private readonly DemoGate $gate,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function put(Request $request, string $resourceKey, array $attributes): array
    {
        $bag = $this->all($request);
        $existing = is_array($bag[$resourceKey] ?? null) ? $bag[$resourceKey] : [];
        $merged = array_replace_recursive($existing, $attributes);
        $bag[$resourceKey] = $merged;
        $request->session()->put(self::SESSION_KEY, $bag);

        return $merged;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(Request $request, string $resourceKey): ?array
    {
        $bag = $this->all($request);
        $value = $bag[$resourceKey] ?? null;

        return is_array($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public function merge(Request $request, string $resourceKey, array $base): array
    {
        $overlay = $this->get($request, $resourceKey);
        if ($overlay === null) {
            return $base;
        }

        return array_replace_recursive($base, $overlay);
    }

    public function applicationKey(string $applicationId): string
    {
        return 'application:'.$applicationId;
    }

    public function emailTemplateKey(string $templateId): string
    {
        return 'email_template:'.$templateId;
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function all(Request $request): array
    {
        if (! $request->hasSession()) {
            return [];
        }

        $bag = $request->session()->get(self::SESSION_KEY, []);

        return is_array($bag) ? $bag : [];
    }

    /**
     * Apply overlay attributes onto an Eloquent-like array payload without mutating DB.
     *
     * @param  array<string, mixed>  $model
     * @param  array<string, mixed>  $overlay
     * @return array<string, mixed>
     */
    public function applyToArray(array $model, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            Arr::set($model, $key, $value);
        }

        return $model;
    }
}
