/**
 * Lowercase slug for organization / subdomain identifiers.
 */
export function toOrgSlug(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 63);
}
