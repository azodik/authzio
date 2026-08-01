import { useEffect } from 'react';
import { Navigate, Outlet, useParams } from 'react-router';
import { isOrganizationId } from '../lib/consolePaths';
import { ORG_ROUTE_SEGMENTS } from '../lib/paths';
import { NotFoundPage } from '../routes/NotFoundPage';
import { useWorkspace } from './WorkspaceContext';

/**
 * Keeps workspace selection in sync with `/:orgId` and optional `/:appId` URL segments.
 */
export function WorkspaceUrlSync() {
    const { orgId, appId } = useParams<{ orgId: string; appId?: string }>();
    const {
        organization,
        organizations,
        application,
        applications,
        loading,
        setOrganizationId,
        setApplicationId,
    } = useWorkspace();

    const orgIdValid = orgId !== undefined && isOrganizationId(orgId);
    const resolvedAppId = appId && !ORG_ROUTE_SEGMENTS.has(appId) ? appId : undefined;

    useEffect(() => {
        if (!orgIdValid || !orgId || loading) {
            return;
        }

        if (organization?.id !== orgId) {
            const known = organizations.some((item) => item.id === orgId);
            if (known || organizations.length === 0) {
                setOrganizationId(orgId, { clearApplication: !resolvedAppId });
            }
        }
    }, [
        orgId,
        orgIdValid,
        organization?.id,
        organizations,
        loading,
        setOrganizationId,
        resolvedAppId,
    ]);

    useEffect(() => {
        if (!orgIdValid || loading || !orgId) {
            return;
        }

        if (resolvedAppId) {
            if (application?.id !== resolvedAppId) {
                const known = applications.some((item) => item.id === resolvedAppId);
                if (known || applications.length === 0) {
                    setApplicationId(resolvedAppId);
                }
            }
        }

        // Org-level route: keep last selected app in workspace for quick re-entry,
        // but the org sidebar does not treat it as App mode (see WorkspaceSwitchers).
    }, [
        resolvedAppId,
        application?.id,
        applications,
        loading,
        orgId,
        orgIdValid,
        setApplicationId,
    ]);

    if (orgId !== undefined && !orgIdValid) {
        return <NotFoundPage />;
    }

    if (!loading && orgId && organizations.length > 0) {
        const knownOrg = organizations.some((item) => item.id === orgId);
        if (!knownOrg) {
            return <Navigate to={`/${organizations[0]!.id}`} replace />;
        }
    }

    if (
        !loading &&
        resolvedAppId &&
        organization?.id === orgId &&
        applications.length > 0 &&
        !applications.some((item) => item.id === resolvedAppId)
    ) {
        return <Navigate to={`/${orgId}/applications`} replace />;
    }

    return <Outlet />;
}
