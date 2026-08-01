import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Check, Copy } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
import { EmptyState } from '@/components/EmptyState';
import { PageHeader } from '@/components/PageHeader';
import { useActiveOrganization } from '@/hooks/useActiveOrganization';
import { useI18n } from '@/hooks/useI18n';
import { apiGet, apiPost } from '@/lib/api';
import { orgApiPath } from '@/lib/orgApi';
import { queryKeys } from '@/lib/queryKeys';
import { toastError, toastSuccess } from '@/lib/toast';
import type { SocialProviderKey } from '@/types';

type SocialProviderRow = {
    provider: SocialProviderKey;
    label: string;
    description: string;
    configured: boolean;
    enabled: boolean;
    client_id: string | null;
    has_client_secret: boolean;
    scopes: string[];
    callback_url: string;
};

type ProviderDraft = {
    client_id: string;
    client_secret: string;
    enabled: boolean;
};

export function SocialProvidersPage() {
    const organization = useActiveOrganization();
    const { t } = useI18n();
    const [copied, setCopied] = useState<string | null>(null);
    const [expanded, setExpanded] = useState<string | null>(null);
    const [drafts, setDrafts] = useState<Record<string, ProviderDraft>>({});

    const queryClient = useQueryClient();
    const providersQuery = useQuery({
        queryKey: organization
            ? queryKeys.org(organization.id).socialProviders()
            : ['org', 'social-providers', 'disabled'],
        enabled: Boolean(organization),
        queryFn: () =>
            apiGet<{ data: SocialProviderRow[] }>(orgApiPath(organization!.id, 'social-providers')),
    });
    const providers = providersQuery.data?.data ?? [];
    useEffect(() => {
        if (!providersQuery.data) return;
        const response = providersQuery.data;
        setDrafts(
            Object.fromEntries(
                response.data.map((provider) => [
                    provider.provider,
                    {
                        client_id: provider.client_id ?? '',
                        client_secret: '',
                        enabled: provider.enabled,
                    },
                ]),
            ),
        );
        setExpanded((current) => current ?? response.data.find((p) => p.enabled)?.provider ?? null);
    }, [providersQuery.data]);
    const saveProvider = useMutation({
        mutationFn: (payload: {
            provider: SocialProviderKey;
            client_id: string;
            client_secret: string | null;
            enabled: boolean;
        }) =>
            apiPost(orgApiPath(organization!.id, 'social-providers'), {
                organization_id: organization!.id,
                ...payload,
            }),
        onError: (error) => toastError(error, 'Failed to save provider.'),
    });

    async function copyCallback(url: string, provider: string): Promise<void> {
        try {
            await navigator.clipboard.writeText(url);
            setCopied(provider);
            window.setTimeout(() => setCopied(null), 2000);
        } catch {
            toastError(new Error('Could not copy callback URL.'));
        }
    }

    async function onSave(
        event: FormEvent<HTMLFormElement>,
        provider: SocialProviderRow,
    ): Promise<void> {
        event.preventDefault();
        if (!organization) {
            return;
        }

        const draft = drafts[provider.provider];
        if (!draft) {
            return;
        }

        try {
            await saveProvider.mutateAsync({
                provider: provider.provider,
                client_id: draft.client_id.trim(),
                client_secret: draft.client_secret.trim() || null,
                enabled: draft.enabled,
            });
            toastSuccess(t('console.page.social.saved', { label: provider.label }));
            await queryClient.invalidateQueries({
                queryKey: queryKeys.org(organization.id).socialProviders(),
            });
        } catch {
            // Mutation reports the error.
        }
    }

    if (!organization) {
        return (
            <EmptyState
                title={t('console.common.need_org_title')}
                description={t('console.page.social.need_org_description')}
            />
        );
    }

    return (
        <div>
            <PageHeader
                title={t('console.page.social.title')}
                description={t('console.page.social.description')}
            />

            {providersQuery.error && (
                <p className="mb-4 text-sm text-danger" role="alert">
                    {providersQuery.error.message || 'Failed to load providers.'}
                </p>
            )}

            <div className="grid gap-4 lg:grid-cols-2">
                {providers.map((provider) => {
                    const draft = drafts[provider.provider] ?? {
                        client_id: '',
                        client_secret: '',
                        enabled: false,
                    };
                    const isOpen = expanded === provider.provider;
                    const statusLabel = draft.enabled
                        ? t('console.common.enabled')
                        : provider.configured
                          ? t('console.page.social.status_configured')
                          : t('console.page.social.status_not_configured');

                    return (
                        <div
                            key={provider.provider}
                            className="border border-mist bg-paper-elevated"
                        >
                            <button
                                type="button"
                                onClick={() =>
                                    setExpanded((current) =>
                                        current === provider.provider ? null : provider.provider,
                                    )
                                }
                                className="flex w-full items-start justify-between gap-3 px-5 py-4 text-left"
                            >
                                <div>
                                    <h2 className="font-display text-lg font-semibold text-ink">
                                        {provider.label}
                                    </h2>
                                    <p className="mt-1 text-sm text-ink-soft/65">
                                        {provider.description}
                                    </p>
                                </div>
                                <span
                                    className={[
                                        'shrink-0 px-2 py-1 text-xs font-medium',
                                        draft.enabled
                                            ? 'bg-teal/15 text-teal-deep'
                                            : 'bg-fog text-ink-soft/70',
                                    ].join(' ')}
                                >
                                    {statusLabel}
                                </span>
                            </button>

                            {isOpen && (
                                <form
                                    onSubmit={(event) => {
                                        void onSave(event, provider);
                                    }}
                                    className="border-t border-fog px-5 py-5"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <code className="min-w-0 flex-1 truncate bg-fog px-2.5 py-1.5 font-mono text-[11px] text-ink-soft/70">
                                            {provider.callback_url}
                                        </code>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                void copyCallback(
                                                    provider.callback_url,
                                                    provider.provider,
                                                );
                                            }}
                                            className="inline-flex items-center gap-1.5 border border-mist px-2.5 py-1.5 text-xs font-medium text-ink hover:bg-fog"
                                        >
                                            {copied === provider.provider ? (
                                                <Check className="size-3.5 text-success" />
                                            ) : (
                                                <Copy className="size-3.5" />
                                            )}
                                            {t('console.common.copy')}
                                        </button>
                                    </div>

                                    <label className="mt-5 flex items-center gap-2 text-sm text-ink">
                                        <input
                                            type="checkbox"
                                            checked={draft.enabled}
                                            onChange={(event) =>
                                                setDrafts((current) => ({
                                                    ...current,
                                                    [provider.provider]: {
                                                        ...draft,
                                                        enabled: event.target.checked,
                                                    },
                                                }))
                                            }
                                        />
                                        {t('console.common.enabled')}
                                    </label>

                                    <div className="mt-4 grid gap-4">
                                        <label className="text-sm">
                                            <span className="mb-1.5 block font-medium text-ink">
                                                {t('console.page.social.client_id')}
                                            </span>
                                            <input
                                                required
                                                value={draft.client_id}
                                                onChange={(event) =>
                                                    setDrafts((current) => ({
                                                        ...current,
                                                        [provider.provider]: {
                                                            ...draft,
                                                            client_id: event.target.value,
                                                        },
                                                    }))
                                                }
                                                className="w-full border border-mist bg-paper px-3 py-2.5 outline-none focus:border-teal"
                                            />
                                        </label>
                                        <label className="text-sm">
                                            <span className="mb-1.5 block font-medium text-ink">
                                                {t('console.page.social.client_secret')}
                                                {provider.has_client_secret
                                                    ? ` ${t('console.page.social.secret_keep')}`
                                                    : ''}
                                            </span>
                                            <input
                                                type="password"
                                                value={draft.client_secret}
                                                onChange={(event) =>
                                                    setDrafts((current) => ({
                                                        ...current,
                                                        [provider.provider]: {
                                                            ...draft,
                                                            client_secret: event.target.value,
                                                        },
                                                    }))
                                                }
                                                className="w-full border border-mist bg-paper px-3 py-2.5 outline-none focus:border-teal"
                                            />
                                        </label>
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={
                                            saveProvider.isPending &&
                                            saveProvider.variables?.provider === provider.provider
                                        }
                                        className="mt-5 bg-ink px-4 py-2.5 text-sm font-semibold text-paper hover:bg-ink-soft disabled:opacity-60"
                                    >
                                        {saveProvider.isPending &&
                                        saveProvider.variables?.provider === provider.provider
                                            ? t('console.common.saving')
                                            : t('console.common.save_changes')}
                                    </button>
                                </form>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
