import { useI18n } from '@/hooks/useI18n';
import type { InvitationStatus, OrganizationInvitation } from '@/types';

function status(invite: OrganizationInvitation): InvitationStatus {
    if (invite.status) return invite.status;
    if (invite.accepted_at) return 'accepted';
    if (invite.revoked_at) return 'revoked';
    return new Date(invite.expires_at).getTime() < Date.now() ? 'expired' : 'pending';
}

type Props = {
    title: string;
    invitations: OrganizationInvitation[];
    history?: boolean;
    canManage?: boolean;
    busyId?: string;
    onResend?: (id: string) => void;
    onRevoke?: (id: string) => void;
};

export function InvitationsList({
    title,
    invitations,
    history = false,
    canManage = false,
    busyId,
    onResend,
    onRevoke,
}: Props) {
    const { t } = useI18n();
    return (
        <section className="mb-10">
            <h2 className="mb-3 font-display text-base font-semibold text-ink">{title}</h2>
            {invitations.length === 0 ? (
                <p className="text-sm text-ink-soft/55">
                    {history
                        ? t('console.page.members.no_history')
                        : t('console.page.members.no_invites')}
                </p>
            ) : (
                <div className="overflow-x-auto border border-mist">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-mist bg-fog text-ink-soft/70">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    {t('console.common.email')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('console.common.role')}
                                </th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">
                                    {history ? 'Updated' : t('console.common.expires')}
                                </th>
                                {!history && <th className="px-4 py-3 font-medium" />}
                            </tr>
                        </thead>
                        <tbody>
                            {invitations.map((invite) => {
                                const updated =
                                    invite.accepted_at ??
                                    invite.revoked_at ??
                                    invite.updated_at ??
                                    invite.created_at;
                                return (
                                    <tr
                                        key={invite.id}
                                        className="border-b border-fog last:border-0"
                                    >
                                        <td className="px-4 py-3 text-ink">{invite.email}</td>
                                        <td className="px-4 py-3 text-ink-soft/70">
                                            {invite.role.name}
                                        </td>
                                        <td className="px-4 py-3 capitalize text-ink-soft/70">
                                            {status(invite)}
                                        </td>
                                        <td className="px-4 py-3 text-ink-soft/70">
                                            {new Date(
                                                history ? updated : invite.expires_at,
                                            ).toLocaleString()}
                                        </td>
                                        {!history && (
                                            <td className="px-4 py-3 text-right">
                                                {canManage && (
                                                    <div className="flex justify-end gap-3">
                                                        <button
                                                            type="button"
                                                            disabled={busyId === invite.id}
                                                            onClick={() => onResend?.(invite.id)}
                                                            className="text-sm font-medium text-teal disabled:opacity-50"
                                                        >
                                                            Resend
                                                        </button>
                                                        <button
                                                            type="button"
                                                            disabled={busyId === invite.id}
                                                            onClick={() => onRevoke?.(invite.id)}
                                                            className="text-sm text-ink-soft/60 hover:text-danger disabled:opacity-50"
                                                        >
                                                            Revoke
                                                        </button>
                                                    </div>
                                                )}
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}
