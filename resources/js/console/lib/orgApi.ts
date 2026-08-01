export function orgApiPath(orgId: string, segment = ''): string {
    const suffix = segment === '' ? '' : segment.startsWith('/') ? segment : `/${segment}`;
    return `/api/v1/organizations/${orgId}${suffix}`;
}
