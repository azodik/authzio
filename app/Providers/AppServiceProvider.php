<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->useLangPath(resource_path('lang'));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Root-relative Vite URLs (/build/...) so CSS/JS always load on the host the
        // browser is using — Herd (authzio.test), Docker (:8080), or bare metal —
        // even when APP_URL in .env is stale or points at another port.
        Vite::createAssetPathsUsing(
            fn (string $path, ?bool $secure = null): string => '/'.ltrim($path, '/'),
        );

        // Optional: force absolute url()/route() roots (e.g. Compose publishes :8080
        // while nginx listens on :80). Vite assets do not need this.
        if (filter_var(config('app.force_url', false), FILTER_VALIDATE_BOOLEAN)) {
            $appUrl = config('app.url');
            if (is_string($appUrl) && $appUrl !== '') {
                URL::forceRootUrl($appUrl);
            }
        }

        // Laravel Cloud defaults SESSION_DRIVER=cookie, which stores session payloads in
        // cookies named by the session id and accumulates until HTTP 431. Never allow it.
        if (config('session.driver') === 'cookie') {
            config(['session.driver' => 'database']);
        }

        Password::defaults(function (): Password {
            $rule = Password::min(config('authzio.password.min_length', 12));

            if (config('authzio.password.require_mixed_case', true)) {
                $rule->mixedCase();
            }

            if (config('authzio.password.require_numbers', true)) {
                $rule->numbers();
            }

            if (config('authzio.password.require_symbols', true)) {
                $rule->symbols();
            }

            return $rule;
        });

        // Playwright (APP_ENV=e2e) only — keep rate limits in local/production/testing.
        $relaxLimits = app()->environment('e2e');

        RateLimiter::for('api', function (Request $request) use ($relaxLimits): Limit {
            if ($relaxLimits) {
                return Limit::none();
            }

            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) use ($relaxLimits): Limit {
            if ($relaxLimits) {
                return Limit::none();
            }

            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
