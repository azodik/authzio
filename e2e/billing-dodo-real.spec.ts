import { expect, test } from '@playwright/test';
import { loginAsOwner, orgIdFromUrl, registerConsoleUser } from './helpers/auth';
import {
    completeDodoHostedPayment,
    confirmUpgradeDialog,
    fetchDodoStatus,
    syncDodoBilling,
    upgradeToPlan,
    waitForPlanCurrent,
} from './helpers/dodoCheckout';
import { E2E_PASSWORD, uniqueEmail } from './helpers/env';
import { clearMailbox, extractHref, waitForEmail } from './helpers/mailpit';

/**
 * Real Dodo Payments test-mode billing (DODO_* in .env).
 */
test.describe('real Dodo billing', () => {
    test.describe.configure({ timeout: 300_000 });

    test('upgrade, premium (Scale), downgrade, and switch to Free', async ({ page, request }) => {
        const status = await fetchDodoStatus(request);
        test.skip(
            !status.configured || !status.sync_enabled,
            'Set DODO_PAYMENTS_API_KEY + DODO_PRODUCT_* and APP_ENV=e2e (php artisan authzio:e2e-prepare)',
        );

        await clearMailbox();

        const email = uniqueEmail('e2e-dodo');
        const orgName = `Dodo ${Date.now().toString(36)}`;
        const slug = `dodo-${Date.now().toString(36)}`;

        await registerConsoleUser(page, { name: 'E2E Dodo Owner', email, password: E2E_PASSWORD });
        const verifyMail = await waitForEmail(email, { subjectIncludes: 'Verify' });
        await page.goto(extractHref(verifyMail.HTML, '/console/verify-email'));
        await expect(page.getByRole('button', { name: /sign out/i })).toBeVisible({ timeout: 30_000 });

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
        const orgId = orgIdFromUrl(page.url());

        await page.goto(`/console/${orgId}/billing`);
        await expect(page.getByRole('heading', { name: /^billing$/i })).toBeVisible();
        await expect(page.getByText(/^Free$/).first()).toBeVisible();

        // 1) Free → Starter (hosted checkout)
        await upgradeToPlan(page, /^Starter/);
        const starterCheckout = await completeDodoHostedPayment(page);
        await syncDodoBilling(request, orgId, {
            subscriptionId: starterCheckout.subscriptionId,
            paymentId: starterCheckout.paymentId,
        });
        await page.goto(`/console/${orgId}/billing`);
        await waitForPlanCurrent(page, /^Starter/);

        // 2) Starter → Growth
        await upgradeToPlan(page, /^Growth/);
        await confirmUpgradeDialog(page);
        if (/dodopayments\.com/i.test(page.url())) {
            const growthPay = await completeDodoHostedPayment(page);
            await syncDodoBilling(request, orgId, {
                subscriptionId: growthPay.subscriptionId,
                paymentId: growthPay.paymentId,
            });
        } else {
            await syncDodoBilling(request, orgId);
        }
        await page.goto(`/console/${orgId}/billing`);
        await waitForPlanCurrent(page, /^Growth/);

        // 3) Growth → Scale (premium)
        await upgradeToPlan(page, /^Scale/);
        await confirmUpgradeDialog(page);
        if (/dodopayments\.com/i.test(page.url())) {
            const scalePay = await completeDodoHostedPayment(page);
            await syncDodoBilling(request, orgId, {
                subscriptionId: scalePay.subscriptionId,
                paymentId: scalePay.paymentId,
            });
        } else {
            await syncDodoBilling(request, orgId);
        }
        await page.goto(`/console/${orgId}/billing`);
        await waitForPlanCurrent(page, /^Scale/);

        // 4) Scale → Starter (scheduled downgrade)
        const starterCard = page.locator('article').filter({ hasText: /^Starter/ });
        await starterCard.getByRole('button', { name: /switch plan/i }).click();
        await expect(page.getByRole('heading', { name: /switch to starter/i })).toBeVisible();
        await page.getByRole('button', { name: /schedule switch/i }).click();
        await expect(page.getByText(/starter is scheduled|scheduled for your next billing/i)).toBeVisible({
            timeout: 30_000,
        });
        await expect(starterCard.getByText(/starts /i)).toBeVisible();
        await expect(page.locator('article').filter({ hasText: /^Scale/ }).getByText(/current/i)).toBeVisible();

        // 5) Switch to Free (cancel at period end — paid plan remains current)
        const freeCard = page.locator('article').filter({ hasText: /^Free$/ });
        await freeCard.getByRole('button', { name: /switch to free/i }).click();
        await page.getByRole('button', { name: /confirm switch to free/i }).click();
        await expect(page.getByText(/cancellation scheduled/i)).toBeVisible({ timeout: 30_000 });
        await expect(page.locator('article').filter({ hasText: /^Scale/ }).getByText(/current/i)).toBeVisible();

        await syncDodoBilling(request, orgId);
        await page.goto(`/console/${orgId}/billing`);
        await expect(page.locator('article').filter({ hasText: /^Scale/ }).getByText(/current/i)).toBeVisible();
    });

    test('seeded owner can open billing when Dodo is configured', async ({ page, request }) => {
        const status = await fetchDodoStatus(request);
        test.skip(!status.configured, 'Real Dodo credentials required in .env');

        const orgId = await loginAsOwner(page);
        await page.goto(`/console/${orgId}/billing`);
        await expect(page.getByRole('heading', { name: /^billing$/i })).toBeVisible();
        await expect(page.locator('article').filter({ hasText: /^Starter/ })).toBeVisible();
    });
});
