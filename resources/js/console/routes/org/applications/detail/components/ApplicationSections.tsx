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

export function LoginSection({
    draft,
    setDraft,
    uploading,
    onUpload,
    onRemove,
}: {
    draft: ApplicationDraft;
    setDraft: SetApplicationDraft;
    uploading: boolean;
    onUpload: (file: File) => void;
    onRemove: () => void;
}) {
    return (
        <section className="border border-mist bg-paper-elevated p-6">
            <h2 className="font-display text-lg font-semibold">Login web UI</h2>
            <div className="mt-5 space-y-4">
                <Field label="Logo">
                    <div className="flex items-center gap-3">
                        {draft.logoUrl && (
                            <img
                                src={draft.logoUrl}
                                alt=""
                                className="size-12 border border-mist object-contain"
                            />
                        )}
                        <label className="cursor-pointer border border-mist px-3 py-2">
                            {uploading ? 'Uploading…' : 'Upload image'}
                            <input
                                type="file"
                                accept="image/png,image/jpeg,image/gif,image/webp"
                                className="sr-only"
                                disabled={uploading}
                                onChange={(event) => {
                                    const file = event.target.files?.[0];
                                    if (file) onUpload(file);
                                }}
                            />
                        </label>
                        {draft.logoUrl && (
                            <button
                                type="button"
                                onClick={onRemove}
                                className="text-sm text-danger"
                            >
                                Remove
                            </button>
                        )}
                    </div>
                    <input
                        value={draft.logoUrl}
                        onChange={(event) => setDraft({ ...draft, logoUrl: event.target.value })}
                        className={`${inputClass} mt-2`}
                    />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Primary color">
                        <input
                            type="color"
                            value={draft.primaryColor}
                            onChange={(event) =>
                                setDraft({ ...draft, primaryColor: event.target.value })
                            }
                            className="h-10 w-full"
                        />
                    </Field>
                    <Field label="Background color">
                        <input
                            type="color"
                            value={draft.backgroundColor}
                            onChange={(event) =>
                                setDraft({ ...draft, backgroundColor: event.target.value })
                            }
                            className="h-10 w-full"
                        />
                    </Field>
                </div>
                <Field label="Layout">
                    <div className="grid gap-2 sm:grid-cols-3">
                        {(
                            [
                                { value: 'centered', label: 'Centered' },
                                { value: 'form_right', label: 'Form on right' },
                                { value: 'form_left', label: 'Form on left' },
                            ] as const
                        ).map((option) => {
                            const selected = draft.loginLayout === option.value;
                            return (
                                <label
                                    key={option.value}
                                    className={`flex cursor-pointer items-center gap-2 border px-3 py-2.5 text-sm ${
                                        selected
                                            ? 'border-teal bg-teal/5 font-medium'
                                            : 'border-mist'
                                    }`}
                                >
                                    <input
                                        type="radio"
                                        name="login_layout"
                                        value={option.value}
                                        checked={selected}
                                        onChange={() =>
                                            setDraft((current) => ({
                                                ...current,
                                                loginLayout: option.value,
                                            }))
                                        }
                                    />
                                    {option.label}
                                </label>
                            );
                        })}
                    </div>
                </Field>
                <Field label="Theme">
                    <div className="grid gap-2 sm:grid-cols-2">
                        {(
                            [
                                { value: 'light', label: 'Light' },
                                { value: 'dark', label: 'Dark' },
                            ] as const
                        ).map((option) => {
                            const selected = draft.loginTheme === option.value;
                            return (
                                <label
                                    key={option.value}
                                    className={`flex cursor-pointer items-center gap-2 border px-3 py-2.5 text-sm ${
                                        selected
                                            ? 'border-teal bg-teal/5 font-medium'
                                            : 'border-mist'
                                    }`}
                                >
                                    <input
                                        type="radio"
                                        name="login_theme"
                                        value={option.value}
                                        checked={selected}
                                        onChange={() =>
                                            setDraft((current) => ({
                                                ...current,
                                                loginTheme: option.value,
                                            }))
                                        }
                                    />
                                    {option.label}
                                </label>
                            );
                        })}
                    </div>
                </Field>
                <Field label="Headline">
                    <input
                        value={draft.headline}
                        onChange={(event) => setDraft({ ...draft, headline: event.target.value })}
                        className={inputClass}
                    />
                </Field>
                <Field label="Supporting text">
                    <textarea
                        value={draft.loginDescription}
                        onChange={(event) =>
                            setDraft({ ...draft, loginDescription: event.target.value })
                        }
                        className={inputClass}
                    />
                </Field>
                <Field label="Button label">
                    <input
                        value={draft.buttonLabel}
                        onChange={(event) =>
                            setDraft({ ...draft, buttonLabel: event.target.value })
                        }
                        className={inputClass}
                    />
                </Field>
                <label className="flex gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={draft.showSignupLink}
                        onChange={(event) =>
                            setDraft({ ...draft, showSignupLink: event.target.checked })
                        }
                    />
                    Show sign-up link
                </label>
                <label className="flex gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={draft.showForgotPasswordLink}
                        onChange={(event) =>
                            setDraft({ ...draft, showForgotPasswordLink: event.target.checked })
                        }
                    />
                    Show forgot password link
                </label>
                <Field label="Default locale">
                    <select
                        value={draft.defaultLocale}
                        onChange={(event) =>
                            setDraft({ ...draft, defaultLocale: event.target.value })
                        }
                        className={inputClass}
                    >
                        {['en', 'fr', 'de', 'es', 'hi'].map((locale) => (
                            <option key={locale}>{locale}</option>
                        ))}
                    </select>
                </Field>
                <label className="flex gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={draft.allowLocaleSwitch}
                        onChange={(event) =>
                            setDraft({ ...draft, allowLocaleSwitch: event.target.checked })
                        }
                    />
                    Allow users to switch locale
                </label>
            </div>
        </section>
    );
}
