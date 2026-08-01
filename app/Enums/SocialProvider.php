<?php

namespace App\Enums;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\BitbucketProvider;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\GitlabProvider;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\LinkedInOpenIdProvider;
use Laravel\Socialite\Two\SlackOpenIdProvider;
use Laravel\Socialite\Two\XProvider;

enum SocialProvider: string
{
    case Google = 'google';
    case GitHub = 'github';
    case Facebook = 'facebook';
    case GitLab = 'gitlab';
    case LinkedIn = 'linkedin';
    case X = 'x';
    case Bitbucket = 'bitbucket';
    case Slack = 'slack';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::GitHub => 'GitHub',
            self::Facebook => 'Facebook',
            self::GitLab => 'GitLab',
            self::LinkedIn => 'LinkedIn',
            self::X => 'X',
            self::Bitbucket => 'Bitbucket',
            self::Slack => 'Slack',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Google => 'Sign in with Google accounts (OpenID Connect).',
            self::GitHub => 'Sign in with GitHub developer accounts.',
            self::Facebook => 'Sign in with Facebook / Meta Login.',
            self::GitLab => 'Sign in with GitLab.com accounts.',

            self::LinkedIn => 'Sign in with LinkedIn OpenID Connect.',
            self::X => 'Sign in with X (Twitter) OAuth 2.0.',
            self::Bitbucket => 'Sign in with Bitbucket Cloud.',
            self::Slack => 'Sign in with Slack OpenID Connect.',
        };
    }

    /**
     * @return list<string>
     */
    public function defaultScopes(): array
    {
        return match ($this) {
            self::Google => ['openid', 'profile', 'email'],
            self::GitHub => ['read:user', 'user:email'],
            self::Facebook => ['email', 'public_profile'],
            self::GitLab => ['read_user'],
            self::LinkedIn => ['openid', 'profile', 'email'],
            self::X => ['users.read', 'tweet.read', 'offline.access'],
            self::Bitbucket => ['account', 'email'],
            self::Slack => ['openid', 'profile', 'email'],
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
            self::Facebook => FacebookProvider::class,
            self::GitLab => GitlabProvider::class,
            self::LinkedIn => LinkedInOpenIdProvider::class,
            self::X => XProvider::class,
            self::Bitbucket => BitbucketProvider::class,
            self::Slack => SlackOpenIdProvider::class,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
