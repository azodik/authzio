import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { useI18n } from '@/hooks/useI18n';
import type { OrganizationDomain } from '@/types';

function CopyableValue({ value, label }: { value: string; label: string }) {
    const { t } = useI18n();
    const [copied, setCopied] = useState(false);
    async function copy(): Promise<void> {
        await navigator.clipboard.writeText(value);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 1500);
    }
    return (
        <div className="flex items-start gap-2">
            <code className="min-w-0 flex-1 break-all font-mono text-xs text-ink">{value}</code>
            <button
                type="button"
                onClick={() => void copy()}
                className="inline-flex shrink-0 items-center gap-1 border border-mist px-2 py-1 text-xs text-ink-soft"
                aria-label={`${label}: ${t('console.page.domains.copy')}`}
            >
                {copied ? <Check className="size-3.5 text-teal" /> : <Copy className="size-3.5" />}
                {copied ? t('console.page.domains.copied') : t('console.page.domains.copy')}
            </button>
        </div>
    );
}

function Record({
    type,
    name,
    value,
    caption,
}: {
    type: string;
    name: string;
    value: string;
    caption?: string;
}) {
    const { t } = useI18n();
    return (
        <div className="border border-mist bg-paper p-3">
            {caption && <p className="mb-2 text-xs font-medium text-ink-soft/70">{caption}</p>}
            <dl className="grid gap-2 text-sm sm:grid-cols-[5.5rem_1fr]">
                <dt className="text-ink-soft/55">{t('console.page.domains.dns_type')}</dt>
                <dd className="font-mono">{type}</dd>
                <dt className="text-ink-soft/55">{t('console.page.domains.dns_name')}</dt>
                <dd>
                    <CopyableValue value={name} label="Name" />
                </dd>
                <dt className="text-ink-soft/55">{t('console.page.domains.dns_value')}</dt>
                <dd>
                    <CopyableValue value={value} label="Value" />
                </dd>
            </dl>
        </div>
    );
}

export function DomainVerificationPanel({
    domain,
    cnameTarget,
    cloudflareSaas,
}: {
    domain: OrganizationDomain;
    cnameTarget: string | null;
    cloudflareSaas: boolean;
}) {
    const { t } = useI18n();
    const records = domain.dns_records ?? [];
    if (domain.type === 'subdomain')
        return (
            <p className="text-sm text-ink-soft/65">{t('console.page.domains.subdomain_auto')}</p>
        );
    if (domain.verified_at) {
        const cname = records.find((record) => record.purpose === 'cname')?.value ?? cnameTarget;
        return (
            <div className="space-y-3">
                <p className="text-sm text-ink-soft/70">{t('console.page.domains.verified_ok')}</p>
                {cname && (
                    <Record
                        type="CNAME"
                        name={domain.host}
                        value={cname}
                        caption={t('console.page.domains.dns_cname_target')}
                    />
                )}
            </div>
        );
    }
    if (records.length === 0)
        return <p className="text-sm text-ink-soft/65">{t('console.page.domains.empty_dns')}</p>;
    return (
        <div className="space-y-4">
            <h3 className="font-display text-sm font-semibold text-ink">
                {t('console.page.domains.verify_heading')}
            </h3>
            <p className="text-sm text-ink-soft/75">
                {t(
                    cloudflareSaas
                        ? 'console.page.domains.verify_step_cloudflare'
                        : 'console.page.domains.verify_step_txt',
                )}
            </p>
            {records.map((record) => (
                <Record key={`${record.purpose}-${record.name}`} {...record} />
            ))}
            <ol className="list-decimal space-y-2 pl-5 text-sm text-ink-soft/75">
                <li>{t('console.page.domains.verify_step_wait')}</li>
                <li>{t('console.page.domains.verify_step_click')}</li>
            </ol>
        </div>
    );
}
