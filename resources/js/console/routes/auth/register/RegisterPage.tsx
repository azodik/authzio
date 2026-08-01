import { useMutation } from '@tanstack/react-query';
import { type FormEvent, useEffect, useState } from 'react';
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router';
import { useAuth } from '@/auth/AuthContext';
import { useI18n } from '@/hooks/useI18n';
import { ApiError } from '@/lib/api';
import { captureRedirectFromSearchParams, peekPendingAuthRedirect } from '@/lib/authRedirect';

export function RegisterPage() {
    const { user, loading, register } = useAuth();
    const { t } = useI18n();
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const [name, setName] = useState('');
    const [email, setEmail] = useState(() => searchParams.get('email') ?? '');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [acceptedTerms, setAcceptedTerms] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const registerMutation = useMutation({
        mutationFn: (payload: {
            name: string;
            email: string;
            password: string;
            passwordConfirmation: string;
            acceptedTerms: boolean;
        }) =>
            register(
                payload.name,
                payload.email,
                payload.password,
                payload.passwordConfirmation,
                payload.acceptedTerms,
            ),
    });

    useEffect(() => {
        captureRedirectFromSearchParams(searchParams);
    }, [searchParams]);

    if (!loading && user) {
        return <Navigate to={peekPendingAuthRedirect() ?? '/'} replace />;
    }

    async function onSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setError(null);

        if (!acceptedTerms) {
            setError(t('console.auth.accept_terms_required'));
            return;
        }

        try {
            await registerMutation.mutateAsync({
                name: name.trim(),
                email: email.trim(),
                password,
                passwordConfirmation,
                acceptedTerms,
            });
            // Keep pending redirect for email verification (may open in another tab).
            navigate(peekPendingAuthRedirect() ?? '/', { replace: true });
        } catch (err) {
            if (err instanceof ApiError) {
                const first = Object.values(err.errors)[0]?.[0];
                setError(first ?? err.message);
            } else {
                setError(t('console.auth.unable_create'));
            }
        }
    }

    const loginQuery = new URLSearchParams();
    const redirect = searchParams.get('redirect');
    if (redirect !== null && redirect !== '') {
        loginQuery.set('redirect', redirect);
    }
    const loginTo = loginQuery.size > 0 ? `/login?${loginQuery.toString()}` : '/login';

    return (
        <div className="flex min-h-screen items-center justify-center bg-paper px-4">
            <div className="w-full max-w-md border border-mist bg-paper-elevated p-8">
                <div className="mb-8 flex items-center gap-2.5">
                    <img src="/images/logo.svg" alt="" className="size-7" width={40} height={40} />
                    <span className="font-display text-xl font-bold tracking-tight">Authzio</span>
                </div>

                <h1 className="font-display text-2xl font-bold text-ink">
                    {t('console.auth.create_account')}
                </h1>
                <p className="mt-2 text-sm text-ink-soft/65">
                    {t('console.auth.create_account_desc')}
                </p>

                <form className="mt-8 space-y-4" onSubmit={onSubmit} noValidate>
                    <label className="block text-sm">
                        <span className="mb-1.5 block font-medium text-ink">
                            {t('console.common.name')}
                        </span>
                        <input
                            type="text"
                            autoComplete="name"
                            required
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                            className="w-full border border-mist bg-paper px-3 py-2.5 text-ink outline-none focus:border-teal"
                        />
                    </label>

                    <label className="block text-sm">
                        <span className="mb-1.5 block font-medium text-ink">
                            {t('console.common.email')}
                        </span>
                        <input
                            type="email"
                            autoComplete="email"
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
                            autoComplete="new-password"
                            required
                            value={password}
                            onChange={(event) => setPassword(event.target.value)}
                            className="w-full border border-mist bg-paper px-3 py-2.5 text-ink outline-none focus:border-teal"
                        />
                        <span className="mt-1 block text-xs text-ink-soft/50">
                            At least 12 characters with mixed case, numbers, and symbols.
                        </span>
                    </label>

                    <label className="block text-sm">
                        <span className="mb-1.5 block font-medium text-ink">Confirm password</span>
                        <input
                            type="password"
                            autoComplete="new-password"
                            required
                            value={passwordConfirmation}
                            onChange={(event) => setPasswordConfirmation(event.target.value)}
                            className="w-full border border-mist bg-paper px-3 py-2.5 text-ink outline-none focus:border-teal"
                        />
                    </label>

                    <label className="flex cursor-pointer items-start gap-2.5 text-sm text-ink-soft/80">
                        <input
                            type="checkbox"
                            checked={acceptedTerms}
                            onChange={(event) => setAcceptedTerms(event.target.checked)}
                            className="peer sr-only"
                            required
                        />
                        <span
                            aria-hidden="true"
                            className="mt-0.5 grid size-4 shrink-0 place-items-center border border-mist bg-paper peer-checked:border-teal peer-checked:bg-teal peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-teal"
                        >
                            <svg
                                viewBox="0 0 12 12"
                                aria-hidden="true"
                                className={`size-2.5 text-paper ${acceptedTerms ? 'opacity-100' : 'opacity-0'}`}
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            >
                                <path d="M2 6.5 4.5 9 10 3" />
                            </svg>
                        </span>
                        <span className="leading-snug">
                            {t('console.auth.accept_terms_prefix')}{' '}
                            <a
                                href="/privacy"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="font-medium text-teal hover:text-teal-deep"
                                onClick={(event) => event.stopPropagation()}
                            >
                                {t('console.auth.privacy_policy')}
                            </a>{' '}
                            {t('console.auth.accept_terms_and')}{' '}
                            <a
                                href="/terms"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="font-medium text-teal hover:text-teal-deep"
                                onClick={(event) => event.stopPropagation()}
                            >
                                {t('console.auth.terms_of_service')}
                            </a>
                            .
                        </span>
                    </label>

                    {error !== null && (
                        <p className="text-sm text-danger" role="alert">
                            {error}
                        </p>
                    )}

                    <button
                        type="submit"
                        disabled={registerMutation.isPending || !acceptedTerms}
                        className="w-full bg-ink px-4 py-2.5 text-sm font-semibold text-paper transition-colors hover:bg-ink-soft disabled:opacity-60"
                    >
                        {registerMutation.isPending
                            ? t('console.common.creating')
                            : t('console.auth.create_account')}
                    </button>
                </form>

                <p className="mt-6 text-sm text-ink-soft/60">
                    {t('console.auth.have_account')}{' '}
                    <Link to={loginTo} className="font-medium text-teal hover:text-teal-deep">
                        {t('console.auth.sign_in')}
                    </Link>
                </p>
            </div>
        </div>
    );
}
