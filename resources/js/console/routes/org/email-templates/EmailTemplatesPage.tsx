import { type FormEvent, useEffect, useState } from 'react';
import { Link } from 'react-router';
import { EmailPreviewPane } from '@/components/EmailPreviewPane';
import { EmailTemplateEditor } from '@/components/EmailTemplateEditor';
import { EmptyState } from '@/components/EmptyState';
import { PageHeader } from '@/components/PageHeader';
import {
    useEmailTemplatePreviewMutation,
    useEmailTemplatesQuery,
    useSaveEmailTemplateMutation,
} from '@/hooks/queries/emailTemplates';
import { useActiveOrganization } from '@/hooks/useActiveOrganization';
import { useI18n } from '@/hooks/useI18n';
import { useWorkspacePaths } from '@/hooks/useWorkspacePaths';
import { emailTemplateLabel } from '@/lib/emailTemplateLabel';
import { toastError } from '@/lib/toast';
import type { EmailTemplatePreview } from '@/types';

export function EmailTemplatesPage() {
    const organization = useActiveOrganization();
    const paths = useWorkspacePaths();
    const { t } = useI18n();
    const orgId = organization?.id;

    const templatesQuery = useEmailTemplatesQuery(orgId);
    const saveMutation = useSaveEmailTemplateMutation(orgId ?? '');
    const previewMutation = useEmailTemplatePreviewMutation(orgId ?? '');

    const templates = templatesQuery.data?.data ?? [];
    const variables = templatesQuery.data?.variables ?? {};
    const previews = templatesQuery.data?.previews ?? {};
    const canEdit = templatesQuery.data?.entitlements.allows_email_customization ?? false;

    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [subject, setSubject] = useState('');
    const [bodyHtml, setBodyHtml] = useState('');
    const [livePreview, setLivePreview] = useState<EmailTemplatePreview | null>(null);

    const selected = templates.find((template) => template.id === selectedId) ?? null;

    useEffect(() => {
        if (templatesQuery.isError) {
            toastError(templatesQuery.error, 'Failed to load templates.');
        }
    }, [templatesQuery.isError, templatesQuery.error]);

    useEffect(() => {
        if (selectedId === null && templates[0]) {
            setSelectedId(templates[0].id);
        }
    }, [templates, selectedId]);

    useEffect(() => {
        if (!selected) {
            return;
        }
        setSubject(selected.subject);
        setBodyHtml(selected.body_html);
        setLivePreview(previews[selected.id] ?? null);
    }, [selected, previews]);

    useEffect(() => {
        if (!selected || !canEdit || !orgId) {
            return;
        }

        const timer = window.setTimeout(() => {
            previewMutation.mutate(
                {
                    templateId: selected.id,
                    subject,
                    body_html: bodyHtml,
                },
                {
                    onSuccess: (response) => setLivePreview(response.data),
                },
            );
        }, 400);

        return () => window.clearTimeout(timer);
    }, [selected, subject, bodyHtml, canEdit, orgId, previewMutation.mutate]);

    async function onSave(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        if (!selected || !canEdit || !orgId) {
            return;
        }

        const response = await saveMutation.mutateAsync({
            templateId: selected.id,
            subject: subject.trim(),
            body_html: bodyHtml,
        });
        setLivePreview(response.preview);
    }

    if (!organization) {
        return (
            <EmptyState
                title={t('console.common.need_org_title')}
                description={t('console.page.email_templates.need_org_description')}
            />
        );
    }

    return (
        <div>
            <PageHeader
                title={t('console.page.email_templates.title')}
                description={t('console.page.email_templates.description')}
            />

            {!canEdit && templatesQuery.isSuccess && (
                <p className="mb-4 border border-mist bg-fog px-4 py-3 text-sm text-ink-soft/70">
                    {t('console.page.email_templates.paid_banner')}{' '}
                    <Link to={paths.billing} className="text-teal hover:text-teal-deep">
                        {t('console.common.upgrade')}
                    </Link>
                </p>
            )}

            <div className="grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)_minmax(280px,360px)]">
                <aside className="border border-mist bg-paper-elevated">
                    <ul>
                        {templates.map((template) => (
                            <li key={template.id} className="border-b border-fog last:border-0">
                                <button
                                    type="button"
                                    onClick={() => setSelectedId(template.id)}
                                    className={[
                                        'w-full px-4 py-3 text-left text-sm transition-colors',
                                        selectedId === template.id
                                            ? 'bg-fog font-medium text-ink'
                                            : 'text-ink-soft/70 hover:bg-fog/60 hover:text-ink',
                                    ].join(' ')}
                                >
                                    {emailTemplateLabel(template.slug, template.name, t)}
                                </button>
                            </li>
                        ))}
                    </ul>
                </aside>

                {selected === null ? (
                    <EmptyState
                        title={t('console.page.email_templates.empty_title')}
                        description={t('console.page.email_templates.empty_description')}
                    />
                ) : (
                    <form onSubmit={onSave} className="border border-mist bg-paper-elevated p-6">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h2 className="font-display text-lg font-semibold text-ink">
                                    {emailTemplateLabel(selected.slug, selected.name, t)}
                                </h2>
                                <p className="mt-1 font-mono text-xs text-ink-soft/50">
                                    {selected.slug}
                                </p>
                            </div>
                        </div>

                        {variables[selected.slug] !== undefined && (
                            <p className="mt-4 text-xs text-ink-soft/55">
                                {t('console.page.email_templates.variables')}{' '}
                                {variables[selected.slug]
                                    .map((variable) => `{{${variable}}}`)
                                    .join(' · ')}
                            </p>
                        )}

                        <label className="mt-6 block text-sm">
                            <span className="mb-1.5 block font-medium text-ink">
                                {t('console.common.subject')}
                            </span>
                            <input
                                required
                                disabled={!canEdit}
                                value={subject}
                                onChange={(event) => setSubject(event.target.value)}
                                className="w-full border border-mist bg-paper px-3 py-2.5 text-ink outline-none focus:border-teal disabled:opacity-60"
                            />
                        </label>

                        <div className="mt-4">
                            <span className="mb-1.5 block text-sm font-medium text-ink">
                                {t('console.page.email_templates.html_body')}
                            </span>
                            <EmailTemplateEditor
                                value={bodyHtml}
                                disabled={!canEdit}
                                onChange={setBodyHtml}
                            />
                        </div>

                        {canEdit && (
                            <button
                                type="submit"
                                disabled={saveMutation.isPending}
                                className="mt-6 bg-ink px-4 py-2.5 text-sm font-semibold text-paper hover:bg-ink-soft disabled:opacity-60"
                            >
                                {saveMutation.isPending
                                    ? t('console.common.saving')
                                    : t('console.page.email_templates.save')}
                            </button>
                        )}
                    </form>
                )}

                <div>
                    {livePreview !== null ? (
                        <EmailPreviewPane
                            subject={livePreview.subject}
                            html={livePreview.html}
                            iframeClassName="h-[min(70vh,44rem)]"
                        />
                    ) : (
                        <div className="flex h-[min(70vh,44rem)] items-center border border-mist bg-paper-elevated p-6 text-sm text-ink-soft/60">
                            {t('console.page.email_templates.select_preview')}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
