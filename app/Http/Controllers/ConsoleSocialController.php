<?php

namespace App\Http\Controllers;

use App\Enums\ConsoleSocialProvider;
use App\Models\User;
use App\Services\Auth\ConsoleSessionService;
use App\Services\Auth\ConsoleSocialAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ConsoleSocialController extends Controller
{
    private const SESSION_KEY = 'authzio_console_social';

    public function __construct(
        private readonly ConsoleSocialAuthService $social,
        private readonly ConsoleSessionService $sessions,
    ) {}

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $consoleProvider = $this->resolveProvider($provider);
        $intent = $request->query('intent', 'login') === 'link' ? 'link' : 'login';

        if ($intent === 'link') {
            /** @var User|null $user */
            $user = $request->user();
            if ($user === null) {
                return redirect('/console/login?error=unauthenticated');
            }
            if ($user->isDemo()) {
                return redirect('/console/settings?error=demo_linked');
            }
        } elseif ($request->user() !== null) {
            return redirect('/console/');
        }

        $request->session()->put(self::SESSION_KEY, [
            'intent' => $intent,
            'provider' => $consoleProvider->value,
            'user_id' => $intent === 'link' ? $request->user()?->id : null,
            'nonce' => Str::random(40),
        ]);

        try {
            return $this->social->configureDriver($consoleProvider)->redirect();
        } catch (ValidationException $exception) {
            return $this->errorRedirect($intent, 'not_configured');
        }
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $consoleProvider = $this->resolveProvider($provider);
        $pending = $request->session()->pull(self::SESSION_KEY);

        if (! is_array($pending)
            || ($pending['provider'] ?? null) !== $consoleProvider->value
            || ! in_array($pending['intent'] ?? null, ['login', 'link'], true)
        ) {
            return redirect('/console/login?error=oauth_state');
        }

        $intent = (string) $pending['intent'];

        try {
            $socialUser = $this->social->configureDriver($consoleProvider)->user();
        } catch (Throwable) {
            return $this->errorRedirect($intent, 'oauth_failed');
        }

        if ($intent === 'link') {
            return $this->handleLink($request, $consoleProvider, $socialUser, $pending);
        }

        return $this->handleLogin($request, $consoleProvider, $socialUser);
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function handleLink(
        Request $request,
        ConsoleSocialProvider $provider,
        \Laravel\Socialite\Two\User $socialUser,
        array $pending,
    ): RedirectResponse {
        /** @var User|null $user */
        $user = $request->user();
        $pendingUserId = $pending['user_id'] ?? null;

        if ($user === null || $pendingUserId === null || (int) $user->id !== (int) $pendingUserId) {
            return redirect('/console/login?error=unauthenticated');
        }

        try {
            $this->social->linkToUser($user, $provider, $socialUser);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            return redirect('/console/settings?error=link_failed'
                .($message ? '&message='.urlencode((string) $message) : ''));
        }

        return redirect('/console/settings?linked='.$provider->value);
    }

    private function handleLogin(
        Request $request,
        ConsoleSocialProvider $provider,
        \Laravel\Socialite\Two\User $socialUser,
    ): RedirectResponse {
        try {
            $result = $this->social->resolveForLogin($provider, $socialUser);
        } catch (ValidationException $exception) {
            return redirect('/console/login?error=oauth_failed');
        }

        if ($result['outcome'] === 'conflict') {
            return redirect('/console/login?error=link_required');
        }

        /** @var User $user */
        $user = $result['user'];

        $session = $this->sessions->establish(
            $request,
            $user,
            remember: true,
            alreadyLoggedIn: false,
        );

        return match ($session['status']) {
            'inactive' => redirect('/console/login?error=deactivated'),
            'mfa' => redirect('/console/mfa'),
            default => redirect('/console/'),
        };
    }

    private function resolveProvider(string $provider): ConsoleSocialProvider
    {
        $consoleProvider = ConsoleSocialProvider::tryFrom($provider);
        abort_if($consoleProvider === null || ! $consoleProvider->enabled(), 404);

        return $consoleProvider;
    }

    private function errorRedirect(string $intent, string $error): RedirectResponse
    {
        if ($intent === 'link') {
            return redirect('/console/settings?error='.$error);
        }

        return redirect('/console/login?error='.$error);
    }
}
