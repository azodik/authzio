import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Globe, Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { Link } from 'react-router';
import { ConfirmDeleteDialog } from '@/components/ConfirmDeleteDialog';
import { EmptyState } from '@/components/EmptyState';
import { PageHeader } from '@/components/PageHeader';
import { useActiveOrganization } from '@/hooks/useActiveOrganization';
import { useDemoPolicy } from '@/hooks/useDemoPolicy';
import { useI18n } from '@/hooks/useI18n';
import { useWorkspacePaths } from '@/hooks/useWorkspacePaths';
import { apiDelete, apiGet, apiPost, apiPut, type JsonValue } from '@/lib/api';
import { orgApiPath } from '@/lib/orgApi';
import { queryKeys } from '@/lib/queryKeys';
import { toastError, toastSuccess } from '@/lib/toast';
import type { OrganizationDomain, PlanEntitlements } from '@/types';
import { DomainVerificationPanel } from './components/DomainVerificationPanel';

type DomainsResponse = {
    domains: OrganizationDomain[];
    root_domain: string;
    cname_target: string | null;
    cloudflare_saas: boolean;
    app_url: string | null;
    organization: { subdomain: string | null };
    entitlements: PlanEntitlements;
};

export function DomainsPage() {
    const organization = useActiveOrganization();
    const paths = useWorkspacePaths();
    const { t } = useI18n();
    const demo = useDemoPolicy();
    const domainsLocked = demo.isDenied('domain.mutate');
    const orgId = organization?.id;
    const queryClient = useQueryClient();
    const [subdomain, setSubdomain] = useState('');
    const [customHost, setCustomHost] = useState('');
    const [removeTarget, setRemoveTarget] = useState<OrganizationDomain | null>(null);
    const query = useQuery({
        queryKey: orgId ? queryKeys.org(orgId).domains() : ['org', 'domains', 'disabled'],
        enabled: Boolean(orgId),
        queryFn: () => apiGet<DomainsResponse>(orgApiPath(orgId!, 'domains')),
    });
    const refresh = () =>
        queryClient.invalidateQueries({ queryKey: queryKeys.org(orgId!).domains() });
    const mutation = useMutation({
        mutationFn: ({
            method,
            path,
            body,
        }: {
            method: 'post' | 'put' | 'delete';
            path: string;
            body?: JsonValue;
        }) => {
            const url = orgApiPath(orgId!, path);
            if (method === 'post') return apiPost(url, body);
            if (method === 'put') return apiPut(url, body);
            return apiDelete(url);
        },
        onSuccess: () => {
            void refresh();
            setRemoveTarget(null);
            toastSuccess('Domain settings updated.');
        },
        onError: (error) => toastError(error, 'Failed to update domains.'),
    });
    const data = query.data;
    const currentSubdomain = subdomain || data?.organization.subdomain || organization?.slug || '';

    function saveSubdomain(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        mutation.mutate({
            method: 'put',
            path: 'domains/subdomain',
            body: { organization_id: orgId!, subdomain: currentSubdomain.trim() },
        });
    }
    function addCustom(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        mutation.mutate(
            {
                method: 'post',
                path: 'domains',
                body: { organization_id: orgId!, host: customHost.trim() },
            },
            { onSuccess: () => setCustomHost('') },
        );
    }
    if (!organization)
        return (
            <EmptyState
                title={t('console.common.need_org_title')}
                description={t('console.page.domains.need_org_description')}
            />
        );

    const canCustom = data?.entitlements.allows_custom_domains ?? false;
    return (
        <div>
            <PageHeader
                title={t('console.page.domains.title')}
                description={t('console.page.domains.description')}
            />
            {query.isError && (
                <p className="mb-4 text-sm text-danger" role="alert">
                    Failed to load domains.
                </p>
            )}
            {domainsLocked && (
                <p className="mb-4 border border-mist bg-fog px-4 py-3 text-sm text-ink-soft/70">
                    {t('console.page.domains.demo_locked')}
                </p>
            )}
            {!canCustom && !domainsLocked && (
                <p className="mb-4 border border-mist bg-fog px-4 py-3 text-sm text-ink-soft/70">
                    {t('console.page.domains.custom_required')}{' '}
                    <Link to={paths.billing} className="text-teal">
                        {t('console.common.upgrade')}
                    </Link>
                </p>
            )}
            <fieldset
                disabled={domainsLocked || mutation.isPending}
                className="min-w-0 border-0 p-0"
            >
                {data?.app_url && (
                    <div className="mb-6 flex gap-3 border border-mist bg-fog p-4">
                        <Globe className="size-4 text-teal" />
                        <div>
                            <p className="text-sm font-medium">
                                {t('console.page.domains.primary_host')}
                            </p>
                            <a href={data.app_url} className="font-mono text-sm text-teal">
                                {data.app_url}
                            </a>
                        </div>
                    </div>
                )}
                <section className="mb-10 border border-mist bg-paper-elevated p-6">
                    <h2 className="font-display font-semibold">
                        {t('console.page.domains.subdomain_heading')}
                    </h2>
                    <p className="mt-1 text-sm text-ink-soft/65">
                        {t('console.page.domains.subdomain_hint')}
                    </p>
                    <form
                        onSubmit={saveSubdomain}
                        className="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end"
                    >
                        <label className="flex-1 text-sm">
                            <span className="mb-1.5 block font-medium">
                                {t('console.page.domains.subdomain_label')}
                            </span>
                            <div className="flex border border-mist">
                                <input
                                    required
                                    value={currentSubdomain}
                                    onChange={(event) => setSubdomain(event.target.value)}
                                    className="min-w-0 flex-1 px-3 py-2.5 outline-none"
                                />
                                <span className="border-l border-mist px-3 py-2.5">
                                    .{data?.root_domain ?? ''}
                                </span>
                            </div>
                        </label>
                        <button
                            type="submit"
                            disabled={mutation.isPending}
                            className="bg-ink px-4 py-2.5 text-sm font-semibold text-paper"
                        >
                            {t('console.page.domains.save_subdomain')}
                        </button>
                    </form>
                </section>
                <section className="mb-10 border border-mist bg-paper-elevated p-6">
                    <h2 className="font-display font-semibold">
                        {t('console.page.domains.custom_heading')}
                    </h2>
                    <p className="mt-1 text-sm text-ink-soft/65">
                        {t('console.page.domains.custom_hint')}
                    </p>
                    {canCustom ? (
                        <form onSubmit={addCustom} className="mt-5 flex gap-3">
                            <input
                                required
                                value={customHost}
                                onChange={(event) => setCustomHost(event.target.value)}
                                placeholder="auth.example.com"
                                className="flex-1 border border-mist px-3 py-2.5"
                            />
                            <button
                                type="submit"
                                disabled={mutation.isPending}
                                className="inline-flex items-center gap-2 bg-teal px-4 text-paper"
                            >
                                <Plus className="size-4" />
                                {t('console.page.domains.add')}
                            </button>
                        </form>
                    ) : (
                        <p className="mt-5 text-sm text-ink-soft/55">
                            {t('console.page.domains.upgrade_hint')}
                        </p>
                    )}
                </section>
                <section>
                    <h2 className="mb-3 font-display font-semibold">
                        {t('console.page.domains.configured')}
                    </h2>
                    {(data?.domains.length ?? 0) === 0 ? (
                        <EmptyState
                            title={t('console.page.domains.empty_title')}
                            description={t('console.page.domains.empty_description')}
                        />
                    ) : (
                        <ul className="space-y-4">
                            {data?.domains.map((domain) => (
                                <li
                                    key={domain.id}
                                    className="border border-mist bg-paper-elevated"
                                >
                                    <div className="flex items-center justify-between border-b border-fog p-4">
                                        <div>
                                            <p className="font-mono text-sm">{domain.host}</p>
                                            <p className="text-xs text-ink-soft/55">
                                                {domain.type} ·{' '}
                                                {domain.verified_at
                                                    ? t('console.common.verified')
                                                    : t('console.common.pending')}
                                            </p>
                                        </div>
                                        <div className="flex gap-2">
                                            {domain.type === 'custom' && !domain.verified_at && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        mutation.mutate({
                                                            method: 'post',
                                                            path: `domains/${domain.id}/verify`,
                                                        })
                                                    }
                                                    className="bg-ink px-3 py-2 text-sm text-paper"
                                                >
                                                    {t('console.common.verify')}
                                                </button>
                                            )}
                                            {domain.type === 'custom' && (
                                                <button
                                                    type="button"
                                                    onClick={() => setRemoveTarget(domain)}
                                                    className="px-3 py-2 text-sm text-danger"
                                                >
                                                    {t('console.common.remove')}
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                    <div className="bg-fog/40 p-4">
                                        <DomainVerificationPanel
                                            domain={domain}
                                            cnameTarget={data.cname_target}
                                            cloudflareSaas={data.cloudflare_saas}
                                        />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </fieldset>

            <ConfirmDeleteDialog
                open={removeTarget !== null}
                title={t('console.page.domains.remove_title')}
                description={t('console.page.domains.remove_body', {
                    host: removeTarget?.host ?? '',
                })}
                confirmLabel={t('console.common.remove')}
                pending={mutation.isPending && mutation.variables?.method === 'delete'}
                onCancel={() => setRemoveTarget(null)}
                onConfirm={() => {
                    if (!removeTarget) {
                        return;
                    }
                    mutation.mutate({
                        method: 'delete',
                        path: `domains/${removeTarget.id}`,
                    });
                }}
            />
        </div>
    );
}
