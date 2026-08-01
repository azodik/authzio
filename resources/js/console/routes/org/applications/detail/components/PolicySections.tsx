import type { ReactNode } from 'react';
import type { ApplicationDraft, SetApplicationDraft } from './applicationEditor';

const inputClass =
    'w-full border border-mist bg-paper px-3 py-2.5 text-sm outline-none focus:border-teal';
function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="block text-sm">
            <span className="mb-1.5 block font-medium">{label}</span>
            {children}
        </div>
    );
}
function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="border border-mist bg-paper-elevated p-6">
            <h2 className="font-display text-lg font-semibold">{title}</h2>
            <div className="mt-5 space-y-4">{children}</div>
        </section>
    );
}

export function PolicySection({
    kind,
    draft,
    setDraft,
}: {
    kind: 'password' | 'security' | 'legal' | 'settings';
    draft: ApplicationDraft;
    setDraft: SetApplicationDraft;
}) {
    if (kind === 'password')
        return (
            <Section title="Password policy">
                <Field label="Minimum length">
                    <input
                        type="number"
                        min={8}
                        max={128}
                        value={draft.passwordPolicy.min_length}
                        onChange={(event) =>
                            setDraft({
                                ...draft,
                                passwordPolicy: {
                                    ...draft.passwordPolicy,
                                    min_length: Number(event.target.value),
                                },
                            })
                        }
                        className={inputClass}
                    />
                </Field>
                {(['require_mixed_case', 'require_numbers', 'require_symbols'] as const).map(
                    (key) => (
                        <label key={key} className="flex gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={draft.passwordPolicy[key]}
                                onChange={(event) =>
                                    setDraft({
                                        ...draft,
                                        passwordPolicy: {
                                            ...draft.passwordPolicy,
                                            [key]: event.target.checked,
                                        },
                                    })
                                }
                            />
                            {key.replaceAll('_', ' ')}
                        </label>
                    ),
                )}
            </Section>
        );
    if (kind === 'security')
        return (
            <Section title="Security policy">
                <label className="flex gap-2">
                    <input
                        type="checkbox"
                        checked={draft.securityPolicy.mfa_required}
                        onChange={(event) =>
                            setDraft({
                                ...draft,
                                securityPolicy: {
                                    ...draft.securityPolicy,
                                    mfa_required: event.target.checked,
                                },
                            })
                        }
                    />
                    Require MFA for all users
                </label>
                <label className="flex gap-2">
                    <input
                        type="checkbox"
                        checked={draft.securityPolicy.single_device}
                        onChange={(event) =>
                            setDraft({
                                ...draft,
                                securityPolicy: {
                                    ...draft.securityPolicy,
                                    single_device: event.target.checked,
                                },
                            })
                        }
                    />
                    Single device sessions
                </label>
                <Field label="Session lifetime (minutes)">
                    <input
                        type="number"
                        value={draft.securityPolicy.session_lifetime_minutes}
                        onChange={(event) =>
                            setDraft({
                                ...draft,
                                securityPolicy: {
                                    ...draft.securityPolicy,
                                    session_lifetime_minutes: Number(event.target.value),
                                },
                            })
                        }
                        className={inputClass}
                    />
                </Field>
            </Section>
        );
    if (kind === 'legal')
        return (
            <Section title="Terms & privacy">
                <Field label="Terms URL">
                    <input
                        value={draft.termsUrl}
                        onChange={(event) => setDraft({ ...draft, termsUrl: event.target.value })}
                        className={inputClass}
                    />
                </Field>
                <Field label="Privacy URL">
                    <input
                        value={draft.privacyUrl}
                        onChange={(event) => setDraft({ ...draft, privacyUrl: event.target.value })}
                        className={inputClass}
                    />
                </Field>
                <label className="flex gap-2">
                    <input
                        type="checkbox"
                        checked={draft.requireLegalAccept}
                        onChange={(event) =>
                            setDraft({ ...draft, requireLegalAccept: event.target.checked })
                        }
                    />
                    Require users to accept
                </label>
            </Section>
        );
    return (
        <Section title="Application settings">
            <Field label="Name">
                <input
                    required
                    value={draft.name}
                    onChange={(event) => setDraft({ ...draft, name: event.target.value })}
                    className={inputClass}
                />
            </Field>
            <Field label="Description">
                <input
                    value={draft.description}
                    onChange={(event) => setDraft({ ...draft, description: event.target.value })}
                    className={inputClass}
                />
            </Field>
            <Field label="Redirect URIs">
                <textarea
                    rows={4}
                    value={draft.redirectUris}
                    onChange={(event) => setDraft({ ...draft, redirectUris: event.target.value })}
                    className={inputClass}
                />
            </Field>
        </Section>
    );
}
