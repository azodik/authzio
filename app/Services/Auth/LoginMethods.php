<?php

namespace App\Services\Auth;

use App\Enums\SocialProvider;
use App\Models\OAuthClient;
use App\Models\Organization;
use App\Models\OrganizationSocialProvider;

class LoginMethods
{
    /**
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        $methods = [
            'password' => true,
            'passkey' => true,
            'email_otp' => true,
            'sync_profile' => true,
            'require_verified_email' => true,
            'allow_unverified_email_with_otp' => true,
        ];

        foreach (SocialProvider::cases() as $provider) {
            $methods[$provider->value] = false;
        }

        return $methods;
    }

    /**
     * @return array<string, bool>
     */
    public static function forClient(OAuthClient $client): array
    {
        $configured = is_array($client->login_methods) ? $client->login_methods : [];

        return array_merge(self::defaults(), $configured);
    }

    /**
     * Providers enabled on the app AND configured+enabled on the org.
     *
     * @return list<array{provider: string, label: string}>
     */
    public static function availableSocialButtons(OAuthClient $client, Organization $organization): array
    {
        $methods = self::forClient($client);
        $buttons = [];

        foreach (SocialProvider::cases() as $provider) {
            if (! ($methods[$provider->value] ?? false)) {
                continue;
            }

            $configured = OrganizationSocialProvider::query()
                ->where('organization_id', $organization->id)
                ->where('provider', $provider->value)
                ->where('enabled', true)
                ->exists();

            if (! $configured) {
                continue;
            }

            $buttons[] = [
                'provider' => $provider->value,
                'label' => $provider->label(),
            ];
        }

        return $buttons;
    }
}
