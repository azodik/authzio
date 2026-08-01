import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { EmptyState } from '@/components/EmptyState';
import { PageHeader } from '@/components/PageHeader';
import { useActiveOrganization } from '@/hooks/useActiveOrganization';
import { useI18n } from '@/hooks/useI18n';
import { apiGet } from '@/lib/api';
import { orgApiPath } from '@/lib/orgApi';
import { queryKeys } from '@/lib/queryKeys';
import type { EndUser, OAuthClient } from '@/types';
import { useWorkspace } from '@/workspace/WorkspaceContext';

type UsersResponse = {
    data: EndUser[];
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};

export function UsersPage() {
    const organization = useActiveOrganization();
    const { applications } = useWorkspace();
    const { t } = useI18n();
    const [q, setQ] = useState('');
    const [applicationId, setApplicationId] = useState('');
    const [verified, setVerified] = useState<'all' | 'true' | 'false'>('all');

    const queryString = useMemo(() => {
        const params = new URLSearchParams();
        if (q.trim() !== '') {
            params.set('q', q.trim());
        }
        if (applicationId !== '') {
            params.set('application_id', applicationId);
        }
        if (verified !== 'all') {
            params.set('verified', verified);
        }
        const encoded = params.toString();
        return encoded === '' ? 'end-users' : `end-users?${encoded}`;
    }, [q, applicationId, verified]);

    const params = useMemo(
        () => Object.fromEntries(new URLSearchParams(queryString.split('?')[1] ?? '')),
        [queryString],
    );
    const { data, error, isLoading } = useQuery({
        queryKey: organization
            ? queryKeys.org(organization.id).users(params)
            : ['org', 'users', 'disabled'],
        enabled: Boolean(organization),
        queryFn: () => apiGet<UsersResponse>(orgApiPath(organization!.id, queryString)),
        placeholderData: keepPreviousData,
    });
    const users = data?.data ?? [];

    if (!organization) {
        return (
            <EmptyState
                title={t('console.common.need_org_title')}
                description={t('console.page.users.need_org_description')}
            />
        );
    }

    return (
        <div>
            <PageHeader
                title={t('console.page.users.title')}
                description={t('console.page.users.description')}
            />

            <div className="mb-6 grid gap-3 sm:grid-cols-3">
                <label className="text-sm">
                    <span className="mb-1.5 block font-medium text-ink">
                        {t('console.common.search')}
                    </span>
                    <input
                        value={q}
                        onChange={(event) => setQ(event.target.value)}
                        placeholder={t('console.page.users.search_placeholder')}
                        className="w-full border border-mist bg-paper px-3 py-2.5 outline-none focus:border-teal"
                    />
                </label>
                <label className="text-sm">
                    <span className="mb-1.5 block font-medium text-ink">
                        {t('console.common.application')}
                    </span>
                    <select
                        value={applicationId}
                        onChange={(event) => setApplicationId(event.target.value)}
                        className="w-full border border-mist bg-paper px-3 py-2.5 outline-none focus:border-teal"
                    >
                        <option value="">{t('console.page.users.all_apps')}</option>
                        {applications.map((app: OAuthClient) => (
                            <option key={app.id} value={app.id}>
                                {app.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="text-sm">
                    <span className="mb-1.5 block font-medium text-ink">
                        {t('console.common.verified')}
                    </span>
                    <select
                        value={verified}
                        onChange={(event) =>
                            setVerified(event.target.value as 'all' | 'true' | 'false')
                        }
                        className="w-full border border-mist bg-paper px-3 py-2.5 outline-none focus:border-teal"
                    >
                        <option value="all">{t('console.common.all')}</option>
                        <option value="true">{t('console.common.verified')}</option>
                        <option value="false">{t('console.common.unverified')}</option>
                    </select>
                </label>
            </div>

            {error && (
                <p className="mb-4 text-sm text-danger" role="alert">
                    {error.message || t('console.page.users.load_error')}
                </p>
            )}

            {isLoading && users.length === 0 ? (
                <p className="text-sm text-ink-soft/60">{t('console.page.users.loading')}</p>
            ) : users.length === 0 ? (
                <EmptyState
                    title={t('console.page.users.empty_title')}
                    description={t('console.page.users.empty_description')}
                />
            ) : (
                <div className="overflow-x-auto border border-mist">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-mist bg-fog text-ink-soft/70">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    {t('console.common.name')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('console.common.email')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('console.page.users.apps')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('console.page.users.last_login')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('console.common.verified')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr
                                    key={`${user.id}-${user.email}`}
                                    className="border-b border-fog last:border-0"
                                >
                                    <td className="px-4 py-3 font-medium text-ink">
                                        {user.name ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-ink-soft/70">
                                        {user.email ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1.5">
                                            {user.applications.length === 0 ? (
                                                <span className="text-ink-soft/45">—</span>
                                            ) : (
                                                user.applications.map((app) => (
                                                    <span
                                                        key={app.id}
                                                        className="border border-mist bg-fog px-2 py-0.5 text-xs text-ink-soft/80"
                                                    >
                                                        {app.name ?? app.id}
                                                    </span>
                                                ))
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-ink-soft/70">
                                        {user.last_login_at
                                            ? new Date(user.last_login_at).toLocaleString()
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-ink-soft/70">
                                        {user.email_verified_at
                                            ? t('console.common.yes')
                                            : t('console.common.no')}
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
