<?php

namespace App\Enums;

/**
 * Global organization permission catalog.
 */
enum OrgPermission: string
{
    case MembersRead = 'members.read';
    case MembersInvite = 'members.invite';
    case MembersRemove = 'members.remove';
    case MembersManageRoles = 'members.manage_roles';

    case ApplicationsRead = 'applications.read';
    case ApplicationsWrite = 'applications.write';
    case ApplicationsDelete = 'applications.delete';

    case DomainsRead = 'domains.read';
    case DomainsWrite = 'domains.write';

    case EmailTemplatesRead = 'email_templates.read';
    case EmailTemplatesWrite = 'email_templates.write';

    case EmailProviderRead = 'email_provider.read';
    case EmailProviderWrite = 'email_provider.write';

    case SocialProvidersRead = 'social_providers.read';
    case SocialProvidersWrite = 'social_providers.write';

    case SsoRead = 'sso.read';
    case SsoWrite = 'sso.write';

    case AuditLogsRead = 'audit_logs.read';

    case BillingRead = 'billing.read';
    case BillingManage = 'billing.manage';

    case EndUsersRead = 'end_users.read';

    case SettingsWrite = 'settings.write';

    case OidcManage = 'oidc.manage';

    case RolesRead = 'roles.read';
    case RolesWrite = 'roles.write';

    /**
     * @return list<array{slug: string, name: string, group: string, description: string}>
     */
    public static function catalog(): array
    {
        return [
            ['slug' => self::MembersRead->value, 'name' => 'View members', 'group' => 'members', 'description' => 'View organization members and invitations.'],
            ['slug' => self::MembersInvite->value, 'name' => 'Invite members', 'group' => 'members', 'description' => 'Invite people to the organization.'],
            ['slug' => self::MembersRemove->value, 'name' => 'Remove members', 'group' => 'members', 'description' => 'Remove members from the organization.'],
            ['slug' => self::MembersManageRoles->value, 'name' => 'Manage member roles', 'group' => 'members', 'description' => 'Change roles assigned to members.'],

            ['slug' => self::ApplicationsRead->value, 'name' => 'View applications', 'group' => 'applications', 'description' => 'View OAuth applications.'],
            ['slug' => self::ApplicationsWrite->value, 'name' => 'Manage applications', 'group' => 'applications', 'description' => 'Create and update OAuth applications.'],
            ['slug' => self::ApplicationsDelete->value, 'name' => 'Delete applications', 'group' => 'applications', 'description' => 'Revoke OAuth applications.'],

            ['slug' => self::DomainsRead->value, 'name' => 'View domains', 'group' => 'domains', 'description' => 'View custom domains and subdomains.'],
            ['slug' => self::DomainsWrite->value, 'name' => 'Manage domains', 'group' => 'domains', 'description' => 'Add and verify domains.'],

            ['slug' => self::EmailTemplatesRead->value, 'name' => 'View email templates', 'group' => 'email', 'description' => 'View transactional email templates.'],
            ['slug' => self::EmailTemplatesWrite->value, 'name' => 'Edit email templates', 'group' => 'email', 'description' => 'Edit transactional email templates.'],

            ['slug' => self::EmailProviderRead->value, 'name' => 'View email provider', 'group' => 'email', 'description' => 'View email delivery settings and usage.'],
            ['slug' => self::EmailProviderWrite->value, 'name' => 'Manage email provider', 'group' => 'email', 'description' => 'Configure BYO email providers.'],

            ['slug' => self::SocialProvidersRead->value, 'name' => 'View social login', 'group' => 'social', 'description' => 'View social login providers.'],
            ['slug' => self::SocialProvidersWrite->value, 'name' => 'Manage social login', 'group' => 'social', 'description' => 'Configure social login providers.'],

            ['slug' => self::SsoRead->value, 'name' => 'View SSO', 'group' => 'sso', 'description' => 'View enterprise OIDC SSO connections.'],
            ['slug' => self::SsoWrite->value, 'name' => 'Manage SSO', 'group' => 'sso', 'description' => 'Configure enterprise OIDC SSO connections.'],

            ['slug' => self::AuditLogsRead->value, 'name' => 'View audit logs', 'group' => 'audit', 'description' => 'View organization audit logs.'],

            ['slug' => self::BillingRead->value, 'name' => 'View billing', 'group' => 'billing', 'description' => 'View plan and usage.'],
            ['slug' => self::BillingManage->value, 'name' => 'Manage billing', 'group' => 'billing', 'description' => 'Change plan and billing settings.'],

            ['slug' => self::EndUsersRead->value, 'name' => 'View end-users', 'group' => 'users', 'description' => 'View application end-users.'],

            ['slug' => self::SettingsWrite->value, 'name' => 'Manage settings', 'group' => 'settings', 'description' => 'Update organization settings.'],

            ['slug' => self::OidcManage->value, 'name' => 'Manage OIDC', 'group' => 'oidc', 'description' => 'Manage OIDC signing keys and discovery.'],

            ['slug' => self::RolesRead->value, 'name' => 'View roles', 'group' => 'roles', 'description' => 'View roles and permissions.'],
            ['slug' => self::RolesWrite->value, 'name' => 'Manage roles', 'group' => 'roles', 'description' => 'Create and edit custom roles.'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allSlugs(): array
    {
        return array_map(static fn (self $permission): string => $permission->value, self::cases());
    }

    /**
     * @return list<string>
     */
    public static function adminDefaults(): array
    {
        return array_values(array_filter(
            self::allSlugs(),
            static fn (string $slug): bool => $slug !== self::MembersManageRoles->value,
        ));
    }

    /**
     * @return list<string>
     */
    public static function memberDefaults(): array
    {
        return [
            self::MembersRead->value,
            self::ApplicationsRead->value,
            self::DomainsRead->value,
            self::EmailTemplatesRead->value,
            self::EmailProviderRead->value,
            self::SocialProvidersRead->value,
            self::SsoRead->value,
            self::AuditLogsRead->value,
            self::BillingRead->value,
            self::EndUsersRead->value,
            self::RolesRead->value,
        ];
    }
}
