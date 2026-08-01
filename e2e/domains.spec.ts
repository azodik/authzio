import { expect, test } from '@playwright/test';
import { loginAsOwner } from './helpers/auth';

/**
 * Custom domain UI only — does not call Cloudflare.
 * Full SaaS provisioning stays in Feature tests / manual ops.
 */
test.describe('custom domains', () => {
    test('domains page shows subdomain and custom domain UI', async ({ page }) => {
        const orgId = await loginAsOwner(page);

        await page.goto(`/console/${orgId}/domains`);
        await expect(page.getByRole('heading', { name: /domains/i })).toBeVisible();
        await expect(page.getByText(/custom domain|hostname|dns|subdomain/i).first()).toBeVisible();
    });
});
