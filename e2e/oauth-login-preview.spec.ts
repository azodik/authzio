import { expect, test } from '@playwright/test';
import { loadE2eFixtures } from './helpers/fixtures';

test.describe('oauth login preview', () => {
    test('preview page renders branded hosted chrome without issuing a code', async ({ page }) => {
        const fixtures = loadE2eFixtures();

        await page.goto(`/preview/login/${fixtures.oauth_client_id}`);
        await expect(page.getByText(/preview/i).first()).toBeVisible();
        await expect(page.getByText(/e2e oauth app/i).first()).toBeVisible();
        await expect(page.getByRole('heading', { name: /sign in/i })).toBeVisible();
        await expect(page).not.toHaveURL(/__oidc_e2e_callback/);
    });
});
