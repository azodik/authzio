const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export function isOrganizationId(value: string | undefined): boolean {
    return typeof value === 'string' && UUID_RE.test(value);
}

/**
 * Paths that should send guests to /login instead of the console 404 page.
 */
export function isProtectedConsolePath(pathname: string): boolean {
    const path = pathname.replace(/\/+$/, '') || '/';
    if (
        path === '/' ||
        path === '/onboarding' ||
        path === '/organizations' ||
        path === '/settings'
    ) {
        return true;
    }

    const first = path.split('/').filter(Boolean)[0];
    return isOrganizationId(first);
}
