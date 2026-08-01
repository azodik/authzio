import { expect, test } from '@playwright/test';
import { loginAsOwner } from './helpers/auth';

test.describe('console applications', () => {
    test('owner can create a web application', async ({ page }) => {
        const orgId = await loginAsOwner(page);
        const appName = `E2E App ${Date.now().toString(36)}`;

        await page.goto(`/console/${orgId}/applications`);
        await expect(page.getByRole('heading', { name: /applications/i })).toBeVisible();

        await page.getByRole('button', { name: /new application/i }).first().click();

        await page.getByLabel(/^name$/i).fill(appName);

        const redirect = page.getByLabel(/redirect/i).first();
        if (await redirect.isVisible().catch(() => false)) {
            await redirect.fill('http://127.0.0.1:8000/__oidc_e2e_callback');
        }

        await page.getByRole('button', { name: /create application/i }).click();

        await expect(page.getByText(appName).first()).toBeVisible({ timeout: 20_000 });
    });
});
