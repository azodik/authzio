import { expect, test } from '@playwright/test';
import { loginAsOwner } from './helpers/auth';

/**
 * Smoke every org console surface for the seeded owner — catches blank/500 pages.
 */
test.describe('console org navigation', () => {
    test('owner can open all primary org pages', async ({ page }) => {
        const orgId = await loginAsOwner(page);

        const pages: Array<{ path: string; heading: RegExp }> = [
            { path: '', heading: /e2e org/i },
            { path: '/applications', heading: /^applications$/i },
            { path: '/members', heading: /^members$/i },
            { path: '/roles', heading: /roles/i },
            { path: '/domains', heading: /^domains$/i },
            { path: '/email-templates', heading: /email templates/i },
            { path: '/email-provider', heading: /email delivery|email provider/i },
            { path: '/social-providers', heading: /social providers/i },
            { path: '/sso', heading: /enterprise sso/i },
            { path: '/billing', heading: /^billing$/i },
            { path: '/users', heading: /end-users/i },
            { path: '/audit-logs', heading: /audit logs/i },
        ];

        for (const entry of pages) {
            await page.goto(`/console/${orgId}${entry.path}`);
            await expect(page.getByRole('heading', { name: entry.heading }).first()).toBeVisible({
                timeout: 20_000,
            });
        }

        await page.goto('/console/organizations');
        await expect(page.getByRole('heading', { name: /organizations/i })).toBeVisible();

        await page.goto('/console/settings');
        await expect(page.getByRole('heading', { name: /settings|account/i }).first()).toBeVisible();
    });
});
