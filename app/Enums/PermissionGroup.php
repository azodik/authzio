<?php

namespace App\Enums;

enum PermissionGroup: string
{
    case Members = 'members';
    case Applications = 'applications';
    case Domains = 'domains';
    case Email = 'email';
    case Social = 'social';
    case Audit = 'audit';
    case Billing = 'billing';
    case Users = 'users';
    case Settings = 'settings';
    case Oidc = 'oidc';
    case Roles = 'roles';

    public function label(): string
    {
        return match ($this) {
            self::Members => 'Members & invites',
            self::Applications => 'Applications',
            self::Domains => 'Domains',
            self::Email => 'Email',
            self::Social => 'Social login',
            self::Audit => 'Audit',
            self::Billing => 'Billing',
            self::Users => 'End-users',
            self::Settings => 'Settings',
            self::Oidc => 'OIDC',
            self::Roles => 'Roles',
        };
    }

    /**
     * @return list<array{slug: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $group): array => [
                'slug' => $group->value,
                'label' => $group->label(),
            ],
            self::cases(),
        );
    }
}
