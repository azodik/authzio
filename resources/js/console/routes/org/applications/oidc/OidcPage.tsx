import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Copy, KeyRound, RefreshCw } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';
import { EmptyState } from '@/components/EmptyState';
import { PageHeader } from '@/components/PageHeader';
import { useActiveOrganization } from '@/hooks/useActiveOrganization';
import { useI18n } from '@/hooks/useI18n';
import { useWorkspacePaths } from '@/hooks/useWorkspacePaths';
import { apiGet, apiPost } from '@/lib/api';
import { orgApiPath } from '@/lib/orgApi';
import { queryKeys } from '@/lib/queryKeys';
import { toastError, toastSuccess } from '@/lib/toast';
import type { OidcSettings } from '@/types';
import { useWorkspace } from '@/workspace/WorkspaceContext';

export function OidcPage() {
    const { appId: clientId } = useParams<{ appId?: string }>();
    const organization = useActiveOrganization();
    const { application, setApplicationId, applications } = useWorkspace();
    const paths = useWorkspacePaths();
    const { t } = useI18n();
    const [privateKey, setPrivateKey] = useState('');
    const [kid, setKid] = useState('');
    const queryClient = useQueryClient();

    const activeApp =
        (clientId ? applications.find((item) => item.id === clientId) : null) ?? application;

    useEffect(() => {
        if (clientId) {
            setApplicationId(clientId);
        }
    }, [clientId, setApplicationId]);

    const settingsQuery = useQuery({
        queryKey:
            organization && activeApp
                ? queryKeys.org(organization.id).oidc(activeApp.id)
                : ['org', 'oidc', 'disabled'],
        enabled: Boolean(organization && activeApp),
        queryFn: () => apiGet<OidcSettings>(orgApiPath(organization!.id, 'oidc')),
    });
    const settings = settingsQuery.data ?? null;
    const generateKey = useMutation({
        mutationFn: () =>
            apiPost<{ message: string; keys: OidcSettings['keys'] }>(
                orgApiPath(organization!.id, 'oidc/keys/generate'),
                { organization_id: organization!.id },
            ),
        onSuccess: (response) => toastSuccess(response.message),
        onError: (error) => toastError(error, 'Failed to generate key.'),
    });
    const importKey = useMutation({
        mutationFn: (payload: {
            organization_id: string;
            private_key: string;
            kid: string | null;
        }) =>
            apiPost<{ message: string }>(orgApiPath(organization!.id, 'oidc/keys/import'), payload),
        onSuccess: (response) => toastSuccess(response.message),
        onError: (error) => toastError(error, 'Failed to import key.'),
    });

    async function onGenerate(): Promise<void> {
        if (!organization) {
            return;
        }

        try {
            await generateKey.mutateAsync();
            await queryClient.invalidateQueries({
                queryKey: queryKeys.org(organization.id).oidc(activeApp!.id),
            });
        } catch {
            // Mutation reports the error.
        }
    }

    async function onImport(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        if (!organization) {
            return;
        }

        try {
            await importKey.mutateAsync({
                organization_id: organization.id,
                private_key: privateKey,
                kid: kid.trim() || null,
            });
            setPrivateKey('');
            setKid('');
            await queryClient.invalidateQueries({
                queryKey: queryKeys.org(organization.id).oidc(activeApp!.id),
            });
        } catch {
            // Mutation reports the error.
        }
    }

    if (!organization) {
        return (
            <EmptyState
                title={t('console.common.need_org_title')}
                description={t('console.page.oidc.need_org_description')}
            />
        );
    }

    if (!activeApp) {
        return (
            <EmptyState
                title={t('console.page.oidc.select_app_title')}
                description={t('console.page.oidc.select_app_description')}
            />
        );
    }

    const canImport = settings?.entitlements.allows_custom_jwks ?? false;

    return (
        <div>
            <PageHeader
                title={t('console.page.oidc.title')}
                description={`Issuer endpoints and JWKS for ${organization.name}, with client ${activeApp.name}.`}
            />

            <div className="mb-6 border border-mist bg-fog/50 p-4 text-sm text-ink-soft/75">
                <p>
                    <span className="font-medium text-ink">Client ID:</span>{' '}
                    <code className="font-mono text-xs">{activeApp.id}</code>
                </p>
                <p className="mt-2">
                    Configure login branding in{' '}
                    <Link
                        to={paths.appHome(activeApp.id)}
                        className="font-medium text-teal hover:text-teal-deep"
                    >
                        Settings &amp; login
                    </Link>
                    . Signing keys are shared across applications in this organization.
                </p>
            </div>

            {settingsQuery.error && (
                <p className="mb-4 text-sm text-danger" role="alert">
                    {settingsQuery.error.message || 'Failed to load OIDC settings.'}
                </p>
            )}

            {settings === null ? (
                <p className="text-sm text-ink-soft/60">{t('console.loading')}</p>
            ) : (
                <>
                    <section className="border border-mist bg-paper-elevated p-6">
                        <h2 className="font-display text-lg font-semibold text-ink">Issuer</h2>
                        <p className="mt-1 text-sm text-ink-soft/65">
                            Resolved from your primary domain / Authzio subdomain.
                        </p>
                        <dl className="mt-5 space-y-3 text-sm">
                            <Row label="Issuer" value={settings.issuer} copyable />
                            <Row label="Discovery" value={settings.discovery_url} copyable />
                            <Row label="JWKS" value={settings.endpoints.jwks_uri} copyable />
                            <Row
                                label="Authorize"
                                value={settings.endpoints.authorization_endpoint}
                                copyable
                            />
                            <Row label="Token" value={settings.endpoints.token_endpoint} copyable />
                            <Row
                                label="UserInfo"
                                value={settings.endpoints.userinfo_endpoint}
                                copyable
                            />
                        </dl>
                        {activeApp.redirect_uris[0] ? (
                            <div className="mt-5 border border-mist bg-fog p-4">
                                <p className="text-sm font-medium text-ink">Sample authorize URL</p>
                                <p className="mt-1 text-xs text-ink-soft/55">
                                    No spaces between params. Encode spaces inside values (e.g.
                                    scope) as <code>%20</code>.
                                </p>
                                <Row
                                    label="Try"
                                    value={`${settings.endpoints.authorization_endpoint}?${new URLSearchParams(
                                        {
                                            client_id: activeApp.id,
                                            redirect_uri: activeApp.redirect_uris[0],
                                            response_type: 'code',
                                            scope: 'openid profile email',
                                            state: 'example-state',
                                            code_challenge:
                                                'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
                                            code_challenge_method: 'S256',
                                        },
                                    ).toString()}`}
                                    copyable
                                />
                            </div>
                        ) : (
                            <p className="mt-4 text-xs text-ink-soft/50">
                                Add a redirect URI on the app to generate a sample authorize URL.
                            </p>
                        )}
                        <p className="mt-4 text-xs text-ink-soft/50">
                            Point app OIDC libraries at the discovery URL. Use subdomain host (e.g.
                            demo-org.authzio.test) for tenant-correct issuer resolution.
                        </p>
                    </section>

                    <section className="mt-8 border border-mist bg-paper-elevated p-6">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 className="font-display text-lg font-semibold text-ink">
                                    Signing keys (JWKS)
                                </h2>
                                <p className="mt-1 text-sm text-ink-soft/65">
                                    ID tokens are signed with RS256. Free plans use managed keys;
                                    paid plans can import a custom private key.
                                </p>
                            </div>
                            <button
                                type="button"
                                disabled={generateKey.isPending}
                                onClick={() => {
                                    void onGenerate();
                                }}
                                className="inline-flex items-center gap-2 bg-ink px-3.5 py-2 text-sm font-medium text-paper hover:bg-ink-soft disabled:opacity-60"
                            >
                                <RefreshCw className="size-4" strokeWidth={1.75} />
                                Rotate / generate
                            </button>
                        </div>

                        <div className="mt-5 overflow-x-auto border border-mist">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-mist bg-fog text-ink-soft/70">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">kid</th>
                                        <th className="px-4 py-3 font-medium">alg</th>
                                        <th className="px-4 py-3 font-medium">Status</th>
                                        <th className="px-4 py-3 font-medium">Source</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {settings.keys.map((key) => (
                                        <tr
                                            key={key.id}
                                            className="border-b border-fog last:border-0"
                                        >
                                            <td className="px-4 py-3 font-mono text-xs text-ink">
                                                {key.kid}
                                            </td>
                                            <td className="px-4 py-3 text-ink-soft/70">
                                                {key.alg}
                                            </td>
                                            <td className="px-4 py-3">
                                                {key.is_active ? (
                                                    <span className="text-teal">Active</span>
                                                ) : (
                                                    <span className="text-ink-soft/50">
                                                        Rotated
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-ink-soft/70">
                                                {key.is_custom ? 'Custom import' : 'Managed'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="mt-8 border border-mist bg-paper-elevated p-6">
                        <div className="flex items-center gap-2">
                            <KeyRound className="size-4 text-teal" strokeWidth={1.75} />
                            <h2 className="font-display text-lg font-semibold text-ink">
                                Import custom private key
                            </h2>
                        </div>
                        {canImport ? (
                            <form onSubmit={onImport} className="mt-5 space-y-4">
                                <label className="block text-sm">
                                    <span className="mb-1.5 block font-medium text-ink">
                                        Optional kid
                                    </span>
                                    <input
                                        value={kid}
                                        onChange={(event) => setKid(event.target.value)}
                                        placeholder="my-key-1"
                                        className="w-full border border-mist bg-paper px-3 py-2.5 outline-none focus:border-teal"
                                    />
                                </label>
                                <label className="block text-sm">
                                    <span className="mb-1.5 block font-medium text-ink">
                                        RSA private key (PEM)
                                    </span>
                                    <textarea
                                        required
                                        rows={10}
                                        value={privateKey}
                                        onChange={(event) => setPrivateKey(event.target.value)}
                                        placeholder="-----BEGIN PRIVATE KEY-----&#10;…&#10;-----END PRIVATE KEY-----"
                                        className="w-full border border-mist bg-paper px-3 py-2.5 font-mono text-[13px] outline-none focus:border-teal"
                                    />
                                </label>
                                <button
                                    type="submit"
                                    disabled={importKey.isPending}
                                    className="bg-teal px-4 py-2.5 text-sm font-semibold text-paper hover:bg-teal-bright disabled:opacity-60"
                                >
                                    Import & activate
                                </button>
                            </form>
                        ) : (
                            <p className="mt-4 text-sm text-ink-soft/65">
                                Custom JWKS import is a paid feature. Free orgs get an
                                auto-generated RS256 key pair on first use.{' '}
                                <Link to={paths.billing} className="text-teal hover:text-teal-deep">
                                    Upgrade
                                </Link>
                            </p>
                        )}
                    </section>
                </>
            )}
        </div>
    );
}

function Row({
    label,
    value,
    copyable = false,
}: {
    label: string;
    value: string;
    copyable?: boolean;
}) {
    return (
        <div className="flex flex-col gap-1 border-b border-fog pb-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
            <dt className="shrink-0 text-ink-soft/60">{label}</dt>
            <dd className="flex min-w-0 items-center gap-2 font-mono text-xs text-ink">
                <span className="truncate">{value}</span>
                {copyable && (
                    <button
                        type="button"
                        className="text-ink-soft/50 hover:text-ink"
                        aria-label={`Copy ${label}`}
                        onClick={() => {
                            void navigator.clipboard.writeText(value);
                        }}
                    >
                        <Copy className="size-3.5" strokeWidth={1.75} />
                    </button>
                )}
            </dd>
        </div>
    );
}
