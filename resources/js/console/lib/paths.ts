/** Console paths under basename `/console`. */

export const ACCOUNT_PATHS = {
    organizations: '/organizations',
    settings: '/settings',
} as const;

export function orgPath(orgId: string, segment = ''): string {
    const suffix = segment === '' ? '' : segment.startsWith('/') ? segment : `/${segment}`;
    return `/${orgId}${suffix}`;
}

export function appPath(orgId: string, appId: string, segment = ''): string {
    const suffix = segment === '' ? '' : segment.startsWith('/') ? segment : `/${segment}`;
    return `/${orgId}/${appId}${suffix}`;
}

/** Route segments reserved for org-level pages (not treated as application IDs). */
export const ORG_ROUTE_SEGMENTS = new Set([
    'applications',
    'members',
    'roles',
    'domains',
    'email-templates',
    'email-provider',
    'social-providers',
    'sso',
    'billing',
    'users',
    'audit-logs',
]);

/** Top-level account routes (Global mode). */
export const GLOBAL_ROUTE_SEGMENTS = new Set(['organizations', 'settings', 'onboarding']);

export function isGlobalPath(pathname: string): boolean {
    const segment = pathname.replace(/^\//, '').split('/')[0] ?? '';
    return GLOBAL_ROUTE_SEGMENTS.has(segment);
}
