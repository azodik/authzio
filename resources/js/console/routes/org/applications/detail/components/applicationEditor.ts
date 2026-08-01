import type { Dispatch, SetStateAction } from 'react';
import type {
    LoginLayout,
    LoginMethods,
    LoginTheme,
    OAuthClient,
    PasswordPolicy,
    PlanEntitlements,
    SecurityPolicy,
} from '@/types';

export type EmailTemplateSummary = {
    id: string;
    slug: string;
    name: string;
    subject: string;
    preview_subject: string;
    preview_html: string;
};
export type ApplicationResponse = {
    data: OAuthClient;
    entitlements: PlanEntitlements;
    preview_url: string;
    defaults: {
        password_policy: PasswordPolicy;
        security_policy: SecurityPolicy;
        login_methods: LoginMethods;
    };
    email_templates: EmailTemplateSummary[];
};
export type ApplicationDraft = {
    name: string;
    description: string;
    redirectUris: string;
    logoUrl: string;
    primaryColor: string;
    backgroundColor: string;
    headline: string;
    loginDescription: string;
    buttonLabel: string;
    showSignupLink: boolean;
    showForgotPasswordLink: boolean;
    defaultLocale: string;
    allowLocaleSwitch: boolean;
    loginLayout: LoginLayout;
    loginTheme: LoginTheme;
    passwordPolicy: PasswordPolicy;
    securityPolicy: SecurityPolicy;
    loginMethods: LoginMethods;
    termsUrl: string;
    privacyUrl: string;
    requireLegalAccept: boolean;
};
export type SetApplicationDraft = Dispatch<SetStateAction<ApplicationDraft>>;

export function draftFromResponse(response: ApplicationResponse): ApplicationDraft {
    const client = response.data;
    return {
        name: client.name,
        description: client.description ?? '',
        redirectUris: client.redirect_uris.join('\n'),
        logoUrl: client.logo_url ?? '',
        primaryColor: client.primary_color || '#0F766E',
        backgroundColor: client.background_color || '#F4F7F6',
        headline: client.login_headline ?? 'Sign in',
        loginDescription: client.login_description ?? '',
        buttonLabel: client.login_button_label || 'Continue',
        showSignupLink: client.show_signup_link,
        showForgotPasswordLink: client.show_forgot_password_link ?? true,
        defaultLocale: client.default_locale ?? 'en',
        allowLocaleSwitch: client.allow_locale_switch ?? true,
        loginLayout: client.login_layout ?? 'form_right',
        loginTheme: client.login_theme ?? 'light',
        passwordPolicy: client.password_policy ?? response.defaults.password_policy,
        securityPolicy: client.security_policy ?? response.defaults.security_policy,
        loginMethods: client.login_methods ?? response.defaults.login_methods,
        termsUrl: client.terms_url ?? '',
        privacyUrl: client.privacy_url ?? '',
        requireLegalAccept: client.require_legal_accept,
    };
}

export function applicationPayload(draft: ApplicationDraft) {
    return {
        name: draft.name.trim(),
        description: draft.description.trim() || null,
        redirect_uris: draft.redirectUris
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean),
        logo_url: draft.logoUrl.trim() || null,
        primary_color: draft.primaryColor,
        background_color: draft.backgroundColor,
        login_headline: draft.headline.trim() || null,
        login_description: draft.loginDescription.trim() || null,
        login_button_label: draft.buttonLabel.trim() || 'Continue',
        show_signup_link: draft.showSignupLink,
        show_forgot_password_link: draft.showForgotPasswordLink,
        default_locale: draft.defaultLocale,
        allow_locale_switch: draft.allowLocaleSwitch,
        login_layout: draft.loginLayout,
        login_theme: draft.loginTheme,
        password_policy: draft.passwordPolicy,
        security_policy: draft.securityPolicy,
        login_methods: draft.loginMethods,
        terms_url: draft.termsUrl.trim() || null,
        privacy_url: draft.privacyUrl.trim() || null,
        require_legal_accept: draft.requireLegalAccept,
    };
}
