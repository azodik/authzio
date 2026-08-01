<?php

namespace App\Http\Controllers\Oidc;

use App\Enums\EmailOtpPurpose;
use App\Enums\SocialProvider;
use App\Exceptions\DemoBoundaryException;
use App\Http\Controllers\Controller;
use App\Models\OAuthClient;
use App\Models\OrganizationSsoConnection;
use App\Models\User;
use App\Services\Auth\EmailOtpService;
use App\Services\Auth\HostedLoginPresentation;
use App\Services\Auth\LoginMethods;
use App\Services\Auth\MfaService;
use App\Services\Auth\PasskeyService;
use App\Services\Auth\SocialIdentityService;
use App\Services\Auth\SsoIdentityService;
use App\Services\Billing\PlanEntitlements;
use App\Services\Demo\DemoCapability;
use App\Services\Oidc\AuthorizationService;
use App\Services\Oidc\IssuerResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class AuthorizeController extends Controller
{
    public function __construct(
        private readonly IssuerResolver $issuerResolver,
        private readonly AuthorizationService $authorization,
        private readonly SocialIdentityService $social,
        private readonly SsoIdentityService $sso,
        private readonly PlanEntitlements $entitlements,
        private readonly EmailOtpService $otp,
        private readonly PasskeyService $passkeys,
        private readonly MfaService $mfa,
        private readonly HostedLoginPresentation $hostedLogin,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $organization = $this->issuerResolver->resolveOrganization($request);

        try {
            $validated = $this->authorization->validateAuthorizeRequest(
                $request->query(),
                $organization->id,
            );
        } catch (ValidationException $exception) {
            return $this->errorRedirect($request, $exception);
        }

        $this->storeAuthorizeContext($request, $organization->id);

        if ($request->session()->has('authzio_pending_email_verify')) {
            return view('auth.verify-email-otp', [
                'organization' => $organization,
                'client' => $validated['client'],
                'query' => $request->query(),
                'email' => $request->session()->get('authzio_pending_email'),
                ...$this->hostedLogin->apply($request, $validated['client']),
            ]);
        }

        if ($request->session()->has('authzio_pending_mfa')) {
            return view('auth.mfa-challenge', [
                'organization' => $organization,
                'client' => $validated['client'],
                'query' => $request->query(),
                ...$this->hostedLogin->apply($request, $validated['client']),
            ]);
        }

        if (Auth::check()) {
            /** @var User $authed */
            $authed = Auth::user();
            if ($authed->isDemo()) {
                Auth::logout();

                throw new DemoBoundaryException(DemoCapability::OAuthHosted);
            }

            return $this->ensureMfaThenComplete($request, $validated);
        }

        $methods = LoginMethods::forClient($validated['client']);
        $allowsSso = $this->entitlements->forOrganization($organization)['allows_sso'];

        return view('auth.authorize', [
            'organization' => $organization,
            'client' => $validated['client'],
            'query' => $request->query(),
            'scopes' => $validated['scopes'],
            'methods' => $methods,
            'socialProviders' => LoginMethods::availableSocialButtons($validated['client'], $organization),
            'ssoConnections' => $allowsSso ? $this->sso->availableButtons($organization) : [],
            'rpId' => $request->getHost(),
            ...$this->hostedLogin->apply($request, $validated['client']),
        ]);
    }

    public function mfaForm(Request $request): View|RedirectResponse
    {
        $organization = $this->issuerResolver->resolveOrganization($request);

        try {
            $validated = $this->authorization->validateAuthorizeRequest(
                $request->query() !== [] ? $request->query() : $this->authorizeQueryFromSession($request),
                $organization->id,
            );
        } catch (ValidationException $exception) {
            return $this->errorRedirect($request, $exception);
        }

        if (! Auth::check() || ! $request->session()->has('authzio_pending_mfa')) {
            return redirect()->route('oauth.authorize', $this->authorizeQueryFromSession($request));
        }

        return view('auth.mfa-challenge', [
            'organization' => $organization,
            'client' => $validated['client'],
            'query' => $request->query() !== [] ? $request->query() : $this->authorizeQueryFromSession($request),
            ...$this->hostedLogin->apply($request, $validated['client']),
        ]);
    }

    public function mfaVerify(Request $request): RedirectResponse
    {
        $organization = $this->issuerResolver->resolveOrganization($request);
        $context = $this->authorizeQueryFromSession($request);
        $validated = $this->authorization->validateAuthorizeRequest($context, $organization->id);

        if (! Auth::check() || ! $request->session()->has('authzio_pending_mfa')) {
            return redirect()->route('oauth.authorize', $context);
        }

        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (! $this->mfa->verify($user, $data['code'])) {
            return back()->withErrors(['code' => 'Invalid authenticator or recovery code.']);
        }

        $request->session()->forget('authzio_pending_mfa');
        $request->session()->put('authzio_mfa_verified', true);

        return $this->complete($request, $validated);
    }

    public function store(Request $request): RedirectResponse|View
    {
        $organization = $this->issuerResolver->resolveOrganization($request);
        $methods = null;

        $validated = $this->authorization->validateAuthorizeRequest(
            $request->except(['email', 'password', '_token', 'accept_legal', 'mode', 'code']),
            $organization->id,
        );
        $methods = LoginMethods::forClient($validated['client']);
        $this->storeAuthorizeContext($request, $organization->id);

        $mode = (string) $request->input('mode', 'password');

        if ($mode === 'password') {
            if (! $methods['password']) {
                return back()->withErrors(['email' => 'Password login is disabled for this application.']);
            }

            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);

            if (! Auth::attempt($credentials, false)) {
                return back()->withErrors(['email' => 'Invalid email or password.'])->withInput($request->except('password'));
            }

            /** @var User $user */
            $user = Auth::user();

            return $this->afterIdentityResolved($request, $validated, $user, $methods, $user->email, $user->email_verified_at !== null);
        }

        if ($mode === 'email_otp_send') {
            if (! $methods['email_otp']) {
                return back()->withErrors(['email' => 'Email OTP login is disabled.']);
            }

            $data = $request->validate(['email' => ['required', 'email']]);
            $email = Str::lower($data['email']);

            $this->otp->send($email, EmailOtpPurpose::Login, $organization, $validated['client']);
            $request->session()->put('authzio_otp_login_email', $email);

            return back()->with('otp_sent', true)->withInput(['email' => $email, 'mode' => 'email_otp_verify']);
        }

        if ($mode === 'email_otp_verify') {
            if (! $methods['email_otp']) {
                return back()->withErrors(['code' => 'Email OTP login is disabled.']);
            }

            $data = $request->validate([
                'email' => ['required', 'email'],
                'code' => ['required', 'string', 'size:6'],
            ]);

            $email = Str::lower($data['email']);
            $this->otp->verify($email, $data['code'], EmailOtpPurpose::Login);

            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => Str::before($email, '@'),
                    'password' => null,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            if (! $user->is_active) {
                return back()->withErrors(['email' => 'This account has been deactivated.']);
            }

            $this->social->login($user);
            $request->session()->forget('authzio_otp_login_email');

            return $this->afterIdentityResolved($request, $validated, $user, $methods, $email, true);
        }

        return back()->withErrors(['email' => 'Unknown login mode.']);
    }

    public function verifyEmailForm(Request $request): View|RedirectResponse
    {
        $organization = $this->issuerResolver->resolveOrganization($request);
        $context = $this->authorizeQueryFromSession($request);

        $validated = $this->authorization->validateAuthorizeRequest($context, $organization->id);

        if (! $request->session()->has('authzio_pending_email_verify')) {
            return redirect()->route('oauth.authorize', $context);
        }

        return view('auth.verify-email-otp', [
            'organization' => $organization,
            'client' => $validated['client'],
            'query' => $context,
            'email' => $request->session()->get('authzio_pending_email'),
            ...$this->hostedLogin->apply($request, $validated['client']),
        ]);
    }

    public function verifyEmail(Request $request): RedirectResponse
    {
        $organization = $this->issuerResolver->resolveOrganization($request);
        $context = $this->authorizeQueryFromSession($request);
        $validated = $this->authorization->validateAuthorizeRequest($context, $organization->id);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $email = Str::lower($data['email']);
        $this->otp->verify($email, $data['code'], EmailOtpPurpose::VerifyEmail);

        /** @var User|null $user */
        $user = Auth::user();
        if ($user === null) {
            return redirect()->route('oauth.authorize', $context);
        }

        if (str_ends_with($user->email, '@users.authzio.local') || $user->email !== $email) {
            $existing = User::query()->where('email', $email)->where('id', '!=', $user->id)->exists();
            if ($existing) {
                return back()->withErrors(['email' => 'That email is already registered.']);
            }
            $user->email = $email;
        }

        $user->email_verified_at = now();
        $user->save();

        $request->session()->forget(['authzio_pending_email_verify', 'authzio_pending_email']);

        return $this->complete($request, $validated);
    }

    public function resendVerifyEmail(Request $request): RedirectResponse
    {
        $organization = $this->issuerResolver->resolveOrganization($request);
        $context = $this->authorizeQueryFromSession($request);
        $validated = $this->authorization->validateAuthorizeRequest($context, $organization->id);

        $data = $request->validate(['email' => ['required', 'email']]);
        $email = Str::lower($data['email']);

        /** @var User|null $user */
        $user = Auth::user();

        $this->otp->send($email, EmailOtpPurpose::VerifyEmail, $organization, $validated['client'], $user);
        $request->session()->put('authzio_pending_email', $email);

        return back()->with('otp_sent', true);
    }

    public function socialRedirect(Request $request, string $provider): SymfonyRedirect
    {
        $organization = $this->issuerResolver->resolveOrganization($request);
        $socialProvider = SocialProvider::from($provider);
        $context = $request->query();

        $validated = $this->authorization->validateAuthorizeRequest($context, $organization->id);
        $methods = LoginMethods::forClient($validated['client']);

        if (! ($methods[$socialProvider->value] ?? false)) {
            abort(404);
        }

        $this->storeAuthorizeContext($request, $organization->id);

        return $this->social->configureDriver($organization, $socialProvider)->redirect();
    }

    public function socialCallback(Request $request, string $provider): RedirectResponse
    {
        $organization = $this->issuerResolver->resolveOrganization($request);
        $socialProvider = SocialProvider::from($provider);
        $context = $this->authorizeQueryFromSession($request);

        $validated = $this->authorization->validateAuthorizeRequest($context, $organization->id);
        $methods = LoginMethods::forClient($validated['client']);

        $driver = $this->social->configureDriver($organization, $socialProvider);
        $socialUser = $driver->user();

        $result = $this->social->resolveOrCreateUser(
            $organization,
            $validated['client'],
            $socialProvider,
            $socialUser,
        );

        $this->social->login($result['user']);

        return $this->afterIdentityResolved(
            $request,
            $validated,
            $result['user'],
            $methods,
            $result['email'],
            ! $result['needs_email_verification'],
        );
    }

    public function ssoRedirect(Request $request, OrganizationSsoConnection $connection): SymfonyRedirect
    {
        $organization = $this->issuerResolver->resolveOrganization($request);

        if ($connection->organization_id !== $organization->id || ! $connection->enabled) {
            abort(404);
        }

        $this->entitlements->assertSso($organization);

        $context = $request->query();
        $this->authorization->validateAuthorizeRequest($context, $organization->id);
        $this->storeAuthorizeContext($request, $organization->id);

        return $this->sso->configureDriver($connection)->redirect();
    }

    public function ssoCallback(Request $request, OrganizationSsoConnection $connection): RedirectResponse
    {
        $organization = $this->issuerResolver->resolveOrganization($request);

        if ($connection->organization_id !== $organization->id) {
            abort(404);
        }

        $this->entitlements->assertSso($organization);

        $context = $this->authorizeQueryFromSession($request);
        $validated = $this->authorization->validateAuthorizeRequest($context, $organization->id);
        $methods = LoginMethods::forClient($validated['client']);

        $driver = $this->sso->configureDriver($connection);
        $socialUser = $driver->user();

        $result = $this->sso->resolveOrCreateUser(
            $organization,
            $validated['client'],
            $connection,
            $socialUser,
        );

        $this->sso->login($result['user']);

        return $this->afterIdentityResolved(
            $request,
            $validated,
            $result['user'],
            $methods,
            $result['email'],
            ! $result['needs_email_verification'],
        );
    }

    public function passkeyOptions(Request $request): JsonResponse
    {
        $organization = $this->issuerResolver->resolveOrganization($request);
        $context = array_merge($this->authorizeQueryFromSession($request), $request->all());
        $validated = $this->authorization->validateAuthorizeRequest(
            array_filter($context, fn ($value, $key) => ! in_array($key, ['email', 'mode'], true), ARRAY_FILTER_USE_BOTH),
            $organization->id,
        );

        if (! LoginMethods::forClient($validated['client'])['passkey']) {
            return response()->json(['message' => 'Passkeys disabled.'], 422);
        }

        $email = $request->string('email')->toString() ?: null;

        return response()->json($this->passkeys->authenticationOptions($request->getHost(), $email));
    }

    public function passkeyVerify(Request $request): RedirectResponse|JsonResponse
    {
        $organization = $this->issuerResolver->resolveOrganization($request);
        $context = $this->authorizeQueryFromSession($request);
        $validated = $this->authorization->validateAuthorizeRequest($context, $organization->id);
        $methods = LoginMethods::forClient($validated['client']);

        if (! $methods['passkey']) {
            return response()->json(['message' => 'Passkeys disabled.'], 422);
        }

        $credential = $request->validate([
            'id' => ['required', 'string'],
            'rawId' => ['required', 'string'],
            'type' => ['required', 'string'],
            'response' => ['required', 'array'],
            'response.clientDataJSON' => ['required', 'string'],
            'response.authenticatorData' => ['required', 'string'],
            'response.signature' => ['required', 'string'],
        ]);

        $user = $this->passkeys->authenticate($credential, $request->getHost());

        if ($request->expectsJson()) {
            $redirect = $this->afterIdentityResolved(
                $request,
                $validated,
                $user,
                $methods,
                $user->email,
                $user->email_verified_at !== null,
            );

            return response()->json(['redirect' => $redirect->getTargetUrl()]);
        }

        return $this->afterIdentityResolved(
            $request,
            $validated,
            $user,
            $methods,
            $user->email,
            $user->email_verified_at !== null,
        );
    }

    /**
     * @param  array{client: OAuthClient, scopes: list<string>, redirect_uri: string, state: string|null, nonce: string|null, code_challenge: string|null, code_challenge_method: string|null}  $validated
     * @param  array<string, bool>  $methods
     */
    private function afterIdentityResolved(
        Request $request,
        array $validated,
        User $user,
        array $methods,
        ?string $email,
        bool $emailVerified,
    ): RedirectResponse {
        if ($user->isDemo()) {
            Auth::logout();

            throw new DemoBoundaryException(DemoCapability::OAuthHosted);
        }

        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors(['email' => 'This account has been deactivated.']);
        }

        if ($validated['client']->require_legal_accept && ! $request->boolean('accept_legal') && ! $request->session()->get('authzio_legal_accepted')) {
            // Social/passkey paths may not post checkbox; require session flag from authorize form only for password.
            if ($request->has('accept_legal') || $request->isMethod('post') && $request->input('mode') === 'password') {
                Auth::logout();

                return back()->withErrors(['accept_legal' => 'You must accept the terms to continue.']);
            }
        }

        if ($request->boolean('accept_legal')) {
            $request->session()->put('authzio_legal_accepted', true);
        }

        if ($methods['require_verified_email'] && ! $emailVerified) {
            if (! $methods['allow_unverified_email_with_otp']) {
                Auth::logout();

                return back()->withErrors(['email' => 'A verified email is required to continue.']);
            }

            $pendingEmail = $email && ! str_ends_with($email, '@users.authzio.local') ? $email : '';
            $request->session()->put('authzio_pending_email_verify', true);
            $request->session()->put('authzio_pending_email', $pendingEmail);

            if ($pendingEmail !== '') {
                $this->otp->send(
                    $pendingEmail,
                    EmailOtpPurpose::VerifyEmail,
                    $validated['client']->organization,
                    $validated['client'],
                    $user,
                );
            }

            return redirect()->route('oauth.verify-email', $this->authorizeQueryFromSession($request));
        }

        return $this->ensureMfaThenComplete($request, $validated);
    }

    /**
     * @param  array{client: OAuthClient, scopes: list<string>, redirect_uri: string, state: string|null, nonce: string|null, code_challenge: string|null, code_challenge_method: string|null}  $validated
     */
    private function ensureMfaThenComplete(Request $request, array $validated): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        abort_if($user === null, 401);

        if (! $this->mfa->isGloballyEnabled()) {
            return $this->complete($request, $validated);
        }

        if ($request->session()->get('authzio_mfa_verified') === true) {
            return $this->complete($request, $validated);
        }

        $mfaRequiredByApp = (bool) ($validated['client']->resolvedSecurityPolicy()['mfa_required'] ?? false);

        if ($mfaRequiredByApp && ! $user->mfa_enabled) {
            Auth::logout();

            return redirect()->away(
                $this->authorization->redirectWithError(
                    $validated['redirect_uri'],
                    'access_denied',
                    'This application requires authenticator MFA. Enable it in your account settings, then try again.',
                    $validated['state'],
                ),
            );
        }

        if ($user->mfa_enabled) {
            $request->session()->put('authzio_pending_mfa', true);

            return redirect()->route('oauth.mfa', $this->authorizeQueryFromSession($request));
        }

        return $this->complete($request, $validated);
    }

    /**
     * @param  array{client: OAuthClient, scopes: list<string>, redirect_uri: string, state: string|null, nonce: string|null, code_challenge: string|null, code_challenge_method: string|null}  $validated
     */
    private function complete(Request $request, array $validated): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $code = $this->authorization->createAuthorizationCode(
            $validated['client'],
            $user,
            $validated['scopes'],
            $validated['redirect_uri'],
            $validated['nonce'],
            $validated['code_challenge'],
            $validated['code_challenge_method'],
        );

        $request->session()->forget([
            'authzio_pending_email_verify',
            'authzio_pending_email',
            'authzio_pending_mfa',
            'authzio_mfa_verified',
            'authzio_legal_accepted',
            'authzio_authorize_query',
        ]);

        return redirect()->away(
            $this->authorization->redirectWithCode(
                $validated['redirect_uri'],
                $code->id,
                $validated['state'],
            ),
        );
    }

    private function storeAuthorizeContext(Request $request, string $organizationId): void
    {
        $query = $request->query();
        if ($query === []) {
            $query = $request->except([
                'email', 'password', '_token', 'accept_legal', 'mode', 'code',
                'id', 'rawId', 'type', 'response',
            ]);
        }

        $request->session()->put('authzio_authorize_query', $query);
        $request->session()->put('authzio_organization_id', $organizationId);
    }

    /**
     * @return array<string, mixed>
     */
    private function authorizeQueryFromSession(Request $request): array
    {
        $query = $request->session()->get('authzio_authorize_query', []);

        return is_array($query) ? $query : [];
    }

    private function errorRedirect(Request $request, ValidationException $exception): RedirectResponse
    {
        $redirectUri = (string) $request->query('redirect_uri', '');
        $state = $request->query('state');
        $clientId = (string) $request->query('client_id', '');

        // Never bounce errors to an unregistered redirect_uri (open redirect).
        $client = $clientId !== ''
            ? OAuthClient::query()->where('id', $clientId)->whereNull('revoked_at')->first()
            : null;
        $allowed = is_array($client?->redirect_uris) ? $client->redirect_uris : [];
        $redirectAllowed = $redirectUri !== ''
            && filter_var($redirectUri, FILTER_VALIDATE_URL) !== false
            && in_array($redirectUri, $allowed, true);

        if ($redirectAllowed) {
            return redirect()->away(
                $this->authorization->redirectWithError(
                    $redirectUri,
                    'invalid_request',
                    collect($exception->errors())->flatten()->first() ?? 'Invalid request',
                    is_string($state) ? $state : null,
                ),
            );
        }

        throw $exception;
    }
}
