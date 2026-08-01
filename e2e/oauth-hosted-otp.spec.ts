import { expect, test } from '@playwright/test';
import { uniqueEmail } from './helpers/env';
import { loadE2eFixtures } from './helpers/fixtures';
import { clearMailbox, extractVerificationCode, waitForEmail } from './helpers/mailpit';
import { authorizeUrl, pkcePair, readCallbackCode } from './helpers/oauth';

test.describe('oauth hosted email OTP', () => {
    test.beforeEach(async () => {
        await clearMailbox();
    });

    test('end user can sign in with email code', async ({ page }) => {
        const fixtures = loadE2eFixtures();
        const email = uniqueEmail('e2e-otp');
        const { challenge } = pkcePair();
        const state = 'e2e-otp-state';

        await page.goto(authorizeUrl(fixtures, { challenge, state }));
        await expect(page.getByRole('heading', { name: /sign in/i })).toBeVisible();

        await page.getByRole('tab', { name: /email code/i }).click();
        const otpPanel = page.locator('[data-panel="otp"]');
        await expect(otpPanel.locator('#otp-email')).toBeVisible();
        await otpPanel.locator('#otp-email').fill(email);
        await otpPanel.getByRole('button', { name: /send code/i }).click();

        await expect(otpPanel.locator('input[name="code"]')).toBeVisible({
            timeout: 20_000,
        });

        const message = await waitForEmail(email, { subjectIncludes: 'code' });
        const code = extractVerificationCode(message.HTML);

        await otpPanel.locator('input[name="code"]').fill(code);
        await otpPanel.getByRole('button', { name: /verify & continue/i }).click();

        const callback = await readCallbackCode(page);
        expect(callback.state).toBe(state);
    });
});
