import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus } from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { ConfirmDeleteDialog } from '@/components/ConfirmDeleteDialog';
import { EmptyState } from '@/components/EmptyState';
import { PageHeader } from '@/components/PageHeader';
import { useActiveOrganization } from '@/hooks/useActiveOrganization';
import { useI18n } from '@/hooks/useI18n';
import { useWorkspacePaths } from '@/hooks/useWorkspacePaths';
import { apiDelete, apiGet, apiPost } from '@/lib/api';
import { orgApiPath } from '@/lib/orgApi';
import { queryKeys } from '@/lib/queryKeys';
import { toastError, toastSuccess } from '@/lib/toast';
import type { ApplicationTypeOption, OAuthClient, PlanEntitlements } from '@/types';
import { useWorkspace } from '@/workspace/WorkspaceContext';
import {
    ApplicationCredentialsCard,
    type CreatedCredentials,
} from './components/ApplicationCredentialsCard';
import { ApplicationsTable } from './components/ApplicationsTable';

export function ApplicationsPage() {
    const navigate = useNavigate();
    const organization = useActiveOrganization();
    const { setApplicationId, refresh, entitlements: workspaceEntitlements } = useWorkspace();
    const paths = useWorkspacePaths();
    const { t } = useI18n();
    const [showForm, setShowForm] = useState(false);
    const [credentials, setCredentials] = useState<CreatedCredentials | null>(null);
    const [revokeTarget, setRevokeTarget] = useState<OAuthClient | null>(null);

    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [applicationType, setApplicationType] = useState<ApplicationTypeOption['value']>('web');
    const [redirectUris, setRedirectUris] = useState('http://localhost:3000/callback');

    const queryClient = useQueryClient();
    const applicationsQuery = useQuery({
        queryKey: organization
            ? queryKeys.org(organization.id).applications()
            : ['org', 'applications', 'disabled'],
        enabled: Boolean(organization),
        queryFn: () =>
            apiGet<{
                data: OAuthClient[];
                application_types: ApplicationTypeOption[];
                entitlements: PlanEntitlements;
            }>(orgApiPath(organization!.id, 'applications')),
    });
    const clients = applicationsQuery.data?.data ?? [];
    const types = applicationsQuery.data?.application_types ?? [];
    const entitlements = applicationsQuery.data?.entitlements ?? null;
    const createApplication = useMutation({
        mutationFn: (payload: {
            organization_id: string;
            name: string;
            description: string | null;
            application_type: ApplicationTypeOption['value'];
            redirect_uris: string[];
            grant_types: string[];
            is_confidential: boolean;
        }) =>
            apiPost<{
                data: OAuthClient;
                client_id: string;
                client_secret: string | null;
                warning: string | null;
            }>(orgApiPath(organization!.id, 'applications'), payload),
        onError: (error) => toastError(error, 'Failed to create application.'),
    });
    const revokeApplication = useMutation({
        mutationFn: (clientId: string) =>
            apiDelete(orgApiPath(organization!.id, `applications/${clientId}`)),
        onError: (error) => toastError(error, 'Failed to revoke application.'),
    });
    const selectedType = useMemo(
        () => types.find((type) => type.value === applicationType) ?? null,
        [types, applicationType],
    );

    const canCreate =
        entitlements?.can_create_application ??
        workspaceEntitlements?.can_create_application ??
        true;

    async function onCreate(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        if (!organization || !selectedType) {
            return;
        }

        try {
            const uris = redirectUris
                .split('\n')
                .map((line) => line.trim())
                .filter(Boolean);

            const response = await createApplication.mutateAsync({
                organization_id: organization.id,
                name: name.trim(),
                description: description.trim() || null,
                application_type: applicationType,
                redirect_uris: selectedType.requires_redirect_uris ? uris : [],
                grant_types: selectedType.grant_types,
                is_confidential: selectedType.is_confidential,
            });

            setCredentials({
                client_id: response.client_id,
                client_secret: response.client_secret,
                warning: response.warning,
            });
            setName('');
            setDescription('');
            setShowForm(false);
            await queryClient.invalidateQueries({
                queryKey: queryKeys.org(organization.id).applications(),
            });
            setApplicationId(response.client_id);
            await refresh();
            toastSuccess('Application created.');
            navigate(paths.appHome(response.client_id));
        } catch {
            // Mutation reports the error.
        }
    }

    async function confirmRevoke(): Promise<void> {
        if (!organization || !revokeTarget) {
            return;
        }

        try {
            await revokeApplication.mutateAsync(revokeTarget.id);
            await queryClient.invalidateQueries({
                queryKey: queryKeys.org(organization.id).applications(),
            });
            await refresh();
            setRevokeTarget(null);
            toastSuccess('Application revoked.');
        } catch {
            // Mutation reports the error.
        }
    }

    if (!organization) {
        return (
            <EmptyState
                icon={Plus}
                title={t('console.common.need_org_title')}
                description={t('console.page.applications.need_org_description')}
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
                title={t('console.page.applications.title')}
                description={t('console.page.applications.description')}
                action={
                    <button
                        type="button"
                        disabled={!canCreate}
                        onClick={() => {
                            setShowForm((value) => !value);
                            setCredentials(null);
                        }}
                        className="inline-flex items-center gap-2 bg-ink px-3.5 py-2 text-sm font-medium text-paper transition-colors hover:bg-ink-soft disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Plus className="size-4" strokeWidth={1.75} />
                        {t('console.page.applications.new')}
                    </button>
                }
            />

            {entitlements?.is_free && (
                <p className="mb-4 border border-mist bg-fog px-4 py-3 text-sm text-ink-soft/70">
                    {t('console.page.applications.free_plan_banner', {
                        limit: entitlements.application_limit ?? 1,
                        used: entitlements.application_count,
                    })}{' '}
                    <Link to={paths.billing} className="text-teal hover:text-teal-deep">
                        {t('console.common.view_billing')}
                    </Link>
                </p>
            )}

            {applicationsQuery.error && (
                <p className="mb-4 text-sm text-danger" role="alert">
                    {applicationsQuery.error.message || 'Failed to load applications.'}
                </p>
            )}

            {credentials !== null && (
                <ApplicationCredentialsCard
                    credentials={credentials}
                    appHome={paths.appHome}
                    clientIdLabel={t('console.page.applications.client_id')}
                    clientSecretLabel={t('console.page.applications.client_secret')}
                    createdLabel={t('console.page.applications.created')}
                    configureLabel={t('console.page.applications.configure_login')}
                />
            )}

            {showForm && canCreate && (
                <form onSubmit={onCreate} className="mb-8 border border-mist bg-paper-elevated p-6">
                    <h2 className="font-display text-lg font-semibold text-ink">
                        {t('console.page.applications.new_oauth')}
                    </h2>
                    <p className="mt-1 text-sm text-ink-soft/65">
                        {t('console.page.applications.new_oauth_hint')}
                    </p>

                    <div className="mt-6 grid gap-3 sm:grid-cols-2">
                        {types.map((type) => (
                            <label
                                key={type.value}
                                className={[
                                    'cursor-pointer border p-4 transition-colors',
                                    applicationType === type.value
                                        ? 'border-teal bg-fog'
                                        : 'border-mist hover:border-mist/80',
                                ].join(' ')}
                            >
                                <input
                                    type="radio"
                                    name="application_type"
                                    value={type.value}
                                    checked={applicationType === type.value}
                                    onChange={() => setApplicationType(type.value)}
                                    className="sr-only"
                                />
                                <span className="font-display text-sm font-semibold text-ink">
                                    {type.label}
                                </span>
                                <span className="mt-1 block text-xs text-ink-soft/60">
                                    {type.grant_types.join(' · ')}
                                    {type.is_confidential ? ' · confidential' : ' · public'}
                                </span>
                            </label>
                        ))}
                    </div>

                    <div className="mt-6 grid gap-4">
                        <label className="text-sm">
                            <span className="mb-1.5 block font-medium text-ink">
                                {t('console.common.name')}
                            </span>
                            <input
                                required
                                value={name}
                                onChange={(event) => setName(event.target.value)}
                                placeholder="Acme Dashboard"
                                className="w-full border border-mist bg-paper px-3 py-2.5 outline-none focus:border-teal"
                            />
                        </label>

                        <label className="text-sm">
                            <span className="mb-1.5 block font-medium text-ink">Description</span>
                            <input
                                value={description}
                                onChange={(event) => setDescription(event.target.value)}
                                placeholder="Optional"
                                className="w-full border border-mist bg-paper px-3 py-2.5 outline-none focus:border-teal"
                            />
                        </label>

                        {selectedType?.requires_redirect_uris && (
                            <label className="text-sm">
                                <span className="mb-1.5 block font-medium text-ink">
                                    Redirect URIs
                                </span>
                                <textarea
                                    required
                                    rows={3}
                                    value={redirectUris}
                                    onChange={(event) => setRedirectUris(event.target.value)}
                                    className="w-full border border-mist bg-paper px-3 py-2.5 font-mono text-[13px] outline-none focus:border-teal"
                                />
                            </label>
                        )}
                    </div>

                    <div className="mt-6 flex gap-3">
                        <button
                            type="submit"
                            disabled={createApplication.isPending}
                            className="bg-teal px-4 py-2.5 text-sm font-semibold text-paper hover:bg-teal-bright disabled:opacity-60"
                        >
                            {createApplication.isPending ? 'Creating…' : 'Create application'}
                        </button>
                        <button
                            type="button"
                            onClick={() => setShowForm(false)}
                            className="px-4 py-2.5 text-sm text-ink-soft/70 hover:text-ink"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            )}

            {clients.length === 0 ? (
                <EmptyState
                    icon={Plus}
                    title={t('console.page.applications.empty_title')}
                    description={t('console.page.applications.empty_description')}
                    action={
                        canCreate ? (
                            <button
                                type="button"
                                onClick={() => setShowForm(true)}
                                className="inline-flex items-center gap-2 bg-teal px-4 py-2.5 text-sm font-semibold text-paper hover:bg-teal-bright"
                            >
                                <Plus className="size-4" strokeWidth={2} />
                                {t('console.page.applications.new')}
                            </button>
                        ) : (
                            <Link
                                to={paths.billing}
                                className="inline-flex bg-teal px-4 py-2.5 text-sm font-semibold text-paper hover:bg-teal-bright"
                            >
                                {t('console.common.upgrade')}
                            </Link>
                        )
                    }
                />
            ) : (
                <ApplicationsTable
                    clients={clients}
                    appHome={paths.appHome}
                    onSelect={setApplicationId}
                    onRevoke={(clientId) => {
                        setRevokeTarget(clients.find((client) => client.id === clientId) ?? null);
                    }}
                />
            )}

            <ConfirmDeleteDialog
                open={revokeTarget !== null}
                title={t('console.page.applications.revoke_title')}
                description={t('console.page.applications.revoke_body', {
                    name: revokeTarget?.name ?? '',
                })}
                confirmLabel={t('console.common.revoke')}
                pending={revokeApplication.isPending}
                onCancel={() => setRevokeTarget(null)}
                onConfirm={() => void confirmRevoke()}
            />
        </div>
    );
}
