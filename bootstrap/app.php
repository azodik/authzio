<?php

use App\Http\Middleware\EnforceDemoPolicy;
use App\Http\Middleware\EnsureJsonRequest;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')
                ->group(base_path('routes/oidc.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->validateCsrfTokens(except: [
            'api/v1/webhooks/*',
            'api/oauth/*',
            '__e2e/*',
        ]);

        $middleware->append(SecurityHeaders::class);
        $middleware->append(SetLocale::class);

        $middleware->api(prepend: [
            EnsureJsonRequest::class,
        ]);

        $middleware->alias([
            'secure.json' => EnsureJsonRequest::class,
            'demo.policy' => EnforceDemoPolicy::class,
        ]);

        $middleware->encryptCookies(except: [
            'XSRF-TOKEN',
        ]);

        $middleware->redirectGuestsTo('/console/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
