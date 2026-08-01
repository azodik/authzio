import { useQuery } from '@tanstack/react-query';
import { EmptyState } from '@/components/EmptyState';
import { PageHeader } from '@/components/PageHeader';
import { useActiveOrganization } from '@/hooks/useActiveOrganization';
import { useI18n } from '@/hooks/useI18n';
import { apiGet } from '@/lib/api';
import { orgApiPath } from '@/lib/orgApi';
import { queryKeys } from '@/lib/queryKeys';
import type { AuditLog } from '@/types';

type AuditResponse = {
    data: AuditLog[];
};

export function AuditLogsPage() {
    const organization = useActiveOrganization();
    const { t } = useI18n();
    const { data, error } = useQuery({
        queryKey: organization
            ? queryKeys.org(organization.id).auditLogs()
            : ['org', 'audit-logs', 'disabled'],
        enabled: Boolean(organization),
        queryFn: () => apiGet<AuditResponse>(orgApiPath(organization!.id, 'audit-logs')),
    });
    const logs = data?.data ?? [];

    if (!organization) {
        return (
            <EmptyState
                title={t('console.common.need_org_title')}
                description={t('console.page.audit.need_org_description')}
            />
        );
    }

    return (
        <div>
            <PageHeader
                title={t('console.page.audit.title')}
                description={t('console.page.audit.description')}
            />

            {error && (
                <p className="mb-4 text-sm text-danger" role="alert">
                    {error.message || 'Failed to load audit logs.'}
                </p>
            )}

            {logs.length === 0 ? (
                <EmptyState
                    title={t('console.page.audit.empty_title')}
                    description={t('console.page.audit.empty_description')}
                />
            ) : (
                <div className="overflow-x-auto border border-mist">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-mist bg-fog text-ink-soft/70">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    {t('console.page.audit.col_when')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('console.page.audit.col_action')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('console.page.audit.col_actor')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('console.page.audit.col_ip')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.map((log) => (
                                <tr key={log.id} className="border-b border-fog last:border-0">
                                    <td className="px-4 py-3 text-ink-soft/70">
                                        {new Date(log.created_at).toLocaleString()}
                                    </td>
                                    <td className="px-4 py-3 font-medium text-ink">{log.action}</td>
                                    <td className="px-4 py-3 text-ink-soft/70">
                                        {log.actor?.email ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-ink-soft/70">
                                        {log.ip_address ?? '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
