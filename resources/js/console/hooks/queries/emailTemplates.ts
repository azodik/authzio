import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiGet, apiPost, apiPut } from '@/lib/api';
import { orgApiPath } from '@/lib/orgApi';
import { queryKeys } from '@/lib/queryKeys';
import { toastError, toastSuccess } from '@/lib/toast';
import type { EmailTemplate, EmailTemplatePreview, PlanEntitlements } from '@/types';

export type EmailTemplatesResponse = {
    data: EmailTemplate[];
    variables: Record<string, string[]>;
    entitlements: PlanEntitlements;
    previews: Record<string, EmailTemplatePreview>;
};

export function useEmailTemplatesQuery(orgId: string | undefined) {
    return useQuery({
        queryKey: orgId
            ? queryKeys.org(orgId).emailTemplates()
            : ['org', 'email-templates', 'disabled'],
        enabled: Boolean(orgId),
        queryFn: () => apiGet<EmailTemplatesResponse>(orgApiPath(orgId!, 'email-templates')),
    });
}

export function useEmailTemplatePreviewMutation(orgId: string) {
    return useMutation({
        mutationFn: (input: { templateId: string; subject: string; body_html: string }) =>
            apiPost<{ data: EmailTemplatePreview }>(
                orgApiPath(orgId, `email-templates/${input.templateId}/preview`),
                {
                    subject: input.subject,
                    body_html: input.body_html,
                },
            ),
    });
}

export function useSaveEmailTemplateMutation(orgId: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (input: { templateId: string; subject: string; body_html: string }) =>
            apiPut<{ data: EmailTemplate; preview: EmailTemplatePreview }>(
                orgApiPath(orgId, `email-templates/${input.templateId}`),
                {
                    subject: input.subject,
                    body_html: input.body_html,
                    is_active: true,
                },
            ),
        onSuccess: (response) => {
            queryClient.setQueryData<EmailTemplatesResponse>(
                queryKeys.org(orgId).emailTemplates(),
                (current) => {
                    if (!current) {
                        return current;
                    }
                    return {
                        ...current,
                        data: current.data.map((template) =>
                            template.id === response.data.id ? response.data : template,
                        ),
                        previews: {
                            ...current.previews,
                            [response.data.id]: response.preview,
                        },
                    };
                },
            );
            toastSuccess('Template saved.');
        },
        onError: (error) => toastError(error, 'Failed to save template.'),
    });
}
