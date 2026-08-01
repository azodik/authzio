<?php

namespace App\Services\Auth;

use App\Models\OAuthClient;
use App\Models\Organization;
use App\Models\OrganizationSsoConnection;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\Oidc\IssuerResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

class SsoIdentityService
{
    public function __construct(
        private readonly IssuerResolver $issuerResolver,
    ) {}

    /**
     * Absolute callback URL registered at the external IdP (org issuer host, not console APP_URL).
     */
    public function callbackUrl(OrganizationSsoConnection $connection): string
    {
        $organization = $connection->relationLoaded('organization')
            ? $connection->organization
            : $connection->organization()->firstOrFail();

        return rtrim($this->issuerResolver->issuerUrl($organization), '/').'/oauth/sso/'.$connection->id.'/callback';
    }

    /**
     * @return list<array{id: string, name: string, slug: string}>
     */
    public function availableButtons(Organization $organization): array
    {
        return OrganizationSsoConnection::query()
            ->where('organization_id', $organization->id)
            ->where('enabled', true)
            ->where('protocol', 'oidc')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(static fn (OrganizationSsoConnection $connection): array => [
                'id' => $connection->id,
                'name' => $connection->name,
                'slug' => $connection->slug,
            ])
            ->all();
    }

    /**
     * @return array{
     *     issuer: string,
     *     authorization_endpoint: string,
     *     token_endpoint: string,
     *     userinfo_endpoint: string,
     *     jwks_uri: string|null
     * }
     */
    public function discover(string $issuer): array
    {
        $issuer = rtrim(trim($issuer), '/');
        if ($issuer === '' || ! filter_var($issuer, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'issuer' => ['Enter a valid OIDC issuer URL.'],
            ]);
        }

        $discoveryUrl = $issuer.'/.well-known/openid-configuration';
        $response = Http::timeout(10)->acceptJson()->get($discoveryUrl);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'issuer' => ['Could not load OpenID Provider metadata from '.$discoveryUrl.'.'],
            ]);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'issuer' => ['OpenID Provider metadata was invalid.'],
            ]);
        }

        $authorization = (string) ($payload['authorization_endpoint'] ?? '');
        $token = (string) ($payload['token_endpoint'] ?? '');
        $userinfo = (string) ($payload['userinfo_endpoint'] ?? '');

        if ($authorization === '' || $token === '' || $userinfo === '') {
            throw ValidationException::withMessages([
                'issuer' => ['OIDC discovery must include authorization, token, and userinfo endpoints.'],
            ]);
        }

        return [
            'issuer' => (string) ($payload['issuer'] ?? $issuer),
            'authorization_endpoint' => $authorization,
            'token_endpoint' => $token,
            'userinfo_endpoint' => $userinfo,
            'jwks_uri' => isset($payload['jwks_uri']) ? (string) $payload['jwks_uri'] : null,
        ];
    }

    public function configureDriver(OrganizationSsoConnection $connection): GenericOidcProvider
    {
        if (! $connection->enabled || $connection->protocol !== 'oidc') {
            throw ValidationException::withMessages([
                'sso' => ['This SSO connection is not available.'],
            ]);
        }

        $authorization = (string) $connection->authorization_endpoint;
        $token = (string) $connection->token_endpoint;
        $userinfo = (string) $connection->userinfo_endpoint;

        if ($authorization === '' || $token === '' || $userinfo === '') {
            $discovered = $this->discover($connection->issuer);
            $authorization = $discovered['authorization_endpoint'];
            $token = $discovered['token_endpoint'];
            $userinfo = $discovered['userinfo_endpoint'];

            $connection->fill([
                'authorization_endpoint' => $authorization,
                'token_endpoint' => $token,
                'userinfo_endpoint' => $userinfo,
                'jwks_uri' => $discovered['jwks_uri'],
            ])->save();
        }

        /** @var GenericOidcProvider $driver */
        $driver = Socialite::buildProvider(
            GenericOidcProvider::class,
            [
                'client_id' => $connection->client_id,
                'client_secret' => $connection->client_secret,
                'redirect' => $this->callbackUrl($connection),
            ],
        );

        $scopes = $connection->scopes ?? ['openid', 'profile', 'email'];
        $driver->scopes($scopes);
        $driver->setEndpoints($authorization, $token, $userinfo);

        return $driver;
    }

    /**
     * @return array{user: User, needs_email_verification: bool, email: string|null}
     */
    public function resolveOrCreateUser(
        Organization $organization,
        OAuthClient $client,
        OrganizationSsoConnection $connection,
        SocialiteUser $socialUser,
    ): array {
        $methods = LoginMethods::forClient($client);
        $providerKey = $connection->identityProviderKey();
        $providerUserId = (string) $socialUser->getId();

        if ($providerUserId === '') {
            throw ValidationException::withMessages([
                'sso' => ['The identity provider did not return a subject (sub).'],
            ]);
        }

        $email = $socialUser->getEmail() !== null ? Str::lower($socialUser->getEmail()) : null;
        $emailVerified = (bool) ($socialUser->user['email_verified'] ?? false);
        $name = $socialUser->getName()
            ?: $socialUser->getNickname()
            ?: ($email ? Str::before($email, '@') : 'User');
        $avatar = $socialUser->getAvatar();

        if ($email !== null) {
            $this->assertEmailDomainAllowed($connection, $email);
        }

        $identity = UserIdentity::query()
            ->where('provider', $providerKey)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($identity !== null) {
            $user = $identity->user;
            if ($user === null || ! $user->is_active) {
                throw ValidationException::withMessages([
                    'sso' => ['This SSO identity is linked to an inactive user.'],
                ]);
            }

            if ($methods['sync_profile']) {
                $user->fill(array_filter([
                    'name' => $name,
                    'avatar_url' => $avatar,
                    'email' => $email && $user->email === null ? $email : null,
                ], fn ($value) => $value !== null));

                if ($emailVerified && $email !== null && $user->email === $email && $user->email_verified_at === null) {
                    $user->email_verified_at = now();
                }

                $user->save();
            }

            $identity->update([
                'provider_email' => $email,
                'email_verified' => $emailVerified,
                'avatar_url' => $avatar,
                'profile' => $socialUser->user,
                'organization_id' => $organization->id,
            ]);

            return [
                'user' => $user->fresh(),
                'needs_email_verification' => $this->needsEmailVerification($user, $email, $emailVerified, $methods),
                'email' => $email ?? $user->email,
            ];
        }

        if ($email === null) {
            if (! ($methods['allow_unverified_email_with_otp'] ?? false) && ! ($methods['email_otp'] ?? false)) {
                throw ValidationException::withMessages([
                    'email' => ['This identity provider did not return an email address.'],
                ]);
            }

            $email = 'pending_sso_'.$providerUserId.'@users.authzio.local';
            $emailVerified = false;
        }

        $user = null;
        if (! str_ends_with($email, '@users.authzio.local')) {
            $user = User::query()->where('email', $email)->first();
        }

        if ($user === null) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => null,
                'avatar_url' => $avatar,
                'email_verified_at' => $emailVerified ? now() : null,
                'is_active' => true,
            ]);
        } elseif (! $user->is_active) {
            throw ValidationException::withMessages([
                'sso' => ['This account is inactive.'],
            ]);
        } elseif ($methods['sync_profile']) {
            $user->fill(array_filter([
                'name' => $name,
                'avatar_url' => $avatar,
            ], fn ($value) => $value !== null));

            if ($emailVerified && $user->email_verified_at === null) {
                $user->email_verified_at = now();
            }

            $user->save();
        }

        UserIdentity::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'provider' => $providerKey,
            'provider_user_id' => $providerUserId,
            'provider_email' => str_ends_with($email, '@users.authzio.local') ? null : $email,
            'email_verified' => $emailVerified,
            'avatar_url' => $avatar,
            'profile' => $socialUser->user,
        ]);

        return [
            'user' => $user->fresh(),
            'needs_email_verification' => $this->needsEmailVerification($user, $email, $emailVerified, $methods),
            'email' => str_ends_with($email, '@users.authzio.local') ? null : $email,
        ];
    }

    public function login(User $user): void
    {
        Auth::login($user, false);
        request()->session()->regenerate();

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);
    }

    private function assertEmailDomainAllowed(OrganizationSsoConnection $connection, string $email): void
    {
        $domains = $connection->normalizedEmailDomains();
        if ($domains === []) {
            return;
        }

        $host = Str::lower((string) Str::after($email, '@'));
        if (! in_array($host, $domains, true)) {
            throw ValidationException::withMessages([
                'email' => ['Your email domain is not allowed for this SSO connection.'],
            ]);
        }
    }

    /**
     * @param  array<string, bool>  $methods
     */
    private function needsEmailVerification(User $user, ?string $email, bool $emailVerified, array $methods): bool
    {
        if (! ($methods['require_verified_email'] ?? false)) {
            return false;
        }

        if ($email === null || str_ends_with($user->email, '@users.authzio.local')) {
            return (bool) ($methods['allow_unverified_email_with_otp'] ?? false);
        }

        if ($emailVerified || $user->email_verified_at !== null) {
            return false;
        }

        return (bool) ($methods['allow_unverified_email_with_otp'] ?? false);
    }
}
