import type { LucideIcon } from 'lucide-react';
import {
    ArrowLeft,
    Building2,
    CreditCard,
    FileText,
    Globe,
    KeyRound,
    Languages,
    LayoutDashboard,
    LogOut,
    Mail,
    Menu,
    Monitor,
    Moon,
    Send,
    Settings,
    Shield,
    Sun,
    UserPlus,
    Users,
    X,
} from 'lucide-react';
import { type ReactNode, useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useParams } from 'react-router';
import { useAuth } from '../auth/AuthContext';
import { useI18n } from '../hooks/useI18n';
import { useWorkspacePaths } from '../hooks/useWorkspacePaths';
import { ApiError, apiPatch } from '../lib/api';
import { readBuildInfo } from '../lib/buildInfo';
import { ACCOUNT_PATHS, isGlobalPath, ORG_ROUTE_SEGMENTS } from '../lib/paths';
import type { AuthUser, UserPreferences } from '../types';
import { useWorkspace } from '../workspace/WorkspaceContext';
import { WorkspaceSwitchers } from './WorkspaceSwitchers';

type NavItem = {
    to: string;
    label: string;
    icon: LucideIcon;
    end?: boolean;
    permission?: string;
};

function navClassName(isActive: boolean): string {
    return [
        'flex min-h-10 items-center gap-2.5 rounded-md px-2.5 py-2 text-[13px] transition-colors',
        isActive
            ? 'bg-fog font-medium text-ink'
            : 'text-ink-soft/70 hover:bg-fog/70 hover:text-ink',
    ].join(' ');
}

function NavSection({
    title,
    items,
    onNavigate,
}: {
    title: string;
    items: NavItem[];
    onNavigate?: () => void;
}) {
    if (items.length === 0) {
        return null;
    }

    return (
        <div className="px-3 pb-4">
            <p className="mb-1.5 px-2.5 text-[10px] font-semibold tracking-[0.14em] text-ink-soft/40 uppercase">
                {title}
            </p>
            <nav className="flex flex-col gap-0.5" aria-label={title}>
                {items.map((item) => (
                    <NavLink
                        key={`${item.to}:${item.label}`}
                        to={item.to}
                        end={item.end}
                        onClick={onNavigate}
                        className={({ isActive }) => navClassName(isActive)}
                    >
                        <item.icon className="size-4 shrink-0 opacity-80" strokeWidth={1.75} />
                        {item.label}
                    </NavLink>
                ))}
            </nav>
        </div>
    );
}

function filterByPermission(items: NavItem[], can: (permission: string) => boolean): NavItem[] {
    return items.filter((item) => item.permission === undefined || can(item.permission));
}

/** Always-visible account-level links. */
function GlobalNav({ onNavigate }: { onNavigate?: () => void }) {
    const paths = useWorkspacePaths();
    const { t } = useI18n();

    return (
        <NavSection
            title={t('console.section.account')}
            items={[
                { to: paths.organizations, label: t('console.nav.organizations'), icon: Building2 },
                { to: paths.settings, label: t('console.nav.settings'), icon: Settings },
            ]}
            onNavigate={onNavigate}
        />
    );
}

/** Organization menus — only when an org is selected and we are not deep in an app. */
function OrganizationNav({ onNavigate }: { onNavigate?: () => void }) {
    const paths = useWorkspacePaths();
    const { can } = useWorkspace();
    const { t } = useI18n();

    const people = filterByPermission(
        [
            {
                to: paths.members,
                label: t('console.nav.members'),
                icon: UserPlus,
                permission: 'members.read',
            },
            {
                to: paths.roles,
                label: t('console.nav.roles'),
                icon: Shield,
                permission: 'roles.read',
            },
            {
                to: paths.users,
                label: t('console.nav.users'),
                icon: Users,
                permission: 'end_users.read',
            },
        ],
        can,
    );

    const workspace = filterByPermission(
        [
            {
                to: paths.overview,
                label: t('console.nav.overview'),
                icon: LayoutDashboard,
                end: true,
            },
            {
                to: paths.applications,
                label: t('console.nav.applications'),
                icon: KeyRound,
                permission: 'applications.read',
            },
        ],
        can,
    );

    const configure = filterByPermission(
        [
            {
                to: paths.domains,
                label: t('console.nav.domains'),
                icon: Globe,
                permission: 'domains.read',
            },
            {
                to: paths.emailTemplates,
                label: t('console.nav.email_templates'),
                icon: Mail,
                permission: 'email_templates.read',
            },
            {
                to: paths.emailProvider,
                label: t('console.nav.email_provider'),
                icon: Send,
                permission: 'email_provider.read',
            },
            {
                to: paths.socialProviders,
                label: t('console.nav.social'),
                icon: Globe,
                permission: 'social_providers.read',
            },
            {
                to: paths.sso,
                label: t('console.nav.sso'),
                icon: Shield,
                permission: 'sso.read',
            },
            {
                to: paths.billing,
                label: t('console.nav.billing'),
                icon: CreditCard,
                permission: 'billing.read',
            },
            {
                to: paths.auditLogs,
                label: t('console.nav.audit'),
                icon: FileText,
                permission: 'audit_logs.read',
            },
        ],
        can,
    );

    return (
        <>
            <NavSection
                title={t('console.section.organization')}
                items={workspace}
                onNavigate={onNavigate}
            />
            <NavSection
                title={t('console.section.people')}
                items={people}
                onNavigate={onNavigate}
            />
            <NavSection
                title={t('console.section.configure')}
                items={configure}
                onNavigate={onNavigate}
            />
        </>
    );
}

/** Application menus — when URL is /:orgId/:appId. */
function ApplicationNav({
    orgId,
    appId,
    appName,
    onNavigate,
}: {
    orgId: string;
    appId: string;
    appName: string;
    onNavigate?: () => void;
}) {
    const paths = useWorkspacePaths();
    const { t } = useI18n();

    return (
        <div className="flex flex-col pb-4">
            <div className="border-b border-mist px-3 pb-3">
                <Link
                    to={`/${orgId}`}
                    onClick={onNavigate}
                    className="flex items-center gap-1.5 px-2.5 py-2 text-[12px] font-medium text-ink-soft/55 transition-colors hover:text-teal"
                >
                    <ArrowLeft className="size-3.5" strokeWidth={2} />
                    {t('console.nav.back_org')}
                </Link>
            </div>

            <div className="px-3 pt-4 pb-2">
                <p className="mb-1 px-2.5 text-[10px] font-semibold tracking-[0.14em] text-ink-soft/40 uppercase">
                    {t('console.section.application')}
                </p>
                <p className="truncate px-2.5 text-sm font-semibold text-ink">{appName}</p>
            </div>

            <NavSection
                title={t('console.section.configure_app')}
                items={[
                    {
                        to: paths.appHome(appId),
                        label: t('console.nav.app_settings'),
                        icon: Settings,
                        end: true,
                    },
                    { to: paths.appOidc(appId), label: t('console.nav.oidc'), icon: Shield },
                ]}
                onNavigate={onNavigate}
            />
        </div>
    );
}

/** Organization menus — org context without an open app. */
function OrganizationChrome({ onNavigate }: { onNavigate?: () => void }) {
    const { t } = useI18n();

    return (
        <div className="border-b border-mist px-3 py-3">
            <Link
                to={ACCOUNT_PATHS.organizations}
                onClick={onNavigate}
                className="flex w-full items-center gap-2 rounded-md px-2.5 py-2.5 text-[13px] font-medium text-ink-soft/70 transition-colors hover:bg-fog/70 hover:text-teal"
            >
                <ArrowLeft className="size-4 shrink-0" strokeWidth={2} />
                {t('console.nav.back_account')}
            </Link>
        </div>
    );
}

type SidebarMode = 'global' | 'org' | 'app' | 'onboarding' | 'boot';

function SidebarBody({
    mode,
    appMode,
    onNavigate,
}: {
    mode: SidebarMode;
    appMode: { orgId: string; appId: string; appName: string } | null;
    onNavigate?: () => void;
}) {
    const { t } = useI18n();

    if (mode === 'boot') {
        return (
            <p className="px-5 py-4 text-sm leading-relaxed text-ink-soft/55">Loading workspace…</p>
        );
    }

    if (mode === 'onboarding') {
        return (
            <div className="flex flex-col gap-4 px-3 pb-4 pt-1">
                <p className="px-2.5 text-sm leading-relaxed text-ink-soft/55">
                    {t('console.onboarding_hint')}
                </p>
                <NavSection
                    title={t('console.context.get_started')}
                    items={[
                        {
                            to: '/',
                            label: t('console.nav.home'),
                            icon: LayoutDashboard,
                            end: true,
                        },
                        {
                            to: '/onboarding',
                            label: t('console.switcher.create_org'),
                            icon: Building2,
                        },
                        {
                            to: ACCOUNT_PATHS.settings,
                            label: t('console.nav.settings'),
                            icon: Settings,
                        },
                    ]}
                    onNavigate={onNavigate}
                />
            </div>
        );
    }

    if (mode === 'app' && appMode) {
        return (
            <ApplicationNav
                orgId={appMode.orgId}
                appId={appMode.appId}
                appName={appMode.appName}
                onNavigate={onNavigate}
            />
        );
    }

    if (mode === 'org') {
        return (
            <>
                <OrganizationChrome onNavigate={onNavigate} />
                <div className="pt-3">
                    <WorkspaceSwitchers />
                </div>
                <OrganizationNav onNavigate={onNavigate} />
            </>
        );
    }

    return <GlobalNav onNavigate={onNavigate} />;
}

function SidebarShell({ children, footer }: { children: ReactNode; footer?: ReactNode }) {
    return (
        <>
            <div className="flex h-14 shrink-0 items-center gap-2.5 border-b border-mist px-4">
                <a
                    href="/"
                    className="flex items-center gap-2.5 text-ink"
                    aria-label="Authzio home"
                >
                    <span className="inline-flex size-7 items-center justify-center rounded-md bg-teal">
                        <img
                            src="/images/logo-mark.svg"
                            alt=""
                            className="size-5"
                            width={40}
                            height={40}
                        />
                    </span>
                    <span className="font-display text-[15px] font-bold tracking-tight">
                        Authzio
                    </span>
                </a>
            </div>
            <div className="flex min-h-0 flex-1 flex-col overflow-y-auto">{children}</div>
            {footer}
        </>
    );
}

function applyThemeClass(theme: UserPreferences['theme']): void {
    const root = document.documentElement;
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const dark = theme === 'dark' || (theme === 'system' && prefersDark);
    root.classList.toggle('dark', dark);
    root.style.colorScheme = dark ? 'dark' : 'light';

    try {
        localStorage.setItem('authzio-theme', theme);
    } catch {
        /* ignore quota / private mode */
    }
}

const localeLabels: Record<string, string> = {
    en: 'English',
    fr: 'Français',
    de: 'Deutsch',
    es: 'Español',
    hi: 'हिन्दी',
};

export function ConsoleLayout() {
    const { user, demo, logout, refresh: refreshAuth } = useAuth();
    const {
        organization,
        application,
        loading,
        error,
        organizations,
        locales,
        userPreferences,
        setUserPreferences,
    } = useWorkspace();
    const { t } = useI18n();
    const location = useLocation();
    const params = useParams<{ orgId?: string; appId?: string }>();
    const [menuOpen, setMenuOpen] = useState(false);
    const [prefsError, setPrefsError] = useState<string | null>(null);
    const initial = (user?.name?.charAt(0) ?? 'U').toUpperCase();

    const urlAppId = params.appId && !ORG_ROUTE_SEGMENTS.has(params.appId) ? params.appId : null;

    const appMode =
        params.orgId && urlAppId
            ? {
                  orgId: params.orgId,
                  appId: urlAppId,
                  appName: application?.name ?? t('console.context.application'),
              }
            : null;

    const hasOrganization = organizations.length > 0 || organization !== null;
    const onGlobalRoute = isGlobalPath(location.pathname);
    const onHomeIndex = location.pathname === '/' || location.pathname === '';
    const storedOrgHint =
        typeof window !== 'undefined'
            ? window.localStorage.getItem('authzio.active_organization_id')
            : null;

    let sidebarMode: SidebarMode = 'global';
    if (appMode) {
        sidebarMode = 'app';
    } else if (params.orgId && !onGlobalRoute) {
        sidebarMode = 'org';
    } else if (loading && onHomeIndex && storedOrgHint) {
        // Returning user with a remembered org — avoid flashing account nav before redirect.
        sidebarMode = 'boot';
    } else if (
        location.pathname === '/onboarding' ||
        onHomeIndex ||
        (!hasOrganization && !loading && !onGlobalRoute)
    ) {
        // No org (or still resolving home): get-started sidebar, not Organizations/Settings.
        sidebarMode = 'onboarding';
    } else if (!hasOrganization && !loading && onGlobalRoute) {
        // Account routes without an org still use a get-started-oriented chrome.
        sidebarMode = 'onboarding';
    }

    const crumb =
        sidebarMode === 'app'
            ? (application?.name ?? t('console.context.application'))
            : sidebarMode === 'org'
              ? (organization?.name ?? t('console.context.organization'))
              : sidebarMode === 'onboarding' || sidebarMode === 'boot'
                ? t('console.context.get_started')
                : t('console.section.account');

    const pathname = location.pathname;
    useEffect(() => {
        if (pathname === '') {
            return;
        }
        setMenuOpen(false);
    }, [pathname]);

    useEffect(() => {
        applyThemeClass(userPreferences.theme);

        if (userPreferences.theme !== 'system') {
            return;
        }

        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = (): void => {
            applyThemeClass('system');
        };
        media.addEventListener('change', onChange);
        return () => media.removeEventListener('change', onChange);
    }, [userPreferences.theme]);

    useEffect(() => {
        if (!menuOpen) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent): void => {
            if (event.key === 'Escape') {
                setMenuOpen(false);
            }
        };

        window.addEventListener('keydown', onKeyDown);
        document.body.style.overflow = 'hidden';

        return () => {
            window.removeEventListener('keydown', onKeyDown);
            document.body.style.overflow = '';
        };
    }, [menuOpen]);

    async function updatePreferences(patch: Partial<UserPreferences>): Promise<void> {
        const previous = userPreferences;
        setUserPreferences(patch);
        setPrefsError(null);

        if (patch.theme !== undefined) {
            applyThemeClass(patch.theme);
        }

        try {
            await apiPatch<{ user: AuthUser }>('/api/v1/auth/preferences', patch);
            await refreshAuth();
        } catch (err) {
            setUserPreferences(previous);
            if (previous.theme !== undefined) {
                applyThemeClass(previous.theme);
            }
            setPrefsError(err instanceof ApiError ? err.message : 'Failed to save preferences.');
        }
    }

    const buildInfo = readBuildInfo();
    const buildLabel =
        buildInfo.build.toLowerCase() === 'dev'
            ? t('console.build_dev', { version: buildInfo.version })
            : t('console.build_label', {
                  version: buildInfo.version,
                  build: buildInfo.build,
              });

    const sidebarFooter = (
        <div className="shrink-0 border-t border-mist p-4">
            {sidebarMode === 'org' || sidebarMode === 'app' ? (
                <>
                    <p className="truncate text-xs font-medium text-ink">
                        {organization?.name ?? t('console.no_organization')}
                    </p>
                    <p className="mt-0.5 truncate text-[11px] text-ink-soft/45">
                        {sidebarMode === 'app'
                            ? (application?.name ?? t('console.context.application'))
                            : t('console.no_application')}
                    </p>
                </>
            ) : null}
            <p
                className={`truncate text-[10px] text-ink-soft/40 ${
                    sidebarMode === 'org' || sidebarMode === 'app' ? 'mt-2' : ''
                }`}
                title={
                    buildInfo.commit !== 'unknown'
                        ? `${buildLabel} · ${buildInfo.commit}`
                        : buildLabel
                }
            >
                {buildLabel}
            </p>
        </div>
    );

    const sidebarContent = (
        <SidebarBody mode={sidebarMode} appMode={appMode} onNavigate={() => setMenuOpen(false)} />
    );

    return (
        <div className="min-h-screen bg-paper md:pl-64">
            {/* Fixed desktop sidebar */}
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-mist bg-paper-elevated md:flex">
                <SidebarShell footer={sidebarFooter}>{sidebarContent}</SidebarShell>
            </aside>

            {menuOpen ? (
                <div
                    className="fixed inset-0 z-50 md:hidden"
                    role="dialog"
                    aria-modal="true"
                    aria-label={t('console.menu')}
                >
                    <button
                        type="button"
                        className="absolute inset-0 bg-ink/40"
                        aria-label={t('console.menu')}
                        onClick={() => setMenuOpen(false)}
                    />
                    <div className="relative flex h-full w-[min(100%,18rem)] flex-col bg-paper-elevated shadow-xl">
                        <div className="flex h-14 shrink-0 items-center justify-between border-b border-mist px-4">
                            <span className="font-display text-sm font-bold tracking-tight text-ink">
                                {t('console.menu')}
                            </span>
                            <button
                                type="button"
                                className="inline-flex size-10 items-center justify-center text-ink-soft/60 hover:text-ink"
                                aria-label={t('console.menu')}
                                onClick={() => setMenuOpen(false)}
                            >
                                <X className="size-5" strokeWidth={1.75} />
                            </button>
                        </div>
                        <div className="flex min-h-0 flex-1 flex-col overflow-y-auto">
                            {sidebarContent}
                        </div>
                        {sidebarFooter}
                    </div>
                </div>
            ) : null}

            <div className="flex min-h-screen min-w-0 flex-col">
                <header className="sticky top-0 z-20 border-b border-mist bg-paper-elevated/95 backdrop-blur-sm">
                    <div className="flex h-14 items-center justify-between gap-3 px-3 sm:px-6">
                        <div className="flex min-w-0 items-center gap-1 md:gap-3">
                            <button
                                type="button"
                                className="inline-flex size-10 items-center justify-center text-ink md:hidden"
                                aria-label={t('console.menu')}
                                aria-expanded={menuOpen}
                                onClick={() => setMenuOpen(true)}
                            >
                                <Menu className="size-5" strokeWidth={1.75} />
                            </button>
                            <div className="min-w-0">
                                <p className="truncate text-[13px] font-medium text-ink">{crumb}</p>
                                <p className="hidden text-[11px] text-ink-soft/45 sm:block">
                                    {sidebarMode === 'app'
                                        ? t('console.context.application')
                                        : sidebarMode === 'org'
                                          ? t('console.context.organization')
                                          : sidebarMode === 'onboarding' || sidebarMode === 'boot'
                                            ? t('console.context.get_started')
                                            : t('console.section.account')}
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-2 sm:gap-3">
                            <label className="relative hidden items-center sm:flex">
                                <Languages
                                    className="pointer-events-none absolute left-2.5 size-3.5 text-ink-soft/50"
                                    strokeWidth={1.75}
                                    aria-hidden="true"
                                />
                                <select
                                    value={userPreferences.preferred_locale}
                                    onChange={(event) => {
                                        const nextLocale = event.target.value;
                                        try {
                                            localStorage.setItem(
                                                'authzio_preferred_locale',
                                                nextLocale,
                                            );
                                        } catch {
                                            /* ignore */
                                        }
                                        void updatePreferences({
                                            preferred_locale: nextLocale,
                                        });
                                    }}
                                    className="max-w-[8.5rem] appearance-none border border-mist bg-paper py-1.5 pr-2 pl-8 text-[12px] text-ink outline-none focus:border-teal"
                                    aria-label={t('console.language')}
                                >
                                    {locales.map((locale) => (
                                        <option key={locale} value={locale}>
                                            {localeLabels[locale] ?? locale}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="relative hidden items-center sm:flex">
                                {userPreferences.theme === 'dark' ? (
                                    <Moon
                                        className="pointer-events-none absolute left-2.5 size-3.5 text-ink-soft/50"
                                        strokeWidth={1.75}
                                        aria-hidden="true"
                                    />
                                ) : userPreferences.theme === 'light' ? (
                                    <Sun
                                        className="pointer-events-none absolute left-2.5 size-3.5 text-ink-soft/50"
                                        strokeWidth={1.75}
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <Monitor
                                        className="pointer-events-none absolute left-2.5 size-3.5 text-ink-soft/50"
                                        strokeWidth={1.75}
                                        aria-hidden="true"
                                    />
                                )}
                                <select
                                    value={userPreferences.theme}
                                    onChange={(event) => {
                                        void updatePreferences({
                                            theme: event.target.value as UserPreferences['theme'],
                                        });
                                    }}
                                    className="appearance-none border border-mist bg-paper py-1.5 pr-2 pl-8 text-[12px] text-ink outline-none focus:border-teal"
                                    aria-label={t('console.theme')}
                                >
                                    <option value="light">{t('console.theme.light')}</option>
                                    <option value="dark">{t('console.theme.dark')}</option>
                                    <option value="system">{t('console.theme.system')}</option>
                                </select>
                            </label>
                            <div className="hidden text-right sm:block">
                                <p className="max-w-[14rem] truncate text-[13px] font-medium text-ink">
                                    {user?.name}
                                </p>
                                <p className="max-w-[14rem] truncate text-[11px] text-ink-soft/45">
                                    {user?.email}
                                </p>
                            </div>
                            <span
                                className="inline-flex size-9 items-center justify-center overflow-hidden rounded-full bg-teal text-xs font-semibold text-paper"
                                aria-hidden="true"
                            >
                                {user?.avatar_url ? (
                                    <img
                                        src={user.avatar_url}
                                        alt=""
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    initial
                                )}
                            </span>
                            <button
                                type="button"
                                onClick={() => {
                                    void logout();
                                }}
                                className="inline-flex min-h-10 min-w-10 items-center justify-center gap-1.5 rounded-md border border-mist bg-paper px-2.5 text-sm text-ink-soft/70 transition-colors hover:border-ink/20 hover:text-ink"
                                aria-label={t('console.sign_out')}
                            >
                                <LogOut className="size-4" strokeWidth={1.75} />
                                <span className="hidden sm:inline">{t('console.sign_out')}</span>
                            </button>
                        </div>
                    </div>
                </header>

                {user?.is_demo ? (
                    <div
                        className="border-b border-teal/20 bg-teal/[0.08] px-4 py-2.5 text-sm text-ink sm:px-6"
                        role="status"
                    >
                        {demo.message || t('console.demo_banner')}
                    </div>
                ) : null}

                {user && user.email_verified_at == null ? (
                    <div className="border-b border-sand/40 bg-sand/15 px-4 py-2.5 text-sm text-ink sm:px-6">
                        {t('console.verify_banner')}{' '}
                        <Link
                            to="/verify-email"
                            className="font-medium text-teal hover:text-teal-deep"
                        >
                            {t('console.verify_link')}
                        </Link>
                    </div>
                ) : null}

                {error !== null || prefsError !== null ? (
                    <div className="border-b border-danger/20 bg-danger/5 px-4 py-2.5 text-sm text-danger sm:px-6">
                        {error ?? prefsError}
                    </div>
                ) : null}

                <main className="flex-1 overflow-auto">
                    <div className="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-8">
                        {loading ? (
                            <div className="animate-pulse space-y-4">
                                <div className="h-8 w-48 rounded bg-mist/70" />
                                <div className="h-24 rounded-lg bg-mist/50" />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="h-28 rounded-lg bg-mist/40" />
                                    <div className="h-28 rounded-lg bg-mist/40" />
                                </div>
                            </div>
                        ) : (
                            <Outlet />
                        )}
                    </div>
                </main>
            </div>
        </div>
    );
}
