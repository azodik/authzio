import { useMutation, useQuery } from '@tanstack/react-query';
import { type FormEvent, useEffect } from 'react';
import { Link, useNavigate, useParams } from 'react-router';
import { useAuth } from '@/auth/AuthContext';
import { apiGet, apiPost } from '@/lib/api';
import { clearPendingAuthRedirect, setPendingAuthRedirect } from '@/lib/authRedirect';
import { toastSuccess } from '@/lib/toast';

type InvitePayload = {
    email: string;
    organization: { id: string; name: string; slug: string } | null;
    role: { id: string; name: string; slug: string } | null;
    expires_at: string;
    is_pending: boolean;
};

export function AcceptInvitePage() {
    const { token } = useParams<{ token: string }>();
    const { user, loading: authLoading } = useAuth();
    const navigate = useNavigate();
    const inviteQuery = useQuery({
        queryKey: ['invitation', token],
        enabled: Boolean(token),
        queryFn: () => apiGet<{ data: InvitePayload }>(`/api/v1/invitations/${token}`),
    });
    const invite = inviteQuery.data?.data ?? null;
    const acceptInvite = useMutation({
        mutationFn: () =>
            apiPost<{ organization_id: string }>(`/api/v1/invitations/${token}/accept`),
    });

    useEffect(() => {
        if (!token) {
            return;
        }

        // Survive register → verify-email (often another tab) → return here.
        setPendingAuthRedirect(`/invites/${token}`);
    }, [token]);

    async function onAccept(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        if (!token) {
            return;
        }

        try {
            const response = await acceptInvite.mutateAsync();
            toastSuccess('Invitation accepted.');
            clearPendingAuthRedirect();
            navigate(`/${response.organization_id}`, { replace: true });
        } catch {
            // Mutation error is rendered below.
        }
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-paper px-4">
            <div className="w-full max-w-md border border-mist bg-paper-elevated p-8">
                <div className="mb-8 flex items-center gap-2.5">
                    <img src="/images/logo.svg" alt="" className="size-7" width={40} height={40} />
                    <span className="font-display text-xl font-bold tracking-tight">Authzio</span>
                </div>

                <h1 className="font-display text-2xl font-bold text-ink">Organization invite</h1>

                {(inviteQuery.error || acceptInvite.error) && (
                    <p className="mt-4 text-sm text-danger" role="alert">
                        {(inviteQuery.error || acceptInvite.error)?.message ||
                            (inviteQuery.error
                                ? 'Invitation not found.'
                                : 'Failed to accept invitation.')}
                    </p>
                )}
                {invite === null && !inviteQuery.error ? (
                    <p className="mt-4 text-sm text-ink-soft/60">Loading invitation…</p>
                ) : invite ? (
                    <div className="mt-6 space-y-3 text-sm">
                        <p className="text-ink-soft/70">
                            You were invited to join{' '}
                            <span className="font-medium text-ink">
                                {invite.organization?.name ?? 'an organization'}
                            </span>{' '}
                            as{' '}
                            <span className="font-medium text-ink">
                                {invite.role?.name ?? 'a member'}
                            </span>
                            .
                        </p>
                        <p className="text-ink-soft/55">
                            Invited email: <span className="text-ink">{invite.email}</span>
                        </p>
                        <p className="text-ink-soft/55">
                            Expires {new Date(invite.expires_at).toLocaleString()}
                        </p>

                        {!invite.is_pending ? (
                            <p className="text-sm text-danger">
                                This invitation is no longer valid.
                            </p>
                        ) : authLoading ? (
                            <p className="text-ink-soft/55">Checking session…</p>
                        ) : !user ? (
                            <div className="space-y-3 pt-2">
                                <p className="text-ink-soft/70">
                                    Sign in or create an account with{' '}
                                    <span className="font-medium text-ink">{invite.email}</span> to
                                    accept.
                                </p>
                                <div className="flex flex-wrap gap-3">
                                    <Link
                                        to={`/login?redirect=${encodeURIComponent(`/invites/${token}`)}`}
                                        className="inline-flex bg-teal px-4 py-2.5 text-sm font-semibold text-paper hover:bg-teal-bright"
                                    >
                                        Sign in to accept
                                    </Link>
                                    <Link
                                        to={`/register?redirect=${encodeURIComponent(`/invites/${token}`)}&email=${encodeURIComponent(invite.email)}`}
                                        className="inline-flex border border-mist px-4 py-2.5 text-sm font-semibold text-ink hover:border-teal"
                                    >
                                        Create account
                                    </Link>
                                </div>
                            </div>
                        ) : (
                            <form onSubmit={onAccept} className="pt-2">
                                <button
                                    data-testid="accept-invitation"
                                    type="submit"
                                    disabled={acceptInvite.isPending}
                                    className="bg-teal px-4 py-2.5 text-sm font-semibold text-paper hover:bg-teal-bright disabled:opacity-60"
                                >
                                    {acceptInvite.isPending ? 'Accepting…' : 'Accept invitation'}
                                </button>
                            </form>
                        )}
                    </div>
                ) : null}
            </div>
        </div>
    );
}
