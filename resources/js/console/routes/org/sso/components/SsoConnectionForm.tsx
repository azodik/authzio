import { type FormEvent, useState } from 'react';
import { useI18n } from '@/hooks/useI18n';

export type SsoDraft = {
    name: string;
    issuer: string;
    client_id: string;
    client_secret: string;
    email_domains: string;
    enabled: boolean;
};
export const emptySsoDraft = (): SsoDraft => ({
    name: '',
    issuer: '',
    client_id: '',
    client_secret: '',
    email_domains: '',
    enabled: true,
});

export function SsoConnectionForm({
    initial = emptySsoDraft(),
    pending,
    editing = false,
    onSubmit,
}: {
    initial?: SsoDraft;
    pending: boolean;
    editing?: boolean;
    onSubmit: (draft: SsoDraft) => Promise<void>;
}) {
    const { t } = useI18n();
    const [draft, setDraft] = useState(initial);
    async function submit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        await onSubmit(draft);
    }
    const fields: {
        key: keyof Pick<
            SsoDraft,
            'name' | 'issuer' | 'client_id' | 'client_secret' | 'email_domains'
        >;
        label: string;
        type?: string;
        required?: boolean;
    }[] = [
        { key: 'name', label: t('console.page.sso.name'), required: true },
        { key: 'issuer', label: t('console.page.sso.issuer'), type: 'url', required: true },
        { key: 'client_id', label: t('console.page.sso.client_id'), required: true },
        {
            key: 'client_secret',
            label: t('console.page.sso.client_secret'),
            type: 'password',
            required: !editing,
        },
        { key: 'email_domains', label: t('console.page.sso.email_domains') },
    ];
    return (
        <form onSubmit={(event) => void submit(event)} className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                {fields.map((field) => (
                    <label
                        key={field.key}
                        className={
                            field.key === 'email_domains'
                                ? 'block text-sm sm:col-span-2'
                                : 'block text-sm'
                        }
                    >
                        <span className="mb-1 block text-ink-soft/70">{field.label}</span>
                        <input
                            required={field.required}
                            type={field.type}
                            value={String(draft[field.key])}
                            onChange={(event) =>
                                setDraft({ ...draft, [field.key]: event.target.value })
                            }
                            className="w-full border border-mist bg-paper px-3 py-2"
                        />
                    </label>
                ))}
            </div>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={draft.enabled}
                    onChange={(event) => setDraft({ ...draft, enabled: event.target.checked })}
                />
                {t('console.page.sso.enabled')}
            </label>
            <button
                type="submit"
                disabled={pending}
                className="bg-teal px-4 py-2.5 text-sm font-semibold text-paper disabled:opacity-60"
            >
                {pending
                    ? t('console.common.saving')
                    : editing
                      ? t('console.common.save')
                      : t('console.page.sso.create')}
            </button>
        </form>
    );
}
