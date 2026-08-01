import {
    Bold,
    Code2,
    Heading2,
    Image as ImageIcon,
    Italic,
    Link2,
    List,
    Redo2,
    Undo2,
} from 'lucide-react';
import { type ReactNode, useEffect, useRef, useState } from 'react';
import { useI18n } from '@/hooks/useI18n';

type Props = {
    value: string;
    disabled?: boolean;
    onChange: (html: string) => void;
};

type Mode = 'visual' | 'source';

function ToolbarButton({
    label,
    onClick,
    disabled,
    children,
}: {
    label: string;
    onClick: () => void;
    disabled?: boolean;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            title={label}
            aria-label={label}
            disabled={disabled}
            onMouseDown={(event) => {
                event.preventDefault();
                onClick();
            }}
            className="inline-flex size-8 items-center justify-center rounded-sm text-ink-soft hover:bg-fog hover:text-ink disabled:cursor-not-allowed disabled:bg-transparent disabled:text-ink-soft disabled:opacity-100"
        >
            {children}
        </button>
    );
}

export function EmailTemplateEditor({ value, disabled = false, onChange }: Props) {
    const { t } = useI18n();
    const editorRef = useRef<HTMLDivElement>(null);
    const [mode, setMode] = useState<Mode>('visual');
    const syncingRef = useRef(false);

    useEffect(() => {
        if (mode !== 'visual' || !editorRef.current) {
            return;
        }
        if (editorRef.current.innerHTML !== value) {
            syncingRef.current = true;
            editorRef.current.innerHTML = value;
            syncingRef.current = false;
        }
    }, [value, mode]);

    function emitFromEditor(): void {
        if (syncingRef.current || !editorRef.current) {
            return;
        }
        onChange(editorRef.current.innerHTML);
    }

    function runCommand(command: string, commandValue?: string): void {
        if (disabled || mode !== 'visual') {
            return;
        }
        editorRef.current?.focus();
        document.execCommand(command, false, commandValue);
        emitFromEditor();
    }

    function insertLink(): void {
        const url = window.prompt(t('console.page.email_templates.prompt_link'));
        if (!url) {
            return;
        }
        runCommand('createLink', url.trim());
    }

    function insertImage(): void {
        const url = window.prompt(t('console.page.email_templates.prompt_image'));
        if (!url) {
            return;
        }
        runCommand('insertImage', url.trim());
    }

    function insertHeading(): void {
        runCommand('formatBlock', 'h2');
    }

    return (
        <div className="email-template-editor border border-mist bg-paper">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-fog px-2 py-1.5">
                <div className="flex flex-wrap items-center">
                    <ToolbarButton
                        label={t('console.page.email_templates.toolbar_bold')}
                        disabled={disabled || mode !== 'visual'}
                        onClick={() => runCommand('bold')}
                    >
                        <Bold className="size-3.5" />
                    </ToolbarButton>
                    <ToolbarButton
                        label={t('console.page.email_templates.toolbar_italic')}
                        disabled={disabled || mode !== 'visual'}
                        onClick={() => runCommand('italic')}
                    >
                        <Italic className="size-3.5" />
                    </ToolbarButton>
                    <ToolbarButton
                        label={t('console.page.email_templates.toolbar_heading')}
                        disabled={disabled || mode !== 'visual'}
                        onClick={insertHeading}
                    >
                        <Heading2 className="size-3.5" />
                    </ToolbarButton>
                    <ToolbarButton
                        label={t('console.page.email_templates.toolbar_list')}
                        disabled={disabled || mode !== 'visual'}
                        onClick={() => runCommand('insertUnorderedList')}
                    >
                        <List className="size-3.5" />
                    </ToolbarButton>
                    <ToolbarButton
                        label={t('console.page.email_templates.toolbar_link')}
                        disabled={disabled || mode !== 'visual'}
                        onClick={insertLink}
                    >
                        <Link2 className="size-3.5" />
                    </ToolbarButton>
                    <ToolbarButton
                        label={t('console.page.email_templates.toolbar_image')}
                        disabled={disabled || mode !== 'visual'}
                        onClick={insertImage}
                    >
                        <ImageIcon className="size-3.5" />
                    </ToolbarButton>
                    <ToolbarButton
                        label={t('console.page.email_templates.toolbar_undo')}
                        disabled={disabled || mode !== 'visual'}
                        onClick={() => runCommand('undo')}
                    >
                        <Undo2 className="size-3.5" />
                    </ToolbarButton>
                    <ToolbarButton
                        label={t('console.page.email_templates.toolbar_redo')}
                        disabled={disabled || mode !== 'visual'}
                        onClick={() => runCommand('redo')}
                    >
                        <Redo2 className="size-3.5" />
                    </ToolbarButton>
                </div>
                <button
                    type="button"
                    disabled={disabled}
                    onClick={() =>
                        setMode((current) => (current === 'visual' ? 'source' : 'visual'))
                    }
                    className="inline-flex items-center gap-1.5 rounded-sm px-2 py-1 text-xs font-medium text-ink-soft hover:bg-fog hover:text-ink disabled:cursor-not-allowed disabled:text-ink-soft disabled:opacity-100"
                >
                    <Code2 className="size-3.5" />
                    {mode === 'visual'
                        ? t('console.page.email_templates.mode_source')
                        : t('console.page.email_templates.mode_visual')}
                </button>
            </div>

            {mode === 'visual' ? (
                // Rich HTML editing requires contentEditable; a plain textarea cannot host the visual toolbar.
                // biome-ignore lint/a11y/useSemanticElements: contentEditable rich-text surface
                <div
                    ref={editorRef}
                    contentEditable={!disabled}
                    tabIndex={disabled ? -1 : 0}
                    role="textbox"
                    aria-multiline="true"
                    aria-label={t('console.page.email_templates.html_body')}
                    onInput={emitFromEditor}
                    onBlur={emitFromEditor}
                    className="min-h-[280px] overflow-x-auto bg-paper px-3 py-3 text-sm leading-relaxed text-ink outline-none focus:bg-paper-elevated disabled:opacity-100 [&_a]:text-teal [&_h1]:mb-3 [&_h1]:text-xl [&_h1]:font-semibold [&_h2]:mb-2 [&_h2]:text-lg [&_h2]:font-semibold [&_img]:my-2 [&_img]:max-w-full [&_li]:text-ink [&_p]:mb-3 [&_p]:text-ink [&_span]:text-ink [&_table]:max-w-full [&_table]:table-fixed [&_td]:max-w-full [&_td]:overflow-hidden [&_.email-code]:max-w-full [&_.email-code-value]:max-w-full [&_.email-code-value]:break-words [&_.email-code-value]:tracking-wide"
                />
            ) : (
                <textarea
                    disabled={disabled}
                    rows={14}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    className="min-h-[280px] w-full resize-y border-0 bg-paper px-3 py-3 font-mono text-[13px] leading-relaxed text-ink outline-none focus:ring-0 disabled:opacity-60"
                />
            )}
        </div>
    );
}
