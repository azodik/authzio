import { Check, ChevronDown, Plus } from 'lucide-react';
import { type FormEvent, type ReactNode, useEffect, useId, useRef, useState } from 'react';
import { useNavigate } from 'react-router';
import { useI18n } from '../hooks/useI18n';
import { ApiError, apiGet, apiPost } from '../lib/api';
import { orgApiPath } from '../lib/orgApi';
import { appPath, orgPath } from '../lib/paths';
import { toOrgSlug } from '../lib/slug';
import type { ApplicationTypeOption, Organization } from '../types';
import { useWorkspace } from '../workspace/WorkspaceContext';

type MenuProps = {
    label: string;
    triggerLabel: string;
    emptyLabel: string;
    children: ReactNode;
};

function SwitcherMenu({ label, triggerLabel, emptyLabel, children }: MenuProps) {
    const [open, setOpen] = useState(false);
    const rootRef = useRef<HTMLDivElement>(null);
    const menuId = useId();

    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointerDown = (event: MouseEvent): void => {
            if (rootRef.current && !rootRef.current.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        const onKeyDown = (event: KeyboardEvent): void => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', onPointerDown);
        window.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('mousedown', onPointerDown);
            window.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    return (
        <div ref={rootRef} className="relative">
            <p className="mb-1.5 text-[11px] font-semibold tracking-[0.14em] text-ink-soft/40 uppercase">
                {label}
            </p>
            <button
                type="button"
                aria-haspopup="listbox"
                aria-expanded={open}
                aria-controls={menuId}
                onClick={() => setOpen((value) => !value)}
                className="flex w-full items-center justify-between gap-2 rounded-md border border-mist bg-paper px-3 py-2.5 text-left text-sm text-ink transition-colors hover:border-ink/20"
            >
                <span className="min-w-0 truncate font-medium">
                    {triggerLabel !== '' ? triggerLabel : emptyLabel}
                </span>
                <ChevronDown
                    className={`size-4 shrink-0 text-ink-soft/40 transition-transform ${open ? 'rotate-180' : ''}`}
                    strokeWidth={1.75}
                />
            </button>

            {open ? (
                <div
                    id={menuId}
                    role="listbox"
                    tabIndex={-1}
                    className="absolute z-30 mt-1.5 w-full overflow-hidden rounded-md border border-mist bg-paper-elevated shadow-lg shadow-ink/10"
                    onClick={() => setOpen(false)}
                    onKeyDown={(event) => {
                        if (event.key === 'Escape') {
                            setOpen(false);
                        }
                    }}
                >
                    <div className="max-h-56 overflow-y-auto py-1">{children}</div>
                </div>
            ) : null}
        </div>
    );
}

function MenuItem({
    active,
    onSelect,
    children,
}: {
    active?: boolean;
    onSelect: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            role="option"
            aria-selected={active === true}
            onClick={onSelect}
            className={[
                'flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm transition-colors',
                active
                    ? 'bg-fog font-medium text-ink'
                    : 'text-ink-soft/80 hover:bg-fog/80 hover:text-ink',
            ].join(' ')}
        >
            <span className="min-w-0 flex-1 truncate">{children}</span>
            {active ? <Check className="size-3.5 shrink-0 text-teal" strokeWidth={2} /> : null}
        </button>
    );
}

function CreateOrgPanel({
    onCreated,
    onCancel,
}: {
    onCreated: (org: Organization) => void;
    onCancel: () => void;
}) {
    const [name, setName] = useState('');
    const [slug, setSlug] = useState('');
    const [slugTouched, setSlugTouched] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const nameRef = useRef<HTMLInputElement>(null);
    const { t } = useI18n();
    const { domainRoot } = useWorkspace();

    useEffect(() => {
        nameRef.current?.focus();
    }, []);

    useEffect(() => {
        if (!slugTouched) {
            setSlug(toOrgSlug(name));
        }
    }, [name, slugTouched]);

    async function onSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        setBusy(true);
        setError(null);

        const normalizedSlug = toOrgSlug(slug);
        if (normalizedSlug.length < 2) {
            setError(t('console.page.organizations.slug_invalid'));
            setBusy(false);
            return;
        }

        try {
            const response = await apiPost<{ data: Organization }>('/api/v1/organizations', {
                name: name.trim(),
                slug: normalizedSlug,
            });
            onCreated(response.data);
        } catch (err) {
            setError(err instanceof ApiError ? err.message : 'Could not create organization.');
        } finally {
            setBusy(false);
        }
    }

    return (
        <form onSubmit={onSubmit} className="mt-2 space-y-3 border border-mist bg-fog/40 p-3">
            <p className="text-xs font-semibold text-ink">{t('console.page.organizations.new')}</p>
            <input
                ref={nameRef}
                required
                value={name}
                onChange={(event) => setName(event.target.value)}
                placeholder="Acme Inc"
                className="w-full border border-mist bg-paper px-3 py-2 text-sm outline-none focus:border-teal"
            />
            <div>
                <input
                    required
                    value={slug}
                    onChange={(event) => {
                        setSlugTouched(true);
                        setSlug(toOrgSlug(event.target.value));
                    }}
                    placeholder="acme"
                    pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                    minLength={2}
                    maxLength={63}
                    className="w-full border border-mist bg-paper px-3 py-2 font-mono text-sm outline-none focus:border-teal"
                />
                <p className="mt-1 text-[11px] text-ink-soft/50">
                    {t('console.page.organizations.slug_hint', {
                        host: `${slug || 'acme'}.${domainRoot}`,
                    })}
                </p>
            </div>
            {error !== null ? (
                <p className="text-xs text-danger" role="alert">
                    {error}
                </p>
            ) : null}
            <div className="flex gap-2">
                <button
                    type="button"
                    onClick={onCancel}
                    className="flex-1 border border-mist px-3 py-2 text-xs font-medium text-ink-soft/70 hover:bg-paper"
                >
                    {t('console.common.cancel')}
                </button>
                <button
                    type="submit"
                    disabled={busy || slug.length < 2}
                    className="flex-1 bg-teal px-3 py-2 text-xs font-semibold text-paper hover:bg-teal-bright disabled:opacity-60"
                >
                    {busy ? t('console.common.creating') : t('console.common.create')}
                </button>
            </div>
        </form>
    );
}

function CreateAppPanel({
    organizationId,
    onCreated,
    onCancel,
}: {
    organizationId: string;
    onCreated: (clientId: string) => void;
    onCancel: () => void;
}) {
    const [name, setName] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [types, setTypes] = useState<ApplicationTypeOption[]>([]);
    const nameRef = useRef<HTMLInputElement>(null);
    const { t } = useI18n();

    useEffect(() => {
        nameRef.current?.focus();
    }, []);

    useEffect(() => {
        void apiGet<{ application_types: ApplicationTypeOption[] }>(
            orgApiPath(organizationId, 'applications'),
        )
            .then((response) => {
                setTypes(response.application_types);
            })
            .catch(() => {
                setTypes([
                    {
                        value: 'web',
                        label: 'Web',
                        grant_types: ['authorization_code', 'refresh_token'],
                        is_confidential: true,
                        requires_redirect_uris: true,
                    },
                ]);
            });
    }, [organizationId]);

    async function onSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        const web = types.find((type) => type.value === 'web') ??
            types[0] ?? {
                value: 'web' as const,
                label: 'Web',
                grant_types: ['authorization_code', 'refresh_token'],
                is_confidential: true,
                requires_redirect_uris: true,
            };

        setBusy(true);
        setError(null);

        try {
            const response = await apiPost<{ client_id: string }>(
                orgApiPath(organizationId, 'applications'),
                {
                    organization_id: organizationId,
                    name: name.trim(),
                    description: null,
                    application_type: web.value,
                    redirect_uris: web.requires_redirect_uris
                        ? ['http://localhost:3000/callback']
                        : [],
                    grant_types: web.grant_types,
                    is_confidential: web.is_confidential,
                },
            );
            onCreated(response.client_id);
        } catch (err) {
            setError(
                err instanceof ApiError
                    ? (Object.values(err.errors)[0]?.[0] ?? err.message)
                    : 'Could not create application.',
            );
        } finally {
            setBusy(false);
        }
    }

    return (
        <form onSubmit={onSubmit} className="mt-2 space-y-3 border border-mist bg-fog/40 p-3">
            <p className="text-xs font-semibold text-ink">{t('console.page.applications.new')}</p>
            <input
                ref={nameRef}
                required
                value={name}
                onChange={(event) => setName(event.target.value)}
                placeholder="My web app"
                className="w-full border border-mist bg-paper px-3 py-2 text-sm outline-none focus:border-teal"
            />
            <p className="text-[11px] leading-relaxed text-ink-soft/55">
                Creates a web OAuth client with a localhost callback. You can edit details after.
            </p>
            {error !== null ? (
                <p className="text-xs text-danger" role="alert">
                    {error}
                </p>
            ) : null}
            <div className="flex gap-2">
                <button
                    type="button"
                    onClick={onCancel}
                    className="flex-1 border border-mist px-3 py-2 text-xs font-medium text-ink-soft/70 hover:bg-paper"
                >
                    {t('console.common.cancel')}
                </button>
                <button
                    type="submit"
                    disabled={busy}
                    className="flex-1 bg-teal px-3 py-2 text-xs font-semibold text-paper hover:bg-teal-bright disabled:opacity-60"
                >
                    {busy ? t('console.common.creating') : t('console.common.create')}
                </button>
            </div>
        </form>
    );
}

export function WorkspaceSwitchers() {
    const navigate = useNavigate();
    const {
        organizations,
        organization,
        applications,
        entitlements,
        setOrganizationId,
        setApplicationId,
        refresh,
    } = useWorkspace();
    const { t } = useI18n();

    const [creatingOrg, setCreatingOrg] = useState(false);
    const [creatingApp, setCreatingApp] = useState(false);

    const canCreateApp = entitlements?.can_create_application ?? true;

    return (
        <div className="space-y-4 border-b border-mist px-3 py-4">
            <div>
                <SwitcherMenu
                    label={t('console.switcher.organization')}
                    triggerLabel={organization?.name ?? ''}
                    emptyLabel={t('console.switcher.select_org')}
                >
                    {organizations.map((org) => (
                        <MenuItem
                            key={org.id}
                            active={organization?.id === org.id}
                            onSelect={() => {
                                setCreatingOrg(false);
                                setOrganizationId(org.id);
                                navigate(orgPath(org.id));
                            }}
                        >
                            {org.name}
                        </MenuItem>
                    ))}
                    <button
                        type="button"
                        onClick={(event) => {
                            event.stopPropagation();
                            setCreatingOrg(true);
                            setCreatingApp(false);
                        }}
                        className="flex w-full items-center gap-2 border-t border-mist px-3 py-2.5 text-left text-sm font-medium text-teal hover:bg-fog/80"
                    >
                        <Plus className="size-3.5" strokeWidth={2} />
                        {t('console.switcher.create_org')}
                    </button>
                </SwitcherMenu>
                {creatingOrg ? (
                    <CreateOrgPanel
                        onCancel={() => setCreatingOrg(false)}
                        onCreated={(org) => {
                            setCreatingOrg(false);
                            setOrganizationId(org.id);
                            void refresh().then(() => navigate(orgPath(org.id)));
                        }}
                    />
                ) : null}
            </div>

            <div>
                <SwitcherMenu
                    label={t('console.switcher.application')}
                    triggerLabel=""
                    emptyLabel={t('console.switcher.open_app')}
                >
                    {applications.map((app) => (
                        <MenuItem
                            key={app.id}
                            active={false}
                            onSelect={() => {
                                setCreatingApp(false);
                                setApplicationId(app.id);
                                if (organization) {
                                    navigate(appPath(organization.id, app.id));
                                }
                            }}
                        >
                            {app.name}
                        </MenuItem>
                    ))}
                    {applications.length === 0 ? (
                        <p className="px-3 py-2 text-xs text-ink-soft/55">
                            {t('console.switcher.no_apps')}
                        </p>
                    ) : null}
                    <button
                        type="button"
                        disabled={!organization || !canCreateApp}
                        onClick={(event) => {
                            event.stopPropagation();
                            if (!organization || !canCreateApp) {
                                return;
                            }
                            setCreatingApp(true);
                            setCreatingOrg(false);
                        }}
                        className="flex w-full items-center gap-2 border-t border-mist px-3 py-2.5 text-left text-sm font-medium text-teal hover:bg-fog/80 disabled:cursor-not-allowed disabled:text-ink-soft/35 disabled:hover:bg-transparent"
                    >
                        <Plus className="size-3.5" strokeWidth={2} />
                        {t('console.switcher.create_app')}
                    </button>
                </SwitcherMenu>
                {creatingApp && organization ? (
                    <CreateAppPanel
                        organizationId={organization.id}
                        onCancel={() => setCreatingApp(false)}
                        onCreated={(clientId) => {
                            setCreatingApp(false);
                            setApplicationId(clientId);
                            void refresh().then(() => navigate(appPath(organization.id, clientId)));
                        }}
                    />
                ) : null}
                {entitlements?.is_free ? (
                    <p className="mt-2 text-[11px] leading-snug text-ink-soft/45">
                        Free: {entitlements.application_count}/{entitlements.application_limit ?? 1}{' '}
                        application
                        {!canCreateApp ? ' · upgrade to add more' : ''}
                    </p>
                ) : null}
            </div>
        </div>
    );
}
