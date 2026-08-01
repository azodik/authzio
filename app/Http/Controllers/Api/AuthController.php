<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\ConsoleSocialProvider;
use App\Enums\EmailTemplateSlug;
use App\Enums\SupportedLocale;
use App\Enums\ThemePreference;
use App\Exceptions\DemoBoundaryException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UploadAvatarRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Auth\ConsoleSessionService;
use App\Services\Auth\ConsoleSocialAuthService;
use App\Services\Auth\EmailVerificationService;
use App\Services\Demo\DemoCapability;
use App\Services\Demo\DemoGate;
use App\Services\Demo\DemoOverlay;
use App\Services\Mail\TransactionalMailer;
use App\Services\Storage\AssetStorage;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly TransactionalMailer $mailer,
        private readonly EmailVerificationService $emailVerification,
        private readonly AssetStorage $assets,
        private readonly ConsoleSessionService $consoleSessions,
        private readonly ConsoleSocialAuthService $consoleSocial,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        $this->auditLogger->log(
            AuditAction::UserCreated,
            $user,
            resourceType: User::class,
            resourceId: (string) $user->id,
        );

        $this->emailVerification->issue($user);

        Auth::login($user);
        $request->session()->regenerate();
        $user->load('organizations');

        return response()->json([
            'user' => $user,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => [__('The provided credentials are incorrect.')],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        $result = $this->consoleSessions->establish(
            $request,
            $user,
            remember: $request->boolean('remember'),
            alreadyLoggedIn: true,
            locale: $request->filled('locale') ? $request->string('locale')->toString() : null,
        );

        if ($result['status'] === 'inactive') {
            throw ValidationException::withMessages([
                'email' => [__('This account has been deactivated.')],
            ]);
        }

        if ($result['status'] === 'mfa') {
            return response()->json([
                'mfa_required' => true,
                'message' => __('Enter your authenticator or recovery code to continue.'),
            ]);
        }

        return response()->json([
            'user' => $result['user'],
        ]);
    }

    public function socialProviders(): JsonResponse
    {
        return response()->json([
            'providers' => ConsoleSocialProvider::enabledMap(),
        ]);
    }

    public function linkedAccounts(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'providers' => ConsoleSocialProvider::enabledMap(),
            'accounts' => $this->consoleSocial->linkedAccountsSummary($user),
        ]);
    }

    public function unlinkAccount(Request $request, string $provider): JsonResponse
    {
        $consoleProvider = ConsoleSocialProvider::tryFrom($provider);
        abort_if($consoleProvider === null, 404);

        /** @var User $user */
        $user = $request->user();

        $this->consoleSocial->unlink($user, $consoleProvider);

        return response()->json([
            'message' => __(':provider disconnected.', ['provider' => $consoleProvider->label()]),
            'accounts' => $this->consoleSocial->linkedAccountsSummary($user->fresh() ?? $user),
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->string('email')->toString();

        $status = Password::broker()->sendResetLink(
            ['email' => $email],
            function (User $user, string $token): void {
                if ($user->isDemo()) {
                    // Hard boundary: never email password-reset links for demo.
                    return;
                }

                $resetUrl = rtrim((string) config('app.url'), '/')
                    .'/console/reset-password?'.http_build_query([
                        'token' => $token,
                        'email' => $user->email,
                    ]);

                $this->mailer->sendPlatform($user->email, EmailTemplateSlug::PasswordReset, [
                    'user_name' => $user->name,
                    'reset_url' => $resetUrl,
                ], $user->preferred_locale ?? 'en');
            },
        );

        if (! in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER], true)) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => __('If an account exists for that email, we sent password reset instructions.'),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                if ($user->isDemo()) {
                    throw new DemoBoundaryException(DemoCapability::AuthPassword);
                }

                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                $this->mailer->sendPlatform($user->email, EmailTemplateSlug::PasswordChanged, [
                    'user_name' => $user->name,
                ], $user->preferred_locale ?? 'en');
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => __('Your password has been reset. You can sign in now.'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null) {
            $this->auditLogger->log(AuditAction::Logout, $user);
        }

        if ($request->hasSession() && $user !== null && $user->isDemo()) {
            app(DemoOverlay::class)->clear($request);
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $this->expireAuthCookies($request);

        return response()->json([
            'message' => __('Logged out successfully.'),
        ]);
    }

    /**
     * Force-expire session / CSRF / remember cookies so browsers drop them even
     * when a prior SESSION_DOMAIN mismatch left duplicates behind.
     */
    private function expireAuthCookies(Request $request): void
    {
        $sessionCookie = (string) config('session.cookie');
        $path = (string) config('session.path', '/');
        $domain = config('session.domain');
        $secure = (bool) config('session.secure', $request->secure());
        $sameSite = config('session.same_site', 'lax');

        $forget = function (string $name) use ($path, $domain, $secure, $sameSite): void {
            Cookie::queue(Cookie::forget($name, $path, is_string($domain) ? $domain : null));

            // Host-only variant (SESSION_DOMAIN=null).
            Cookie::queue(cookie(
                $name,
                '',
                -2628000,
                $path,
                null,
                $secure,
                $name !== 'XSRF-TOKEN',
                false,
                is_string($sameSite) ? $sameSite : 'lax',
            ));
        };

        $forget($sessionCookie);
        $forget('XSRF-TOKEN');

        $guard = Auth::guard('web');
        if ($guard instanceof SessionGuard) {
            $forget($guard->getRecallerName());
        }

        foreach ($request->cookies->keys() as $name) {
            if (is_string($name) && preg_match('/^[A-Za-z0-9]{40}$/', $name)) {
                $forget($name);
            }
        }
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->load('organizations');

        $demoGate = app(DemoGate::class);

        return response()->json([
            'user' => $user,
            'locales' => SupportedLocale::values(),
            'linked_accounts' => $this->consoleSocial->linkedAccountsSummary($user),
            'social_providers' => ConsoleSocialProvider::enabledMap(),
            'demo' => $demoGate->isDemo($user) ? [
                'active' => true,
                'capabilities' => $demoGate->capabilityMapFor($user),
                'message' => (string) config('demo.banner'),
            ] : [
                'active' => false,
                'capabilities' => [],
                'message' => null,
            ],
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferred_locale' => ['sometimes', Rule::enum(SupportedLocale::class)],
            'theme' => ['sometimes', Rule::enum(ThemePreference::class)],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->fill($validated)->save();

        if (isset($validated['preferred_locale'])) {
            $request->session()->put('locale', $validated['preferred_locale']);
            app()->setLocale($validated['preferred_locale']);
        }

        return response()->json(['user' => $user->fresh()]);
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $file = $request->file('avatar');
        abort_unless($file !== null, 422);

        $url = $this->assets->storePublicImage(
            $file,
            'avatars/'.$user->uuid,
            $user->avatar_url,
        );

        $user->forceFill(['avatar_url' => $url])->save();

        return response()->json(['user' => $user->fresh()->load('organizations')]);
    }

    public function destroyAvatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->assets->deleteManagedUrl($user->avatar_url);
        $user->forceFill(['avatar_url' => null])->save();

        return response()->json(['user' => $user->fresh()->load('organizations')]);
    }

    public function resendConfirmation(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->email_verified_at !== null) {
            return response()->json(['message' => __('Email is already verified.')]);
        }

        $this->emailVerification->issue($user);

        return response()->json(['message' => __('Confirmation email sent.')]);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        if ($request->filled('token')) {
            $user = $this->emailVerification->verifyToken($request->string('token')->toString());
            Auth::login($user);
            $request->session()->regenerate();
            $user->load('organizations');

            return response()->json(['message' => __('Email verified.'), 'user' => $user->fresh() ?? $user]);
        }

        if ($request->user() === null) {
            throw ValidationException::withMessages([
                'code' => [__('Sign in to verify with a code, or use the email link.')],
            ]);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $this->emailVerification->verifyCode($request->user(), $validated['code']);
        $request->session()->regenerate();
        $user->load('organizations');

        return response()->json(['message' => __('Email verified.'), 'user' => $user->fresh() ?? $user]);
    }
}
