import { expect, test } from '@playwright/test';

test.describe('marketing', () => {
    test('home page loads', async ({ page }) => {
        const response = await page.goto('/');
        expect(response?.ok()).toBeTruthy();
        await expect(page.getByRole('heading').first()).toBeVisible();
    });

    test('docs page loads', async ({ page }) => {
        const response = await page.goto('/docs');
        expect(response?.ok()).toBeTruthy();
        await expect(page.getByRole('heading', { name: /documentation|authzio/i }).first()).toBeVisible();
    });

    test('demo page links to console login with demo=1', async ({ page }) => {
        await page.goto('/demo');
        const loginLink = page.getByRole('link', { name: /open console login/i });
        await expect(loginLink).toBeVisible();
        await expect(loginLink).toHaveAttribute('href', /\/console\/login\?demo=1/);

        await loginLink.click();
        await expect(page).toHaveURL(/\/console\/login\?demo=1/);
        await expect(page.getByLabel(/^email$/i)).toHaveValue('demo@authzio.com');
        await expect(page.getByLabel(/^password$/i)).toHaveValue('AuthzioDemo2026!');
    });
});
