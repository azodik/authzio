import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Check, Copy, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router';
import { ConfirmDeleteDialog } from '@/components/ConfirmDeleteDialog';
import { EmptyState } from '@/components/EmptyState';
import { PageHeader } from '@/components/PageHeader';
import { useActiveOrganization } from '@/hooks/useActiveOrganization';
import { useI18n } from '@/hooks/useI18n';
import { useWorkspacePaths } from '@/hooks/useWorkspacePaths';
import { apiDelete, apiGet, apiPost, apiPut } from '@/lib/api';
import { orgApiPath } from '@/lib/orgApi';
import { queryKeys } from '@/lib/queryKeys';
import { toastError, toastSuccess } from '@/lib/toast';
import type { PlanEntitlements } from '@/types';
import { emptySsoDraft, SsoConnectionForm, type SsoDraft } from './components/SsoConnectionForm';

type SsoConnection = {
    id: string;
    name: string;
    issuer: string;
    client_id: string;
    email_domains: string[];
    enabled: boolean;
    callback_url: string;
};
type SsoResponse = { entitlements: PlanEntitlements; data: SsoConnection[] };
const payload = (draft: SsoDraft, orgId: string) => ({
    organization_id: orgId,
    name: draft.name.trim(),
    issuer: draft.issuer.trim(),
    client_id: draft.client_id.trim(),
    client_secret: draft.client_secret.trim() || null,
    email_domains: draft.email_domains
        .split(/[\s,]+/)
        .map((value) => value.trim().toLowerCase())
        .filter(Boolean),
    enabled: draft.enabled,
    discover: true,
});

export function SsoPage() {
    const organization = useActiveOrganization();
    const paths = useWorkspacePaths();
    const { t } = useI18n();
    const orgId = organization?.id;
    const queryClient = useQueryClient();
    const [creating, setCreating] = useState(false);
    const [editingId, setEditingId] = useState<string | null>(null);
    const [copied, setCopied] = useState<string | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<SsoConnection | null>(null);
    const query = useQuery({
        queryKey: orgId ? queryKeys.org(orgId).sso() : ['org', 'sso', 'disabled'],
        enabled: Boolean(orgId),
        queryFn: () => apiGet<SsoResponse>(orgApiPath(orgId!, 'sso-connections')),
    });
    const mutation = useMutation({
        mutationFn: ({
            method,
            id,
            draft,
        }: {
            method: 'create' | 'update' | 'delete';
            id?: string;
            draft?: SsoDraft;
        }) => {
            if (method === 'delete') return apiDelete(orgApiPath(orgId!, `sso-connections/${id}`));
            const body = payload(draft!, orgId!);
            return method === 'create'
                ? apiPost(orgApiPath(orgId!, 'sso-connections'), body)
                : apiPut(orgApiPath(orgId!, `sso-connections/${id}`), body);
        },
        onSuccess: (_, variables) => {
            void queryClient.invalidateQueries({ queryKey: queryKeys.org(orgId!).sso() });
            toastSuccess(
                variables.method === 'delete'
                    ? t('console.page.sso.deleted')
                    : variables.method === 'create'
                      ? t('console.page.sso.created')
                      : t('console.page.sso.saved'),
            );
            setCreating(false);
            setEditingId(null);
            setDeleteTarget(null);
        },
        onError: (error) => toastError(error, 'Failed to update SSO connection.'),
    });
    if (!organization)
        return (
            <EmptyState
                title={t('console.common.need_org_title')}
                description={t('console.page.sso.need_org_description')}
            />
        );
    const allowsSso = query.data?.entitlements.allows_sso ?? false;
    return (
        <div className="space-y-6">
            <PageHeader
                title={t('console.page.sso.title')}
                description={t('console.page.sso.description')}
            />
            {query.isError && (
                <p className="text-sm text-danger">Failed to load SSO connections.</p>
            )}
            {!allowsSso && (
                <div className="border border-amber-200 bg-amber-50 p-4 text-sm">
                    {t('console.page.sso.upgrade_banner')}{' '}
                    <Link to={paths.billing} className="underline">
                        {t('console.common.view_billing')}
                    </Link>
                </div>
            )}
            {allowsSso && (
                <div className="flex justify-end">
                    <button
                        type="button"
                        onClick={() => setCreating(!creating)}
                        className="inline-flex items-center gap-2 bg-ink px-3.5 py-2 text-sm text-paper"
                    >
                        <Plus className="size-4" />
                        {t('console.page.sso.add')}
                    </button>
                </div>
            )}
            {creating && (
                <div className="border border-mist bg-paper-elevated p-5">
                    <h3 className="mb-4 font-display text-lg font-semibold">
                        {t('console.page.sso.new_heading')}
                    </h3>
                    <SsoConnectionForm
                        pending={mutation.isPending}
                        onSubmit={(draft) =>
                            mutation.mutateAsync({ method: 'create', draft }).then(() => undefined)
                        }
                    />
                </div>
            )}
            {(query.data?.data.length ?? 0) === 0 && (
                <EmptyState
                    title={t('console.page.sso.empty_title')}
                    description={
                        allowsSso
                            ? t('console.page.sso.empty_description')
                            : t('console.page.sso.upgrade_banner')
                    }
                />
            )}
            {query.data?.data.map((connection) => (
                <div key={connection.id} className="border border-mist bg-paper-elevated p-5">
                    <div className="flex justify-between gap-3">
                        <div>
                            <h3 className="font-display text-lg font-semibold">
                                {connection.name}
                            </h3>
                            <p className="text-sm text-ink-soft/60">{connection.issuer}</p>
                        </div>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() =>
                                    void navigator.clipboard
                                        .writeText(connection.callback_url)
                                        .then(() => {
                                            setCopied(connection.id);
                                            window.setTimeout(() => setCopied(null), 2000);
                                        })
                                        .catch((error: unknown) =>
                                            toastError(error, 'Could not copy callback URL.'),
                                        )
                                }
                                className="inline-flex items-center gap-1 border border-mist px-2 py-1 text-xs"
                            >
                                {copied === connection.id ? (
                                    <Check className="size-3.5" />
                                ) : (
                                    <Copy className="size-3.5" />
                                )}
                                {t('console.page.sso.copy_callback')}
                            </button>
                            {allowsSso && (
                                <>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setEditingId(
                                                editingId === connection.id ? null : connection.id,
                                            )
                                        }
                                        className="border border-mist px-2 py-1 text-xs"
                                    >
                                        {editingId === connection.id
                                            ? t('console.common.cancel')
                                            : t('console.common.edit')}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setDeleteTarget(connection)}
                                        className="inline-flex items-center gap-1 text-xs text-danger"
                                    >
                                        <Trash2 className="size-3.5" />
                                        {t('console.common.delete')}
                                    </button>
                                </>
                            )}
                        </div>
                    </div>
                    {connection.email_domains.length > 0 && (
                        <p className="mt-3 text-sm text-ink-soft/70">
                            {t('console.page.sso.domains_label')}:{' '}
                            {connection.email_domains.join(', ')}
                        </p>
                    )}
                    {editingId === connection.id && (
                        <div className="mt-4 border-t border-fog pt-4">
                            <SsoConnectionForm
                                editing
                                pending={mutation.isPending}
                                initial={{
                                    ...emptySsoDraft(),
                                    name: connection.name,
                                    issuer: connection.issuer,
                                    client_id: connection.client_id,
                                    email_domains: connection.email_domains.join(', '),
                                    enabled: connection.enabled,
                                }}
                                onSubmit={(draft) =>
                                    mutation
                                        .mutateAsync({ method: 'update', id: connection.id, draft })
                                        .then(() => undefined)
                                }
                            />
                        </div>
                    )}
                </div>
            ))}

            <ConfirmDeleteDialog
                open={deleteTarget !== null}
                title={t('console.page.sso.delete_title')}
                description={t('console.page.sso.delete_body', {
                    name: deleteTarget?.name ?? '',
                })}
                pending={mutation.isPending && mutation.variables?.method === 'delete'}
                onCancel={() => setDeleteTarget(null)}
                onConfirm={() => {
                    if (!deleteTarget) {
                        return;
                    }
                    mutation.mutate({ method: 'delete', id: deleteTarget.id });
                }}
            />
        </div>
    );
}
