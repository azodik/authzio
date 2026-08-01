import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router';
import { EmptyState } from '@/components/EmptyState';
import { PageHeader } from '@/components/PageHeader';
import { useActiveOrganization } from '@/hooks/useActiveOrganization';
import { useDemoPolicy } from '@/hooks/useDemoPolicy';
import { useI18n } from '@/hooks/useI18n';
import { apiGet, apiPost } from '@/lib/api';
import { orgApiPath } from '@/lib/orgApi';
import { queryKeys } from '@/lib/queryKeys';
import { toastError, toastSuccess } from '@/lib/toast';
import type {
    BillingDashboard,
    BillingInvoice,
    BillingPlan,
    BillingPlanChangePreview,
} from '@/types';
import { Invoices, PlanPicker, UsageCard } from './components/BillingSections';

type CheckoutResponse = { checkout_url: string; session_id?: string; mode?: string };

export function BillingPage() {
    const organization = useActiveOrganization();
    const { t } = useI18n();
    const demo = useDemoPolicy();
    const billingLocked = demo.isDenied('billing.checkout');
    const orgId = organization?.id;
    const queryClient = useQueryClient();
    const [searchParams, setSearchParams] = useSearchParams();
    const [paymentPending, setPaymentPending] = useState(false);
    const [downloadingId, setDownloadingId] = useState<string | null>(null);
    const billing = useQuery({
        queryKey: orgId ? queryKeys.org(orgId).billing() : ['org', 'billing', 'disabled'],
        enabled: Boolean(orgId),
        queryFn: () =>
            apiGet<{ data: BillingDashboard }>(orgApiPath(orgId!, 'billing')).then(
                (response) => response.data,
            ),
        refetchInterval: paymentPending ? 15_000 : false,
    });
    const invoices = useQuery({
        queryKey: orgId
            ? [...queryKeys.org(orgId).billing(), 'invoices']
            : ['org', 'billing-invoices', 'disabled'],
        enabled: Boolean(orgId),
        queryFn: () =>
            apiGet<{ data: BillingInvoice[] }>(orgApiPath(orgId!, 'billing/invoices')).then(
                (response) => response.data,
            ),
    });
    const checkout = useMutation({
        mutationFn: (plan: BillingPlan) =>
            apiPost<CheckoutResponse>(orgApiPath(orgId!, 'billing/checkout'), {
                plan_slug: plan.slug,
            }).then((response) => ({ plan, response })),
        onSuccess: ({ plan, response }) => {
            if (
                response.checkout_url &&
                (response.checkout_url.startsWith('http') || response.checkout_url.startsWith('/'))
            ) {
                window.location.assign(response.checkout_url);
                return;
            }
            void queryClient.invalidateQueries({ queryKey: queryKeys.org(orgId!).billing() });
            if (response.mode === 'plan_change_pending') setPaymentPending(true);
            else if (response.mode === 'already_on_plan')
                toastError(
                    new Error(t('console.page.billing.already_on_plan', { plan: plan.name })),
                );
            else
                toastSuccess(
                    response.mode === 'plan_change_scheduled'
                        ? t('console.page.billing.change_success_scheduled', { plan: plan.name })
                        : t('console.page.billing.change_success_upgrade', { plan: plan.name }),
                );
        },
        onError: (error) => toastError(error, 'Checkout failed.'),
    });
    const preview = useMutation({
        mutationFn: (plan: BillingPlan) =>
            apiPost<{ data: BillingPlanChangePreview }>(
                orgApiPath(orgId!, 'billing/preview-change'),
                { plan_slug: plan.slug },
            ).then((response) => ({ plan, preview: response.data })),
        onSuccess: ({ plan, preview: result }) => {
            if (result.requires_checkout) {
                checkout.mutate(plan);
                return;
            }
            if (
                window.confirm(
                    `${result.message}\n\n${result.from_plan.name} → ${result.to_plan.name}`,
                )
            )
                checkout.mutate(plan);
        },
        onError: (error) => toastError(error, 'Could not preview plan change.'),
    });

    useEffect(() => {
        if (!searchParams.get('checkout')) return;
        setPaymentPending(true);
        const next = new URLSearchParams(searchParams);
        next.delete('checkout');
        setSearchParams(next, { replace: true });
    }, [searchParams, setSearchParams]);

    function selectPlan(plan: BillingPlan): void {
        const dashboard = billing.data;
        if (!dashboard) return;
        if (billingLocked) {
            toastError(new Error(t('console.page.billing.demo_locked')));
            return;
        }
        const locked =
            dashboard.subscription.pending_requires_payment &&
            dashboard.subscription.pending_plan_kind === 'upgrade';
        if (locked && plan.slug !== dashboard.subscription.pending_plan_slug) {
            toastError(new Error(t('console.page.billing.upgrade_payment_locked')));
            return;
        }
        if (
            plan.slug === 'free' &&
            dashboard.plan.slug !== 'free' &&
            !window.confirm(
                t('console.page.billing.switch_free_body', {
                    date_suffix: '',
                    plan: dashboard.plan.name,
                }),
            )
        )
            return;
        const paidChange =
            dashboard.plan.slug !== 'free' &&
            Boolean(dashboard.subscription.dodo_subscription_id) &&
            plan.slug !== 'free';
        if (paidChange) preview.mutate(plan);
        else checkout.mutate(plan);
    }

    async function downloadInvoice(invoice: BillingInvoice): Promise<void> {
        setDownloadingId(invoice.payment_id);
        try {
            const response = await fetch(invoice.download_path, {
                credentials: 'same-origin',
                headers: { Accept: 'application/pdf', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) throw new Error('Download failed.');
            const url = URL.createObjectURL(await response.blob());
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = `invoice-${invoice.payment_id}.pdf`;
            anchor.click();
            URL.revokeObjectURL(url);
            toastSuccess('Invoice downloaded.');
        } catch (error) {
            toastError(error, 'Could not download invoice.');
        } finally {
            setDownloadingId(null);
        }
    }

    if (!organization)
        return (
            <div>
                <PageHeader
                    title={t('console.page.billing.title')}
                    description={t('console.page.billing.description')}
                />
                <EmptyState
                    title={t('console.common.need_org_title')}
                    description={t('console.page.billing.need_org_description')}
                />
            </div>
        );
    const dashboard = billing.data;
    return (
        <div>
            <PageHeader
                title={t('console.page.billing.title')}
                description={t('console.page.billing.description')}
            />
            {billing.isError && <p className="mb-4 text-sm text-danger">Failed to load billing.</p>}
            {billingLocked && (
                <p className="mb-4 border border-mist bg-fog px-4 py-3 text-sm text-ink-soft/70">
                    {t('console.page.billing.demo_locked')}
                </p>
            )}
            {paymentPending && (
                <div className="mb-4 border border-teal/30 bg-fog px-4 py-3 text-sm">
                    <p className="font-medium">{t('console.page.billing.payment_pending_title')}</p>
                    <p>{t('console.page.billing.payment_pending_body')}</p>
                </div>
            )}
            {dashboard?.subscription.cancel_at_period_end && (
                <div className="mb-4 border border-danger/30 bg-fog px-4 py-3 text-sm">
                    <p className="font-medium">
                        {t('console.page.billing.cancel_scheduled_title')}
                    </p>
                </div>
            )}
            {dashboard && (
                <>
                    <UsageCard dashboard={dashboard} />
                    <PlanPicker
                        dashboard={dashboard}
                        locked={billingLocked}
                        pendingSlug={
                            checkout.isPending
                                ? (checkout.variables?.slug ?? null)
                                : preview.isPending
                                  ? (preview.variables?.slug ?? null)
                                  : null
                        }
                        onSelect={selectPlan}
                    />
                </>
            )}
            <Invoices
                invoices={invoices.data ?? []}
                downloadingId={downloadingId}
                onDownload={(invoice) => void downloadInvoice(invoice)}
            />
        </div>
    );
}
