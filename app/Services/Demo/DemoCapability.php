<?php

namespace App\Services\Demo;

enum DemoCapability: string
{
    case AuthLogout = 'auth.logout';
    case AuthPreferences = 'auth.preferences';
    case AuthPassword = 'auth.password';
    case AuthProfile = 'auth.profile';
    case AuthAvatar = 'auth.avatar';
    case AuthMfa = 'auth.mfa';
    case AuthLinkedAccounts = 'auth.linked_accounts';
    case AuthEmailResend = 'auth.email_resend';
    case InvitationAccept = 'invitation.accept';
    case OrganizationCreate = 'organization.create';
    case MemberInvite = 'member.invite';
    case MemberUpdate = 'member.update';
    case MemberDestroy = 'member.destroy';
    case RoleMutate = 'role.mutate';
    case DomainMutate = 'domain.mutate';
    case ApplicationCreate = 'application.create';
    case ApplicationUpdate = 'application.update';
    case ApplicationLogo = 'application.logo';
    case ApplicationDestroy = 'application.destroy';
    case EmailTemplatePreview = 'email_template.preview';
    case EmailTemplateUpdate = 'email_template.update';
    case EmailProviderMutate = 'email_provider.mutate';
    case BillingCheckout = 'billing.checkout';
    case BillingPreview = 'billing.preview';
    case OidcKeys = 'oidc.keys';
    case SocialProviderMutate = 'social_provider.mutate';
    case SsoMutate = 'sso.mutate';
    case OAuthHosted = 'oauth.hosted';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $capability): string => $capability->value, self::cases());
    }
}
