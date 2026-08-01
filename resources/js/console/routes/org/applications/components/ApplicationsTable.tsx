import { Settings2, Trash2 } from 'lucide-react';
import { Link } from 'react-router';
import { useI18n } from '@/hooks/useI18n';
import type { OAuthClient } from '@/types';

type ApplicationsTableProps = {
    clients: OAuthClient[];
    appHome: (applicationId: string) => string;
    onSelect: (applicationId: string) => void;
    onRevoke: (applicationId: string) => void;
};

export function ApplicationsTable({
    clients,
    appHome,
    onSelect,
    onRevoke,
}: ApplicationsTableProps) {
    const { t } = useI18n();

    return (
        <div className="overflow-x-auto border border-mist">
            <table className="w-full text-left text-sm">
                <thead className="border-b border-mist bg-fog text-ink-soft/70">
                    <tr>
                        <th className="px-4 py-3 font-medium">{t('console.common.name')}</th>
                        <th className="px-4 py-3 font-medium">{t('console.common.type')}</th>
                        <th className="px-4 py-3 font-medium">
                            {t('console.page.applications.client_id')}
                        </th>
                        <th className="px-4 py-3 font-medium">
                            {t('console.page.applications.branding')}
                        </th>
                        <th className="px-4 py-3 font-medium" />
                    </tr>
                </thead>
                <tbody>
                    {clients.map((client) => (
                        <tr key={client.id} className="border-b border-fog last:border-0">
                            <td className="px-4 py-3">
                                <Link
                                    to={appHome(client.id)}
                                    className="font-medium text-ink hover:text-teal"
                                    onClick={() => onSelect(client.id)}
                                >
                                    {client.name}
                                </Link>
                                {client.description !== null && (
                                    <p className="text-xs text-ink-soft/55">{client.description}</p>
                                )}
                            </td>
                            <td className="px-4 py-3 capitalize text-ink-soft/70">
                                {client.application_type}
                            </td>
                            <td className="px-4 py-3 font-mono text-xs text-ink-soft/70">
                                {client.id}
                            </td>
                            <td className="px-4 py-3">
                                <span
                                    className="inline-block size-3 border border-mist"
                                    style={{ backgroundColor: client.primary_color || '#0F766E' }}
                                    title={client.primary_color || undefined}
                                />
                            </td>
                            <td className="px-4 py-3 text-right">
                                <Link
                                    to={appHome(client.id)}
                                    onClick={() => onSelect(client.id)}
                                    className="mr-3 inline-flex items-center gap-1 text-ink-soft/60 hover:text-ink"
                                    aria-label={`Configure ${client.name}`}
                                >
                                    <Settings2 className="size-4" strokeWidth={1.75} />
                                </Link>
                                <button
                                    type="button"
                                    onClick={() => onRevoke(client.id)}
                                    className="inline-flex items-center gap-1 text-ink-soft/60 hover:text-danger"
                                    aria-label={`Revoke ${client.name}`}
                                >
                                    <Trash2 className="size-4" strokeWidth={1.75} />
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
