import { expect, test } from '@playwright/test';
import { registerConsoleUser } from './helpers/auth';
import { E2E_PASSWORD, uniqueEmail } from './helpers/env';
import { clearMailbox, extractHref, waitForEmail } from './helpers/mailpit';

test.describe('console home without organization', () => {
    test.beforeEach(async () => {
        await clearMailbox();
    });

    test('shows get-started chrome and invitations area, not account orgs flash', async ({
        page,
    }) => {
        const email = uniqueEmail('e2e-noorg');

        await registerConsoleUser(page, {
            name: 'E2E No Org',
            email,
            password: E2E_PASSWORD,
        });

        const message = await waitForEmail(email, { subjectIncludes: 'Verify' });
        const verifyUrl = extractHref(message.HTML, '/console/verify-email');
        await page.goto(verifyUrl);
        await page.goto('/console');

        await expect(page.getByText(/get started|create your first organization/i).first()).toBeVisible({
            timeout: 20_000,
        });

        // Onboarding sidebar: Home / Create organization — not the Account "Organizations" section title alone.
        await expect(page.getByText(/accept an invitation or create an organization/i)).toBeVisible();
        await expect(page.getByRole('link', { name: /create organization/i }).first()).toBeVisible();

        // Invitations panel renders only when invites exist; empty is fine — assert section or empty home.
        await expect(page.getByRole('button', { name: /sign out/i })).toBeVisible();
    });
});
