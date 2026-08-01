import { CreditCard, Download, TrendingUp } from 'lucide-react';
import { useI18n } from '@/hooks/useI18n';
import type { BillingDashboard, BillingInvoice, BillingPlan } from '@/types';

export function formatMoney(cents: number, currency: string, slug?: string): string {
    if (slug === 'enterprise') return 'Custom';
    if (cents === 0) return 'Free';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(cents / 100);
}
export function formatDate(value: string | null): string {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('en-US', { dateStyle: 'medium' }).format(date);
}

export function UsageCard({ dashboard }: { dashboard: BillingDashboard }) {
    const { t } = useI18n();
    const { usage, plan, subscription } = dashboard;
    const max = Math.max(1, ...usage.daily.map((day) => day.mau));
    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <section className="border border-mist bg-paper-elevated p-5 lg:col-span-2">
                <div className="flex justify-between">
                    <div>
                        <p className="text-sm text-ink-soft/65">
                            {t('console.page.billing.mau_label')}
                        </p>
                        <p className="mt-2 font-display text-4xl font-semibold">
                            {usage.mau.toLocaleString()}
                        </p>
                        <p className="text-sm text-ink-soft/55">
                            {t('console.page.billing.mau_of_plan', {
                                mau: usage.mau_limit.toLocaleString(),
                                plan: plan.name,
                                period: usage.year_month,
                            })}
                        </p>
                    </div>
                    <TrendingUp className="size-5 text-teal" />
                </div>
                <div className="mt-5 h-2 bg-fog">
                    <div
                        className={usage.over_limit ? 'h-2 bg-danger' : 'h-2 bg-teal'}
                        style={{ width: `${Math.min(100, usage.utilization_percent)}%` }}
                    />
                </div>
                <div className="mt-8 flex h-28 items-end gap-1">
                    {usage.daily.map((day) => (
                        <div
                            key={day.date}
                            className="flex-1 bg-teal/80"
                            style={{ height: `${Math.max(4, (day.mau / max) * 100)}%` }}
                            title={`${day.date}: ${day.mau} MAU`}
                        />
                    ))}
                </div>
            </section>
            <section className="border border-mist bg-paper-elevated p-5">
                <div className="flex justify-between">
                    <p className="text-sm text-ink-soft/65">
                        {t('console.page.billing.current_plan')}
                    </p>
                    <CreditCard className="size-4 text-teal" />
                </div>
                <p className="mt-3 font-display text-2xl font-semibold">{plan.name}</p>
                <p className="text-sm text-ink-soft/60">
                    {formatMoney(plan.price_cents_monthly, plan.currency, plan.slug)}
                </p>
                <p className="mt-4 text-xs text-ink-soft/55">
                    {t('console.page.billing.status_period', {
                        status: subscription.status,
                        date: formatDate(subscription.current_period_end),
                    })}
                </p>
            </section>
        </div>
    );
}

export function PlanPicker({
    dashboard,
    pendingSlug,
    locked = false,
    onSelect,
}: {
    dashboard: BillingDashboard;
    pendingSlug: string | null;
    locked?: boolean;
    onSelect: (plan: BillingPlan) => void;
}) {
    const { t } = useI18n();
    return (
        <section className="mt-8">
            <h2 className="font-display text-lg font-semibold">
                {t('console.page.billing.plans_heading')}
            </h2>
            <p className="text-sm text-ink-soft/60">{t('console.page.billing.plans_lede')}</p>
            <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {dashboard.plans.map((plan) => {
                    const current = plan.slug === dashboard.plan.slug;
                    return (
                        <article
                            key={plan.id}
                            className={`border bg-paper-elevated p-5 ${current ? 'border-teal' : 'border-mist'}`}
                        >
                            <div className="flex justify-between">
                                <h3 className="font-display text-lg font-semibold">{plan.name}</h3>
                                {current && (
                                    <span className="text-xs text-teal">
                                        {t('console.page.billing.current_badge')}
                                    </span>
                                )}
                            </div>
                            <p className="mt-2 font-display text-2xl font-semibold">
                                {formatMoney(plan.price_cents_monthly, plan.currency, plan.slug)}
                            </p>
                            <p className="text-sm text-ink-soft/55">
                                {t('console.page.billing.mau_limit', {
                                    mau: plan.mau_limit.toLocaleString(),
                                })}
                            </p>
                            <p className="mt-3 text-sm text-ink-soft/70">{plan.description}</p>
                            <ul className="mt-4 space-y-1 text-sm text-ink-soft/75">
                                {plan.features?.map((feature) => (
                                    <li key={feature}>{feature}</li>
                                ))}
                            </ul>
                            {!current && plan.is_self_serve && (
                                <button
                                    type="button"
                                    disabled={locked || pendingSlug !== null}
                                    onClick={() => onSelect(plan)}
                                    className="mt-5 bg-ink px-3 py-2 text-sm font-semibold text-paper disabled:opacity-50"
                                >
                                    {pendingSlug === plan.slug
                                        ? t('console.page.billing.working')
                                        : locked
                                          ? t('console.page.billing.demo_locked_cta')
                                          : plan.slug === 'free'
                                            ? t('console.page.billing.switch_to_free')
                                            : t('console.page.billing.switch_plan')}
                                </button>
                            )}
                            {!current && !plan.is_self_serve && (
                                <p className="mt-5 text-sm text-ink-soft/55">
                                    {t('console.page.billing.contact_sales')}
                                </p>
                            )}
                        </article>
                    );
                })}
            </div>
        </section>
    );
}

export function Invoices({
    invoices,
    downloadingId,
    onDownload,
}: {
    invoices: BillingInvoice[];
    downloadingId: string | null;
    onDownload: (invoice: BillingInvoice) => void;
}) {
    const { t } = useI18n();
    return (
        <section className="mt-10">
            <h2 className="font-display text-lg font-semibold">
                {t('console.page.billing.invoices_heading')}
            </h2>
            {invoices.length === 0 ? (
                <p className="mt-4 text-sm text-ink-soft/55">
                    {t('console.page.billing.no_invoices')}
                </p>
            ) : (
                <ul className="mt-4 divide-y divide-mist border border-mist">
                    {invoices.map((invoice) => (
                        <li
                            key={invoice.payment_id}
                            className="flex items-center justify-between p-4"
                        >
                            <div>
                                <p className="text-sm font-medium">
                                    {formatMoney(invoice.amount_cents, invoice.currency)}
                                </p>
                                <p className="text-xs text-ink-soft/55">
                                    {formatDate(invoice.created_at)} · {invoice.payment_id}
                                </p>
                            </div>
                            <button
                                type="button"
                                disabled={downloadingId === invoice.payment_id}
                                onClick={() => onDownload(invoice)}
                                className="inline-flex items-center gap-1 border border-mist px-3 py-1.5 text-sm"
                            >
                                <Download className="size-3.5" />
                                {downloadingId === invoice.payment_id
                                    ? t('console.page.billing.downloading')
                                    : t('console.page.billing.download_pdf')}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
