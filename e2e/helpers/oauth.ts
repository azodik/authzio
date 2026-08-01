import { createHash, randomBytes } from 'node:crypto';
import type { APIRequestContext, Page } from '@playwright/test';
import { expect } from '@playwright/test';
import type { E2eFixtures } from './fixtures';
import { loadE2eFixtures } from './fixtures';

export type PkcePair = {
    verifier: string;
    challenge: string;
};

export function pkcePair(): PkcePair {
    const verifier = randomBytes(32).toString('base64url');
    const challenge = createHash('sha256').update(verifier).digest('base64url');

    return { verifier, challenge };
}

export function authorizeUrl(
    fixtures: E2eFixtures,
    options: {
        challenge: string;
        state?: string;
        nonce?: string;
        scope?: string;
    },
): string {
    const params = new URLSearchParams({
        client_id: fixtures.oauth_client_id,
        redirect_uri: fixtures.oauth_redirect_uri,
        response_type: 'code',
        scope: options.scope ?? 'openid profile email offline_access',
        state: options.state ?? 'e2e-oauth-state',
        nonce: options.nonce ?? 'e2e-nonce',
        code_challenge: options.challenge,
        code_challenge_method: 'S256',
    });

    return `/oauth/authorize?${params.toString()}`;
}

/** Rewrite issuer-host reset links from email to the local Playwright base URL. */
export function toLocalAppUrl(absoluteUrl: string, baseURL = 'http://127.0.0.1:8000'): string {
    const remote = new URL(absoluteUrl);
    const local = new URL(baseURL);
    local.pathname = remote.pathname;
    local.search = remote.search;
    local.hash = remote.hash;

    return local.toString();
}

export async function completeHostedPasswordLogin(
    page: Page,
    options: { email: string; password: string },
): Promise<void> {
    const panel = page.locator('[data-panel="password"]');
    await panel.locator('input[name="email"]').fill(options.email);
    await panel.locator('input[name="password"]').fill(options.password);
    await panel.getByRole('button', { name: /^continue$/i }).click();
}

export async function readCallbackCode(page: Page): Promise<{ code: string; state: string }> {
    await page.waitForURL(/\/__oidc_e2e_callback/, { timeout: 30_000 });
    const codeText = await page.getByTestId('oidc-code').innerText();
    const stateText = await page.getByTestId('oidc-state').innerText();
    const code = codeText.replace(/^code=/, '');
    const state = stateText.replace(/^state=/, '');
    expect(code.length).toBeGreaterThan(10);

    return { code, state };
}

export async function exchangeAuthorizationCode(
    request: APIRequestContext,
    options: {
        code: string;
        verifier: string;
        fixtures?: E2eFixtures;
    },
): Promise<{
    access_token: string;
    refresh_token?: string;
    id_token?: string;
    token_type: string;
}> {
    const fixtures = options.fixtures ?? loadE2eFixtures();
    const response = await request.post('/api/oauth/token', {
        form: {
            grant_type: 'authorization_code',
            code: options.code,
            redirect_uri: fixtures.oauth_redirect_uri,
            client_id: fixtures.oauth_client_id,
            code_verifier: options.verifier,
        },
    });

    expect(response.ok(), await response.text()).toBeTruthy();
    const body = (await response.json()) as {
        access_token: string;
        refresh_token?: string;
        id_token?: string;
        token_type: string;
    };
    expect(body.access_token).toBeTruthy();
    expect(body.token_type.toLowerCase()).toBe('bearer');

    return body;
}
