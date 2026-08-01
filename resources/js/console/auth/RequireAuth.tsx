import { Navigate, Outlet, useLocation } from 'react-router';
import { useAuth } from '../auth/AuthContext';
import { setPendingAuthRedirect } from '../lib/authRedirect';
import { isProtectedConsolePath } from '../lib/consolePaths';
import { NotFoundPage } from '../routes/NotFoundPage';

export function RequireAuth() {
    const { user, loading } = useAuth();
    const location = useLocation();

    if (loading) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-paper text-sm text-ink-soft/60">
                Loading console…
            </div>
        );
    }

    if (!user) {
        if (!isProtectedConsolePath(location.pathname)) {
            return <NotFoundPage standalone />;
        }

        const redirect = `${location.pathname}${location.search}`;
        if (redirect !== '/' && !redirect.startsWith('/login')) {
            setPendingAuthRedirect(redirect);
        }

        return <Navigate to="/login" replace />;
    }

    return <Outlet />;
}
