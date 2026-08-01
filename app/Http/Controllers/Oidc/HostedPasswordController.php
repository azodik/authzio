<?php

namespace App\Http\Controllers\Oidc;

use App\Enums\EmailTemplateSlug;
use App\Exceptions\DemoBoundaryException;
use App\Http\Controllers\Controller;
use App\Models\OAuthClient;
use App\Models\User;
use App\Services\Auth\HostedLoginPresentation;
use App\Services\Auth\LoginMethods;
use App\Services\Demo\DemoCapability;
use App\Services\Mail\TransactionalMailer;
use App\Services\Oidc\IssuerResolver;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HostedPasswordController extends Controller
{
    public function __construct(
        private readonly IssuerResolver $issuerResolver,
        private readonly TransactionalMailer $mailer,
        private readonly HostedLoginPresentation $hostedLogin,
    ) {}

    public function showForgot(Request $request): View|RedirectResponse
    {
        $client = $this->resolveClient($request);

        if (! $this->forgotPasswordEnabled($client)) {
            return redirect()->route('oauth.authorize', $this->authorizeQuery($request, $client));
        }

        $this->rememberAuthorizeQuery($request);

        return view('auth.forgot-password', [
            'client' => $client,
            'organization' => $client->organization,
            'query' => $this->authorizeQuery($request, $client),
            'sent' => false,
            ...$this->hostedLogin->apply($request, $client),
        ]);
    }

    public function sendForgot(Request $request): View|RedirectResponse
    {
        $client = $this->resolveClient($request);

        if (! $this->forgotPasswordEnabled($client)) {
            return redirect()->route('oauth.authorize', $this->authorizeQuery($request, $client));
        }

        $this->rememberAuthorizeQuery($request);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'client_id' => ['required', 'string'],
        ]);

        $email = Str::lower($data['email']);
        $organization = $client->organization;
        $issuer = rtrim($this->issuerResolver->issuerUrl($organization), '/');
        $returnQuery = $this->authorizeQuery($request, $client);

        Password::broker()->sendResetLink(
            ['email' => $email],
            function (User $user, string $token) use ($client, $organization, $issuer, $returnQuery): void {
                if ($user->isDemo()) {
                    return;
                }

                $resetUrl = $issuer.'/oauth/reset-password?'.http_build_query([
                    'token' => $token,
                    'email' => $user->email,
                    'client_id' => $client->id,
                    ...$returnQuery,
                ]);

                $this->mailer->sendOrganization(
                    $organization,
                    $user->email,
                    EmailTemplateSlug::PasswordReset,
                    [
                        'user_name' => $user->name,
                        'product_name' => $client->name,
                        'reset_url' => $resetUrl,
                    ],
                    $user->preferred_locale ?? $client->default_locale ?? 'en',
                );
            },
        );

        return view('auth.forgot-password', [
            'client' => $client,
            'organization' => $organization,
            'query' => $returnQuery,
            'sent' => true,
            ...$this->hostedLogin->apply($request, $client),
        ]);
    }

    public function showReset(Request $request): View
    {
        $client = $this->resolveClient($request);
        $this->rememberAuthorizeQuery($request);

        return view('auth.reset-password', [
            'client' => $client,
            'organization' => $client->organization,
            'query' => $this->authorizeQuery($request, $client),
            'token' => $request->string('token')->toString(),
            'email' => $request->string('email')->toString(),
            ...$this->hostedLogin->apply($request, $client),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $client = $this->resolveClient($request);
        $this->rememberAuthorizeQuery($request);
        $policy = $client->resolvedPasswordPolicy();

        $passwordRule = PasswordRule::defaults()->min($policy['min_length']);
        if ($policy['require_mixed_case']) {
            $passwordRule = $passwordRule->mixedCase();
        }
        if ($policy['require_numbers']) {
            $passwordRule = $passwordRule->numbers();
        }
        if ($policy['require_symbols']) {
            $passwordRule = $passwordRule->symbols();
        }

        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', $passwordRule],
            'client_id' => ['required', 'string'],
        ]);

        $status = Password::broker()->reset(
            [
                'email' => Str::lower($data['email']),
                'password' => $data['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $data['token'],
            ],
            function (User $user, string $password) use ($client): void {
                if ($user->isDemo()) {
                    throw new DemoBoundaryException(DemoCapability::AuthPassword);
                }

                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                $this->mailer->sendOrganization(
                    $client->organization,
                    $user->email,
                    EmailTemplateSlug::PasswordChanged,
                    [
                        'user_name' => $user->name,
                        'product_name' => $client->name,
                    ],
                    $user->preferred_locale ?? $client->default_locale ?? 'en',
                );
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return redirect()
            ->route('oauth.authorize', $this->authorizeQuery($request, $client))
            ->with('status', 'Password updated. Sign in to continue.');
    }

    private function resolveClient(Request $request): OAuthClient
    {
        $organization = $this->issuerResolver->resolveOrganization($request);
        $clientId = $request->string('client_id')->toString();

        if ($clientId === '') {
            $clientId = (string) $request->session()->get('authzio_authorize_query.client_id', '');
        }

        abort_if($clientId === '', 404, 'Missing client_id.');

        $client = OAuthClient::query()
            ->where('id', $clientId)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        abort_if($client->isRevoked(), 404);

        return $client;
    }

    private function forgotPasswordEnabled(OAuthClient $client): bool
    {
        $methods = LoginMethods::forClient($client);

        return ($methods['password'] ?? false)
            && ($client->show_forgot_password_link ?? true);
    }

    /**
     * @return array<string, string>
     */
    private function authorizeQuery(Request $request, OAuthClient $client): array
    {
        $keys = [
            'client_id',
            'redirect_uri',
            'response_type',
            'scope',
            'state',
            'nonce',
            'code_challenge',
            'code_challenge_method',
        ];

        $fromSession = $request->session()->get('authzio_authorize_query', []);
        $query = ['client_id' => $client->id];

        foreach ($keys as $key) {
            $value = $request->input($key);
            if (! is_string($value) || $value === '') {
                $value = is_array($fromSession) ? ($fromSession[$key] ?? null) : null;
            }
            if (is_string($value) && $value !== '') {
                $query[$key] = $value;
            }
        }

        $query['client_id'] = $client->id;

        return $query;
    }

    private function rememberAuthorizeQuery(Request $request): void
    {
        $keys = [
            'client_id',
            'redirect_uri',
            'response_type',
            'scope',
            'state',
            'nonce',
            'code_challenge',
            'code_challenge_method',
        ];

        $existing = $request->session()->get('authzio_authorize_query', []);
        $merged = is_array($existing) ? $existing : [];

        foreach ($keys as $key) {
            $value = $request->input($key);
            if (is_string($value) && $value !== '') {
                $merged[$key] = $value;
            }
        }

        if ($merged !== []) {
            $request->session()->put('authzio_authorize_query', $merged);
        }
    }
}
