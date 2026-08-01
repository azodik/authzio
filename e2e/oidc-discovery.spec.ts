import { expect, test } from '@playwright/test';
import { loadE2eFixtures } from './helpers/fixtures';

test.describe('OIDC discovery and JWKS', () => {
    test('openid-configuration advertises authorize, token, and jwks endpoints', async ({
        request,
    }) => {
        const response = await request.get('/.well-known/openid-configuration');
        expect(response.ok()).toBeTruthy();

        const discovery = (await response.json()) as {
            issuer: string;
            authorization_endpoint: string;
            token_endpoint: string;
            userinfo_endpoint: string;
            jwks_uri: string;
            revocation_endpoint: string;
            response_types_supported: string[];
            code_challenge_methods_supported?: string[];
        };

        expect(discovery.issuer).toMatch(/^https?:\/\//);
        expect(discovery.authorization_endpoint).toContain('/oauth/authorize');
        expect(discovery.token_endpoint).toContain('/api/oauth/token');
        expect(discovery.userinfo_endpoint).toContain('/api/oauth/userinfo');
        expect(discovery.jwks_uri).toContain('/.well-known/jwks.json');
        expect(discovery.revocation_endpoint).toContain('/api/oauth/revoke');
        expect(discovery.response_types_supported).toContain('code');
        expect(discovery.code_challenge_methods_supported ?? []).toContain('S256');
    });

    test('jwks.json returns at least one signing key', async ({ request }) => {
        const response = await request.get('/.well-known/jwks.json');
        expect(response.ok()).toBeTruthy();

        const jwks = (await response.json()) as {
            keys: Array<{ kty: string; kid?: string; use?: string }>;
        };

        expect(Array.isArray(jwks.keys)).toBeTruthy();
        expect(jwks.keys.length).toBeGreaterThan(0);
        expect(jwks.keys[0]?.kty).toBeTruthy();
    });

    test('console app OIDC page surfaces discovery URLs for the seeded client', async ({
        page,
    }) => {
        const fixtures = loadE2eFixtures();
        await page.goto('/console/login');
        await page.getByLabel(/^email$/i).fill(fixtures.owner_email);
        await page.locator('input[type="password"]').fill('E2eTestPass123!');
        await page.getByRole('button', { name: /sign in/i }).click();
        await expect(page.getByRole('button', { name: /sign out/i })).toBeVisible({
            timeout: 30_000,
        });

        await page.goto(
            `/console/${fixtures.organization_id}/${fixtures.oauth_client_id}/oidc`,
        );
        await expect(page.getByText(/openid|discovery|jwks|authorize/i).first()).toBeVisible({
            timeout: 20_000,
        });
        await expect(page.getByText(/\.well-known\/openid-configuration/i).first()).toBeVisible();
    });
});
