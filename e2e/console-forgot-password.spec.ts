import { expect, test } from '@playwright/test';
import { registerConsoleUser } from './helpers/auth';
import { E2E_PASSWORD, uniqueEmail } from './helpers/env';
import { clearMailbox, extractHref, waitForEmail } from './helpers/mailpit';

test.describe('console password reset', () => {
    test.beforeEach(async () => {
        await clearMailbox();
    });

    test('forgot password page loads and accepts email', async ({ page }) => {
        await page.goto('/console/forgot-password');
        await expect(page.getByRole('heading', { name: /forgot|reset/i })).toBeVisible();
        await page.getByLabel(/^email$/i).fill('e2e-owner@authzio.test');
        await page.getByRole('button', { name: /send|reset|continue/i }).click();
        await expect(page.getByText(/sent|check your email|if an account/i).first()).toBeVisible({
            timeout: 15_000,
        });
    });

    test('reset-password without token shows recovery guidance', async ({ page }) => {
        await page.goto('/console/reset-password');
        await expect(page.getByText(/missing a token|request a new/i)).toBeVisible();
        await expect(page.getByRole('link', { name: /request reset/i })).toBeVisible();
    });

    test('full reset via Mailpit then login with new password', async ({ page }) => {
        const email = uniqueEmail('e2e-pwreset');
        const newPassword = `E2eNew${Date.now().toString(36)}!`;

        await registerConsoleUser(page, {
            name: 'E2E PW Reset',
            email,
            password: E2E_PASSWORD,
        });
        const verifyMail = await waitForEmail(email, { subjectIncludes: 'Verify' });
        await page.goto(extractHref(verifyMail.HTML, '/console/verify-email'));
        await expect(page.getByRole('button', { name: /sign out/i })).toBeVisible({
            timeout: 20_000,
        });
        await page.getByRole('button', { name: /sign out/i }).click();
        await page.waitForURL(/\/console\/login/);

        await clearMailbox();
        await page.goto('/console/forgot-password');
        await page.getByLabel(/^email$/i).fill(email);
        await page.getByRole('button', { name: /send reset link|send|reset/i }).click();
        await expect(page.getByText(/if an account exists|sent/i).first()).toBeVisible({
            timeout: 15_000,
        });

        const resetMail = await waitForEmail(email, { subjectIncludes: 'reset' });
        await page.goto(extractHref(resetMail.HTML, '/console/reset-password'));
        await expect(page.getByRole('heading', { name: /reset password/i })).toBeVisible();

        await page.getByLabel(/^email$/i).fill(email);
        await page.locator('input[type="password"]').nth(0).fill(newPassword);
        await page.locator('input[type="password"]').nth(1).fill(newPassword);
        await page.getByRole('button', { name: /reset password/i }).click();

        await page.waitForURL(/\/console\/login/, { timeout: 20_000 });
        await page.getByLabel(/^email$/i).fill(email);
        await page.locator('input[type="password"]').fill(newPassword);
        await page.getByRole('button', { name: /sign in/i }).click();
        await expect(page.getByRole('button', { name: /sign out/i })).toBeVisible({
            timeout: 30_000,
        });
    });
});
