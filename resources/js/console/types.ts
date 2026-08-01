export type AuthUser = {
    id: number;
    uuid: string;
    name: string;
    email: string;
    avatar_url?: string | null;
    is_active: boolean;
    is_demo: boolean;
    mfa_enabled: boolean;
    last_login_at: string | null;
    preferred_locale?: string;
    theme?: 'light' | 'dark' | 'system';
    email_verified_at?: string | null;
    organizations?: Organization[];
};

export type UserPreferences = {
    preferred_locale: string;
    theme: 'light' | 'dark' | 'system';
};

export type OverviewStats = {
    users: number;
    organizations: number;
    applications: number;
    mfa_enabled_users: number;
    end_users?: number;
};

export type Organization = {
    id: string;
    name: string;
    slug: string;
    subdomain?: string | null;
    primary_domain?: string | null;
    billing_email?: string | null;
    created_at?: string;
};

export type ApplicationTypeOption = {
    value: 'web' | 'spa' | 'native' | 'machine';
    label: string;
    grant_types: string[];
    is_confidential: boolean;
    requires_redirect_uris: boolean;
};

export type LoginLayout = 'centered' | 'form_right' | 'form_left';
export type LoginTheme = 'light' | 'dark';

export type OAuthClient = {
    id: string;
    name: string;
    application_type: 'web' | 'spa' | 'native' | 'machine';
    description: string | null;
    logo_url: string | null;
    primary_color: string;
    background_color: string;
    login_headline: string | null;
    login_description: string | null;
    login_button_label: string;
    show_signup_link: boolean;
    show_forgot_password_link?: boolean;
    default_locale?: string;
    allow_locale_switch?: boolean;
    login_layout?: LoginLayout;
    login_theme?: LoginTheme;
    password_policy: PasswordPolicy | null;
    security_policy: SecurityPolicy | null;
    login_methods: LoginMethods | null;
    terms_url: string | null;
    privacy_url: string | null;
    require_legal_accept: boolean;
    redirect_uris: string[];
    grant_types: string[];
    is_confidential: boolean;
    is_first_party: boolean;
    revoked_at: string | null;
    created_at: string;
    organization_id?: string;
};

export type PasswordPolicy = {
    min_length: number;
    require_mixed_case: boolean;
    require_numbers: boolean;
    require_symbols: boolean;
};

export type SecurityPolicy = {
    mfa_required: boolean;
    session_lifetime_minutes: number;
    single_device: boolean;
};

export type SocialProviderKey =
    | 'google'
    | 'github'
    | 'facebook'
    | 'gitlab'
    | 'linkedin'
    | 'x'
    | 'bitbucket'
    | 'slack';

export type LoginMethods = {
    password: boolean;
    passkey: boolean;
    email_otp: boolean;
    sync_profile: boolean;
    require_verified_email: boolean;
    allow_unverified_email_with_otp: boolean;
} & Record<SocialProviderKey, boolean>;

export const SOCIAL_PROVIDER_OPTIONS: { key: SocialProviderKey; label: string }[] = [
    { key: 'google', label: 'Google' },
    { key: 'github', label: 'GitHub' },
    { key: 'facebook', label: 'Facebook' },
    { key: 'gitlab', label: 'GitLab' },
    { key: 'linkedin', label: 'LinkedIn' },
    { key: 'x', label: 'X' },
    { key: 'bitbucket', label: 'Bitbucket' },
    { key: 'slack', label: 'Slack' },
];

export type PlanEntitlements = {
    plan_slug: string;
    plan_name: string;
    is_free: boolean;
    application_limit: number | null;
    application_count: number;
    can_create_application: boolean;
    allows_custom_domains: boolean;
    allows_email_customization: boolean;
    allows_login_customization: boolean;
    allows_custom_jwks: boolean;
    allows_custom_email_provider: boolean;
    allows_sso: boolean;
    email_daily_limit: number | null;
    email_monthly_limit: number | null;
};

export type Permission = {
    id: string;
    slug: string;
    name: string;
    group: string;
    description?: string | null;
};

export type Role = {
    id: string;
    name: string;
    slug: string;
    description?: string | null;
    is_system: boolean;
    is_owner: boolean;
    members_count?: number;
    permissions?: Permission[];
};

export type RoleSummary = {
    id: string;
    name: string;
    slug: string;
    is_owner?: boolean;
    is_system?: boolean;
};

export type SigningKeySummary = {
    id: string;
    kid: string;
    alg: string;
    is_active: boolean;
    is_custom: boolean;
    created_at: string | null;
    public_jwk: Record<string, string>;
};

export type OidcSettings = {
    issuer: string;
    discovery_url: string;
    endpoints: {
        issuer: string;
        authorization_endpoint: string;
        token_endpoint: string;
        userinfo_endpoint: string;
        revocation_endpoint: string;
        introspection_endpoint: string;
        jwks_uri: string;
        end_session_endpoint?: string;
    };
    keys: SigningKeySummary[];
    entitlements: PlanEntitlements;
};

export type EmailTemplatePreview = {
    subject: string;
    html: string;
    text: string | null;
    variables: Record<string, string>;
};

export type OrganizationMember = {
    id: string;
    role_id: string;
    role: RoleSummary;
    status: string;
    joined_at: string | null;
    user: {
        id: number;
        uuid: string;
        name: string;
        email: string;
        mfa_enabled: boolean;
        last_login_at: string | null;
    };
};

export type InvitationStatus = 'pending' | 'expired' | 'accepted' | 'revoked';

export type OrganizationInvitation = {
    id: string;
    email: string;
    role_id: string;
    role: RoleSummary;
    expires_at: string;
    created_at: string;
    updated_at?: string;
    accepted_at?: string | null;
    revoked_at?: string | null;
    status?: InvitationStatus;
    token?: string;
    organization?: {
        id: string;
        name: string;
        slug: string;
    } | null;
    inviter?: {
        id: number;
        name: string;
        email: string;
    } | null;
};

export type DomainDnsRecord = {
    purpose: string;
    type: string;
    name: string;
    value: string;
    caption?: string;
};

export type OrganizationDomain = {
    id: string;
    host: string;
    type: 'subdomain' | 'custom';
    is_primary: boolean;
    verification_token: string | null;
    cloudflare_hostname_id?: string | null;
    cloudflare_status?: string | null;
    cloudflare_ssl_status?: string | null;
    dns_records?: DomainDnsRecord[] | null;
    verified_at: string | null;
    created_at: string;
};

export type EmailTemplate = {
    id: string;
    slug: string;
    name: string;
    subject: string;
    body_html: string;
    body_text: string | null;
    is_active: boolean;
};

export type AuditLog = {
    id: string;
    action: string;
    resource_type: string | null;
    resource_id: string | null;
    ip_address: string | null;
    created_at: string;
    actor?: {
        id: number;
        name: string;
        email: string;
    } | null;
};

export type BillingPlan = {
    id: string;
    slug: string;
    name: string;
    description: string | null;
    mau_limit: number;
    price_cents_monthly: number;
    currency: string;
    is_public: boolean;
    is_self_serve: boolean;
    features: string[] | null;
};

export type BillingUsageDay = {
    date: string;
    mau: number;
    authentications: number;
};

export type BillingDowngradePreview = {
    from_plan: string;
    to_plan: string;
    losses: string[];
    keeps_access_until_period_end: boolean;
};

export type BillingPlanChangePreview = {
    requires_checkout: boolean;
    is_upgrade: boolean;
    effective_at: 'immediately' | 'next_billing_date' | null;
    from_plan: {
        slug: string;
        name: string;
        price_cents_monthly: number;
    };
    to_plan: {
        slug: string;
        name: string;
        price_cents_monthly: number;
    };
    immediate_charge_cents: number | null;
    currency: string;
    message: string;
};

export type BillingInvoice = {
    payment_id: string;
    amount_cents: number;
    currency: string;
    status: string | null;
    created_at: string | null;
    invoice_url: string | null;
    download_path: string;
};

export type BillingDashboard = {
    subscription: {
        id: string;
        status: string;
        current_period_start: string | null;
        current_period_end: string | null;
        cancelled_at: string | null;
        dodo_subscription_id: string | null;
        dodo_customer_id: string | null;
        cancel_at_period_end: boolean;
        cancels_at: string | null;
        pending_plan_slug: string | null;
        pending_plan_kind: 'upgrade' | 'downgrade' | null;
        pending_requires_payment: boolean;
        scheduled_plan_change_at: string | null;
    };
    plan: BillingPlan;
    usage: {
        mau: number;
        mau_limit: number;
        utilization_percent: number;
        over_limit: boolean;
        authentication_count: number;
        year_month: string;
        daily: BillingUsageDay[];
    };
    plans: BillingPlan[];
    entitlements: PlanEntitlements;
    downgrade: BillingDowngradePreview | null;
    dodo_configured: boolean;
    billing_enabled: boolean;
};

export type EndUserApplication = {
    id: string;
    name: string | null;
    application_type: string | null;
    last_login_at: string | null;
    sign_in_count: number;
};

export type EndUser = {
    id: number;
    uuid: string | null;
    name: string | null;
    email: string | null;
    email_verified_at: string | null;
    is_active: boolean | null;
    preferred_locale: string | null;
    last_login_at: string | null;
    last_seen_at: string | null;
    applications: EndUserApplication[];
};

export type EmailProviderDriver = 'smtp' | 'resend' | 'postmark' | 'ses' | 'mailgun';

export type EmailProviderInfo = {
    driver: EmailProviderDriver;
    from_address: string;
    from_name: string | null;
    is_active: boolean;
    verified_at: string | null;
    last_error: string | null;
    has_credentials: boolean;
};

export type EmailUsageSnapshot = {
    daily_count: number;
    monthly_count: number;
    daily_limit: number | null;
    monthly_limit: number | null;
    can_send: boolean;
};
