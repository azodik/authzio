import { expect, test } from '@playwright/test';
import { registerConsoleUser } from './helpers/auth';
import { E2E_PASSWORD, uniqueEmail } from './helpers/env';
import { clearMailbox, extractHref, waitForEmail } from './helpers/mailpit';

test.describe('console onboarding', () => {
    test.beforeEach(async () => {
        await clearMailbox();
    });

    test('new user can create an organization via onboarding', async ({ page }) => {
        const email = uniqueEmail('e2e-onboard');
        const orgName = `Onboard ${Date.now().toString(36)}`;
        const slug = `onboard-${Date.now().toString(36)}`;

        await registerConsoleUser(page, { name: 'E2E Onboard', email, password: E2E_PASSWORD });
        const verifyMail = await waitForEmail(email, { subjectIncludes: 'Verify' });
        await page.goto(extractHref(verifyMail.HTML, '/console/verify-email'));

        await page.goto('/console/onboarding');
        await expect(
            page.getByRole('heading', { name: /organization|get started|create/i }).first(),
        ).toBeVisible({ timeout: 20_000 });

        await page.getByLabel(/organization name|^name$/i).fill(orgName);
        const slugInput = page.getByLabel(/^(url )?slug$/i);
        if (await slugInput.isVisible().catch(() => false)) {
            await slugInput.fill(slug);
        }

        await page.getByRole('button', { name: /create organization|create/i }).click();
        await expect(page).toHaveURL(/\/console\/[0-9a-f-]{20,}/i, { timeout: 30_000 });
        await expect(page.getByText(orgName).first()).toBeVisible({ timeout: 20_000 });
    });
});
