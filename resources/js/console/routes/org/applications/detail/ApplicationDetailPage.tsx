import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, ExternalLink } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router';
import { LoginBoxPreview } from '@/components/LoginBoxPreview';
import { PageHeader } from '@/components/PageHeader';
import { useI18n } from '@/hooks/useI18n';
import { useWorkspacePaths } from '@/hooks/useWorkspacePaths';
import { apiDelete, apiGet, apiPut, apiUpload } from '@/lib/api';
import { orgApiPath } from '@/lib/orgApi';
import { queryKeys } from '@/lib/queryKeys';
import { toastError, toastSuccess } from '@/lib/toast';
import { useWorkspace } from '@/workspace/WorkspaceContext';
import { LoginSection } from './components/ApplicationSections';
import { AuthSection, EmailSection } from './components/AuthEmailSections';
import {
    type ApplicationDraft,
    type ApplicationResponse,
    applicationPayload,
    draftFromResponse,
    type SetApplicationDraft,
} from './components/applicationEditor';
import { PolicySection } from './components/PolicySections';

type Tab = 'login' | 'auth' | 'password' | 'security' | 'legal' | 'email' | 'settings';
const tabs: { id: Tab; label: string }[] = [
    { id: 'login', label: 'Login experience' },
    { id: 'auth', label: 'Authentication' },
    { id: 'password', label: 'Password policy' },
    { id: 'security', label: 'Security' },
    { id: 'legal', label: 'Legal' },
    { id: 'email', label: 'Email preview' },
    { id: 'settings', label: 'Settings' },
];

export function ApplicationDetailPage() {
    const { orgId, appId } = useParams<{ orgId: string; appId: string }>();
    const { setApplicationId, organization } = useWorkspace();
    const paths = useWorkspacePaths();
    const { t } = useI18n();
    const resolvedOrgId = orgId ?? organization?.id;
    const queryClient = useQueryClient();
    const [tab, setTab] = useState<Tab>('login');
    const [draft, setDraft] = useState<ApplicationDraft | null>(null);
    const [selectedEmailId, setSelectedEmailId] = useState<string | null>(null);
    const query = useQuery({
        queryKey:
            resolvedOrgId && appId
                ? queryKeys.org(resolvedOrgId).application(appId)
                : ['application', 'disabled'],
        enabled: Boolean(resolvedOrgId && appId),
        queryFn: () =>
            apiGet<ApplicationResponse>(orgApiPath(resolvedOrgId!, `applications/${appId}`)),
    });
    useEffect(() => {
        if (appId) setApplicationId(appId);
    }, [appId, setApplicationId]);
    useEffect(() => {
        if (!query.data) return;
        setDraft(draftFromResponse(query.data));
        setSelectedEmailId(query.data.email_templates[0]?.id ?? null);
    }, [query.data]);

    const save = useMutation({
        mutationFn: (next: ApplicationDraft) =>
            apiPut<{
                data: ApplicationResponse['data'];
                preview_url: string;
                demo_soft?: boolean;
            }>(orgApiPath(resolvedOrgId!, `applications/${appId}`), applicationPayload(next)),
        onSuccess: (response) => {
            queryClient.setQueryData<ApplicationResponse>(
                queryKeys.org(resolvedOrgId!).application(appId!),
                (current) =>
                    current
                        ? { ...current, data: response.data, preview_url: response.preview_url }
                        : current,
            );
            toastSuccess(
                response.demo_soft
                    ? t('console.demo_soft_saved')
                    : t('console.page.applications.saved'),
            );
        },
        onError: (error) => toastError(error, 'Failed to save application.'),
    });
    const logo = useMutation({
        mutationFn: async ({ action, file }: { action: 'upload' | 'remove'; file?: File }) => {
            const path = orgApiPath(resolvedOrgId!, `applications/${appId}/logo`);
            if (action === 'remove')
                return apiDelete<{ data: ApplicationResponse['data']; preview_url: string }>(path);
            const form = new FormData();
            form.append('logo', file!);
            return apiUpload<{ data: ApplicationResponse['data']; preview_url: string }>(
                path,
                form,
            );
        },
        onSuccess: (response) => {
            setDraft((current) =>
                current ? { ...current, logoUrl: response.data.logo_url ?? '' } : current,
            );
            void queryClient.invalidateQueries({
                queryKey: queryKeys.org(resolvedOrgId!).application(appId!),
            });
            toastSuccess('Logo updated.');
        },
        onError: (error) => toastError(error, 'Failed to update logo.'),
    });
    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        if (draft) save.mutate(draft);
    }
    if (query.isPending || !draft)
        return <p className="text-sm text-ink-soft/60">Loading application…</p>;
    if (query.isError || !query.data)
        return <p className="text-sm text-danger">Failed to load application.</p>;
    const {
        data: client,
        entitlements,
        preview_url: previewUrl,
        email_templates: templates,
    } = query.data;
    const updateDraft: SetApplicationDraft = (value) => {
        setDraft((current) => {
            if (current === null) return current;
            return typeof value === 'function' ? value(current) : value;
        });
    };

    return (
        <div>
            <Link
                to={paths.applications}
                className="mb-4 inline-flex items-center gap-1.5 text-sm text-ink-soft/65"
            >
                <ArrowLeft className="size-4" />
                Applications
            </Link>
            <PageHeader
                title={client.name}
                description={`${client.application_type.toUpperCase()} · Customize hosted login, policies, and live previews for your users.`}
                action={
                    previewUrl ? (
                        <a
                            href={previewUrl}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-2 border border-mist px-3.5 py-2 text-sm"
                        >
                            <ExternalLink className="size-4" />
                            Full-page preview
                        </a>
                    ) : null
                }
            />
            {entitlements.is_free && (
                <p className="mb-4 border border-mist bg-fog px-4 py-3 text-sm">
                    Free plan: custom domains and email template edits require a paid plan.
                </p>
            )}
            <div className="mb-6 flex flex-wrap gap-1 border-b border-mist">
                {tabs.map((item) => (
                    <button
                        type="button"
                        key={item.id}
                        onClick={() => setTab(item.id)}
                        className={`px-3 py-2.5 text-sm ${tab === item.id ? 'border-b-2 border-teal font-medium' : 'text-ink-soft/60'}`}
                    >
                        {item.label}
                    </button>
                ))}
            </div>
            <form
                onSubmit={submit}
                className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(360px,480px)]"
            >
                <div className="space-y-6">
                    {tab === 'login' && (
                        <LoginSection
                            draft={draft}
                            setDraft={updateDraft}
                            uploading={logo.isPending}
                            onUpload={(file) => logo.mutate({ action: 'upload', file })}
                            onRemove={() => logo.mutate({ action: 'remove' })}
                        />
                    )}
                    {tab === 'auth' && (
                        <AuthSection
                            draft={draft}
                            setDraft={updateDraft}
                            socialPath={paths.socialProviders}
                        />
                    )}
                    {(['password', 'security', 'legal', 'settings'] as const).includes(
                        tab as 'password' | 'security' | 'legal' | 'settings',
                    ) && (
                        <PolicySection
                            kind={tab as 'password' | 'security' | 'legal' | 'settings'}
                            draft={draft}
                            setDraft={updateDraft}
                        />
                    )}
                    {tab === 'email' && (
                        <EmailSection
                            templates={templates}
                            selectedId={selectedEmailId}
                            onSelect={setSelectedEmailId}
                            emailPath={paths.emailTemplates}
                        />
                    )}
                    <button
                        type="submit"
                        disabled={save.isPending}
                        className="bg-ink px-4 py-2.5 text-sm font-semibold text-paper disabled:opacity-60"
                    >
                        {save.isPending ? 'Saving…' : 'Save changes'}
                    </button>
                </div>
                <aside className="xl:sticky xl:top-6 xl:self-start">
                    <p className="mb-2 text-xs uppercase text-ink-soft/50">Live login preview</p>
                    <div className="overflow-x-auto border border-mist">
                        <LoginBoxPreview
                            appName={draft.name || client.name}
                            logoUrl={draft.logoUrl}
                            primaryColor={draft.primaryColor}
                            backgroundColor={draft.backgroundColor}
                            headline={draft.headline}
                            description={draft.loginDescription}
                            buttonLabel={draft.buttonLabel}
                            showSignupLink={draft.showSignupLink}
                            showForgotPasswordLink={draft.showForgotPasswordLink}
                            loginLayout={draft.loginLayout}
                            loginTheme={draft.loginTheme}
                            termsUrl={draft.termsUrl}
                            privacyUrl={draft.privacyUrl}
                            requireLegalAccept={draft.requireLegalAccept}
                            passwordPolicy={draft.passwordPolicy}
                            securityPolicy={draft.securityPolicy}
                        />
                    </div>
                </aside>
            </form>
        </div>
    );
}
