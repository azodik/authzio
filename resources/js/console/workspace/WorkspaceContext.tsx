import {
    createContext,
    type ReactNode,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
} from 'react';
import { ApiError, apiGet } from '../lib/api';
import type { OAuthClient, Organization, PlanEntitlements, UserPreferences } from '../types';

const ORG_KEY = 'authzio.active_organization_id';
const APP_KEY = 'authzio.active_application_id';

type WorkspaceContextValue = {
    organizations: Organization[];
    organization: Organization | null;
    applications: OAuthClient[];
    application: OAuthClient | null;
    entitlements: PlanEntitlements | null;
    permissions: string[];
    locales: string[];
    domainRoot: string;
    userPreferences: UserPreferences;
    loading: boolean;
    error: string | null;
    can: (permission: string) => boolean;
    setOrganizationId: (id: string, options?: { clearApplication?: boolean }) => void;
    setApplicationId: (id: string | null) => void;
    setUserPreferences: (prefs: Partial<UserPreferences>) => void;
    refresh: () => Promise<void>;
};

const WorkspaceContext = createContext<WorkspaceContextValue | null>(null);

export type { WorkspaceContextValue };
export { WorkspaceContext };

const defaultPreferences: UserPreferences = {
    preferred_locale: 'en',
    theme: 'system',
};

type WorkspaceProviderProps = {
    children: ReactNode;
};

export function WorkspaceProvider({ children }: WorkspaceProviderProps) {
    const [organizations, setOrganizations] = useState<Organization[]>([]);
    const [organization, setOrganization] = useState<Organization | null>(null);
    const [applications, setApplications] = useState<OAuthClient[]>([]);
    const [application, setApplication] = useState<OAuthClient | null>(null);
    const [entitlements, setEntitlements] = useState<PlanEntitlements | null>(null);
    const [permissions, setPermissions] = useState<string[]>([]);
    const [locales, setLocales] = useState<string[]>(['en', 'fr', 'de', 'es', 'hi']);
    const [domainRoot, setDomainRoot] = useState('authzio.test');
    const [userPreferences, setUserPreferencesState] =
        useState<UserPreferences>(defaultPreferences);
    const [organizationId, setOrganizationIdState] = useState<string | null>(() =>
        localStorage.getItem(ORG_KEY),
    );
    const [applicationId, setApplicationIdState] = useState<string | null>(() =>
        localStorage.getItem(APP_KEY),
    );
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const refresh = useCallback(async (): Promise<void> => {
        const params = new URLSearchParams();
        if (organizationId) {
            params.set('organization_id', organizationId);
        }
        if (applicationId) {
            params.set('application_id', applicationId);
        }

        const query = params.toString();
        const response = await apiGet<{
            organizations: Organization[];
            organization: Organization | null;
            applications: OAuthClient[];
            application: OAuthClient | null;
            entitlements: PlanEntitlements | null;
            permissions?: string[];
            locales?: string[];
            domain_root?: string;
            user_preferences?: UserPreferences;
        }>(`/api/v1/workspace${query !== '' ? `?${query}` : ''}`);

        setOrganizations(response.organizations);
        setOrganization(response.organization);
        setApplications(response.applications);
        setEntitlements(response.entitlements);
        setPermissions(response.permissions ?? []);
        if (response.locales && response.locales.length > 0) {
            setLocales(response.locales);
        }
        if (response.domain_root) {
            setDomainRoot(response.domain_root);
        }
        if (response.user_preferences) {
            setUserPreferencesState(response.user_preferences);
        }

        const resolvedOrgId = response.organization?.id ?? null;
        if (resolvedOrgId && resolvedOrgId !== organizationId) {
            localStorage.setItem(ORG_KEY, resolvedOrgId);
            setOrganizationIdState(resolvedOrgId);
        } else if (resolvedOrgId === null && organizationId) {
            localStorage.removeItem(ORG_KEY);
            setOrganizationIdState(null);
        }

        let nextApp = response.application;
        if (nextApp === null && response.applications.length === 1) {
            nextApp = response.applications[0] ?? null;
        }
        if (
            nextApp === null &&
            applicationId &&
            response.applications.some((item) => item.id === applicationId)
        ) {
            nextApp = response.applications.find((item) => item.id === applicationId) ?? null;
        }

        setApplication(nextApp);
        if (nextApp) {
            localStorage.setItem(APP_KEY, nextApp.id);
            if (nextApp.id !== applicationId) {
                setApplicationIdState(nextApp.id);
            }
        } else if (
            applicationId &&
            !response.applications.some((item) => item.id === applicationId)
        ) {
            localStorage.removeItem(APP_KEY);
            setApplicationIdState(null);
        }

        setError(null);
    }, [organizationId, applicationId]);

    useEffect(() => {
        void (async () => {
            setLoading(true);
            try {
                await refresh();
            } catch (err) {
                setError(err instanceof ApiError ? err.message : 'Failed to load workspace.');
            } finally {
                setLoading(false);
            }
        })();
    }, [refresh]);

    const setOrganizationId = useCallback(
        (id: string, options?: { clearApplication?: boolean }) => {
            localStorage.setItem(ORG_KEY, id);
            const clearApplication = options?.clearApplication !== false;
            if (clearApplication) {
                localStorage.removeItem(APP_KEY);
                setApplicationIdState(null);
            }
            setOrganizationIdState(id);
        },
        [],
    );

    const setApplicationId = useCallback((id: string | null) => {
        if (id === null) {
            localStorage.removeItem(APP_KEY);
        } else {
            localStorage.setItem(APP_KEY, id);
        }
        setApplicationIdState(id);
    }, []);

    const setUserPreferences = useCallback((prefs: Partial<UserPreferences>) => {
        setUserPreferencesState((current) => ({ ...current, ...prefs }));
    }, []);

    const can = useCallback(
        (permission: string): boolean => {
            if (permissions.length === 0) {
                return true;
            }
            return permissions.includes(permission);
        },
        [permissions],
    );

    const value = useMemo<WorkspaceContextValue>(
        () => ({
            organizations,
            organization,
            applications,
            application,
            entitlements,
            permissions,
            locales,
            domainRoot,
            userPreferences,
            loading,
            error,
            can,
            setOrganizationId,
            setApplicationId,
            setUserPreferences,
            refresh,
        }),
        [
            organizations,
            organization,
            applications,
            application,
            entitlements,
            permissions,
            locales,
            domainRoot,
            userPreferences,
            loading,
            error,
            can,
            setOrganizationId,
            setApplicationId,
            setUserPreferences,
            refresh,
        ],
    );

    return <WorkspaceContext.Provider value={value}>{children}</WorkspaceContext.Provider>;
}

export function useWorkspace(): WorkspaceContextValue {
    const context = useContext(WorkspaceContext);
    if (context === null) {
        throw new Error('useWorkspace must be used within WorkspaceProvider');
    }
    return context;
}
