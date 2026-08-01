import { useI18n } from '@/hooks/useI18n';
import { useIsDarkMode } from '@/hooks/useTheme';

type EmailPreviewPaneProps = {
    subject: string;
    html: string;
    /** Tailwind height classes for the iframe (default: h-72). */
    iframeClassName?: string;
};

const FORCE_DARK_CSS = `
:root { color-scheme: dark; }
.email-bg { background: #0c1211 !important; }
.email-card { background: #151c1a !important; border-color: #2a3532 !important; }
.email-header { border-color: #2a3532 !important; }
.email-footer { border-color: #2a3532 !important; color: #8b9a95 !important; }
.email-heading, .email-text, .email-brand { color: #e8eeec !important; }
.email-muted { color: #8b9a95 !important; }
.email-code { background: #1c2623 !important; border-color: #2a3532 !important; overflow: hidden !important; }
.email-code span, .email-code-value { color: #5ecfcf !important; max-width: 100% !important; display: inline-block !important; overflow-wrap: anywhere !important; word-break: break-word !important; letter-spacing: 0.06em !important; font-size: clamp(15px, 4vw, 28px) !important; }
.email-btn { background: #0d8a8a !important; }
.email-text a, a { color: #5ecfcf !important; }
.logo-light { display: none !important; }
.logo-dark { display: inline-block !important; max-height: none !important; width: auto !important; height: 32px !important; }
table { max-width: 100% !important; table-layout: fixed; }
body { background: #0c1211 !important; color: #e8eeec !important; }
`;

const FORCE_LIGHT_CSS = `
:root { color-scheme: light; }
body { background: #F4F7F6; color: #14201E; }
table { max-width: 100% !important; table-layout: fixed; }
.email-code { overflow: hidden !important; }
.email-code span, .email-code-value { max-width: 100% !important; display: inline-block !important; overflow-wrap: anywhere !important; word-break: break-word !important; letter-spacing: 0.06em !important; font-size: clamp(15px, 4vw, 28px) !important; }
`;

function isFullHtmlDocument(html: string): boolean {
    const trimmed = html.trimStart().toLowerCase();
    return trimmed.startsWith('<!doctype') || trimmed.startsWith('<html');
}

function buildPreviewSrcDoc(html: string, isDark: boolean): string {
    const themeCss = isDark ? FORCE_DARK_CSS : FORCE_LIGHT_CSS;
    const colorScheme = isDark ? 'dark' : 'light';
    const themeBlock = `<meta name="color-scheme" content="${colorScheme}"><style data-authzio-console-theme>${themeCss}</style>`;

    if (isFullHtmlDocument(html)) {
        if (/<head[^>]*>/i.test(html)) {
            return html.replace(/<head([^>]*)>/i, `<head$1>${themeBlock}`);
        }
        if (/<html[^>]*>/i.test(html)) {
            return html.replace(/<html([^>]*)>/i, `<html$1><head>${themeBlock}</head>`);
        }
        return themeBlock + html;
    }

    return `<!DOCTYPE html><html><head><meta charset="utf-8">${themeBlock}</head><body>${html}</body></html>`;
}

export function EmailPreviewPane({
    subject,
    html,
    iframeClassName = 'h-72',
}: EmailPreviewPaneProps) {
    const { t } = useI18n();
    const isDark = useIsDarkMode();
    const srcDoc = buildPreviewSrcDoc(html, isDark);

    return (
        <div className="border border-mist bg-paper-elevated">
            <div className="border-b border-mist bg-fog px-4 py-3">
                <p className="text-xs uppercase tracking-[0.12em] text-ink-soft/50">
                    {t('console.common.preview')}
                </p>
                <p className="mt-1 truncate text-sm font-medium text-ink">
                    {subject || t('console.common.subject')}
                </p>
            </div>
            <iframe
                title={t('console.common.preview')}
                sandbox=""
                srcDoc={srcDoc}
                className={`${iframeClassName} w-full bg-paper`}
                style={{ colorScheme: isDark ? 'dark' : 'light' }}
            />
        </div>
    );
}
