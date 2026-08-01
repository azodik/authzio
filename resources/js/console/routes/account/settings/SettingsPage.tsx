import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type ChangeEvent, type FormEvent, useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router';
import { useAuth } from '@/auth/AuthContext';
import { PageHeader } from '@/components/PageHeader';
import { TrustedHtml } from '@/components/TrustedHtml';
import { useDemoPolicy } from '@/hooks/useDemoPolicy';
import { useI18n } from '@/hooks/useI18n';
import { apiDelete, apiGet, apiPost, apiUpload } from '@/lib/api';
import { toastError, toastSuccess } from '@/lib/toast';
import type { AuthUser } from '@/types';

type MfaSetup = {
    secret: string;
    otpauth_url: string;
    qr_svg: string;
};

type LinkedAccount = {
    provider: string;
    label: string;
    linked: boolean;
    provider_email: string | null;
    can_unlink: boolean;
    enabled: boolean;
};

type LinkedAccountsResponse = {
    providers: Record<string, boolean>;
    accounts: LinkedAccount[];
};

export function SettingsPage() {
    const { user, refresh } = useAuth();
    const { t } = useI18n();
    const demo = useDemoPolicy();
    const queryClient = useQueryClient();
    const [searchParams, setSearchParams] = useSearchParams();
    const avatarLocked = demo.isDenied('auth.avatar');
    const mfaLocked = demo.isDenied('auth.mfa');
    const linkedLocked = demo.isDenied('auth.linked_accounts');
    const fileInputRef = useRef<HTMLInputElement>(null);

    const [setup, setSetup] = useState<MfaSetup | null>(null);
    const [confirmCode, setConfirmCode] = useState('');
    const [disableCode, setDisableCode] = useState('');
    const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
    const avatarMutation = useMutation({
        mutationFn: (action: { kind: 'upload'; data: FormData } | { kind: 'remove' }) =>
            action.kind === 'upload'
                ? apiUpload<{ user: AuthUser }>('/api/v1/auth/avatar', action.data)
                : apiDelete<{ user: AuthUser }>('/api/v1/auth/avatar'),
        onError: (error) => toastError(error, 'Avatar update failed.'),
    });
    const setupMutation = useMutation({
        mutationFn: () => apiPost<MfaSetup>('/api/v1/auth/mfa/setup'),
        onError: (error) => toastError(error, 'Unable to start MFA setup.'),
    });
    const confirmMutation = useMutation({
        mutationFn: (code: string) =>
            apiPost<{ recovery_codes: string[]; warning?: string }>('/api/v1/auth/mfa/confirm', {
                code,
            }),
        onError: (error) => toastError(error, 'Unable to confirm authenticator.'),
    });
    const disableMutation = useMutation({
        mutationFn: (code: string) => apiPost('/api/v1/auth/mfa/disable', { code }),
        onError: (error) => toastError(error, 'Unable to disable MFA.'),
    });
    const recoveryMutation = useMutation({
        mutationFn: (code: string) =>
            apiPost<{ recovery_codes: string[]; warning?: string }>(
                '/api/v1/auth/mfa/recovery-codes',
                { code },
            ),
        onError: (error) => toastError(error, 'Unable to regenerate recovery codes.'),
    });
    const linkedAccountsQuery = useQuery({
        queryKey: ['auth', 'linked-accounts'],
        queryFn: () => apiGet<LinkedAccountsResponse>('/api/v1/auth/linked-accounts'),
    });
    const unlinkMutation = useMutation({
        mutationFn: (provider: string) =>
            apiDelete<{ message: string; accounts: LinkedAccount[] }>(
                `/api/v1/auth/linked-accounts/${provider}`,
            ),
        onSuccess: (response) => {
            toastSuccess(response.message);
            void queryClient.invalidateQueries({ queryKey: ['auth', 'linked-accounts'] });
        },
        onError: (error) => toastError(error, 'Unable to unlink account.'),
    });

    useEffect(() => {
        const linked = searchParams.get('linked');
        const error = searchParams.get('error');
        if (linked) {
            toastSuccess(
                t('console.page.settings.linked_success', {
                    provider: linked === 'github' ? 'GitHub' : 'Google',
                }),
            );
            void queryClient.invalidateQueries({ queryKey: ['auth', 'linked-accounts'] });
            const next = new URLSearchParams(searchParams);
            next.delete('linked');
            setSearchParams(next, { replace: true });
        }
        if (error === 'demo_linked') {
            toastError(new Error(t('console.page.settings.linked_demo')));
            const next = new URLSearchParams(searchParams);
            next.delete('error');
            setSearchParams(next, { replace: true });
        } else if (
            error === 'link_failed' ||
            error === 'not_configured' ||
            error === 'oauth_failed'
        ) {
            const message = searchParams.get('message');
            toastError(
                new Error(
                    message && message !== '' ? message : t('console.auth.error_oauth_failed'),
                ),
            );
            const next = new URLSearchParams(searchParams);
            next.delete('error');
            next.delete('message');
            setSearchParams(next, { replace: true });
        }
    }, [searchParams, setSearchParams, t, queryClient]);

    const uploading = avatarMutation.isPending;
    const mfaBusy =
        setupMutation.isPending ||
        confirmMutation.isPending ||
        disableMutation.isPending ||
        recoveryMutation.isPending;

    async function onAvatarSelected(event: ChangeEvent<HTMLInputElement>): Promise<void> {
        const file = event.target.files?.[0] ?? null;
        event.target.value = '';

        if (file === null) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('avatar', file);
            await avatarMutation.mutateAsync({ kind: 'upload', data: formData });
            await refresh();
            toastSuccess('Avatar updated.');
        } catch {
            // Mutation reports the error.
        }
    }

    async function onRemoveAvatar(): Promise<void> {
        try {
            await avatarMutation.mutateAsync({ kind: 'remove' });
            await refresh();
            toastSuccess('Avatar removed.');
        } catch {
            // Mutation reports the error.
        }
    }

    async function beginMfaSetup(): Promise<void> {
        setRecoveryCodes(null);

        try {
            const response = await setupMutation.mutateAsync();
            setSetup(response);
            setConfirmCode('');
        } catch {
            // Mutation reports the error.
        }
    }

    async function confirmMfaSetup(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        try {
            const response = await confirmMutation.mutateAsync(confirmCode.trim());
            setSetup(null);
            setConfirmCode('');
            setRecoveryCodes(response.recovery_codes);
            toastSuccess(response.warning ?? 'Authenticator enabled.');
            await refresh();
        } catch {
            // Mutation reports the error.
        }
    }

    async function disableMfa(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        try {
            await disableMutation.mutateAsync(disableCode.trim());
            setDisableCode('');
            setRecoveryCodes(null);
            setSetup(null);
            toastSuccess('Authenticator MFA disabled.');
            await refresh();
        } catch {
            // Mutation reports the error.
        }
    }

    async function regenerateRecoveryCodes(): Promise<void> {
        const code = window.prompt('Enter an authenticator or recovery code to regenerate codes:');
        if (code === null || code.trim() === '') {
            return;
        }

        try {
            const response = await recoveryMutation.mutateAsync(code.trim());
            setRecoveryCodes(response.recovery_codes);
            toastSuccess(response.warning ?? 'Recovery codes regenerated.');
        } catch {
            // Mutation reports the error.
        }
    }

    const initial = (user?.name?.trim().charAt(0) || user?.email?.charAt(0) || '?').toUpperCase();

    return (
        <div>
            <PageHeader
                title={t('console.page.settings.title')}
                description={t('console.page.settings.description')}
            />

            <div className="max-w-2xl space-y-6">
                {(avatarLocked || mfaLocked || linkedLocked) && (
                    <p className="border border-mist bg-fog px-4 py-3 text-sm text-ink-soft/70">
                        {t('console.page.settings.demo_locked')}
                    </p>
                )}
                <section className="border border-mist bg-paper-elevated p-6">
                    <h2 className="font-display text-base font-semibold text-ink">
                        {t('console.page.settings.account')}
                    </h2>

                    <div className="mt-5 flex flex-wrap items-center gap-4">
                        {user?.avatar_url ? (
                            <img
                                src={user.avatar_url}
                                alt=""
                                className="size-16 rounded-full object-cover border border-mist"
                            />
                        ) : (
                            <span className="inline-flex size-16 items-center justify-center rounded-full bg-teal text-lg font-semibold text-paper">
                                {initial}
                            </span>
                        )}
                        <div className="space-y-2">
                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    disabled={uploading || avatarLocked}
                                    onClick={() => fileInputRef.current?.click()}
                                    className="border border-mist bg-paper px-3 py-2 text-sm text-ink hover:border-ink/25 disabled:opacity-50"
                                >
                                    {uploading
                                        ? t('console.page.settings.uploading')
                                        : t('console.page.settings.upload_avatar')}
                                </button>
                                {user?.avatar_url ? (
                                    <button
                                        type="button"
                                        disabled={uploading || avatarLocked}
                                        onClick={() => {
                                            void onRemoveAvatar();
                                        }}
                                        className="px-3 py-2 text-sm text-ink-soft/65 hover:text-danger disabled:opacity-50"
                                    >
                                        {t('console.page.settings.remove_avatar')}
                                    </button>
                                ) : null}
                            </div>
                            <p className="text-xs text-ink-soft/55">
                                {t('console.page.settings.avatar_hint')}
                            </p>
                            <input
                                ref={fileInputRef}
                                type="file"
                                accept="image/png,image/jpeg,image/gif,image/webp"
                                className="sr-only"
                                onChange={(event) => {
                                    void onAvatarSelected(event);
                                }}
                            />
                        </div>
                    </div>

                    <dl className="mt-6 space-y-3 text-sm">
                        <div className="flex justify-between gap-4 border-b border-fog pb-3">
                            <dt className="text-ink-soft/60">{t('console.common.name')}</dt>
                            <dd className="font-medium text-ink">{user?.name}</dd>
                        </div>
                        <div className="flex justify-between gap-4 border-b border-fog pb-3">
                            <dt className="text-ink-soft/60">{t('console.common.email')}</dt>
                            <dd className="font-medium text-ink">{user?.email}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-ink-soft/60">{t('console.page.settings.mfa')}</dt>
                            <dd className="font-medium text-ink">
                                <a href="#authenticator" className="text-teal hover:underline">
                                    {user?.mfa_enabled
                                        ? t('console.common.enabled')
                                        : t('console.common.not_enabled')}
                                </a>
                            </dd>
                        </div>
                    </dl>
                </section>

                {(linkedAccountsQuery.data?.accounts.length ?? 0) > 0 ||
                linkedAccountsQuery.data?.providers.google ||
                linkedAccountsQuery.data?.providers.github ? (
                    <section className="border border-mist bg-paper-elevated p-6">
                        <h2 className="font-display text-base font-semibold text-ink">
                            {t('console.page.settings.linked_accounts')}
                        </h2>
                        <p className="mt-2 text-sm text-ink-soft/65">
                            {t('console.page.settings.linked_accounts_desc')}
                        </p>
                        <ul className="mt-5 space-y-3">
                            {(linkedAccountsQuery.data?.accounts ?? []).map((account) => (
                                <li
                                    key={account.provider}
                                    className="flex flex-wrap items-center justify-between gap-3 border border-mist bg-paper px-4 py-3"
                                >
                                    <div>
                                        <p className="text-sm font-medium text-ink">
                                            {account.label}
                                        </p>
                                        <p className="text-xs text-ink-soft/55">
                                            {account.linked
                                                ? (account.provider_email ??
                                                  t('console.page.settings.linked_connected'))
                                                : t('console.common.not_enabled')}
                                        </p>
                                    </div>
                                    {account.linked ? (
                                        <button
                                            type="button"
                                            disabled={
                                                linkedLocked ||
                                                !account.can_unlink ||
                                                unlinkMutation.isPending
                                            }
                                            onClick={() => unlinkMutation.mutate(account.provider)}
                                            className="border border-mist px-3 py-2 text-sm text-danger hover:bg-danger/5 disabled:opacity-50"
                                        >
                                            {t('console.page.settings.linked_disconnect')}
                                        </button>
                                    ) : account.enabled ? (
                                        <a
                                            href={`/console/auth/${account.provider}/redirect?intent=link`}
                                            className={`border border-mist px-3 py-2 text-sm font-medium text-ink hover:bg-fog ${
                                                linkedLocked ? 'pointer-events-none opacity-50' : ''
                                            }`}
                                            aria-disabled={linkedLocked}
                                        >
                                            {t('console.page.settings.linked_connect')}
                                        </a>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    </section>
                ) : null}

                <section
                    id="authenticator"
                    className="scroll-mt-8 border border-mist bg-paper-elevated p-6"
                >
                    <h2 className="font-display text-base font-semibold text-ink">
                        {t('console.page.settings.authenticator_title')}
                    </h2>
                    <p className="mt-2 text-sm text-ink-soft/65">
                        {t('console.page.settings.authenticator_desc')}
                    </p>

                    {!user?.mfa_enabled && setup === null ? (
                        <button
                            type="button"
                            disabled={mfaBusy || mfaLocked}
                            onClick={() => {
                                void beginMfaSetup();
                            }}
                            className="mt-5 bg-ink px-4 py-2.5 text-sm font-semibold text-paper hover:bg-ink-soft disabled:opacity-60"
                        >
                            {mfaBusy
                                ? t('console.page.settings.starting')
                                : t('console.page.settings.enable_authenticator')}
                        </button>
                    ) : null}

                    {setup !== null ? (
                        <div className="mt-5 space-y-4">
                            <TrustedHtml
                                html={setup.qr_svg}
                                className="inline-block border border-mist bg-paper p-3 [&_svg]:block"
                            />
                            <p className="text-sm text-ink-soft/70">
                                {t('console.page.settings.manual_secret')}{' '}
                                <code className="font-mono text-ink">{setup.secret}</code>
                            </p>
                            <form className="space-y-3" onSubmit={confirmMfaSetup}>
                                <label className="block text-sm">
                                    <span className="mb-1.5 block font-medium text-ink">
                                        {t('console.page.settings.confirm_code_label')}
                                    </span>
                                    <input
                                        type="text"
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        required
                                        value={confirmCode}
                                        onChange={(event) => setConfirmCode(event.target.value)}
                                        className="w-full border border-mist bg-paper px-3 py-2.5 text-ink tracking-widest outline-none focus:border-teal"
                                    />
                                </label>
                                <div className="flex flex-wrap gap-2">
                                    <button
                                        type="submit"
                                        disabled={mfaBusy || mfaLocked}
                                        className="bg-ink px-4 py-2.5 text-sm font-semibold text-paper hover:bg-ink-soft disabled:opacity-60"
                                    >
                                        {mfaBusy
                                            ? t('console.page.settings.confirming')
                                            : t('console.page.settings.confirm_enable')}
                                    </button>
                                    <button
                                        type="button"
                                        disabled={mfaBusy || mfaLocked}
                                        onClick={() => {
                                            setSetup(null);
                                            setConfirmCode('');
                                        }}
                                        className="border border-mist px-4 py-2.5 text-sm text-ink-soft"
                                    >
                                        {t('console.common.cancel')}
                                    </button>
                                </div>
                            </form>
                        </div>
                    ) : null}

                    {user?.mfa_enabled ? (
                        <div className="mt-5 space-y-4">
                            <form className="space-y-3" onSubmit={disableMfa}>
                                <label className="block text-sm">
                                    <span className="mb-1.5 block font-medium text-ink">
                                        {t('console.page.settings.disable_code_label')}
                                    </span>
                                    <input
                                        type="text"
                                        autoComplete="one-time-code"
                                        required
                                        value={disableCode}
                                        onChange={(event) => setDisableCode(event.target.value)}
                                        className="w-full border border-mist bg-paper px-3 py-2.5 text-ink outline-none focus:border-teal"
                                    />
                                </label>
                                <div className="flex flex-wrap gap-2">
                                    <button
                                        type="submit"
                                        disabled={mfaBusy || mfaLocked}
                                        className="border border-danger/40 px-4 py-2.5 text-sm font-semibold text-danger hover:bg-danger/5 disabled:opacity-60"
                                    >
                                        {t('console.page.settings.disable_mfa')}
                                    </button>
                                    <button
                                        type="button"
                                        disabled={mfaBusy || mfaLocked}
                                        onClick={() => {
                                            void regenerateRecoveryCodes();
                                        }}
                                        className="border border-mist px-4 py-2.5 text-sm text-ink"
                                    >
                                        {t('console.page.settings.regenerate_recovery')}
                                    </button>
                                </div>
                            </form>
                        </div>
                    ) : null}

                    {recoveryCodes !== null ? (
                        <div className="mt-5 border border-teal/30 bg-teal/5 p-4">
                            <p className="text-sm font-semibold text-ink">
                                {t('console.page.settings.store_recovery')}
                            </p>
                            <ul className="mt-3 grid grid-cols-2 gap-2 font-mono text-sm text-ink">
                                {recoveryCodes.map((code) => (
                                    <li key={code}>{code}</li>
                                ))}
                            </ul>
                        </div>
                    ) : null}
                </section>
            </div>
        </div>
    );
}
