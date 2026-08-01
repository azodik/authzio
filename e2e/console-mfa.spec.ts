import { expect, test } from '@playwright/test';
import { logoutConsole, registerConsoleUser } from './helpers/auth';
import { E2E_PASSWORD, uniqueEmail } from './helpers/env';
import { clearMailbox, extractHref, waitForEmail } from './helpers/mailpit';
import { generateTotp } from './helpers/totp';

test.describe('console MFA', () => {
    test.beforeEach(async () => {
        await clearMailbox();
    });

    test('operator can enroll authenticator and pass MFA challenge on next login', async ({
        page,
    }) => {
        const email = uniqueEmail('e2e-mfa');
        const orgName = `MFA Org ${Date.now().toString(36)}`;

        await registerConsoleUser(page, {
            name: 'E2E MFA',
            email,
            password: E2E_PASSWORD,
        });
        const verifyMail = await waitForEmail(email, { subjectIncludes: 'Verify' });
        await page.goto(extractHref(verifyMail.HTML, '/console/verify-email'));
        await expect(page.getByRole('button', { name: /sign out/i })).toBeVisible({
            timeout: 20_000,
        });

        await page.goto('/console/onboarding');
        await page.getByLabel(/organization name|^name$/i).fill(orgName);
        await page.getByRole('button', { name: /create organization|create/i }).click();
        await expect(page).toHaveURL(/\/console\/[0-9a-f-]{20,}/i, { timeout: 30_000 });

        await page.goto('/console/settings');
        await expect(page.getByRole('heading', { name: /settings|account/i }).first()).toBeVisible();
        await page.getByRole('button', { name: /enable authenticator/i }).click();
        await expect(page.getByText(/enter this secret manually/i)).toBeVisible({
            timeout: 15_000,
        });

        const secret = (await page.locator('code.font-mono').innerText()).trim();
        const setupCode = generateTotp(secret);
        await page.getByLabel(/confirm with a 6-digit code|6-digit/i).fill(setupCode);
        await page.getByRole('button', { name: /confirm & enable/i }).click();
        await expect(page.getByText(/enabled|recovery/i).first()).toBeVisible({
            timeout: 15_000,
        });

        await logoutConsole(page);

        await page.goto('/console/login');
        await page.getByLabel(/^email$/i).fill(email);
        await page.locator('input[type="password"]').fill(E2E_PASSWORD);
        await page.getByRole('button', { name: /sign in/i }).click();

        await expect(page).toHaveURL(/\/console\/mfa/, { timeout: 20_000 });
        await expect(page.getByRole('heading', { name: /authenticator|mfa|verification/i })).toBeVisible();

        const challengeCode = generateTotp(secret);
        await page.getByLabel(/^code$/i).fill(challengeCode);
        await page.getByRole('button', { name: /^continue$/i }).click();

        await expect(page.getByRole('button', { name: /sign out/i })).toBeVisible({
            timeout: 30_000,
        });
    });
});
