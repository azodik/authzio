import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiGet, apiPost } from '@/lib/api';
import { orgApiPath } from '@/lib/orgApi';
import { queryKeys } from '@/lib/queryKeys';
import { toastError, toastSuccess } from '@/lib/toast';
import type { BillingDashboard } from '@/types';

export function useBillingQuery(orgId: string | undefined) {
    return useQuery({
        queryKey: orgId ? queryKeys.org(orgId).billing() : ['org', 'billing', 'disabled'],
        enabled: Boolean(orgId),
        queryFn: () => apiGet<{ data: BillingDashboard }>(orgApiPath(orgId!, 'billing')),
    });
}

export function useBillingCheckoutMutation(orgId: string) {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: (input: { plan_slug: string }) =>
            apiPost<{ checkout_url: string; session_id?: string; mode?: string }>(
                orgApiPath(orgId, 'billing/checkout'),
                input,
            ),
        onSuccess: (response) => {
            void queryClient.invalidateQueries({ queryKey: queryKeys.org(orgId).billing() });
            const url = response.checkout_url;
            if (url !== '') {
                window.location.assign(url);
                return;
            }
            toastSuccess('Billing updated.');
        },
        onError: (error) => toastError(error, 'Checkout failed.'),
    });
}

export function useCancelBillingMutation(orgId: string) {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: () => apiPost(orgApiPath(orgId, 'billing/cancel')),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: queryKeys.org(orgId).billing() });
            toastSuccess('Cancellation scheduled.');
        },
        onError: (error) => toastError(error, 'Failed to cancel plan.'),
    });
}
