import { useQuery } from '@tanstack/react-query';
import type { LucideIcon } from 'lucide-react';
import { Building2, KeyRound, Plus, ShieldCheck, Users } from 'lucide-react';
import { Link } from 'react-router';
import { EmptyState } from '@/components/EmptyState';
import { MyInvitationsPanel } from '@/components/MyInvitationsPanel';
import { PageHeader } from '@/components/PageHeader';
import { useI18n } from '@/hooks/useI18n';
import { useWorkspacePaths } from '@/hooks/useWorkspacePaths';
import { apiGet } from '@/lib/api';
import { orgApiPath } from '@/lib/orgApi';
import { queryKeys } from '@/lib/queryKeys';
import type { OverviewStats } from '@/types';
import { useWorkspace } from '@/workspace/WorkspaceContext';

type StatCard = {
    key: keyof OverviewStats | 'organizations';
    labelKey: string;
    hintKey: string;
    icon: LucideIcon;
};

const cards: StatCard[] = [
    {
        key: 'users',
        labelKey: 'console.page.overview.stat_users',
        hintKey: 'console.page.overview.stat_users_hint',
        icon: Users,
    },
    {
        key: 'organizations',
        labelKey: 'console.page.overview.stat_orgs',
        hintKey: 'console.page.overview.stat_orgs_hint',
        icon: Building2,
    },
    {
        key: 'applications',
        labelKey: 'console.page.overview.stat_apps',
        hintKey: 'console.page.overview.stat_apps_hint',
        icon: KeyRound,
    },
    {
        key: 'mfa_enabled_users',
        labelKey: 'console.page.overview.stat_mfa',
        hintKey: 'console.page.overview.stat_mfa_hint',
        icon: ShieldCheck,
    },
];

export function OverviewPage() {
    const { organization, entitlements, application, organizations, applications } = useWorkspace();
    const { t } = useI18n();
    const paths = useWorkspacePaths();
    const { data, error } = useQuery({
        queryKey: organization
            ? queryKeys.org(organization.id).overview()
            : ['org', 'overview', 'disabled'],
        enabled: Boolean(organization),
        queryFn: () =>
            apiGet<{ data: OverviewStats }>(orgApiPath(organization!.id, 'overview/stats')),
    });
    const stats = data?.data ?? null;

    if (!organization) {
        return (
            <EmptyState
                icon={Building2}
                title={t('console.page.overview.need_org_title')}
                description={t('console.page.overview.need_org_description')}
                action={
                    <Link
                        to="/onboarding"
                        className="inline-flex items-center gap-2 bg-teal px-4 py-2.5 text-sm font-semibold text-paper hover:bg-teal-bright"
                    >
                        <Plus className="size-4" strokeWidth={2} />
                        {t('console.switcher.create_org')}
                    </Link>
                }
            />
        );
    }

    return (
        <div>
            <PageHeader
                title={organization.name}
                description={t('console.page.overview.description')}
            />

            {error && (
                <p className="mb-4 text-sm text-danger" role="alert">
                    {error.message || t('console.page.overview.load_error')}
                </p>
            )}

            <MyInvitationsPanel variant="banner" />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {cards.map((card) => (
                    <div key={card.key} className="border border-mist bg-paper-elevated p-5">
                        <div className="flex items-center justify-between">
                            <p className="text-sm text-ink-soft/65">{t(card.labelKey)}</p>
                            <card.icon className="size-4 text-teal" strokeWidth={1.75} />
                        </div>
                        <p className="mt-3 font-display text-3xl font-semibold text-ink">
                            {stats
                                ? card.key === 'organizations'
                                    ? organizations.length
                                    : stats[card.key]
                                : '—'}
                        </p>
                        <p className="mt-1 text-xs text-ink-soft/50">{t(card.hintKey)}</p>
                    </div>
                ))}
            </div>

            {applications.length === 0 ? (
                <div className="mt-8">
                    <EmptyState
                        icon={KeyRound}
                        title={t('console.page.overview.first_app_title')}
                        description={t('console.page.overview.first_app_description')}
                        action={
                            <Link
                                to={paths.applications}
                                className="inline-flex items-center gap-2 bg-teal px-4 py-2.5 text-sm font-semibold text-paper hover:bg-teal-bright"
                            >
                                <Plus className="size-4" strokeWidth={2} />
                                {t('console.switcher.create_app')}
                            </Link>
                        }
                    />
                </div>
            ) : (
                <section className="mt-8 border border-mist bg-paper-elevated p-6">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="font-display text-lg font-semibold text-ink">
                                {t('console.page.overview.apps_heading')}
                            </h2>
                            <p className="mt-1 text-sm text-ink-soft/60">
                                {t('console.page.overview.apps_hint')}
                            </p>
                        </div>
                        <Link
                            to={paths.applications}
                            className="text-sm font-medium text-teal hover:text-teal-deep"
                        >
                            {t('console.page.overview.view_all')}
                        </Link>
                    </div>
                    <ul className="mt-5 divide-y divide-fog border border-mist">
                        {applications.slice(0, 5).map((app) => (
                            <li key={app.id}>
                                <Link
                                    to={paths.appHome(app.id)}
                                    className="flex items-center justify-between gap-3 px-4 py-3.5 text-sm transition-colors hover:bg-fog/60"
                                >
                                    <span className="font-medium text-ink">{app.name}</span>
                                    <span className="capitalize text-ink-soft/50">
                                        {app.application_type}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                    {application ? (
                        <p className="mt-4 text-sm text-ink-soft/60">
                            {t('console.page.overview.last_selected')}{' '}
                            <Link
                                to={paths.appHome(application.id)}
                                className="font-medium text-teal hover:text-teal-deep"
                            >
                                {application.name}
                            </Link>
                        </p>
                    ) : null}
                </section>
            )}

            {entitlements?.is_free ? (
                <p className="mt-6 text-sm text-ink-soft/65">
                    {t('console.page.overview.free_plan', {
                        count: entitlements.application_count,
                        limit: entitlements.application_limit ?? 1,
                    })}{' '}
                    <Link to={paths.billing} className="font-medium text-teal hover:text-teal-deep">
                        {t('console.page.overview.upgrade_billing')}
                    </Link>
                </p>
            ) : null}
        </div>
    );
}
