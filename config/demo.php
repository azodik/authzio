<?php

use App\Services\Demo\DemoCapability;
use App\Services\Demo\DemoMode;

return [
    /*
    |--------------------------------------------------------------------------
    | Demo entitlement façade
    |--------------------------------------------------------------------------
    |
    | Demo org API responses clone this plan's entitlement flags so the console
    | unlocks paid UI. Hard boundaries (domains, billing checkout, OAuth) still deny.
    |
    */
    'entitlement_plan_slug' => 'growth',

    'banner' => 'Demo session — explore freely. Some changes are temporary; identity, domains, and hosted login stay locked.',

    'messages' => [
        'default' => 'This action is not available on the demo account.',
        DemoCapability::AuthPassword->value => 'The demo account password cannot be changed.',
        DemoCapability::AuthProfile->value => 'Demo profile details cannot be changed.',
        DemoCapability::AuthAvatar->value => 'Demo avatar cannot be changed.',
        DemoCapability::AuthMfa->value => 'MFA settings are locked on the demo account.',
        DemoCapability::AuthLinkedAccounts->value => 'Linked accounts cannot be changed on the demo account.',
        DemoCapability::DomainMutate->value => 'Custom domains are locked on the demo account.',
        DemoCapability::BillingCheckout->value => 'Billing changes are locked on the demo account.',
        DemoCapability::OAuthHosted->value => 'Demo accounts cannot use hosted login or the OAuth server.',
        DemoCapability::OrganizationCreate->value => 'Creating organizations is locked on the demo account.',
        DemoCapability::ApplicationDestroy->value => 'Revoking the demo application is locked.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Capability modes for demo users
    |--------------------------------------------------------------------------
    */
    'capabilities' => [
        DemoCapability::AuthLogout->value => DemoMode::Allow->value,
        DemoCapability::AuthPreferences->value => DemoMode::Allow->value,
        DemoCapability::AuthPassword->value => DemoMode::Deny->value,
        DemoCapability::AuthProfile->value => DemoMode::Deny->value,
        DemoCapability::AuthAvatar->value => DemoMode::Deny->value,
        DemoCapability::AuthMfa->value => DemoMode::Deny->value,
        DemoCapability::AuthLinkedAccounts->value => DemoMode::Deny->value,
        DemoCapability::AuthEmailResend->value => DemoMode::Deny->value,
        DemoCapability::InvitationAccept->value => DemoMode::Deny->value,
        DemoCapability::OrganizationCreate->value => DemoMode::Deny->value,
        DemoCapability::MemberInvite->value => DemoMode::Soft->value,
        DemoCapability::MemberUpdate->value => DemoMode::Soft->value,
        DemoCapability::MemberDestroy->value => DemoMode::Soft->value,
        DemoCapability::RoleMutate->value => DemoMode::Soft->value,
        DemoCapability::DomainMutate->value => DemoMode::Deny->value,
        DemoCapability::ApplicationCreate->value => DemoMode::Soft->value,
        DemoCapability::ApplicationUpdate->value => DemoMode::Soft->value,
        DemoCapability::ApplicationLogo->value => DemoMode::Soft->value,
        DemoCapability::ApplicationDestroy->value => DemoMode::Deny->value,
        DemoCapability::EmailTemplatePreview->value => DemoMode::Allow->value,
        DemoCapability::EmailTemplateUpdate->value => DemoMode::Soft->value,
        DemoCapability::EmailProviderMutate->value => DemoMode::Soft->value,
        DemoCapability::BillingCheckout->value => DemoMode::Deny->value,
        DemoCapability::BillingPreview->value => DemoMode::Deny->value,
        DemoCapability::OidcKeys->value => DemoMode::Soft->value,
        DemoCapability::SocialProviderMutate->value => DemoMode::Soft->value,
        DemoCapability::SsoMutate->value => DemoMode::Soft->value,
        DemoCapability::OAuthHosted->value => DemoMode::Deny->value,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route → capability map (first match wins)
    |--------------------------------------------------------------------------
    |
    | uri patterns use * as a single path segment wildcard.
    |
    */
    'routes' => [
        ['methods' => ['POST'], 'uri' => 'api/v1/auth/logout', 'capability' => DemoCapability::AuthLogout->value],
        ['methods' => ['PATCH'], 'uri' => 'api/v1/auth/preferences', 'capability' => DemoCapability::AuthPreferences->value],
        ['methods' => ['POST', 'DELETE'], 'uri' => 'api/v1/auth/avatar', 'capability' => DemoCapability::AuthAvatar->value],
        ['methods' => ['POST'], 'uri' => 'api/v1/auth/email/resend-confirmation', 'capability' => DemoCapability::AuthEmailResend->value],
        ['methods' => ['POST'], 'uri' => 'api/v1/auth/mfa/*', 'capability' => DemoCapability::AuthMfa->value],
        ['methods' => ['DELETE'], 'uri' => 'api/v1/auth/linked-accounts/*', 'capability' => DemoCapability::AuthLinkedAccounts->value],
        ['methods' => ['POST'], 'uri' => 'api/v1/invitations/*/accept', 'capability' => DemoCapability::InvitationAccept->value],
        ['methods' => ['POST'], 'uri' => 'api/v1/organizations', 'capability' => DemoCapability::OrganizationCreate->value],
        ['methods' => ['POST'], 'uri' => 'api/v1/organizations/*/invitations', 'capability' => DemoCapability::MemberInvite->value],
        ['methods' => ['POST'], 'uri' => 'api/v1/organizations/*/invitations/*/resend', 'capability' => DemoCapability::MemberInvite->value],
        ['methods' => ['DELETE'], 'uri' => 'api/v1/organizations/*/invitations/*', 'capability' => DemoCapability::MemberInvite->value],
        ['methods' => ['PATCH'], 'uri' => 'api/v1/organizations/*/members/*/role', 'capability' => DemoCapability::MemberUpdate->value],
        ['methods' => ['DELETE'], 'uri' => 'api/v1/organizations/*/members/*', 'capability' => DemoCapability::MemberDestroy->value],
        ['methods' => ['POST', 'PUT', 'DELETE'], 'uri' => 'api/v1/organizations/*/roles', 'capability' => DemoCapability::RoleMutate->value],
        ['methods' => ['PUT', 'DELETE'], 'uri' => 'api/v1/organizations/*/roles/*', 'capability' => DemoCapability::RoleMutate->value],
        ['methods' => ['POST', 'PUT', 'DELETE'], 'uri' => 'api/v1/organizations/*/domains', 'capability' => DemoCapability::DomainMutate->value],
        ['methods' => ['PUT'], 'uri' => 'api/v1/organizations/*/domains/subdomain', 'capability' => DemoCapability::DomainMutate->value],
        ['methods' => ['POST', 'DELETE'], 'uri' => 'api/v1/organizations/*/domains/*', 'capability' => DemoCapability::DomainMutate->value],
        ['methods' => ['POST'], 'uri' => 'api/v1/organizations/*/applications', 'capability' => DemoCapability::ApplicationCreate->value],
        ['methods' => ['POST', 'DELETE'], 'uri' => 'api/v1/organizations/*/applications/*/logo', 'capability' => DemoCapability::ApplicationLogo->value],
        ['methods' => ['PUT', 'PATCH'], 'uri' => 'api/v1/organizations/*/applications/*', 'capability' => DemoCapability::ApplicationUpdate->value],
        ['methods' => ['DELETE'], 'uri' => 'api/v1/organizations/*/applications/*', 'capability' => DemoCapability::ApplicationDestroy->value],
        ['methods' => ['POST'], 'uri' => 'api/v1/organizations/*/email-templates/*/preview', 'capability' => DemoCapability::EmailTemplatePreview->value],
        ['methods' => ['PUT', 'PATCH'], 'uri' => 'api/v1/organizations/*/email-templates/*', 'capability' => DemoCapability::EmailTemplateUpdate->value],
        ['methods' => ['PUT', 'POST'], 'uri' => 'api/v1/organizations/*/email-provider', 'capability' => DemoCapability::EmailProviderMutate->value],
        ['methods' => ['POST'], 'uri' => 'api/v1/organizations/*/email-provider/test', 'capability' => DemoCapability::EmailProviderMutate->value],
        ['methods' => ['POST'], 'uri' => 'api/v1/organizations/*/billing/checkout', 'capability' => DemoCapability::BillingCheckout->value],
        ['methods' => ['POST'], 'uri' => 'api/v1/organizations/*/billing/preview-change', 'capability' => DemoCapability::BillingPreview->value],
        ['methods' => ['POST'], 'uri' => 'api/v1/organizations/*/oidc/keys/*', 'capability' => DemoCapability::OidcKeys->value],
        ['methods' => ['POST'], 'uri' => 'api/v1/organizations/*/social-providers', 'capability' => DemoCapability::SocialProviderMutate->value],
        ['methods' => ['POST', 'PUT', 'DELETE'], 'uri' => 'api/v1/organizations/*/sso-connections', 'capability' => DemoCapability::SsoMutate->value],
        ['methods' => ['POST', 'PUT', 'DELETE'], 'uri' => 'api/v1/organizations/*/sso-connections/*', 'capability' => DemoCapability::SsoMutate->value],
    ],
];
