import { useMutation, useQuery } from '@tanstack/react-query';
import { type FormEvent, useEffect, useState } from 'react';
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router';
import { useAuth } from '@/auth/AuthContext';
import { useI18n } from '@/hooks/useI18n';
import { ApiError, apiGet } from '@/lib/api';
import {
    captureRedirectFromSearchParams,
    consumePendingAuthRedirect,
    peekPendingAuthRedirect,
} from '@/lib/authRedirect';

const DEMO_EMAIL = 'demo@authzio.com';
const DEMO_PASSWORD = 'AuthzioDemo2026!';

type SocialProvidersResponse = {
    providers: {
        google?: boolean;
        github?: boolean;
    };
};

function oauthErrorMessage(code: string | null, t: (key: string) => string): string | null {
    switch (code) {
        case 'link_required':
            return t('console.auth.error_link_required');
        case 'oauth_failed':
            return t('console.auth.error_oauth_failed');
        case 'oauth_state':
            return t('console.auth.error_oauth_state');
        case 'not_configured':
            return t('console.auth.error_not_configured');
        case 'deactivated':
            return t('console.auth.error_deactivated');
        default:
            return null;
    }
}

export function LoginPage() {
    const { user, loading, login } = useAuth();
    const { t } = useI18n();
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const fromDemo = searchParams.get('demo') === '1';
    const [email, setEmail] = useState(fromDemo ? DEMO_EMAIL : '');
    const [password, setPassword] = useState(fromDemo ? DEMO_PASSWORD : '');
    const [error, setError] = useState<string | null>(() =>
        oauthErrorMessage(searchParams.get('error'), t),
    );
    const loginMutation = useMutation({
        mutationFn: (credentials: { email: string; password: string }) =>
            login(credentials.email, credentials.password, true),
    });
    const socialProviders = useQuery({
        queryKey: ['auth', 'social-providers'],
        queryFn: () => apiGet<SocialProvidersResponse>('/api/v1/auth/social-providers'),
        staleTime: 60_000,
    });

    useEffect(() => {
        captureRedirectFromSearchParams(searchParams);
        if (searchParams.get('demo') === '1') {
            setEmail((current) => (current === '' ? DEMO_EMAIL : current));
            setPassword((current) => (current === '' ? DEMO_PASSWORD : current));
        }
        const oauthError = oauthErrorMessage(searchParams.get('error'), t);
        if (oauthError !== null) {
            setError(oauthError);
        }
    }, [searchParams, t]);

    if (!loading && user) {
        return <Navigate to={peekPendingAuthRedirect() ?? '/'} replace />;
    }

    async function onSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setError(null);
        try {
            const result = await loginMutation.mutateAsync({ email: email.trim(), password });
            if (result.mfaRequired) {
                navigate('/mfa', { replace: true });
                return;
            }
            navigate(consumePendingAuthRedirect('/'), { replace: true });
        } catch (err) {
            if (err instanceof ApiError) {
                setError(err.errors.email?.[0] ?? err.message);
            } else {
                setError(t('console.auth.unable_sign_in'));
            }
        }
    }

    const registerQuery = new URLSearchParams();
    const redirectParam = searchParams.get('redirect');
    if (redirectParam !== null && redirectParam !== '') {
        registerQuery.set('redirect', redirectParam);
    }
    const registerTo =
        registerQuery.size > 0 ? `/register?${registerQuery.toString()}` : '/register';

    const googleEnabled = socialProviders.data?.providers.google === true;
    const githubEnabled = socialProviders.data?.providers.github === true;
    const showSocial = googleEnabled || githubEnabled;

    return (
        <div className="flex min-h-screen items-center justify-center bg-paper px-4">
            <div className="w-full max-w-md border border-mist bg-paper-elevated p-8">
                <div className="mb-8 flex items-center gap-2.5">
                    <img src="/images/logo.svg" alt="" className="size-7" width={40} height={40} />
                    <span className="font-display text-xl font-bold tracking-tight">Authzio</span>
                </div>

                <h1 className="font-display text-2xl font-bold text-ink">
                    {t('console.auth.sign_in')}
                </h1>
                <p className="mt-2 text-sm text-ink-soft/65">{t('console.auth.sign_in_desc')}</p>
                {fromDemo ? (
                    <p className="mt-2 text-sm text-ink-soft/55">
                        Demo account (read-only):{' '}
                        <span className="font-mono text-ink-soft/80">{DEMO_EMAIL}</span>
                    </p>
                ) : null}

                {showSocial ? (
                    <div className="mt-8 space-y-3">
                        {googleEnabled ? (
                            <a
                                href="/console/auth/google/redirect"
                                className="flex w-full items-center justify-center border border-mist bg-paper px-4 py-2.5 text-sm font-semibold text-ink hover:bg-fog"
                            >
                                {t('console.auth.continue_with_google')}
                            </a>
                        ) : null}
                        {githubEnabled ? (
                            <a
                                href="/console/auth/github/redirect"
                                className="flex w-full items-center justify-center border border-mist bg-paper px-4 py-2.5 text-sm font-semibold text-ink hover:bg-fog"
                            >
                                {t('console.auth.continue_with_github')}
                            </a>
                        ) : null}
                        <div className="flex items-center gap-3 pt-1">
                            <span className="h-px flex-1 bg-mist" />
                            <span className="text-xs uppercase tracking-wide text-ink-soft/50">
                                {t('console.auth.or_email')}
                            </span>
                            <span className="h-px flex-1 bg-mist" />
                        </div>
                    </div>
                ) : null}

                <form
                    className={`${showSocial ? 'mt-6' : 'mt-8'} space-y-4`}
                    onSubmit={onSubmit}
                    noValidate
                >
                    <label className="block text-sm">
                        <span className="mb-1.5 block font-medium text-ink">
                            {t('console.common.email')}
                        </span>
                        <input
                            type="email"
                            autoComplete="username"
                            required
                            value={email}
                            onChange={(event) => setEmail(event.target.value)}
                            className="w-full border border-mist bg-paper px-3 py-2.5 text-ink outline-none focus:border-teal"
                        />
                    </label>

                    <label className="block text-sm">
                        <span className="mb-1.5 block font-medium text-ink">
                            {t('console.common.password')}
                        </span>
                        <input
                            type="password"
                            autoComplete="current-password"
                            required
                            value={password}
                            onChange={(event) => setPassword(event.target.value)}
                            className="w-full border border-mist bg-paper px-3 py-2.5 text-ink outline-none focus:border-teal"
                        />
                    </label>

                    <div className="flex justify-end">
                        <Link
                            to="/forgot-password"
                            className="text-sm font-medium text-teal hover:text-teal-deep"
                        >
                            {t('console.auth.forgot_password')}
                        </Link>
                    </div>

                    {error !== null && (
                        <p className="text-sm text-danger" role="alert">
                            {error}
                        </p>
                    )}

                    <button
                        type="submit"
                        disabled={loginMutation.isPending}
                        className="w-full bg-ink px-4 py-2.5 text-sm font-semibold text-paper transition-colors hover:bg-ink-soft disabled:opacity-60"
                    >
                        {loginMutation.isPending
                            ? t('console.auth.signing_in')
                            : t('console.auth.sign_in')}
                    </button>
                </form>

                <p className="mt-6 text-sm text-ink-soft/60">
                    {t('console.auth.no_account')}{' '}
                    <Link to={registerTo} className="font-medium text-teal hover:text-teal-deep">
                        {t('console.auth.create_account')}
                    </Link>
                </p>
            </div>
        </div>
    );
}
