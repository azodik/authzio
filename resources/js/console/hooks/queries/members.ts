import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiDelete, apiGet, apiPatch, apiPost } from '@/lib/api';
import { orgApiPath } from '@/lib/orgApi';
import { queryKeys } from '@/lib/queryKeys';
import { toastError, toastSuccess } from '@/lib/toast';
import type { OrganizationInvitation, OrganizationMember, Role } from '@/types';

export function useMembersQuery(orgId: string | undefined) {
    return useQuery({
        queryKey: orgId ? queryKeys.org(orgId).members() : ['org', 'members', 'disabled'],
        enabled: Boolean(orgId),
        queryFn: () =>
            apiGet<{
                members: OrganizationMember[];
                invitations: OrganizationInvitation[];
                invitation_history?: OrganizationInvitation[];
            }>(orgApiPath(orgId!, 'members')),
    });
}

export function useInvitationsQuery(orgId: string | undefined) {
    return useQuery({
        queryKey: orgId ? queryKeys.org(orgId).invitations() : ['org', 'invitations', 'disabled'],
        enabled: Boolean(orgId),
        queryFn: () =>
            apiGet<{
                members: OrganizationMember[];
                invitations: OrganizationInvitation[];
            }>(orgApiPath(orgId!, 'members')).then((response) => response.invitations),
    });
}

export function useInvitationHistoryQuery(orgId: string | undefined) {
    return useQuery({
        queryKey: orgId
            ? queryKeys.org(orgId).invitationHistory()
            : ['org', 'invitation-history', 'disabled'],
        enabled: Boolean(orgId),
        queryFn: () =>
            apiGet<{
                members: OrganizationMember[];
                invitations: OrganizationInvitation[];
                invitation_history?: OrganizationInvitation[];
            }>(orgApiPath(orgId!, 'members')).then((response) => response.invitation_history ?? []),
    });
}

export function useRolesQuery(orgId: string | undefined) {
    return useQuery({
        queryKey: orgId ? queryKeys.org(orgId).roles() : ['org', 'roles', 'disabled'],
        enabled: Boolean(orgId),
        queryFn: () => apiGet<{ data: Role[] }>(orgApiPath(orgId!, 'roles')).then((r) => r.data),
    });
}

function invalidateMemberQueries(queryClient: ReturnType<typeof useQueryClient>, orgId: string) {
    void queryClient.invalidateQueries({ queryKey: queryKeys.org(orgId).members() });
    void queryClient.invalidateQueries({ queryKey: queryKeys.org(orgId).invitations() });
    void queryClient.invalidateQueries({ queryKey: queryKeys.org(orgId).invitationHistory() });
    void queryClient.invalidateQueries({ queryKey: queryKeys.org(orgId).roles() });
}

export function useInviteMemberMutation(orgId: string) {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: (input: { email: string; role_id: string }) =>
            apiPost(orgApiPath(orgId, 'invitations'), input),
        onSuccess: () => {
            invalidateMemberQueries(queryClient, orgId);
            toastSuccess('Invitation sent.');
        },
        onError: (error) => toastError(error, 'Failed to send invitation.'),
    });
}

export function useUpdateMemberRoleMutation(orgId: string) {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: (input: { memberId: string; role_id: string }) =>
            apiPatch(orgApiPath(orgId, `members/${input.memberId}/role`), {
                role_id: input.role_id,
            }),
        onSuccess: () => {
            invalidateMemberQueries(queryClient, orgId);
            toastSuccess('Role updated.');
        },
        onError: (error) => toastError(error, 'Failed to update role.'),
    });
}

export function useRemoveMemberMutation(orgId: string) {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: (memberId: string) => apiDelete(orgApiPath(orgId, `members/${memberId}`)),
        onSuccess: () => {
            invalidateMemberQueries(queryClient, orgId);
            toastSuccess('Member removed.');
        },
        onError: (error) => toastError(error, 'Failed to remove member.'),
    });
}

export function useRevokeInvitationMutation(orgId: string) {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: (invitationId: string) =>
            apiDelete(orgApiPath(orgId, `invitations/${invitationId}`)),
        onSuccess: () => {
            invalidateMemberQueries(queryClient, orgId);
            toastSuccess('Invitation revoked.');
        },
        onError: (error) => toastError(error, 'Failed to revoke invitation.'),
    });
}

export function useResendInvitationMutation(orgId: string) {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: (invitationId: string) =>
            apiPost(orgApiPath(orgId, `invitations/${invitationId}/resend`)),
        onSuccess: () => {
            invalidateMemberQueries(queryClient, orgId);
            toastSuccess('Invitation resent.');
        },
        onError: (error) => toastError(error, 'Failed to resend invitation.'),
    });
}
