import { expect, test } from '@playwright/test';
import { loginConsoleUser, logoutConsole, registerConsoleUser } from './helpers/auth';
import { E2E_PASSWORD, uniqueEmail } from './helpers/env';
import {
    clearMailbox,
    extractHref,
    extractVerificationCode,
    waitForEmail,
} from './helpers/mailpit';

test.describe('console auth', () => {
    test.beforeEach(async () => {
        await clearMailbox();
    });

    test('register, verify email via Mailpit, and reach console', async ({ page }) => {
        const email = uniqueEmail('e2e-register');

        await registerConsoleUser(page, {
            name: 'E2E Register',
            email,
            password: E2E_PASSWORD,
        });

        await expect(page).toHaveURL(/\/console(\/|$|\?)/);

        const message = await waitForEmail(email, { subjectIncludes: 'Verify' });
        const verifyUrl = extractHref(message.HTML, '/console/verify-email');

        await page.goto(verifyUrl);
        // Verify may land on get-started home (no org yet) or show a verified banner.
        await expect(page.getByRole('button', { name: /sign out/i })).toBeVisible({
            timeout: 20_000,
        });
        await expect(
            page
                .getByText(/email verified|already verified|create your first organization|get started/i)
                .first(),
        ).toBeVisible({ timeout: 20_000 });
    });

    test('login and logout for seeded owner', async ({ page }) => {
        await loginConsoleUser(page, {
            email: 'e2e-owner@authzio.test',
            password: E2E_PASSWORD,
        });

        await expect(page.getByRole('button', { name: /sign out/i })).toBeVisible({
            timeout: 30_000,
        });
        await expect(page).toHaveURL(/\/console\/[0-9a-f]{8}-[0-9a-f]{4}-/i);

        await logoutConsole(page);
        await page.goto('/console/login');
        await expect(page.getByRole('heading', { name: /sign in/i })).toBeVisible();
    });

    test('login rejects invalid credentials', async ({ page }) => {
        await loginConsoleUser(page, {
            email: 'e2e-owner@authzio.test',
            password: 'DefinitelyWrong123!',
        });

        await expect(page.getByRole('alert').or(page.getByText(/invalid|credentials|unable/i))).toBeVisible({
            timeout: 15_000,
        });
        await expect(page).toHaveURL(/\/console\/login/);
    });

    test('login page links to register and forgot password', async ({ page }) => {
        await page.goto('/console/login');
        await expect(page.getByRole('link', { name: /forgot password/i })).toBeVisible();
        await expect(page.getByRole('link', { name: /create account/i })).toBeVisible();
        await page.getByRole('link', { name: /create account/i }).click();
        await expect(page).toHaveURL(/\/console\/register/);
    });

    test('verify-email page accepts a 6-digit code from Mailpit', async ({ page }) => {
        const email = uniqueEmail('e2e-verify-code');

        await registerConsoleUser(page, {
            name: 'E2E Verify Code',
            email,
            password: E2E_PASSWORD,
        });

        const message = await waitForEmail(email, { subjectIncludes: 'Verify' });
        const code = extractVerificationCode(message.HTML);

        await page.goto('/console/verify-email');
        await expect(page.getByRole('heading', { name: /verify email/i })).toBeVisible();
        await page.getByLabel(/code|verification/i).fill(code);
        await page.getByRole('button', { name: /verify code|verify/i }).click();

        await expect(page.getByRole('button', { name: /sign out/i })).toBeVisible({
            timeout: 20_000,
        });
    });
});

