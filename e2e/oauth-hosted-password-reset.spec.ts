import { expect, test } from '@playwright/test';
import { loadE2eFixtures } from './helpers/fixtures';
import { clearMailbox, extractHref, waitForEmail } from './helpers/mailpit';
import {
    authorizeUrl,
    completeHostedPasswordLogin,
    pkcePair,
    readCallbackCode,
    toLocalAppUrl,
} from './helpers/oauth';

test.describe('oauth hosted password reset', () => {
    test.beforeEach(async () => {
        await clearMailbox();
    });

    test('end user can reset password via Mailpit and sign in with the new password', async ({
        page,
    }) => {
        const fixtures = loadE2eFixtures();
        const { challenge } = pkcePair();
        const newPassword = `E2eReset${Date.now().toString(36)}!`;

        await page.goto(authorizeUrl(fixtures, { challenge }));
        await page.getByRole('link', { name: /forgot password/i }).click();
        await expect(page.getByRole('heading', { name: /forgot password/i })).toBeVisible();

        await page.locator('input[name="email"]').fill(fixtures.oidc_reset_user_email);
        await page.getByRole('button', { name: /send reset|send|continue/i }).click();
        await expect(page.locator('.alert.ok')).toContainText(/sent password reset/i, {
            timeout: 15_000,
        });

        const message = await waitForEmail(fixtures.oidc_reset_user_email, {
            subjectIncludes: 'reset',
        });
        const remoteResetUrl = extractHref(message.HTML, '/oauth/reset-password');
        await page.goto(toLocalAppUrl(remoteResetUrl));

        await expect(page.getByRole('heading', { name: /new password|reset/i })).toBeVisible();
        await page.locator('input[name="password"]').fill(newPassword);
        await page.locator('input[name="password_confirmation"]').fill(newPassword);
        await page.getByRole('button', { name: /update password/i }).click();

        // After reset, continue through authorize with the new password.
        await page.waitForURL(/\/oauth\/authorize/, { timeout: 20_000 });
        if (!page.url().includes('code_challenge')) {
            await page.goto(authorizeUrl(fixtures, { challenge: pkcePair().challenge }));
        }

        await completeHostedPasswordLogin(page, {
            email: fixtures.oidc_reset_user_email,
            password: newPassword,
        });
        await readCallbackCode(page);
    });
});
