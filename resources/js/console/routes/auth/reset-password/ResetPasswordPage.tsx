import { useMutation } from '@tanstack/react-query';
import { type FormEvent, useMemo, useState } from 'react';
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router';
import { useAuth } from '@/auth/AuthContext';
import { useI18n } from '@/hooks/useI18n';
import { ApiError, apiPost } from '@/lib/api';
import { toastSuccess } from '@/lib/toast';

export function ResetPasswordPage() {
    const { user, loading } = useAuth();
    const { t } = useI18n();
    const navigate = useNavigate();
    const [params] = useSearchParams();
    const token = useMemo(() => params.get('token') ?? '', [params]);
    const emailFromQuery = useMemo(() => params.get('email') ?? '', [params]);

    const [email, setEmail] = useState(emailFromQuery);
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [error, setError] = useState<string | null>(null);
    const resetPasswordMutation = useMutation({
        mutationFn: (payload: {
            token: string;
            email: string;
            password: string;
            password_confirmation: string;
        }) => apiPost<{ message: string }>('/api/v1/auth/reset-password', payload),
    });

    if (!loading && user) {
        return <Navigate to="/" replace />;
    }

    async function onSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setError(null);
        try {
            const response = await resetPasswordMutation.mutateAsync({
                token,
                email: email.trim(),
                password,
                password_confirmation: passwordConfirmation,
            });
            toastSuccess(response.message);
            navigate('/login', { replace: true });
        } catch (err) {
            if (err instanceof ApiError) {
                setError(
                    err.errors.email?.[0] ??
                        err.errors.password?.[0] ??
                        err.errors.token?.[0] ??
                        err.message,
                );
            } else {
                setError('Unable to reset password.');
            }
        }
    }

    if (token === '') {
        return (
            <div className="flex min-h-screen items-center justify-center bg-paper px-4">
                <div className="w-full max-w-md border border-mist bg-paper-elevated p-8">
                    <p className="text-sm text-danger" role="alert">
                        This reset link is missing a token. Request a new password reset.
                    </p>
                    <Link
                        to="/forgot-password"
                        className="mt-4 inline-block text-sm font-medium text-teal"
                    >
                        Request reset
                    </Link>
                </div>
            </div>
        );
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-paper px-4 py-10">
            <div className="w-full max-w-md border border-mist bg-paper-elevated p-6 sm:p-8">
                <div className="mb-8 flex items-center gap-2.5">
                    <img src="/images/logo.svg" alt="" className="size-7" width={40} height={40} />
                    <span className="font-display text-xl font-bold tracking-tight">Authzio</span>
                </div>

                <h1 className="font-display text-2xl font-bold text-ink">
                    {t('console.auth.reset_title')}
                </h1>
                <p className="mt-2 text-sm text-ink-soft/65">{t('console.auth.reset_desc')}</p>

                <form className="mt-8 space-y-4" onSubmit={onSubmit} noValidate>
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
                            autoComplete="new-password"
                            required
                            value={password}
                            onChange={(event) => setPassword(event.target.value)}
                            className="w-full border border-mist bg-paper px-3 py-2.5 text-ink outline-none focus:border-teal"
                        />
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

                    {error !== null && (
                        <p className="text-sm text-danger" role="alert">
                            {error}
                        </p>
                    )}

                    <button
                        type="submit"
                        disabled={resetPasswordMutation.isPending}
                        className="w-full bg-ink px-4 py-2.5 text-sm font-semibold text-paper transition-colors hover:bg-ink-soft disabled:opacity-60"
                    >
                        {resetPasswordMutation.isPending
                            ? t('console.common.saving')
                            : t('console.auth.reset_submit')}
                    </button>
                </form>
            </div>
        </div>
    );
}
