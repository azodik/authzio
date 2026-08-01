import { expect, test } from '@playwright/test';
import { loginAsOwner } from './helpers/auth';

test.describe('console usage and quotas', () => {
    test('billing shows MAU usage and email provider shows send quotas', async ({ page }) => {
        const orgId = await loginAsOwner(page);

        await page.goto(`/console/${orgId}/billing`);
        await expect(page.getByRole('heading', { name: /^billing$/i })).toBeVisible();
        await expect(page.getByText(/% of plan MAU used/i)).toBeVisible();
        await expect(page.getByText(/last 30 days/i)).toBeVisible();

        await page.goto(`/console/${orgId}/email-provider`);
        await expect(page.getByRole('heading', { name: /email delivery|email provider/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /^usage$/i })).toBeVisible();
        // Seeded counters; limits may be absent on paid plans after billing E2E mutates the org.
        await expect(page.getByText('42', { exact: true }).first()).toBeVisible();
        await expect(page.getByText('420', { exact: true }).first()).toBeVisible();
    });
});
