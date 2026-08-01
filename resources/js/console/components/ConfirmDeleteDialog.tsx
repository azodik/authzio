import { type FormEvent, useEffect, useId, useRef, useState } from 'react';
import { useI18n } from '@/hooks/useI18n';

export const CONFIRM_DELETE_WORD = 'confirm';

type ConfirmDeleteDialogProps = {
    open: boolean;
    title: string;
    description: string;
    confirmLabel?: string;
    pending?: boolean;
    onCancel: () => void;
    onConfirm: () => void | Promise<void>;
};

export function ConfirmDeleteDialog({
    open,
    title,
    description,
    confirmLabel,
    pending = false,
    onCancel,
    onConfirm,
}: ConfirmDeleteDialogProps) {
    const { t } = useI18n();
    const titleId = useId();
    const inputId = useId();
    const inputRef = useRef<HTMLInputElement>(null);
    const [typed, setTyped] = useState('');

    useEffect(() => {
        if (!open) {
            return;
        }
        setTyped('');
        const timer = window.setTimeout(() => inputRef.current?.focus(), 0);
        return () => window.clearTimeout(timer);
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }
        function onKeyDown(event: KeyboardEvent): void {
            if (event.key === 'Escape' && !pending) {
                onCancel();
            }
        }
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [open, pending, onCancel]);

    if (!open) {
        return null;
    }

    const matched = typed.trim().toLowerCase() === CONFIRM_DELETE_WORD;

    async function onSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        if (!matched || pending) {
            return;
        }
        await onConfirm();
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <button
                type="button"
                aria-label={t('console.common.cancel')}
                className="absolute inset-0 bg-ink/40"
                disabled={pending}
                onClick={() => {
                    if (!pending) {
                        onCancel();
                    }
                }}
            />
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                className="relative z-10 w-full max-w-md border border-mist bg-paper-elevated p-6 shadow-lg"
            >
                <h2 id={titleId} className="font-display text-lg font-semibold text-ink">
                    {title}
                </h2>
                <p className="mt-2 text-sm leading-relaxed text-ink-soft/70">{description}</p>
                <form onSubmit={(event) => void onSubmit(event)} className="mt-5">
                    <label htmlFor={inputId} className="block text-sm">
                        <span className="mb-1.5 block font-medium text-ink">
                            {t('console.common.confirm_delete_prompt', {
                                word: CONFIRM_DELETE_WORD,
                            })}
                        </span>
                        <input
                            id={inputId}
                            ref={inputRef}
                            value={typed}
                            disabled={pending}
                            autoComplete="off"
                            spellCheck={false}
                            onChange={(event) => setTyped(event.target.value)}
                            placeholder={CONFIRM_DELETE_WORD}
                            className="w-full border border-mist bg-paper px-3 py-2.5 font-mono text-sm text-ink outline-none focus:border-teal disabled:opacity-60"
                        />
                    </label>
                    <div className="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            disabled={pending}
                            onClick={onCancel}
                            className="border border-mist px-3.5 py-2 text-sm font-medium text-ink hover:bg-fog disabled:opacity-60"
                        >
                            {t('console.common.cancel')}
                        </button>
                        <button
                            type="submit"
                            disabled={!matched || pending}
                            className="bg-danger px-3.5 py-2 text-sm font-semibold text-paper hover:opacity-90 disabled:opacity-50"
                        >
                            {pending
                                ? t('console.common.working')
                                : (confirmLabel ?? t('console.common.delete'))}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
