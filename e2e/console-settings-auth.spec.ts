import { expect, test } from '@playwright/test';
import { loginAsOwner } from './helpers/auth';

test.describe('console settings auth surface', () => {
    test('settings page shows account profile and MFA enrollment entry', async ({ page }) => {
        await loginAsOwner(page);
        await page.goto('/console/settings');

        await expect(page.getByRole('heading', { name: /settings|account/i }).first()).toBeVisible();
        await expect(page.getByRole('main').getByText(/e2e-owner@authzio.test/i)).toBeVisible();
        await expect(page.getByRole('button', { name: /enable authenticator/i })).toBeVisible();
    });
});
