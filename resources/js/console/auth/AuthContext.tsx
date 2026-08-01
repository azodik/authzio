import {
    createContext,
    type ReactNode,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
} from 'react';
import { ApiError, apiGet, apiPost } from '../lib/api';
import { clearClientAuthState } from '../lib/clearClientAuthState';
import { type DemoPolicy, emptyDemoPolicy } from '../lib/demoPolicy';
import type { AuthUser } from '../types';

type LoginResult = {
    mfaRequired: boolean;
};

type AuthMeResponse = {
    user: AuthUser;
    demo?: DemoPolicy;
};

type AuthContextValue = {
    user: AuthUser | null;
    demo: DemoPolicy;
    loading: boolean;
    setUser: (user: AuthUser | null) => void;
    login: (email: string, password: string, remember?: boolean) => Promise<LoginResult>;
    register: (
        name: string,
        email: string,
        password: string,
        passwordConfirmation: string,
        acceptedTerms: boolean,
    ) => Promise<void>;
    logout: () => Promise<void>;
    refresh: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

type AuthProviderProps = {
    children: ReactNode;
};

function isSessionDeadStatus(status: number): boolean {
    return status === 401 || status === 419 || status === 431;
}

export function AuthProvider({ children }: AuthProviderProps) {
    const [user, setUser] = useState<AuthUser | null>(null);
    const [demo, setDemo] = useState<DemoPolicy>(emptyDemoPolicy);
    const [loading, setLoading] = useState(true);

    const refresh = useCallback(async (): Promise<void> => {
        try {
            const response = await apiGet<AuthMeResponse>('/api/v1/auth/me');
            setUser(response.user);
            setDemo(
                response.demo ?? {
                    active: response.user.is_demo,
                    capabilities: {},
                    message: null,
                },
            );
        } catch (error) {
            setUser(null);
            setDemo(emptyDemoPolicy);
            if (error instanceof ApiError && isSessionDeadStatus(error.status)) {
                clearClientAuthState();
            }
        }
    }, []);

    useEffect(() => {
        void (async () => {
            await refresh();
            setLoading(false);
        })();
    }, [refresh]);

    const login = useCallback(
        async (email: string, password: string, remember = false): Promise<LoginResult> => {
            const response = await apiPost<{ user?: AuthUser; mfa_required?: boolean }>(
                '/api/v1/auth/login',
                {
                    email,
                    password,
                    remember,
                },
            );

            if (response.mfa_required) {
                setUser(null);
                return { mfaRequired: true };
            }

            if (!response.user) {
                throw new ApiError(500, 'Login response missing user.');
            }

            setUser(response.user);
            await refresh();
            return { mfaRequired: false };
        },
        [refresh],
    );

    const register = useCallback(
        async (
            name: string,
            email: string,
            password: string,
            passwordConfirmation: string,
            acceptedTerms: boolean,
        ) => {
            const response = await apiPost<{ user: AuthUser }>('/api/v1/auth/register', {
                name,
                email,
                password,
                password_confirmation: passwordConfirmation,
                accepted_terms: acceptedTerms,
            });
            setUser(response.user);
            await refresh();
        },
        [refresh],
    );

    const logout = useCallback(async () => {
        try {
            await apiPost('/api/v1/auth/logout');
        } catch {
            // Still clear local state if the network/session is already gone.
        } finally {
            clearClientAuthState();
            setUser(null);
            setDemo(emptyDemoPolicy);
        }
    }, []);

    const value = useMemo(
        () => ({ user, demo, loading, setUser, login, register, logout, refresh }),
        [user, demo, loading, login, register, logout, refresh],
    );

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within AuthProvider');
    }
    return context;
}
