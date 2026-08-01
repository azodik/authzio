<?php

namespace App\Services\Auth;

use App\Enums\SocialProvider;
use App\Models\OAuthClient;
use App\Models\Organization;
use App\Models\OrganizationSocialProvider;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\Oidc\IssuerResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

class SocialIdentityService
{
    public function __construct(
        private readonly IssuerResolver $issuerResolver,
    ) {}

    /**
     * Absolute callback URL registered at the external IdP (org issuer host, not console APP_URL).
     */
    public function callbackUrl(Organization $organization, SocialProvider $provider): string
    {
        return rtrim($this->issuerResolver->issuerUrl($organization), '/').'/oauth/social/'.$provider->value.'/callback';
    }

    public function configureDriver(Organization $organization, SocialProvider $provider): AbstractProvider
    {
        $record = OrganizationSocialProvider::query()
            ->where('organization_id', $organization->id)
            ->where('provider', $provider->value)
            ->where('enabled', true)
            ->first();

        if ($record === null) {
            throw ValidationException::withMessages([
                'provider' => ["{$provider->label()} is not configured for this organization."],
            ]);
        }

        /** @var AbstractProvider $driver */
        $driver = Socialite::buildProvider(
            $provider->socialiteClass(),
            [
                'client_id' => $record->client_id,
                'client_secret' => $record->client_secret,
                'redirect' => $this->callbackUrl($organization, $provider),
            ],
        );

        $scopes = $record->scopes ?? $provider->defaultScopes();
        $driver->scopes($scopes);

        return $driver;
    }

    /**
     * @return array{user: User, needs_email_verification: bool, email: string|null}
     */
    public function resolveOrCreateUser(
        Organization $organization,
        OAuthClient $client,
        SocialProvider $provider,
        SocialiteUser $socialUser,
    ): array {
        $methods = LoginMethods::forClient($client);
        $providerUserId = (string) $socialUser->getId();
        $email = $socialUser->getEmail() !== null ? Str::lower($socialUser->getEmail()) : null;
        $emailVerified = $this->providerEmailVerified($provider, $socialUser);
        $name = $socialUser->getName() ?: $socialUser->getNickname() ?: ($email ? Str::before($email, '@') : 'User');
        $avatar = $socialUser->getAvatar();

        $identity = UserIdentity::query()
            ->where('provider', $provider->value)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($identity !== null) {
            $user = $identity->user;
            if ($user === null || ! $user->is_active) {
                throw ValidationException::withMessages([
                    'provider' => ['This social account is linked to an inactive user.'],
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
                'user' => $user->fresh() ?? $user,
                'needs_email_verification' => $this->needsEmailVerification($methods, $email, $emailVerified, $user),
                'email' => $email ?? $user->email,
            ];
        }

        if ($email === null) {
            if (! $methods['allow_unverified_email_with_otp'] && $methods['require_verified_email']) {
                throw ValidationException::withMessages([
                    'email' => ['This provider did not return an email address. Enable email OTP verification or choose another login method.'],
                ]);
            }

            // Create placeholder user; OTP step will collect/verify email.
            $user = User::query()->create([
                'name' => $name,
                'email' => 'pending_'.$provider->value.'_'.$providerUserId.'@users.authzio.local',
                'password' => null,
                'avatar_url' => $avatar,
                'is_active' => true,
                'email_verified_at' => null,
            ]);
        } else {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => null,
                    'avatar_url' => $avatar,
                    'is_active' => true,
                    'email_verified_at' => $emailVerified ? now() : null,
                ]);
            } elseif ($methods['sync_profile']) {
                $user->update(array_filter([
                    'name' => $name,
                    'avatar_url' => $avatar,
                ], fn ($value) => $value !== null && $value !== ''));

                if ($emailVerified && $user->email_verified_at === null) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }
            }
        }

        UserIdentity::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'provider' => $provider->value,
            'provider_user_id' => $providerUserId,
            'provider_email' => $email,
            'email_verified' => $emailVerified,
            'avatar_url' => $avatar,
            'profile' => $socialUser->user,
        ]);

        return [
            'user' => $user->fresh() ?? $user,
            'needs_email_verification' => $this->needsEmailVerification($methods, $email, $emailVerified, $user),
            'email' => $email ?? $user->email,
        ];
    }

    /**
     * @param  array<string, bool>  $methods
     */
    public function needsEmailVerification(array $methods, ?string $email, bool $emailVerified, User $user): bool
    {
        if (! $methods['require_verified_email']) {
            return false;
        }

        if ($email === null || str_ends_with($user->email, '@users.authzio.local')) {
            return $methods['allow_unverified_email_with_otp'];
        }

        if ($emailVerified || $user->email_verified_at !== null) {
            return false;
        }

        return $methods['allow_unverified_email_with_otp'];
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

    private function providerEmailVerified(SocialProvider $provider, SocialiteUser $socialUser): bool
    {
        $raw = $socialUser->user;

        return match ($provider) {
            SocialProvider::Google => (bool) ($raw['email_verified'] ?? $raw['verified_email'] ?? false),
            SocialProvider::LinkedIn, SocialProvider::Slack => (bool) ($raw['email_verified'] ?? false),
            SocialProvider::GitHub,
            SocialProvider::GitLab,
            SocialProvider::Bitbucket,
            SocialProvider::Facebook,
            SocialProvider::X => $socialUser->getEmail() !== null,
        };
    }
}
