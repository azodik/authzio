export const queryKeys = {
    auth: {
        me: () => ['auth', 'me'] as const,
    },
    workspace: (organizationId?: string | null, applicationId?: string | null) =>
        ['workspace', organizationId ?? null, applicationId ?? null] as const,
    org: (orgId: string) => ({
        all: ['org', orgId] as const,
        overview: () => ['org', orgId, 'overview'] as const,
        members: () => ['org', orgId, 'members'] as const,
        invitations: () => ['org', orgId, 'invitations'] as const,
        invitationHistory: () => ['org', orgId, 'invitation-history'] as const,
        roles: () => ['org', orgId, 'roles'] as const,
        applications: () => ['org', orgId, 'applications'] as const,
        application: (appId: string) => ['org', orgId, 'applications', appId] as const,
        oidc: (appId: string) => ['org', orgId, 'applications', appId, 'oidc'] as const,
        domains: () => ['org', orgId, 'domains'] as const,
        emailTemplates: () => ['org', orgId, 'email-templates'] as const,
        emailProvider: () => ['org', orgId, 'email-provider'] as const,
        socialProviders: () => ['org', orgId, 'social-providers'] as const,
        sso: () => ['org', orgId, 'sso'] as const,
        billing: () => ['org', orgId, 'billing'] as const,
        users: (params?: Record<string, string>) => ['org', orgId, 'users', params ?? {}] as const,
        auditLogs: (params?: Record<string, string>) =>
            ['org', orgId, 'audit-logs', params ?? {}] as const,
    }),
    account: {
        organizations: () => ['account', 'organizations'] as const,
        invitations: () => ['account', 'invitations'] as const,
        settings: () => ['account', 'settings'] as const,
    },
} as const;
