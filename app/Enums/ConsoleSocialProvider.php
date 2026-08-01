<?php

namespace App\Enums;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\GoogleProvider;

enum ConsoleSocialProvider: string
{
    case Google = 'google';
    case GitHub = 'github';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::GitHub => 'GitHub',
        };
    }

    /**
     * Identity provider key stored on user_identities (namespaced from org social).
     */
    public function identityKey(): string
    {
        return match ($this) {
            self::Google => 'console_google',
            self::GitHub => 'console_github',
        };
    }

    public function servicesConfigKey(): string
    {
        return match ($this) {
            self::Google => 'console_google',
            self::GitHub => 'console_github',
        };
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return match ($this) {
            self::Google => ['openid', 'profile', 'email'],
            self::GitHub => ['read:user', 'user:email'],
        };
    }

    /**
     * @return class-string<AbstractProvider>
     */
    public function socialiteClass(): string
    {
        return match ($this) {
            self::Google => GoogleProvider::class,
            self::GitHub => GithubProvider::class,
        };
    }

    public function enabled(): bool
    {
        $config = config('services.'.$this->servicesConfigKey(), []);
        $clientId = is_array($config) ? trim((string) ($config['client_id'] ?? '')) : '';
        $clientSecret = is_array($config) ? trim((string) ($config['client_secret'] ?? '')) : '';

        return $clientId !== '' && $clientSecret !== '';
    }

    /**
     * @return array{client_id: string, client_secret: string, redirect: string}
     */
    public function credentials(): array
    {
        $config = config('services.'.$this->servicesConfigKey(), []);

        return [
            'client_id' => (string) ($config['client_id'] ?? ''),
            'client_secret' => (string) ($config['client_secret'] ?? ''),
            'redirect' => (string) ($config['redirect'] ?? ''),
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * @return array<string, bool>
     */
    public static function enabledMap(): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = $case->enabled();
        }

        return $map;
    }
}
