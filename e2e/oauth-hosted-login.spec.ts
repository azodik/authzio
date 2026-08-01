import { expect, test } from '@playwright/test';
import { loadE2eFixtures } from './helpers/fixtures';
import {
    authorizeUrl,
    completeHostedPasswordLogin,
    exchangeAuthorizationCode,
    pkcePair,
    readCallbackCode,
} from './helpers/oauth';

test.describe('oauth hosted login', () => {
    test('password authorize returns a code and exchanges for tokens', async ({
        page,
        request,
    }) => {
        const fixtures = loadE2eFixtures();
        const { verifier, challenge } = pkcePair();
        const state = 'e2e-oauth-state';

        await page.goto(authorizeUrl(fixtures, { challenge, state }));
        await expect(page.getByRole('heading', { name: /sign in/i })).toBeVisible();
        await expect(page.getByText(/e2e oauth app/i).first()).toBeVisible();

        await completeHostedPasswordLogin(page, {
            email: fixtures.oidc_user_email,
            password: fixtures.oidc_user_password,
        });

        const { code, state: returnedState } = await readCallbackCode(page);
        expect(returnedState).toBe(state);

        const tokens = await exchangeAuthorizationCode(request, { code, verifier, fixtures });
        expect(tokens.id_token).toBeTruthy();
        expect(tokens.refresh_token).toBeTruthy();

        const userinfo = await request.get('/api/oauth/userinfo', {
            headers: { Authorization: `Bearer ${tokens.access_token}` },
        });
        expect(userinfo.ok()).toBeTruthy();
        const profile = (await userinfo.json()) as { email?: string; sub?: string };
        expect(profile.email).toBe(fixtures.oidc_user_email);
        expect(profile.sub).toBeTruthy();

        const revoke = await request.post('/api/oauth/revoke', {
            form: {
                token: tokens.access_token,
                client_id: fixtures.oauth_client_id,
            },
        });
        expect(revoke.ok()).toBeTruthy();
    });

    test('wrong password shows an error and stays on authorize', async ({ page }) => {
        const fixtures = loadE2eFixtures();
        const { challenge } = pkcePair();

        await page.goto(authorizeUrl(fixtures, { challenge }));
        await completeHostedPasswordLogin(page, {
            email: fixtures.oidc_user_email,
            password: 'WrongPassword123!',
        });

        await expect(page.getByRole('alert').or(page.locator('.alert.error'))).toBeVisible({
            timeout: 15_000,
        });
        await expect(page).toHaveURL(/\/oauth\/authorize/);
    });

    test('forgot password link is available from hosted login', async ({ page }) => {
        const fixtures = loadE2eFixtures();
        const { challenge } = pkcePair();

        await page.goto(authorizeUrl(fixtures, { challenge }));
        await page.getByRole('link', { name: /forgot password/i }).click();
        await expect(page).toHaveURL(/\/oauth\/forgot-password/);
        await expect(page.getByRole('heading', { name: /forgot password/i })).toBeVisible();
    });
});
