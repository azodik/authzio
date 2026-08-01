import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiDelete, apiGet, apiPatch, apiPost, apiPut, type JsonValue } from '@/lib/api';
import { orgApiPath } from '@/lib/orgApi';
import { queryKeys } from '@/lib/queryKeys';
import { toastError, toastSuccess } from '@/lib/toast';

export function useOrgResourceQuery<T>(
    orgId: string | undefined,
    key: readonly unknown[],
    path: string,
    enabled = true,
) {
    return useQuery({
        queryKey: key,
        enabled: Boolean(orgId) && enabled,
        queryFn: () => apiGet<T>(orgApiPath(orgId!, path)),
    });
}

export function useOrgMutation(
    orgId: string,
    options: {
        invalidate: readonly unknown[][];
        successMessage: string;
        errorMessage: string;
    },
) {
    const queryClient = useQueryClient();

    const invalidate = () => {
        for (const key of options.invalidate) {
            void queryClient.invalidateQueries({ queryKey: key });
        }
    };

    return {
        post: useMutation({
            mutationFn: ({ path, body }: { path: string; body?: JsonValue }) =>
                apiPost(orgApiPath(orgId, path), body),
            onSuccess: () => {
                invalidate();
                toastSuccess(options.successMessage);
            },
            onError: (error) => toastError(error, options.errorMessage),
        }),
        put: useMutation({
            mutationFn: ({ path, body }: { path: string; body?: JsonValue }) =>
                apiPut(orgApiPath(orgId, path), body),
            onSuccess: () => {
                invalidate();
                toastSuccess(options.successMessage);
            },
            onError: (error) => toastError(error, options.errorMessage),
        }),
        patch: useMutation({
            mutationFn: ({ path, body }: { path: string; body?: JsonValue }) =>
                apiPatch(orgApiPath(orgId, path), body),
            onSuccess: () => {
                invalidate();
                toastSuccess(options.successMessage);
            },
            onError: (error) => toastError(error, options.errorMessage),
        }),
        destroy: useMutation({
            mutationFn: (path: string) => apiDelete(orgApiPath(orgId, path)),
            onSuccess: () => {
                invalidate();
                toastSuccess(options.successMessage);
            },
            onError: (error) => toastError(error, options.errorMessage),
        }),
        queryClient,
        invalidate,
    };
}

export function useOverviewQuery(orgId: string | undefined) {
    return useOrgResourceQuery<{ data: Record<string, unknown> }>(
        orgId,
        orgId ? queryKeys.org(orgId).overview() : ['org', 'overview', 'disabled'],
        'overview/stats',
    );
}

export function useDomainsQuery(orgId: string | undefined) {
    return useOrgResourceQuery<{ data: unknown[] }>(
        orgId,
        orgId ? queryKeys.org(orgId).domains() : ['org', 'domains', 'disabled'],
        'domains',
    );
}

export function useApplicationsQuery(orgId: string | undefined) {
    return useOrgResourceQuery<{ data: unknown[] }>(
        orgId,
        orgId ? queryKeys.org(orgId).applications() : ['org', 'applications', 'disabled'],
        'applications',
    );
}

export function useEmailProviderQuery(orgId: string | undefined) {
    return useOrgResourceQuery<{ data: unknown }>(
        orgId,
        orgId ? queryKeys.org(orgId).emailProvider() : ['org', 'email-provider', 'disabled'],
        'email-provider',
    );
}

export function useSocialProvidersQuery(orgId: string | undefined) {
    return useOrgResourceQuery<{ data: unknown[] }>(
        orgId,
        orgId ? queryKeys.org(orgId).socialProviders() : ['org', 'social-providers', 'disabled'],
        'social-providers',
    );
}

export function useSsoQuery(orgId: string | undefined) {
    return useOrgResourceQuery<{ data: unknown[] }>(
        orgId,
        orgId ? queryKeys.org(orgId).sso() : ['org', 'sso', 'disabled'],
        'sso',
    );
}

export function useAuditLogsQuery(orgId: string | undefined, params: Record<string, string> = {}) {
    const search = new URLSearchParams(params).toString();
    return useOrgResourceQuery<{ data: unknown[] }>(
        orgId,
        orgId ? queryKeys.org(orgId).auditLogs(params) : ['org', 'audit-logs', 'disabled'],
        search !== '' ? `audit-logs?${search}` : 'audit-logs',
    );
}

export function useUsersQuery(orgId: string | undefined, params: Record<string, string> = {}) {
    const search = new URLSearchParams(params).toString();
    return useOrgResourceQuery<{ data: unknown[] }>(
        orgId,
        orgId ? queryKeys.org(orgId).users(params) : ['org', 'users', 'disabled'],
        search !== '' ? `users?${search}` : 'users',
    );
}
