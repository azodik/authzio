import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Plus, Shield } from 'lucide-react';
import { type FormEvent, useCallback, useMemo, useState } from 'react';
import { EmptyState } from '@/components/EmptyState';
import { PageHeader } from '@/components/PageHeader';
import { useActiveOrganization } from '@/hooks/useActiveOrganization';
import { useI18n } from '@/hooks/useI18n';
import { apiGet, apiPost, apiPut } from '@/lib/api';
import { orgApiPath } from '@/lib/orgApi';
import { queryKeys } from '@/lib/queryKeys';
import { toastError, toastSuccess } from '@/lib/toast';
import type { Permission, Role } from '@/types';
import { useWorkspace } from '@/workspace/WorkspaceContext';
import { RolesTable } from './components/RolesTable';

type PermissionGroupOption = {
    slug: string;
    label: string;
};

export function RolesPage() {
    const organization = useActiveOrganization();
    const { can } = useWorkspace();
    const { t } = useI18n();
    const [showCreate, setShowCreate] = useState(false);
    const [editingId, setEditingId] = useState<string | null>(null);
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [selectedPermissions, setSelectedPermissions] = useState<string[]>([]);
    const queryClient = useQueryClient();
    const rolesQuery = useQuery({
        queryKey: organization
            ? queryKeys.org(organization.id).roles()
            : ['org', 'roles', 'disabled'],
        enabled: Boolean(organization),
        queryFn: () =>
            apiGet<{
                data: Role[];
                permissions: Permission[];
                groups?: PermissionGroupOption[];
            }>(orgApiPath(organization!.id, 'roles')),
    });
    const roles = rolesQuery.data?.data ?? [];
    const catalog = rolesQuery.data?.permissions ?? [];
    const groupLabels = rolesQuery.data?.groups ?? [];
    const saveRole = useMutation({
        mutationFn: ({
            id,
            ...payload
        }: {
            id: string | null;
            name: string;
            description: string | null;
            permissions: string[];
        }) =>
            id
                ? apiPut(orgApiPath(organization!.id, `roles/${id}`), payload)
                : apiPost(orgApiPath(organization!.id, 'roles'), payload),
        onError: (error) => toastError(error, 'Failed to save role.'),
    });

    const canWrite = can('roles.write');

    const labelForGroup = useCallback(
        (group: string): string => {
            const translated = t(`permission.group.${group}`);
            if (translated !== `permission.group.${group}`) {
                return translated;
            }
            return groupLabels.find((item) => item.slug === group)?.label ?? group;
        },
        [groupLabels, t],
    );

    const groupedPermissions = useMemo(() => {
        const groups = new Map<string, Permission[]>();
        for (const permission of catalog) {
            const list = groups.get(permission.group) ?? [];
            list.push(permission);
            groups.set(permission.group, list);
        }

        const ordered =
            groupLabels.length > 0
                ? groupLabels.map((group) => group.slug).filter((slug) => groups.has(slug))
                : Array.from(groups.keys());

        for (const key of groups.keys()) {
            if (!ordered.includes(key)) {
                ordered.push(key);
            }
        }

        return ordered.map((slug) => [slug, groups.get(slug) ?? []] as const);
    }, [catalog, groupLabels]);

    function startCreate(): void {
        setShowCreate(true);
        setEditingId(null);
        setName('');
        setDescription('');
        setSelectedPermissions([]);
    }

    function startEdit(role: Role): void {
        setShowCreate(false);
        setEditingId(role.id);
        setName(role.name);
        setDescription(role.description ?? '');
        setSelectedPermissions((role.permissions ?? []).map((permission) => permission.slug));
    }

    function togglePermission(slug: string): void {
        setSelectedPermissions((current) =>
            current.includes(slug) ? current.filter((item) => item !== slug) : [...current, slug],
        );
    }

    function setGroupSelected(slugs: string[], selected: boolean): void {
        setSelectedPermissions((current) => {
            if (selected) {
                return Array.from(new Set([...current, ...slugs]));
            }
            return current.filter((slug) => !slugs.includes(slug));
        });
    }

    function groupState(slugs: string[]): 'all' | 'some' | 'none' {
        const selected = slugs.filter((slug) => selectedPermissions.includes(slug)).length;
        if (selected === 0) {
            return 'none';
        }
        if (selected === slugs.length) {
            return 'all';
        }
        return 'some';
    }

    async function onSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        if (!organization) {
            return;
        }

        try {
            const wasEditing = editingId !== null;
            await saveRole.mutateAsync({
                id: editingId,
                name: name.trim(),
                description: description.trim() || null,
                permissions: selectedPermissions,
            });
            toastSuccess(wasEditing ? 'Role updated.' : 'Role created.');
            if (!wasEditing) {
                setShowCreate(false);
            }
            setEditingId(null);
            await queryClient.invalidateQueries({
                queryKey: queryKeys.org(organization.id).roles(),
            });
        } catch {
            // Mutation reports the error.
        }
    }

    if (!organization) {
        return (
            <EmptyState
                title={t('console.page.roles.need_org_title')}
                description={t('console.page.roles.need_org_description')}
            />
        );
    }

    const formOpen = showCreate || editingId !== null;

    return (
        <div>
            <PageHeader
                title={t('console.page.roles.title')}
                description={t('console.page.roles.description')}
                action={
                    canWrite ? (
                        <button
                            type="button"
                            onClick={startCreate}
                            className="inline-flex items-center gap-2 bg-ink px-3.5 py-2 text-sm font-medium text-paper hover:bg-ink-soft"
                        >
                            <Plus className="size-4" strokeWidth={1.75} />
                            {t('console.page.roles.create')}
                        </button>
                    ) : null
                }
            />

            {rolesQuery.error && (
                <p className="mb-4 text-sm text-danger" role="alert">
                    {rolesQuery.error.message || 'Failed to load roles.'}
                </p>
            )}

            {formOpen && canWrite && (
                <form onSubmit={onSubmit} className="mb-8 border border-mist bg-paper-elevated p-6">
                    <h2 className="font-display text-lg font-semibold text-ink">
                        {editingId ? t('console.page.roles.edit') : t('console.page.roles.new')}
                    </h2>
                    <p className="mt-1 text-sm text-ink-soft/60">
                        {t('console.page.roles.groups_hint')}
                    </p>
                    <div className="mt-5 grid gap-4 sm:grid-cols-2">
                        <label className="text-sm">
                            <span className="mb-1.5 block font-medium text-ink">
                                {t('console.common.name')}
                            </span>
                            <input
                                required
                                value={name}
                                onChange={(event) => setName(event.target.value)}
                                className="w-full border border-mist bg-paper px-3 py-2.5 outline-none focus:border-teal"
                            />
                        </label>
                        <label className="text-sm">
                            <span className="mb-1.5 block font-medium text-ink">
                                {t('console.common.description')}
                            </span>
                            <input
                                value={description}
                                onChange={(event) => setDescription(event.target.value)}
                                className="w-full border border-mist bg-paper px-3 py-2.5 outline-none focus:border-teal"
                            />
                        </label>
                    </div>

                    <div className="mt-6 space-y-5">
                        {groupedPermissions.map(([group, permissions]) => {
                            const slugs = permissions.map((permission) => permission.slug);
                            const state = groupState(slugs);

                            return (
                                <div key={group} className="border border-mist bg-paper">
                                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-mist bg-fog/60 px-3 py-2.5">
                                        <label className="flex items-center gap-2 text-sm font-semibold text-ink">
                                            <input
                                                type="checkbox"
                                                checked={state === 'all'}
                                                ref={(element) => {
                                                    if (element) {
                                                        element.indeterminate = state === 'some';
                                                    }
                                                }}
                                                onChange={(event) =>
                                                    setGroupSelected(slugs, event.target.checked)
                                                }
                                            />
                                            {labelForGroup(group)}
                                            <span className="font-normal text-ink-soft/50">
                                                ({permissions.length})
                                            </span>
                                        </label>
                                        <div className="flex gap-2 text-xs">
                                            <button
                                                type="button"
                                                className="text-teal hover:text-teal-deep"
                                                onClick={() => setGroupSelected(slugs, true)}
                                            >
                                                {t('console.common.select_all')}
                                            </button>
                                            <button
                                                type="button"
                                                className="text-ink-soft/55 hover:text-ink"
                                                onClick={() => setGroupSelected(slugs, false)}
                                            >
                                                {t('console.common.clear')}
                                            </button>
                                        </div>
                                    </div>
                                    <div className="grid gap-2 p-3 sm:grid-cols-2">
                                        {permissions.map((permission) => (
                                            <label
                                                key={permission.id}
                                                className="flex items-start gap-2 border border-mist bg-paper-elevated px-3 py-2.5 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    className="mt-0.5"
                                                    checked={selectedPermissions.includes(
                                                        permission.slug,
                                                    )}
                                                    onChange={() =>
                                                        togglePermission(permission.slug)
                                                    }
                                                />
                                                <span>
                                                    <span className="block font-medium text-ink">
                                                        {permission.name}
                                                    </span>
                                                    {permission.description ? (
                                                        <span className="mt-0.5 block text-xs text-ink-soft/55">
                                                            {permission.description}
                                                        </span>
                                                    ) : null}
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    <div className="mt-6 flex gap-3">
                        <button
                            type="submit"
                            disabled={saveRole.isPending}
                            className="bg-teal px-4 py-2.5 text-sm font-semibold text-paper hover:bg-teal-bright disabled:opacity-60"
                        >
                            {saveRole.isPending
                                ? t('console.common.saving')
                                : editingId
                                  ? t('console.page.roles.save')
                                  : t('console.page.roles.create')}
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setShowCreate(false);
                                setEditingId(null);
                            }}
                            className="px-4 py-2.5 text-sm text-ink-soft/70 hover:text-ink"
                        >
                            {t('console.common.cancel')}
                        </button>
                    </div>
                </form>
            )}

            {roles.length === 0 ? (
                <EmptyState
                    icon={Shield}
                    title={t('console.page.roles.empty_title')}
                    description={t('console.page.roles.empty_description')}
                />
            ) : (
                <RolesTable roles={roles} canWrite={canWrite} onEdit={startEdit} />
            )}
        </div>
    );
}
