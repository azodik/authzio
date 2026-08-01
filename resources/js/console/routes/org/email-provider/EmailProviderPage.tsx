import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router';
import { EmptyState } from '@/components/EmptyState';
import { PageHeader } from '@/components/PageHeader';
import { useActiveOrganization } from '@/hooks/useActiveOrganization';
import { useI18n } from '@/hooks/useI18n';
import { useWorkspacePaths } from '@/hooks/useWorkspacePaths';
import { apiGet, apiPost, apiPut } from '@/lib/api';
import { orgApiPath } from '@/lib/orgApi';
import { queryKeys } from '@/lib/queryKeys';
import { toastError, toastSuccess } from '@/lib/toast';
import type { EmailProviderDriver, EmailProviderInfo, EmailUsageSnapshot } from '@/types';
import { EmailUsageCard } from './components/EmailUsageCard';

type ProviderResponse = {
    entitlements: {
        allows_custom_email_provider: boolean;
        email_daily_limit: number | null;
        email_monthly_limit: number | null;
    };
    usage: EmailUsageSnapshot;
    provider: EmailProviderInfo | null;
    drivers: EmailProviderDriver[];
};

type CredentialDraft = Record<string, string>;

const driverFields: Record<
    EmailProviderDriver,
    { key: string; labelKey: string; type?: string }[]
> = {
    smtp: [
        { key: 'host', labelKey: 'console.page.email_provider.field.host' },
        { key: 'port', labelKey: 'console.page.email_provider.field.port' },
        { key: 'encryption', labelKey: 'console.page.email_provider.field.encryption' },
        { key: 'username', labelKey: 'console.page.email_provider.field.username' },
        {
            key: 'password',
            labelKey: 'console.page.email_provider.field.password',
            type: 'password',
        },
    ],
    resend: [
        {
            key: 'api_key',
            labelKey: 'console.page.email_provider.field.api_key',
            type: 'password',
        },
    ],
    postmark: [
        {
            key: 'api_key',
            labelKey: 'console.page.email_provider.field.server_token',
            type: 'password',
        },
    ],
    ses: [
        { key: 'key', labelKey: 'console.page.email_provider.field.access_key_id' },
        {
            key: 'secret',
            labelKey: 'console.page.email_provider.field.secret_access_key',
            type: 'password',
        },
        { key: 'region', labelKey: 'console.page.email_provider.field.region' },
    ],
    mailgun: [
        { key: 'domain', labelKey: 'console.page.email_provider.field.domain' },
        {
            key: 'api_key',
            labelKey: 'console.page.email_provider.field.api_key',
            type: 'password',
        },
        { key: 'endpoint', labelKey: 'console.page.email_provider.field.endpoint' },
    ],
};

export function EmailProviderPage() {
    const organization = useActiveOrganization();
    const paths = useWorkspacePaths();
    const { t } = useI18n();

    const [driver, setDriver] = useState<EmailProviderDriver>('smtp');
    const [fromAddress, setFromAddress] = useState('');
    const [fromName, setFromName] = useState('');
    const [isActive, setIsActive] = useState(true);
    const [credentials, setCredentials] = useState<CredentialDraft>({});

    const queryClient = useQueryClient();
    const providerQuery = useQuery({
        queryKey: organization
            ? queryKeys.org(organization.id).emailProvider()
            : ['org', 'email-provider', 'disabled'],
        enabled: Boolean(organization),
        queryFn: () => apiGet<ProviderResponse>(orgApiPath(organization!.id, 'email-provider')),
    });
    const data = providerQuery.data ?? null;
    const allowsCustom = data?.entitlements.allows_custom_email_provider ?? false;
    useEffect(() => {
        if (!data?.provider) return;
        setDriver(data.provider.driver);
        setFromAddress(data.provider.from_address);
        setFromName(data.provider.from_name ?? '');
        setIsActive(data.provider.is_active);
    }, [data]);
    const saveProvider = useMutation({
        mutationFn: (payload: {
            driver: EmailProviderDriver;
            from_address: string;
            from_name: string | null;
            is_active: boolean;
            credentials: Record<string, string | number>;
        }) => apiPut(orgApiPath(organization!.id, 'email-provider'), payload),
        onError: (error) => toastError(error, 'Failed to save provider.'),
    });
    const testProvider = useMutation({
        mutationFn: () =>
            apiPost<{ message: string }>(orgApiPath(organization!.id, 'email-provider/test')),
        onSuccess: (response) => toastSuccess(response.message),
        onError: (error) => toastError(error, 'Test send failed.'),
    });

    const fields = useMemo(() => driverFields[driver], [driver]);

    async function onSave(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        if (!organization) {
            return;
        }

        try {
            const payloadCredentials: Record<string, string | number> = {};
            for (const field of fields) {
                const value = credentials[field.key]?.trim() ?? '';
                if (value === '') {
                    continue;
                }
                payloadCredentials[field.key] = field.key === 'port' ? Number(value) : value;
            }

            await saveProvider.mutateAsync({
                driver,
                from_address: fromAddress.trim(),
                from_name: fromName.trim() || null,
                is_active: isActive,
                credentials: payloadCredentials,
            });
            setCredentials({});
            toastSuccess('Email provider saved.');
            await queryClient.invalidateQueries({
                queryKey: queryKeys.org(organization.id).emailProvider(),
            });
        } catch {
            // Mutation reports the error.
        }
    }

    async function onTest(): Promise<void> {
        if (!organization) {
            return;
        }

        try {
            await testProvider.mutateAsync();
        } catch {
            // Mutation reports the error.
        }
    }

    if (!organization) {
        return (
            <EmptyState
                title={t('console.common.need_org_title')}
                description={t('console.page.email_provider.need_org_description')}
            />
        );
    }

    return (
        <div>
            <PageHeader
                title={t('console.page.email_provider.title')}
                description={t('console.page.email_provider.description')}
            />

            {providerQuery.error && (
                <p className="mb-4 text-sm text-danger" role="alert">
                    {providerQuery.error.message || 'Failed to load email provider.'}
                </p>
            )}

            {data ? (
                <EmailUsageCard
                    usage={data.usage}
                    todayLabel={t('console.common.today')}
                    monthLabel={t('console.common.this_month')}
                    pausedLabel={t('console.page.email_provider.paused')}
                />
            ) : null}

            {!allowsCustom ? (
                <div className="border border-mist bg-fog px-5 py-4 text-sm text-ink-soft/75">
                    {t('console.page.email_provider.free_banner')}{' '}
                    <Link to={paths.billing} className="font-medium text-teal hover:text-teal-deep">
                        {t('console.common.view_billing')}
                    </Link>
                </div>
            ) : (
                <form onSubmit={onSave} className="border border-mist bg-paper-elevated p-6">
                    <h2 className="font-display text-lg font-semibold text-ink">
                        {t('console.page.email_provider.custom_heading')}
                    </h2>
                    <p className="mt-1 text-sm text-ink-soft/65">
                        {t('console.page.email_provider.custom_hint')}
                    </p>

                    <div className="mt-6 grid gap-4 sm:grid-cols-2">
                        <label className="text-sm">
                            <span className="mb-1.5 block font-medium text-ink">
                                {t('console.page.email_provider.driver')}
                            </span>
                            <select
                                value={driver}
                                onChange={(event) => {
                                    setDriver(event.target.value as EmailProviderDriver);
                                    setCredentials({});
                                }}
                                className="w-full border border-mist bg-paper px-3 py-2.5 outline-none focus:border-teal"
                            >
                                {(data?.drivers ?? Object.keys(driverFields)).map((item) => (
                                    <option key={item} value={item}>
                                        {item}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <div className="text-sm">
                            <span className="mb-1.5 block font-medium text-ink">
                                {t('console.page.email_provider.status')}
                            </span>
                            <label className="inline-flex h-[42px] cursor-pointer select-none items-center gap-2.5 text-ink">
                                <input
                                    type="checkbox"
                                    checked={isActive}
                                    onChange={(event) => setIsActive(event.target.checked)}
                                    className="peer sr-only"
                                />
                                <span
                                    aria-hidden="true"
                                    className="grid size-4 shrink-0 place-items-center border border-mist bg-paper peer-checked:border-teal peer-checked:bg-teal peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-teal"
                                >
                                    <svg
                                        viewBox="0 0 12 12"
                                        aria-hidden="true"
                                        className={`size-2.5 text-paper ${isActive ? 'opacity-100' : 'opacity-0'}`}
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <path d="M2 6.5 4.5 9 10 3" />
                                    </svg>
                                </span>
                                <span className="text-sm leading-none">
                                    {t('console.page.email_provider.active')}
                                </span>
                            </label>
                        </div>
                        <label className="text-sm">
                            <span className="mb-1.5 block font-medium text-ink">
                                {t('console.page.email_provider.from_address')}
                            </span>
                            <input
                                type="email"
                                required
                                value={fromAddress}
                                onChange={(event) => setFromAddress(event.target.value)}
                                className="w-full border border-mist bg-paper px-3 py-2.5 outline-none focus:border-teal"
                            />
                        </label>
                        <label className="text-sm">
                            <span className="mb-1.5 block font-medium text-ink">
                                {t('console.page.email_provider.from_name')}
                            </span>
                            <input
                                value={fromName}
                                onChange={(event) => setFromName(event.target.value)}
                                className="w-full border border-mist bg-paper px-3 py-2.5 outline-none focus:border-teal"
                            />
                        </label>
                    </div>

                    <div className="mt-5 grid gap-4 sm:grid-cols-2">
                        {fields.map((field) => (
                            <label key={field.key} className="text-sm">
                                <span className="mb-1.5 block font-medium text-ink">
                                    {t(field.labelKey)}
                                    {data?.provider?.has_credentials && field.type === 'password'
                                        ? t('console.page.email_provider.secret_optional')
                                        : ''}
                                </span>
                                <input
                                    type={field.type ?? 'text'}
                                    value={credentials[field.key] ?? ''}
                                    onChange={(event) =>
                                        setCredentials((current) => ({
                                            ...current,
                                            [field.key]: event.target.value,
                                        }))
                                    }
                                    required={
                                        !(
                                            data?.provider?.has_credentials &&
                                            field.type === 'password'
                                        ) &&
                                        field.key !== 'endpoint' &&
                                        field.key !== 'encryption'
                                    }
                                    className="w-full border border-mist bg-paper px-3 py-2.5 outline-none focus:border-teal"
                                />
                            </label>
                        ))}
                    </div>

                    {data?.provider?.last_error ? (
                        <p className="mt-4 text-sm text-danger">{data.provider.last_error}</p>
                    ) : null}

                    <div className="mt-6 flex flex-wrap gap-3">
                        <button
                            type="submit"
                            disabled={saveProvider.isPending}
                            className="bg-teal px-4 py-2.5 text-sm font-semibold text-paper hover:bg-teal-bright disabled:opacity-60"
                        >
                            {saveProvider.isPending
                                ? t('console.common.saving')
                                : t('console.common.save')}
                        </button>
                        <button
                            type="button"
                            disabled={testProvider.isPending || !data?.provider?.is_active}
                            onClick={() => {
                                void onTest();
                            }}
                            className="border border-mist px-4 py-2.5 text-sm font-medium text-ink hover:bg-fog disabled:opacity-60"
                        >
                            {testProvider.isPending
                                ? t('console.common.sending')
                                : t('console.common.send_test_email')}
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}
