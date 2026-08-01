import { Trash2 } from 'lucide-react';
import { EmptyState } from '@/components/EmptyState';
import { useI18n } from '@/hooks/useI18n';
import type { OrganizationMember, Role } from '@/types';

type Props = {
    members: OrganizationMember[];
    roles: Role[];
    canManageRoles: boolean;
    canRemove: boolean;
    busy: boolean;
    onChangeRole: (memberId: string, roleId: string) => void;
    onRemove: (memberId: string) => void;
};

export function MembersTable({
    members,
    roles,
    canManageRoles,
    canRemove,
    busy,
    onChangeRole,
    onRemove,
}: Props) {
    const { t } = useI18n();
    if (members.length === 0) {
        return (
            <EmptyState
                title={t('console.page.members.empty_title')}
                description={t('console.page.members.empty_description')}
            />
        );
    }

    return (
        <div className="overflow-x-auto border border-mist">
            <table className="w-full text-left text-sm">
                <thead className="border-b border-mist bg-fog text-ink-soft/70">
                    <tr>
                        <th className="px-4 py-3 font-medium">{t('console.common.name')}</th>
                        <th className="px-4 py-3 font-medium">{t('console.common.email')}</th>
                        <th className="px-4 py-3 font-medium">{t('console.common.role')}</th>
                        <th className="px-4 py-3 font-medium">{t('console.page.settings.mfa')}</th>
                        <th className="px-4 py-3 font-medium" />
                    </tr>
                </thead>
                <tbody>
                    {members.map((member) => {
                        const owner = member.role.is_owner === true || member.role.slug === 'owner';
                        return (
                            <tr key={member.id} className="border-b border-fog last:border-0">
                                <td className="px-4 py-3 font-medium text-ink">
                                    {member.user.name}
                                </td>
                                <td className="px-4 py-3 text-ink-soft/70">{member.user.email}</td>
                                <td className="px-4 py-3 text-ink-soft/70">
                                    {canManageRoles && !owner ? (
                                        <select
                                            value={member.role_id || member.role.id}
                                            disabled={busy}
                                            onChange={(event) =>
                                                onChangeRole(member.id, event.target.value)
                                            }
                                            className="border border-mist bg-paper px-2 py-1.5 text-sm outline-none focus:border-teal"
                                        >
                                            {roles.map((role) => (
                                                <option key={role.id} value={role.id}>
                                                    {role.name}
                                                </option>
                                            ))}
                                        </select>
                                    ) : (
                                        member.role.name
                                    )}
                                </td>
                                <td className="px-4 py-3 text-ink-soft/70">
                                    {member.user.mfa_enabled
                                        ? t('console.common.on')
                                        : t('console.common.off')}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    {!owner && canRemove && (
                                        <button
                                            type="button"
                                            disabled={busy}
                                            onClick={() => onRemove(member.id)}
                                            className="text-ink-soft/50 hover:text-danger disabled:opacity-50"
                                            aria-label={`Remove ${member.user.name}`}
                                        >
                                            <Trash2 className="size-4" strokeWidth={1.75} />
                                        </button>
                                    )}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
