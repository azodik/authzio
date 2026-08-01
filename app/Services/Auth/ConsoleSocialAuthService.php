<?php

namespace App\Services\Auth;

use App\Enums\AuditAction;
use App\Enums\ConsoleSocialProvider;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

class ConsoleSocialAuthService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function configureDriver(ConsoleSocialProvider $provider): AbstractProvider
    {
        if (! $provider->enabled()) {
            throw ValidationException::withMessages([
                'provider' => [__(':provider sign-in is not configured.', ['provider' => $provider->label()])],
            ]);
        }

        $credentials = $provider->credentials();

        /** @var AbstractProvider $driver */
        $driver = Socialite::buildProvider(
            $provider->socialiteClass(),
            [
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'redirect' => $credentials['redirect'],
            ],
        );

        $driver->scopes($provider->scopes());

        return $driver;
    }

    /**
     * Resolve an existing identity, detect email conflict, or create a new console user.
     *
     * @return array{outcome: 'login'|'conflict'|'created', user?: User}
     */
    public function resolveForLogin(ConsoleSocialProvider $provider, SocialiteUser $socialUser): array
    {
        $providerUserId = (string) $socialUser->getId();
        $email = $this->normalizedEmail($socialUser);
        $identityKey = $provider->identityKey();

        $identity = UserIdentity::query()
            ->where('provider', $identityKey)
            ->where('provider_user_id', $providerUserId)
            ->whereNull('organization_id')
            ->first();

        if ($identity !== null) {
            $user = $identity->user;
            if ($user === null) {
                throw ValidationException::withMessages([
                    'provider' => [__('This social account is no longer valid.')],
                ]);
            }

            $this->refreshIdentity($identity, $socialUser, $email);

            return ['outcome' => 'login', 'user' => $user];
        }

        if ($email !== null) {
            $existing = User::query()->where('email', $email)->first();
            if ($existing !== null) {
                return ['outcome' => 'conflict'];
            }
        }

        if ($email === null) {
            throw ValidationException::withMessages([
                'provider' => [__('Your :provider account did not share an email address. Enable email access and try again.', [
                    'provider' => $provider->label(),
                ])],
            ]);
        }

        $user = $this->createUserWithIdentity($provider, $socialUser, $email);

        return ['outcome' => 'created', 'user' => $user];
    }

    /**
     * Attach a provider identity to the authenticated console user.
     *
     * @throws ValidationException
     */
    public function linkToUser(User $user, ConsoleSocialProvider $provider, SocialiteUser $socialUser): UserIdentity
    {
        if ($user->isDemo()) {
            throw ValidationException::withMessages([
                'provider' => [__('Linked accounts cannot be changed on the demo account.')],
            ]);
        }

        $providerUserId = (string) $socialUser->getId();
        $email = $this->normalizedEmail($socialUser);
        $identityKey = $provider->identityKey();

        if ($email !== null && strcasecmp($email, $user->email) !== 0) {
            throw ValidationException::withMessages([
                'provider' => [__('The :provider account email must match your Authzio email (:email).', [
                    'provider' => $provider->label(),
                    'email' => $user->email,
                ])],
            ]);
        }

        $existing = UserIdentity::query()
            ->where('provider', $identityKey)
            ->where('provider_user_id', $providerUserId)
            ->whereNull('organization_id')
            ->first();

        if ($existing !== null && (int) $existing->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'provider' => [__('This :provider account is already linked to another Authzio user.', [
                    'provider' => $provider->label(),
                ])],
            ]);
        }

        if ($existing !== null) {
            $this->refreshIdentity($existing, $socialUser, $email);

            return $existing;
        }

        $alreadyLinked = UserIdentity::query()
            ->where('user_id', $user->id)
            ->where('provider', $identityKey)
            ->whereNull('organization_id')
            ->exists();

        if ($alreadyLinked) {
            throw ValidationException::withMessages([
                'provider' => [__('You already have a :provider account linked.', [
                    'provider' => $provider->label(),
                ])],
            ]);
        }

        return UserIdentity::query()->create([
            'user_id' => $user->id,
            'organization_id' => null,
            'provider' => $identityKey,
            'provider_user_id' => $providerUserId,
            'provider_email' => $email,
            'email_verified' => $this->providerEmailVerified($provider, $socialUser),
            'avatar_url' => $socialUser->getAvatar(),
            'profile' => $socialUser->getRaw(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function unlink(User $user, ConsoleSocialProvider $provider): void
    {
        if ($user->isDemo()) {
            throw ValidationException::withMessages([
                'provider' => [__('Linked accounts cannot be changed on the demo account.')],
            ]);
        }

        $identity = UserIdentity::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider->identityKey())
            ->whereNull('organization_id')
            ->first();

        if ($identity === null) {
            throw ValidationException::withMessages([
                'provider' => [__(':provider is not linked.', ['provider' => $provider->label()])],
            ]);
        }

        if (! $this->canUnlink($user, $provider)) {
            throw ValidationException::withMessages([
                'provider' => [__('Add a password or keep another sign-in method before unlinking :provider.', [
                    'provider' => $provider->label(),
                ])],
            ]);
        }

        $identity->delete();
    }

    public function canUnlink(User $user, ConsoleSocialProvider $provider): bool
    {
        $hasPassword = filled($user->getAuthPassword());

        $otherLinked = UserIdentity::query()
            ->where('user_id', $user->id)
            ->whereNull('organization_id')
            ->where('provider', '!=', $provider->identityKey())
            ->whereIn('provider', array_map(
                static fn (ConsoleSocialProvider $case): string => $case->identityKey(),
                ConsoleSocialProvider::cases(),
            ))
            ->exists();

        return $hasPassword || $otherLinked;
    }

    /**
     * @return list<array{provider: string, label: string, linked: bool, provider_email: string|null, can_unlink: bool}>
     */
    public function linkedAccountsSummary(User $user): array
    {
        $identities = UserIdentity::query()
            ->where('user_id', $user->id)
            ->whereNull('organization_id')
            ->whereIn('provider', array_map(
                static fn (ConsoleSocialProvider $case): string => $case->identityKey(),
                ConsoleSocialProvider::cases(),
            ))
            ->get()
            ->keyBy('provider');

        $rows = [];
        foreach (ConsoleSocialProvider::cases() as $case) {
            if (! $case->enabled() && ! $identities->has($case->identityKey())) {
                continue;
            }

            $identity = $identities->get($case->identityKey());
            $linked = $identity !== null;

            $rows[] = [
                'provider' => $case->value,
                'label' => $case->label(),
                'linked' => $linked,
                'provider_email' => $identity?->provider_email,
                'can_unlink' => $linked && $this->canUnlink($user, $case),
                'enabled' => $case->enabled(),
            ];
        }

        return $rows;
    }

    private function createUserWithIdentity(
        ConsoleSocialProvider $provider,
        SocialiteUser $socialUser,
        string $email,
    ): User {
        return DB::transaction(function () use ($provider, $socialUser, $email): User {
            $name = $socialUser->getName()
                ?: $socialUser->getNickname()
                ?: Str::before($email, '@');

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => null,
                'avatar_url' => $socialUser->getAvatar(),
                'email_verified_at' => $this->providerEmailVerified($provider, $socialUser) ? now() : null,
                'is_active' => true,
            ]);

            UserIdentity::query()->create([
                'user_id' => $user->id,
                'organization_id' => null,
                'provider' => $provider->identityKey(),
                'provider_user_id' => (string) $socialUser->getId(),
                'provider_email' => $email,
                'email_verified' => $this->providerEmailVerified($provider, $socialUser),
                'avatar_url' => $socialUser->getAvatar(),
                'profile' => $socialUser->getRaw(),
            ]);

            $this->auditLogger->log(
                AuditAction::UserCreated,
                $user,
                resourceType: User::class,
                resourceId: (string) $user->id,
            );

            return $user;
        });
    }

    private function refreshIdentity(UserIdentity $identity, SocialiteUser $socialUser, ?string $email): void
    {
        $identity->forceFill([
            'provider_email' => $email ?? $identity->provider_email,
            'avatar_url' => $socialUser->getAvatar() ?? $identity->avatar_url,
            'profile' => $socialUser->getRaw(),
        ])->save();
    }

    private function normalizedEmail(SocialiteUser $socialUser): ?string
    {
        $email = $socialUser->getEmail();

        return is_string($email) && $email !== '' ? Str::lower($email) : null;
    }

    private function providerEmailVerified(ConsoleSocialProvider $provider, SocialiteUser $socialUser): bool
    {
        $raw = $socialUser->getRaw();

        return match ($provider) {
            ConsoleSocialProvider::Google => (bool) ($raw['email_verified'] ?? false),
            ConsoleSocialProvider::GitHub => true,
        };
    }
}
