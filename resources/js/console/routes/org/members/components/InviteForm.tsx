import { type FormEvent, useState } from 'react';
import { useI18n } from '@/hooks/useI18n';
import type { Role } from '@/types';

type Props = {
    roles: Role[];
    pending: boolean;
    onInvite: (email: string, roleId: string) => Promise<void>;
};

export function InviteForm({ roles, pending, onInvite }: Props) {
    const { t } = useI18n();
    const defaultRoleId = roles.find((role) => role.slug === 'member')?.id ?? roles[0]?.id ?? '';
    const [email, setEmail] = useState('');
    const [roleId, setRoleId] = useState(defaultRoleId);

    async function submit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        await onInvite(email.trim(), roleId);
        setEmail('');
    }

    return (
        <form
            onSubmit={(event) => void submit(event)}
            className="mb-8 grid gap-4 border border-mist bg-paper-elevated p-5 sm:grid-cols-[1fr_180px_auto]"
        >
            <label className="text-sm">
                <span className="mb-1.5 block font-medium text-ink">
                    {t('console.common.email')}
                </span>
                <input
                    type="email"
                    required
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                    className="w-full border border-mist bg-paper px-3 py-2.5 outline-none focus:border-teal"
                />
            </label>
            <label className="text-sm">
                <span className="mb-1.5 block font-medium text-ink">
                    {t('console.common.role')}
                </span>
                <select
                    required
                    value={roleId}
                    onChange={(event) => setRoleId(event.target.value)}
                    className="w-full border border-mist bg-paper px-3 py-2.5 outline-none focus:border-teal"
                >
                    {roles.map((role) => (
                        <option key={role.id} value={role.id}>
                            {role.name}
                        </option>
                    ))}
                </select>
            </label>
            <div className="flex items-end">
                <button
                    type="submit"
                    disabled={pending || roleId === ''}
                    className="w-full bg-teal px-4 py-2.5 text-sm font-semibold text-paper hover:bg-teal-bright disabled:opacity-60"
                >
                    {pending ? t('console.common.sending') : t('console.common.send_invite')}
                </button>
            </div>
        </form>
    );
}
