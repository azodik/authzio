import { expect, test } from '@playwright/test';
import { loginAsOwner } from './helpers/auth';
import { uniqueEmail } from './helpers/env';
import { clearMailbox, waitForEmail } from './helpers/mailpit';

test.describe('console members manage', () => {
    test.beforeEach(async () => {
        await clearMailbox();
    });

    test('owner can resend and revoke a pending invitation', async ({ page }) => {
        const orgId = await loginAsOwner(page);
        const inviteeEmail = uniqueEmail('e2e-revoke');

        await page.goto(`/console/${orgId}/members`);
        await page.getByRole('button', { name: /^invite$/i }).click();
        await page.getByLabel(/^email$/i).fill(inviteeEmail);
        await page.getByRole('button', { name: /send invite/i }).click();
        await expect(page.getByText(inviteeEmail)).toBeVisible({ timeout: 15_000 });

        await waitForEmail(inviteeEmail, { subjectIncludes: 'invited' });

        const row = page.locator('tr', { hasText: inviteeEmail });
        await row.getByRole('button', { name: /resend/i }).click();
        await expect(page.getByText(/invitation resent|resent/i)).toBeVisible({ timeout: 15_000 });

        await waitForEmail(inviteeEmail, { subjectIncludes: 'invited' });

        await row.getByRole('button', { name: /revoke/i }).click();
        await expect(page.getByText(/invitation revoked|revoked/i)).toBeVisible({ timeout: 15_000 });

        await expect(page.getByRole('heading', { name: /invitation history/i })).toBeVisible();
        await expect(page.getByText(inviteeEmail)).toBeVisible();
        await expect(page.getByText(/revoked/i).first()).toBeVisible();
    });
});
