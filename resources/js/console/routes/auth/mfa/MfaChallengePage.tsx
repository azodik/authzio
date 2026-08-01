import { useMutation } from '@tanstack/react-query';
import { type FormEvent, useEffect, useRef, useState } from 'react';
import { Link, Navigate, useNavigate } from 'react-router';
import { useAuth } from '@/auth/AuthContext';
import { ApiError, apiPost } from '@/lib/api';
import { consumePendingAuthRedirect, peekPendingAuthRedirect } from '@/lib/authRedirect';
import type { AuthUser } from '@/types';

export function MfaChallengePage() {
    const { user, loading, setUser } = useAuth();
    const navigate = useNavigate();
    const [code, setCode] = useState('');
    const [error, setError] = useState<string | null>(null);
    const codeRef = useRef<HTMLInputElement>(null);
    const challengeMutation = useMutation({
        mutationFn: (codeValue: string) =>
            apiPost<{ user: AuthUser }>('/api/v1/auth/mfa/challenge', { code: codeValue }),
    });

    useEffect(() => {
        codeRef.current?.focus();
    }, []);

    if (!loading && user) {
        return <Navigate to={peekPendingAuthRedirect() ?? '/'} replace />;
    }

    async function onSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setError(null);
        try {
            const response = await challengeMutation.mutateAsync(code.trim());
            setUser(response.user);
            navigate(consumePendingAuthRedirect('/'), { replace: true });
        } catch (err) {
            if (err instanceof ApiError) {
                setError(err.errors.code?.[0] ?? err.message);
            } else {
                setError('Unable to verify code.');
            }
        }
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-paper px-4">
            <div className="w-full max-w-md border border-mist bg-paper-elevated p-8">
                <div className="mb-8 flex items-center gap-2.5">
                    <img src="/images/logo.svg" alt="" className="size-7" width={40} height={40} />
                    <span className="font-display text-xl font-bold tracking-tight">Authzio</span>
                </div>

                <h1 className="font-display text-2xl font-bold text-ink">Authenticator</h1>
                <p className="mt-2 text-sm text-ink-soft/65">
                    Enter the 6-digit code from your authenticator app, or a recovery code.
                </p>

                <form className="mt-8 space-y-4" onSubmit={onSubmit} noValidate>
                    <label className="block text-sm">
                        <span className="mb-1.5 block font-medium text-ink">Code</span>
                        <input
                            ref={codeRef}
                            type="text"
                            autoComplete="one-time-code"
                            required
                            value={code}
                            onChange={(event) => setCode(event.target.value)}
                            className="w-full border border-mist bg-paper px-3 py-2.5 text-ink tracking-widest outline-none focus:border-teal"
                        />
                    </label>

                    {error !== null && (
                        <p className="text-sm text-danger" role="alert">
                            {error}
                        </p>
                    )}

                    <button
                        type="submit"
                        disabled={challengeMutation.isPending}
                        className="w-full bg-ink px-4 py-2.5 text-sm font-semibold text-paper transition-colors hover:bg-ink-soft disabled:opacity-60"
                    >
                        {challengeMutation.isPending ? 'Verifying…' : 'Continue'}
                    </button>
                </form>

                <p className="mt-6 text-sm text-ink-soft/60">
                    <Link to="/login" className="font-medium text-teal hover:text-teal-deep">
                        Back to sign in
                    </Link>
                </p>
            </div>
        </div>
    );
}
