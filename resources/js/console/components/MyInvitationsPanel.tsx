import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { ApiError, apiGet, apiPost } from '../lib/api';
import { orgPath } from '../lib/paths';
import type { OrganizationInvitation } from '../types';
import { useWorkspace } from '../workspace/WorkspaceContext';

function roleLabel(role: OrganizationInvitation['role']): string {
    return role.name;
}

type MyInvitationsPanelProps = {
    /** Compact banner vs full section on Organizations. */
    variant?: 'section' | 'banner';
};

export function MyInvitationsPanel({ variant = 'section' }: MyInvitationsPanelProps) {
    const navigate = useNavigate();
    const { refresh, setOrganizationId } = useWorkspace();
    const [invites, setInvites] = useState<OrganizationInvitation[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [acceptingId, setAcceptingId] = useState<string | null>(null);

    const load = useCallback(async (): Promise<void> => {
        const response = await apiGet<{ data: OrganizationInvitation[] }>('/api/v1/invitations');
        setInvites(response.data);
    }, []);

    useEffect(() => {
        void load().catch((err: unknown) => {
            setError(err instanceof ApiError ? err.message : 'Failed to load invitations.');
        });
    }, [load]);

    async function onAccept(invite: OrganizationInvitation): Promise<void> {
        if (invite.token === undefined || invite.token === '') {
            setError('Invitation link is missing. Open the email invite instead.');
            return;
        }

        setAcceptingId(invite.id);
        setError(null);

        try {
            const response = await apiPost<{ organization_id: string }>(
                `/api/v1/invitations/${invite.token}/accept`,
            );
            setOrganizationId(response.organization_id);
            await refresh();
            setInvites((current) => current.filter((row) => row.id !== invite.id));
            navigate(orgPath(response.organization_id), { replace: true });
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Failed to accept invitation.');
        } finally {
            setAcceptingId(null);
        }
    }

    if (invites.length === 0 && error === null) {
        return null;
    }

    const list = (
        <ul className="divide-y divide-fog border border-mist">
            {invites.map((invite) => (
                <li
                    key={invite.id}
                    className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div className="text-sm">
                        <p className="font-medium text-ink">
                            {invite.organization?.name ?? 'Organization'}
                        </p>
                        <p className="mt-0.5 text-ink-soft/60">
                            As {roleLabel(invite.role)}
                            {invite.inviter?.name ? ` · invited by ${invite.inviter.name}` : ''}
                            {' · expires '}
                            {new Date(invite.expires_at).toLocaleDateString()}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            disabled={acceptingId === invite.id || !invite.token}
                            onClick={() => {
                                void onAccept(invite);
                            }}
                            className="bg-teal px-3.5 py-2 text-sm font-semibold text-paper hover:bg-teal-bright disabled:opacity-60"
                        >
                            {acceptingId === invite.id ? 'Accepting…' : 'Accept'}
                        </button>
                        {invite.token ? (
                            <Link
                                to={`/invites/${invite.token}`}
                                className="border border-mist px-3.5 py-2 text-sm font-medium text-ink hover:border-teal"
                            >
                                View
                            </Link>
                        ) : null}
                    </div>
                </li>
            ))}
        </ul>
    );

    if (variant === 'banner') {
        return (
            <div className="mb-6 border border-teal/30 bg-teal/5 p-4">
                <p className="text-sm font-medium text-ink">You have organization invitations</p>
                {error !== null ? (
                    <p className="mt-2 text-sm text-danger" role="alert">
                        {error}
                    </p>
                ) : null}
                <div className="mt-3">{list}</div>
            </div>
        );
    }

    return (
        <section className="mb-10">
            <h2 className="mb-3 font-display text-base font-semibold text-ink">
                Invitations for you
            </h2>
            <p className="mb-3 text-sm text-ink-soft/60">
                Organizations you were invited to join. Accept here or open the email link.
            </p>
            {error !== null ? (
                <p className="mb-3 text-sm text-danger" role="alert">
                    {error}
                </p>
            ) : null}
            {list}
        </section>
    );
}
