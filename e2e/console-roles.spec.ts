import { expect, test } from '@playwright/test';
import { loginAsOwner } from './helpers/auth';

test.describe('console roles', () => {
    test('owner can create a custom role', async ({ page }) => {
        const orgId = await loginAsOwner(page);
        const roleName = `E2E Role ${Date.now().toString(36)}`;

        await page.goto(`/console/${orgId}/roles`);
        await expect(page.getByRole('heading', { name: /roles/i })).toBeVisible();

        await page.getByRole('button', { name: /new custom role|create role/i }).first().click();
        await page.getByLabel(/^name$/i).fill(roleName);

        const firstPermission = page.locator('input[type="checkbox"]').first();
        if (await firstPermission.isVisible().catch(() => false)) {
            await firstPermission.check();
        }

        await page.getByRole('button', { name: /create role|save role|create|save/i }).last().click();
        await expect(page.getByText(roleName).first()).toBeVisible({ timeout: 20_000 });
    });
});
