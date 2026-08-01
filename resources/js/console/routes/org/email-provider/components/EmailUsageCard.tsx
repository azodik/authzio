import { Mail } from 'lucide-react';
import { useI18n } from '@/hooks/useI18n';
import type { EmailUsageSnapshot } from '@/types';

type EmailUsageCardProps = {
    usage: EmailUsageSnapshot;
    todayLabel: string;
    monthLabel: string;
    pausedLabel: string;
};

function Meter({ label, count, limit }: { label: string; count: number; limit: number | null }) {
    const percent =
        limit === null || limit === 0 ? 0 : Math.min(100, Math.round((count / limit) * 100));
    return (
        <div>
            <div className="mb-1.5 flex items-center justify-between text-sm">
                <span className="text-ink-soft/70">{label}</span>
                <span className="font-medium text-ink">
                    {count}
                    {limit !== null ? ` / ${limit}` : ''}
                </span>
            </div>
            <div className="h-2 overflow-hidden bg-mist/70">
                <div
                    className="h-full bg-teal transition-[width]"
                    style={{ width: `${limit === null ? 8 : percent}%` }}
                />
            </div>
        </div>
    );
}

export function EmailUsageCard({
    usage,
    todayLabel,
    monthLabel,
    pausedLabel,
}: EmailUsageCardProps) {
    const { t } = useI18n();
    return (
        <section className="mb-8 border border-mist bg-paper-elevated p-6">
            <div className="mb-4 flex items-center gap-2">
                <Mail className="size-4 text-teal" strokeWidth={1.75} />
                <h2 className="font-display text-lg font-semibold text-ink">
                    {t('console.page.email_provider.usage')}
                </h2>
            </div>
            <div className="grid gap-5 sm:grid-cols-2">
                <Meter label={todayLabel} count={usage.daily_count} limit={usage.daily_limit} />
                <Meter label={monthLabel} count={usage.monthly_count} limit={usage.monthly_limit} />
            </div>
            {!usage.can_send && <p className="mt-4 text-sm text-danger">{pausedLabel}</p>}
        </section>
    );
}
