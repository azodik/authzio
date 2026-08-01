import { useI18n } from '@/hooks/useI18n';
import type { Role } from '@/types';

type RolesTableProps = {
    roles: Role[];
    canWrite: boolean;
    onEdit: (role: Role) => void;
};

export function RolesTable({ roles, canWrite, onEdit }: RolesTableProps) {
    const { t } = useI18n();

    return (
        <div className="overflow-x-auto border border-mist">
            <table className="w-full text-left text-sm">
                <thead className="border-b border-mist bg-fog text-ink-soft/70">
                    <tr>
                        <th className="px-4 py-3 font-medium">{t('console.common.role')}</th>
                        <th className="px-4 py-3 font-medium">{t('console.common.members')}</th>
                        <th className="px-4 py-3 font-medium">
                            {t('console.page.roles.col_permissions')}
                        </th>
                        <th className="px-4 py-3 font-medium" />
                    </tr>
                </thead>
                <tbody>
                    {roles.map((role) => (
                        <tr key={role.id} className="border-b border-fog last:border-0">
                            <td className="px-4 py-3">
                                <p className="font-medium text-ink">{role.name}</p>
                                <p className="text-xs text-ink-soft/55">
                                    {role.is_owner
                                        ? t('console.page.roles.kind_owner')
                                        : role.is_system
                                          ? t('console.page.roles.kind_system')
                                          : t('console.page.roles.kind_custom')}
                                    {role.description ? ` · ${role.description}` : ''}
                                </p>
                            </td>
                            <td className="px-4 py-3 text-ink-soft/70">
                                {role.members_count ?? 0}
                            </td>
                            <td className="px-4 py-3 text-ink-soft/70">
                                {role.is_owner
                                    ? t('console.page.roles.permissions_all')
                                    : (role.permissions?.length ?? 0)}
                            </td>
                            <td className="px-4 py-3 text-right">
                                {canWrite && !role.is_owner ? (
                                    <button
                                        type="button"
                                        onClick={() => onEdit(role)}
                                        className="text-sm text-teal hover:text-teal-deep"
                                    >
                                        {t('console.common.edit')}
                                    </button>
                                ) : null}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
