import { useMutation } from '@tanstack/react-query';
import { type FormEvent, useEffect, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router';
import { useAuth } from '@/auth/AuthContext';
import { useI18n } from '@/hooks/useI18n';
import { ApiError, apiGet, apiPost } from '@/lib/api';
import { consumePendingAuthRedirect } from '@/lib/authRedirect';
import { toastSuccess } from '@/lib/toast';
import type { AuthUser } from '@/types';

export function VerifyEmailPage() {
    const [searchParams] = useSearchParams();
    const tokenFromQuery = searchParams.get('token') ?? '';
    const { user, loading: authLoading, setUser, refresh } = useAuth();
    const { t } = useI18n();
    const navigate = useNavigate();
    const [code, setCode] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [autoTried, setAutoTried] = useState(false);
    const verifyMutation = useMutation({
        mutationFn: (payload: { code?: string; token?: string }) =>
            apiPost<{ message: string; user?: AuthUser }>('/api/v1/auth/email/verify', payload),
    });
    const resendMutation = useMutation({
        mutationFn: () => apiPost<{ message: string }>('/api/v1/auth/email/resend-confirmation'),
    });

    useEffect(() => {
        // Wait for the initial /auth/me round-trip so it cannot race Auth::login
        // on the verify request and overwrite the new session (guest last-write-wins).
        if (authLoading || tokenFromQuery === '' || autoTried) {
            return;
        }

        setAutoTried(true);
        setBusy(true);
        setError(null);

        void apiPost<{ message: string; user?: AuthUser }>('/api/v1/auth/email/verify', {
            token: tokenFromQuery,
        })
            .then(async (response) => {
                toastSuccess(response.message);
                if (response.user) {
                    setUser(response.user);
                } else {
                    await refresh();
                }
                navigate(consumePendingAuthRedirect('/'), { replace: true });
            })
            .catch(async (err: unknown) => {
                // Token may already be consumed from a prior click; if this session is
                // verified, continue into the console instead of showing a dead-link error.
                try {
                    const me = await apiGet<{ user: AuthUser }>('/api/v1/auth/me');
                    if (me.user.email_verified_at != null) {
                        setUser(me.user);
                        toastSuccess('Email verified.');
                        navigate(consumePendingAuthRedirect('/'), { replace: true });
                        return;
                    }
                } catch {
                    // stay on error UI
                }

                setError(err instanceof ApiError ? err.message : 'Verification failed.');
            })
            .finally(() => {
                setBusy(false);
            });
    }, [authLoading, tokenFromQuery, autoTried, setUser, refresh, navigate]);

    async function onVerifyCode(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setBusy(true);
        setError(null);

        try {
            const response = await verifyMutation.mutateAsync({ code: code.trim() });
            toastSuccess(response.message);
            if (response.user) {
                setUser(response.user);
            } else {
                await refresh();
            }
            navigate(consumePendingAuthRedirect('/'), { replace: true });
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Verification failed.');
        } finally {
            setBusy(false);
        }
    }

    async function onResend(): Promise<void> {
        setBusy(true);
        setError(null);

        try {
            const response = await resendMutation.mutateAsync();
            toastSuccess(response.message);
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Could not resend confirmation.');
        } finally {
            setBusy(false);
        }
    }

    const alreadyVerified = user?.email_verified_at != null;
    const autoVerifying = authLoading || (busy && tokenFromQuery !== '' && error === null);

    return (
        <div className="flex min-h-screen items-center justify-center bg-paper px-4">
            <div className="w-full max-w-md border border-mist bg-paper-elevated p-8">
                <div className="mb-8 flex items-center gap-2.5">
                    <img src="/images/logo.svg" alt="" className="size-7" width={40} height={40} />
                    <span className="font-display text-xl font-bold tracking-tight">Authzio</span>
                </div>

                <h1 className="font-display text-2xl font-bold text-ink">
                    {t('console.auth.verify_title')}
                </h1>
                <p className="mt-2 text-sm text-ink-soft/65">{t('console.auth.verify_desc')}</p>

                {autoVerifying ? (
                    <p className="mt-6 text-sm text-ink-soft/60">{t('console.auth.verifying')}</p>
                ) : null}

                {alreadyVerified && !autoVerifying ? (
                    <p className="mt-6 text-sm text-success">Your email is already verified.</p>
                ) : null}

                {error !== null && (
                    <p className="mt-4 text-sm text-danger" role="alert">
                        {error}
                    </p>
                )}
                <form className="mt-8 space-y-4" onSubmit={onVerifyCode}>
                    <label className="block text-sm">
                        <span className="mb-1.5 block font-medium text-ink">Verification code</span>
                        <input
                            value={code}
                            onChange={(event) => setCode(event.target.value)}
                            maxLength={6}
                            pattern="[0-9]{6}"
                            placeholder="123456"
                            className="w-full border border-mist bg-paper px-3 py-2.5 font-mono tracking-widest outline-none focus:border-teal"
                        />
                    </label>
                    <button
                        type="submit"
                        disabled={busy || code.trim().length !== 6}
                        className="w-full bg-teal px-4 py-2.5 text-sm font-semibold text-paper hover:bg-teal-bright disabled:opacity-60"
                    >
                        {busy ? t('console.auth.verifying') : t('console.auth.verify_code')}
                    </button>
                </form>

                <div className="mt-4 flex flex-col gap-2 text-sm">
                    {user ? (
                        <button
                            type="button"
                            disabled={busy || alreadyVerified}
                            onClick={() => {
                                void onResend();
                            }}
                            className="text-left text-teal hover:text-teal-deep disabled:opacity-50"
                        >
                            {t('console.auth.resend')}
                        </button>
                    ) : (
                        <Link to="/login" className="text-teal hover:text-teal-deep">
                            Sign in to resend confirmation
                        </Link>
                    )}
                    <Link to="/" className="text-ink-soft/55 hover:text-ink">
                        Back to console
                    </Link>
                </div>
            </div>
        </div>
    );
}
