import { expect, test } from '@playwright/test';
import { loginAsOwner } from './helpers/auth';

test.describe('console application detail', () => {
    test('owner can open app settings and OIDC page', async ({ page }) => {
        const orgId = await loginAsOwner(page);
        const appName = `E2E Detail ${Date.now().toString(36)}`;

        await page.goto(`/console/${orgId}/applications`);
        await page.getByRole('button', { name: /new application/i }).first().click();
        await page.getByLabel(/^name$/i).fill(appName);
        await page.getByRole('button', { name: /create application/i }).click();

        // Create navigates into the app settings page.
        await expect(page.getByRole('heading', { level: 1, name: appName })).toBeVisible({
            timeout: 20_000,
        });
        await expect(page).toHaveURL(new RegExp(`/console/${orgId}/[0-9a-f-]{20,}`));

        await page.getByRole('link', { name: /oidc/i }).click();
        await expect(page).toHaveURL(/\/oidc$/);
        await expect(page.getByText(/openid|discovery|jwks|authorize/i).first()).toBeVisible({
            timeout: 20_000,
        });
    });
});
