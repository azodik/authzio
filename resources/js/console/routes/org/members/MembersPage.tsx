import { Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDeleteDialog } from '@/components/ConfirmDeleteDialog';
import { EmptyState } from '@/components/EmptyState';
import { PageHeader } from '@/components/PageHeader';
import {
    useInviteMemberMutation,
    useMembersQuery,
    useRemoveMemberMutation,
    useResendInvitationMutation,
    useRevokeInvitationMutation,
    useRolesQuery,
    useUpdateMemberRoleMutation,
} from '@/hooks/queries/members';
import { useActiveOrganization } from '@/hooks/useActiveOrganization';
import { useI18n } from '@/hooks/useI18n';
import type { OrganizationInvitation, OrganizationMember } from '@/types';
import { useWorkspace } from '@/workspace/WorkspaceContext';
import { InvitationsList } from './components/InvitationsList';
import { InviteForm } from './components/InviteForm';
import { MembersTable } from './components/MembersTable';

export function MembersPage() {
    const organization = useActiveOrganization();
    const { can } = useWorkspace();
    const { t } = useI18n();
    const [showInvite, setShowInvite] = useState(false);
    const [busyInviteId, setBusyInviteId] = useState<string | null>(null);
    const [removeTarget, setRemoveTarget] = useState<OrganizationMember | null>(null);
    const [revokeInviteTarget, setRevokeInviteTarget] = useState<OrganizationInvitation | null>(
        null,
    );
    const orgId = organization?.id;
    const membersQuery = useMembersQuery(orgId);
    const rolesQuery = useRolesQuery(orgId);
    const invite = useInviteMemberMutation(orgId ?? '');
    const updateRole = useUpdateMemberRoleMutation(orgId ?? '');
    const removeMember = useRemoveMemberMutation(orgId ?? '');
    const resend = useResendInvitationMutation(orgId ?? '');
    const revoke = useRevokeInvitationMutation(orgId ?? '');
    const roles = (rolesQuery.data ?? []).filter((role) => !role.is_owner);

    const canInvite = can('members.invite');
    const canRemove = can('members.remove');
    const canManageRoles = can('members.manage_roles');

    async function onResend(inviteId: string): Promise<void> {
        setBusyInviteId(inviteId);
        try {
            await resend.mutateAsync(inviteId);
        } finally {
            setBusyInviteId(null);
        }
    }

    async function confirmRevokeInvite(): Promise<void> {
        if (!revokeInviteTarget) {
            return;
        }
        setBusyInviteId(revokeInviteTarget.id);
        try {
            await revoke.mutateAsync(revokeInviteTarget.id);
            setRevokeInviteTarget(null);
        } finally {
            setBusyInviteId(null);
        }
    }

    async function confirmRemoveMember(): Promise<void> {
        if (!removeTarget) {
            return;
        }
        await removeMember.mutateAsync(removeTarget.id);
        setRemoveTarget(null);
    }

    if (!organization) {
        return (
            <EmptyState
                title={t('console.common.need_org_title')}
                description={t('console.page.members.need_org_description')}
            />
        );
    }

    return (
        <div>
            <PageHeader
                title={t('console.page.members.title')}
                description={t('console.page.members.description_org', { name: organization.name })}
                action={
                    canInvite ? (
                        <button
                            type="button"
                            onClick={() => setShowInvite((value) => !value)}
                            className="inline-flex items-center gap-2 bg-ink px-3.5 py-2 text-sm font-medium text-paper hover:bg-ink-soft"
                        >
                            <Plus className="size-4" strokeWidth={1.75} />
                            {t('console.common.invite')}
                        </button>
                    ) : null
                }
            />

            {membersQuery.error !== null && (
                <p className="mb-4 text-sm text-danger" role="alert">
                    Failed to load members.
                </p>
            )}
            {showInvite && canInvite && (
                <InviteForm
                    roles={roles}
                    pending={invite.isPending}
                    onInvite={async (email, roleId) => {
                        await invite.mutateAsync({ email, role_id: roleId });
                        setShowInvite(false);
                    }}
                />
            )}
            <section className="mb-10">
                <h2 className="mb-3 font-display text-base font-semibold text-ink">
                    {t('console.page.members.active')}
                </h2>
                <MembersTable
                    members={membersQuery.data?.members ?? []}
                    roles={roles}
                    canManageRoles={canManageRoles}
                    canRemove={canRemove}
                    busy={updateRole.isPending || removeMember.isPending}
                    onChangeRole={(memberId, roleId) =>
                        updateRole.mutate({ memberId, role_id: roleId })
                    }
                    onRemove={(memberId) => {
                        const member = (membersQuery.data?.members ?? []).find(
                            (item) => item.id === memberId,
                        );
                        setRemoveTarget(member ?? null);
                    }}
                />
            </section>
            <InvitationsList
                title={t('console.page.members.pending')}
                invitations={membersQuery.data?.invitations ?? []}
                canManage={canInvite}
                busyId={busyInviteId ?? undefined}
                onResend={(id) => void onResend(id)}
                onRevoke={(id) => {
                    const invite = (membersQuery.data?.invitations ?? []).find(
                        (item) => item.id === id,
                    );
                    setRevokeInviteTarget(invite ?? null);
                }}
            />
            <InvitationsList
                title={t('console.page.members.history')}
                invitations={membersQuery.data?.invitation_history ?? []}
                history
            />

            <ConfirmDeleteDialog
                open={removeTarget !== null}
                title={t('console.page.members.remove_title')}
                description={t('console.page.members.remove_body', {
                    name: removeTarget?.user.name ?? '',
                })}
                confirmLabel={t('console.common.remove')}
                pending={removeMember.isPending}
                onCancel={() => setRemoveTarget(null)}
                onConfirm={() => void confirmRemoveMember()}
            />
            <ConfirmDeleteDialog
                open={revokeInviteTarget !== null}
                title={t('console.page.members.revoke_invite_title')}
                description={t('console.page.members.revoke_invite_body', {
                    email: revokeInviteTarget?.email ?? '',
                })}
                confirmLabel={t('console.common.revoke')}
                pending={busyInviteId === revokeInviteTarget?.id}
                onCancel={() => setRevokeInviteTarget(null)}
                onConfirm={() => void confirmRevokeInvite()}
            />
        </div>
    );
}
