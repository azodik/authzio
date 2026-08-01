import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Building2 } from 'lucide-react';
import { type FormEvent, useEffect, useRef, useState } from 'react';
import { Navigate, useNavigate } from 'react-router';
import { useI18n } from '@/hooks/useI18n';
import { apiPost } from '@/lib/api';
import { orgPath } from '@/lib/paths';
import { queryKeys } from '@/lib/queryKeys';
import { toOrgSlug } from '@/lib/slug';
import { toastError, toastSuccess } from '@/lib/toast';
import type { Organization } from '@/types';
import { useWorkspace } from '@/workspace/WorkspaceContext';

export function OnboardingPage() {
    const navigate = useNavigate();
    const { setOrganizationId, refresh, organizations, loading, domainRoot } = useWorkspace();
    const { t } = useI18n();
    const [name, setName] = useState('');
    const [slug, setSlug] = useState('');
    const [slugTouched, setSlugTouched] = useState(false);
    const nameRef = useRef<HTMLInputElement>(null);
    const queryClient = useQueryClient();
    const createOrganization = useMutation({
        mutationFn: (payload: { name: string; slug: string }) =>
            apiPost<{ data: Organization }>('/api/v1/organizations', payload),
        onError: (error) => toastError(error, 'Could not create organization.'),
    });

    useEffect(() => {
        nameRef.current?.focus();
    }, []);

    useEffect(() => {
        if (!slugTouched) {
            setSlug(toOrgSlug(name));
        }
    }, [name, slugTouched]);

    if (!loading && organizations.length > 0) {
        return <Navigate to={orgPath(organizations[0]!.id)} replace />;
    }

    async function onSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
        event.preventDefault();
        const normalizedSlug = toOrgSlug(slug);
        if (normalizedSlug.length < 2) {
            toastError(new Error(t('console.page.organizations.slug_invalid')));
            return;
        }

        try {
            const response = await createOrganization.mutateAsync({
                name: name.trim(),
                slug: normalizedSlug,
            });
            await queryClient.invalidateQueries({ queryKey: queryKeys.account.organizations() });
            setOrganizationId(response.data.id);
            await refresh();
            toastSuccess('Organization created.');
            navigate(orgPath(response.data.id), { replace: true });
        } catch {
            // Mutation reports the error.
        }
    }

    return (
        <div className="mx-auto max-w-lg py-8">
            <div className="mb-8 text-center">
                <span className="mx-auto mb-4 inline-flex size-12 items-center justify-center rounded-lg bg-teal/10 text-teal">
                    <Building2 className="size-6" strokeWidth={1.75} />
                </span>
                <h1 className="font-display text-2xl font-bold tracking-tight text-ink">
                    {t('console.auth.onboarding_title')}
                </h1>
                <p className="mt-2 text-sm leading-relaxed text-ink-soft/65">
                    {t('console.auth.onboarding_desc')}
                </p>
            </div>

            <form
                onSubmit={onSubmit}
                className="space-y-4 border border-mist bg-paper-elevated p-6 sm:p-8"
            >
                <label className="block">
                    <span className="text-sm font-medium text-ink">
                        {t('console.page.organizations.name_label')}
                    </span>
                    <input
                        ref={nameRef}
                        required
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        placeholder="Acme Inc"
                        className="mt-1.5 w-full border border-mist bg-paper px-3 py-2.5 text-sm outline-none focus:border-teal"
                    />
                </label>

                <label className="block">
                    <span className="text-sm font-medium text-ink">{t('console.common.slug')}</span>
                    <input
                        required
                        value={slug}
                        onChange={(event) => {
                            setSlugTouched(true);
                            setSlug(toOrgSlug(event.target.value));
                        }}
                        placeholder="acme"
                        pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                        minLength={2}
                        maxLength={63}
                        className="mt-1.5 w-full border border-mist bg-paper px-3 py-2.5 font-mono text-sm outline-none focus:border-teal"
                    />
                    <span className="mt-1.5 block text-xs text-ink-soft/55">
                        {t('console.page.organizations.slug_hint', {
                            host: `${slug || 'acme'}.${domainRoot}`,
                        })}
                    </span>
                </label>

                <button
                    type="submit"
                    disabled={createOrganization.isPending || name.trim() === '' || slug.length < 2}
                    className="w-full bg-teal px-4 py-2.5 text-sm font-semibold text-paper hover:bg-teal-bright disabled:opacity-60"
                >
                    {createOrganization.isPending
                        ? t('console.common.creating')
                        : t('console.switcher.create_org')}
                </button>
            </form>
        </div>
    );
}
