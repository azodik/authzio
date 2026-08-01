import type { ReactNode } from 'react';
import { Link } from 'react-router';
import { EmailPreviewPane } from '@/components/EmailPreviewPane';
import { useI18n } from '@/hooks/useI18n';
import { emailTemplateLabel } from '@/lib/emailTemplateLabel';
import { SOCIAL_PROVIDER_OPTIONS } from '@/types';
import type {
    ApplicationDraft,
    EmailTemplateSummary,
    SetApplicationDraft,
} from './applicationEditor';

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="border border-mist bg-paper-elevated p-6">
            <h2 className="font-display text-lg font-semibold">{title}</h2>
            <div className="mt-5 space-y-4">{children}</div>
        </section>
    );
}

export function AuthSection({
    draft,
    setDraft,
    socialPath,
}: {
    draft: ApplicationDraft;
    setDraft: SetApplicationDraft;
    socialPath: string;
}) {
    const methods = [
        ['password', 'Email & password'],
        ['passkey', 'Passkeys (WebAuthn)'],
        ['email_otp', 'Email one-time code'],
    ] as const;
    const profile = [
        'sync_profile',
        'require_verified_email',
        'allow_unverified_email_with_otp',
    ] as const;
    return (
        <Section title="Login methods">
            <p className="text-sm text-ink-soft/65">
                Configure OAuth credentials under{' '}
                <Link to={socialPath} className="text-teal">
                    Social providers
                </Link>
                .
            </p>
            {[
                ...methods,
                ...SOCIAL_PROVIDER_OPTIONS.map(({ key, label }) => [key, label] as const),
            ].map(([key, label]) => (
                <label key={key} className="flex gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={draft.loginMethods[key]}
                        onChange={(event) =>
                            setDraft({
                                ...draft,
                                loginMethods: {
                                    ...draft.loginMethods,
                                    [key]: event.target.checked,
                                },
                            })
                        }
                    />
                    {label}
                </label>
            ))}
            {profile.map((key) => (
                <label key={key} className="flex gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={draft.loginMethods[key]}
                        onChange={(event) =>
                            setDraft({
                                ...draft,
                                loginMethods: {
                                    ...draft.loginMethods,
                                    [key]: event.target.checked,
                                },
                            })
                        }
                    />
                    {key.replaceAll('_', ' ')}
                </label>
            ))}
        </Section>
    );
}

export function EmailSection({
    templates,
    selectedId,
    onSelect,
    emailPath,
}: {
    templates: EmailTemplateSummary[];
    selectedId: string | null;
    onSelect: (id: string) => void;
    emailPath: string;
}) {
    const { t } = useI18n();
    const selected = templates.find((template) => template.id === selectedId);
    return (
        <Section title="Email template preview">
            <div className="flex flex-wrap gap-2">
                {templates.map((template) => (
                    <button
                        type="button"
                        key={template.id}
                        onClick={() => onSelect(template.id)}
                        className="border border-mist px-3 py-1.5 text-sm"
                    >
                        {emailTemplateLabel(template.slug, template.name, t)}
                    </button>
                ))}
            </div>
            {selected && (
                <EmailPreviewPane subject={selected.preview_subject} html={selected.preview_html} />
            )}
            <Link to={emailPath} className="text-sm text-teal">
                Open email templates →
            </Link>
        </Section>
    );
}
