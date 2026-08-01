import { expect, test } from '@playwright/test';
import { loginAsOwner } from './helpers/auth';
import { E2E_PASSWORD, uniqueEmail } from './helpers/env';
import { clearMailbox, extractHref, waitForEmail } from './helpers/mailpit';

test.describe('console invitations', () => {
    test.beforeEach(async () => {
        await clearMailbox();
    });

    test('owner invites, invitee verifies and accepts', async ({ page, browser }) => {
        const inviteeEmail = uniqueEmail('e2e-invitee');
        const orgId = await loginAsOwner(page);

        await page.goto(`/console/${orgId}/members`);
        await expect(page.getByRole('heading', { name: 'Members', exact: true })).toBeVisible();

        await page.getByRole('button', { name: /^invite$/i }).click();
        await page.getByLabel(/^email$/i).fill(inviteeEmail);
        await page.getByRole('button', { name: /send invite/i }).click();
        await expect(page.getByText(inviteeEmail)).toBeVisible({ timeout: 15_000 });

        const inviteMail = await waitForEmail(inviteeEmail, { subjectIncludes: 'invited' });
        const inviteUrl = extractHref(inviteMail.HTML, '/console/invites/');

        // Fresh context so the invitee is not authenticated as the owner.
        const inviteeContext = await browser.newContext();
        const inviteePage = await inviteeContext.newPage();
        await inviteePage.goto(inviteUrl);
        await expect(inviteePage.getByRole('heading', { name: /organization invite/i })).toBeVisible();
        await inviteePage.getByRole('link', { name: /create account/i }).click();

        await expect(inviteePage).toHaveURL(/\/console\/register/);
        await expect(inviteePage.getByLabel(/^email$/i)).toHaveValue(inviteeEmail);

        await inviteePage.getByLabel(/^name$/i).fill('E2E Invitee');
        await inviteePage.locator('input[type="password"]').nth(0).fill(E2E_PASSWORD);
        await inviteePage.locator('input[type="password"]').nth(1).fill(E2E_PASSWORD);
        await inviteePage
            .locator('label')
            .filter({ has: inviteePage.locator('input[type="checkbox"]') })
            .click();
        await inviteePage.getByRole('button', { name: /create account/i }).click();

        const verifyMail = await waitForEmail(inviteeEmail, { subjectIncludes: 'Verify' });
        const verifyUrl = extractHref(verifyMail.HTML, '/console/verify-email');
        await inviteePage.goto(verifyUrl);

        await inviteePage.waitForURL(/\/console\/(invites\/|$)/, { timeout: 20_000 });
        if (!inviteePage.url().includes('/invites/')) {
            await inviteePage.goto(inviteUrl);
        }

        await expect(
            inviteePage.getByRole('button', { name: /accept invitation/i }),
        ).toBeVisible({ timeout: 20_000 });
        await inviteePage.getByRole('button', { name: /accept invitation/i }).click();

        await expect(inviteePage).toHaveURL(new RegExp(`/console/${orgId}`), { timeout: 20_000 });
        await inviteeContext.close();

        await page.goto(`/console/${orgId}/members`);
        await expect(page.getByText(inviteeEmail).first()).toBeVisible();
        await expect(page.getByRole('heading', { name: /invitation history/i })).toBeVisible();
        await expect(page.getByText(/accepted/i).first()).toBeVisible();
    });
});
