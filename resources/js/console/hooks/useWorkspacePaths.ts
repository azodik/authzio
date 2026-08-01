import { useMemo } from 'react';
import { ACCOUNT_PATHS, appPath, orgPath } from '../lib/paths';
import { useWorkspace } from '../workspace/WorkspaceContext';

export function useWorkspacePaths() {
    const { organization, application } = useWorkspace();
    const orgId = organization?.id ?? null;
    const appId = application?.id ?? null;

    return useMemo(() => {
        const account = {
            organizations: ACCOUNT_PATHS.organizations,
            settings: ACCOUNT_PATHS.settings,
        };

        if (!orgId) {
            return {
                orgId: null as string | null,
                appId: null as string | null,
                overview: ACCOUNT_PATHS.organizations,
                applications: ACCOUNT_PATHS.organizations,
                members: ACCOUNT_PATHS.organizations,
                roles: ACCOUNT_PATHS.organizations,
                domains: ACCOUNT_PATHS.organizations,
                emailTemplates: ACCOUNT_PATHS.organizations,
                emailProvider: ACCOUNT_PATHS.organizations,
                socialProviders: ACCOUNT_PATHS.organizations,
                sso: ACCOUNT_PATHS.organizations,
                billing: ACCOUNT_PATHS.organizations,
                users: ACCOUNT_PATHS.organizations,
                auditLogs: ACCOUNT_PATHS.organizations,
                ...account,
                appHome: (_id: string): string => ACCOUNT_PATHS.organizations,
                appOidc: (_id: string): string => ACCOUNT_PATHS.organizations,
                currentApp: null as string | null,
                currentAppOidc: null as string | null,
            };
        }

        return {
            orgId,
            appId,
            overview: orgPath(orgId),
            applications: orgPath(orgId, 'applications'),
            members: orgPath(orgId, 'members'),
            roles: orgPath(orgId, 'roles'),
            domains: orgPath(orgId, 'domains'),
            emailTemplates: orgPath(orgId, 'email-templates'),
            emailProvider: orgPath(orgId, 'email-provider'),
            socialProviders: orgPath(orgId, 'social-providers'),
            sso: orgPath(orgId, 'sso'),
            billing: orgPath(orgId, 'billing'),
            users: orgPath(orgId, 'users'),
            auditLogs: orgPath(orgId, 'audit-logs'),
            ...account,
            appHome: (id: string): string => appPath(orgId, id),
            appOidc: (id: string): string => appPath(orgId, id, 'oidc'),
            currentApp: appId ? appPath(orgId, appId) : null,
            currentAppOidc: appId ? appPath(orgId, appId, 'oidc') : null,
        };
    }, [orgId, appId]);
}
