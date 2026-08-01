import { useMutation } from '@tanstack/react-query';
import { type FormEvent, useState } from 'react';
import { Link, Navigate } from 'react-router';
import { useAuth } from '@/auth/AuthContext';
import { useI18n } from '@/hooks/useI18n';
import { ApiError, apiPost } from '@/lib/api';
import { toastSuccess } from '@/lib/toast';

export function ForgotPasswordPage() {
    const { user, loading } = useAuth();
    const { t } = useI18n();
    const [email, setEmail] = useState('');
    const [error, setError] = useState<string | null>(null);
    const forgotPasswordMutation = useMutation({
        mutationFn: (emailValue: string) =>
            apiPost<{ message: string }>('/api/v1/auth/forgot-password', { email: emailValue }),
    });

    if (!loading && user) {
        return <Navigate to="/" replace />;
    }

    async function onSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setError(null);
        try {
            const response = await forgotPasswordMutation.mutateAsync(email.trim());
            toastSuccess(response.message);
        } catch (err) {
            if (err instanceof ApiError) {
                setError(err.errors.email?.[0] ?? err.message);
            } else {
                setError('Unable to send reset email.');
            }
        }
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-paper px-4 py-10">
            <div className="w-full max-w-md border border-mist bg-paper-elevated p-6 sm:p-8">
                <div className="mb-8 flex items-center gap-2.5">
                    <img src="/images/logo.svg" alt="" className="size-7" width={40} height={40} />
                    <span className="font-display text-xl font-bold tracking-tight">Authzio</span>
                </div>

                <h1 className="font-display text-2xl font-bold text-ink">
                    {t('console.auth.forgot_title')}
                </h1>
                <p className="mt-2 text-sm text-ink-soft/65">{t('console.auth.forgot_desc')}</p>

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

                    {error !== null && (
                        <p className="text-sm text-danger" role="alert">
                            {error}
                        </p>
                    )}
                    <button
                        type="submit"
                        disabled={forgotPasswordMutation.isPending}
                        className="w-full bg-ink px-4 py-2.5 text-sm font-semibold text-paper transition-colors hover:bg-ink-soft disabled:opacity-60"
                    >
                        {forgotPasswordMutation.isPending
                            ? t('console.common.sending')
                            : t('console.auth.send_reset')}
                    </button>
                </form>

                <p className="mt-6 text-sm text-ink-soft/60">
                    <Link to="/login" className="font-medium text-teal hover:text-teal-deep">
                        {t('console.auth.sign_in')}
                    </Link>
                </p>
            </div>
        </div>
    );
}
