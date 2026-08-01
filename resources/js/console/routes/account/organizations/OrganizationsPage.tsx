import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Building2, Plus } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
import { useNavigate } from 'react-router';
import { EmptyState } from '@/components/EmptyState';
import { MyInvitationsPanel } from '@/components/MyInvitationsPanel';
import { PageHeader } from '@/components/PageHeader';
import { useI18n } from '@/hooks/useI18n';
import { apiGet, apiPost } from '@/lib/api';
import { orgPath } from '@/lib/paths';
import { queryKeys } from '@/lib/queryKeys';
import { toOrgSlug } from '@/lib/slug';
import { toastError, toastSuccess } from '@/lib/toast';
import type { Organization } from '@/types';
import { useWorkspace } from '@/workspace/WorkspaceContext';

export function OrganizationsPage() {
    const navigate = useNavigate();
    const { t } = useI18n();
    const { organization, setOrganizationId, refresh, domainRoot } = useWorkspace();
    const [name, setName] = useState('');
    const [slug, setSlug] = useState('');
    const [slugTouched, setSlugTouched] = useState(false);
    const [showForm, setShowForm] = useState(false);
    const queryClient = useQueryClient();

    useEffect(() => {
        if (!slugTouched) {
            setSlug(toOrgSlug(name));
        }
    }, [name, slugTouched]);

    const organizationsQuery = useQuery({
        queryKey: queryKeys.account.organizations(),
        queryFn: () => apiGet<{ data: Organization[] }>('/api/v1/organizations'),
    });
    const organizations = organizationsQuery.data?.data ?? [];
    const createOrganization = useMutation({
        mutationFn: (payload: { name: string; slug: string }) =>
            apiPost<{ data: Organization }>('/api/v1/organizations', payload),
        onError: (error) => toastError(error, 'Failed to create organization.'),
    });

    async function onCreate(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        const normalizedSlug = toOrgSlug(slug);
        if (normalizedSlug.length < 2) {
            toastError(new Error(t('console.page.organizations.slug_invalid')));
            return;
        }

        try {
            const response = await createOrganization.mutateAsync({
                name: name.trim(),
                slug: normalizedSlug,
            });
            setName('');
            setSlug('');
            setSlugTouched(false);
            setShowForm(false);
            await queryClient.invalidateQueries({ queryKey: queryKeys.account.organizations() });
            setOrganizationId(response.data.id);
            await refresh();
            toastSuccess('Organization created.');
            navigate(orgPath(response.data.id));
        } catch {
            // Mutation reports the error.
        }
    }

    return (
        <div>
            <PageHeader
                title={t('console.page.organizations.title')}
                description={t('console.page.organizations.description')}
                action={
                    <button
                        type="button"
                        onClick={() => setShowForm((value) => !value)}
                        className="inline-flex items-center gap-2 bg-ink px-3.5 py-2 text-sm font-medium text-paper transition-colors hover:bg-ink-soft"
                    >
                        <Plus className="size-4" strokeWidth={1.75} />
                        {t('console.page.organizations.new')}
                    </button>
                }
            />

            {organizationsQuery.error && (
                <p className="mb-4 text-sm text-danger" role="alert">
                    {organizationsQuery.error.message || 'Failed to load organizations.'}
                </p>
            )}

            <MyInvitationsPanel />

            {showForm && (
                <form
                    onSubmit={onCreate}
                    className="mb-6 flex flex-col gap-3 border border-mist bg-paper-elevated p-4 sm:flex-row sm:items-end"
                >
                    <label className="flex-1 text-sm">
                        <span className="mb-1.5 block font-medium text-ink">
                            {t('console.common.name')}
                        </span>
                        <input
                            required
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                            className="w-full border border-mist bg-paper px-3 py-2 outline-none focus:border-teal"
                        />
                    </label>
                    <label className="flex-1 text-sm">
                        <span className="mb-1.5 block font-medium text-ink">
                            {t('console.common.slug')}
                        </span>
                        <input
                            required
                            value={slug}
                            onChange={(event) => {
                                setSlugTouched(true);
                                setSlug(toOrgSlug(event.target.value));
                            }}
                            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                            minLength={2}
                            maxLength={63}
                            className="w-full border border-mist bg-paper px-3 py-2 font-mono outline-none focus:border-teal"
                        />
                        <span className="mt-1 block text-xs text-ink-soft/55">
                            {t('console.page.organizations.slug_hint', {
                                host: `${slug || 'acme'}.${domainRoot}`,
                            })}
                        </span>
                    </label>
                    <button
                        type="submit"
                        disabled={createOrganization.isPending || slug.length < 2}
                        className="bg-teal px-4 py-2 text-sm font-semibold text-paper hover:bg-teal-bright disabled:opacity-60"
                    >
                        {createOrganization.isPending
                            ? t('console.common.creating')
                            : t('console.common.create')}
                    </button>
                </form>
            )}

            {organizations.length === 0 ? (
                <EmptyState
                    icon={Building2}
                    title={t('console.page.organizations.empty')}
                    description={t('console.page.organizations.empty_description')}
                    action={
                        <button
                            type="button"
                            onClick={() => setShowForm(true)}
                            className="inline-flex items-center gap-2 bg-teal px-4 py-2.5 text-sm font-semibold text-paper hover:bg-teal-bright"
                        >
                            <Plus className="size-4" strokeWidth={2} />
                            {t('console.page.organizations.new')}
                        </button>
                    }
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
                                    {t('console.common.slug')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('console.common.created')}
                                </th>
                                <th className="px-4 py-3 font-medium"> </th>
                            </tr>
                        </thead>
                        <tbody>
                            {organizations.map((org) => {
                                const active = organization?.id === org.id;
                                function openOrganization(): void {
                                    setOrganizationId(org.id);
                                    navigate(orgPath(org.id));
                                }
                                return (
                                    <tr
                                        key={org.id}
                                        className="cursor-pointer border-b border-fog last:border-0 hover:bg-fog/70"
                                        onClick={openOrganization}
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter' || event.key === ' ') {
                                                event.preventDefault();
                                                openOrganization();
                                            }
                                        }}
                                        tabIndex={0}
                                        aria-label={`${t('console.common.open')} ${org.name}`}
                                    >
                                        <td className="px-4 py-3 font-medium text-ink">
                                            {org.name}
                                            {active ? (
                                                <span className="ml-2 text-xs font-normal text-ink-soft/45">
                                                    {t('console.common.last_opened')}
                                                </span>
                                            ) : null}
                                        </td>
                                        <td className="px-4 py-3 text-ink-soft/70">{org.slug}</td>
                                        <td className="px-4 py-3 text-ink-soft/70">
                                            {org.created_at
                                                ? new Date(org.created_at).toLocaleDateString()
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <span className="min-h-10 px-3 text-sm font-medium text-teal">
                                                {t('console.common.open')}
                                            </span>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
