import { Building2, Plus } from 'lucide-react';
import { Link, Navigate } from 'react-router';
import { EmptyState } from '@/components/EmptyState';
import { MyInvitationsPanel } from '@/components/MyInvitationsPanel';
import { useI18n } from '@/hooks/useI18n';
import { useWorkspace } from '@/workspace/WorkspaceContext';

/**
 * `/console` → last/active organization, or invites + create-org when none exist.
 */
export function WorkspaceHomeRedirect() {
    const { organization, organizations, loading } = useWorkspace();
    const { t } = useI18n();

    if (loading) {
        return (
            <div className="flex min-h-[40vh] items-center justify-center text-sm text-ink-soft/55">
                Loading workspace…
            </div>
        );
    }

    const target = organization ?? organizations[0] ?? null;
    if (target) {
        return <Navigate to={`/${target.id}`} replace />;
    }

    return (
        <div>
            <MyInvitationsPanel variant="banner" />
            <EmptyState
                icon={Building2}
                title={t('console.page.organizations.first_title')}
                description={t('console.page.organizations.first_description')}
                action={
                    <Link
                        to="/onboarding"
                        className="inline-flex items-center gap-2 bg-teal px-4 py-2.5 text-sm font-semibold text-paper hover:bg-teal-bright"
                    >
                        <Plus className="size-4" strokeWidth={2} />
                        {t('console.switcher.create_org')}
                    </Link>
                }
            />
        </div>
    );
}
