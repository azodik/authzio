import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';
import { E2E_OWNER_EMAIL, E2E_OWNER_PASSWORD, E2E_PASSWORD } from './env';
import { loadE2eFixtures } from './fixtures';

const AUTH_SEGMENTS = new Set([
    'login',
    'register',
    'mfa',
    'forgot-password',
    'reset-password',
    'verify-email',
    'invites',
    'organizations',
    'settings',
    'onboarding',
]);

export async function acceptTerms(page: Page): Promise<void> {
    // Checkbox is visually hidden (sr-only); the wrapping label receives clicks.
    await page.locator('label').filter({ has: page.locator('input[type="checkbox"]') }).click();
}

export async function registerConsoleUser(
    page: Page,
    options: { name: string; email: string; password?: string },
): Promise<void> {
    const password = options.password ?? E2E_PASSWORD;

    await page.goto('/console/register');
    await page.getByLabel(/^name$/i).fill(options.name);
    await page.getByLabel(/^email$/i).fill(options.email);
    await page.locator('input[type="password"]').nth(0).fill(password);
    await page.locator('input[type="password"]').nth(1).fill(password);
    await acceptTerms(page);
    await page.getByRole('button', { name: /create account/i }).click();
}

export async function loginConsoleUser(
    page: Page,
    options: { email: string; password: string },
): Promise<void> {
    await page.goto('/console/login');
    await page.getByLabel(/^email$/i).fill(options.email);
    await page.locator('input[type="password"]').fill(options.password);
    await page.getByRole('button', { name: /sign in/i }).click();
}

export async function loginAsOwner(page: Page): Promise<string> {
    await loginConsoleUser(page, {
        email: E2E_OWNER_EMAIL,
        password: E2E_OWNER_PASSWORD,
    });

    await expect(page.getByRole('button', { name: /sign out/i })).toBeVisible({ timeout: 30_000 });

    // Login may land on /console while org membership hydrates; open the seeded org.
    if (!/\/console\/[0-9a-f]{8}-[0-9a-f]{4}-/i.test(page.url())) {
        const fixtures = loadE2eFixtures();
        await page.goto(`/console/${fixtures.organization_id}`);
    }

    await expect(page).toHaveURL(/\/console\/[0-9a-f]{8}-[0-9a-f]{4}-/i, { timeout: 30_000 });
    await expect(page.getByText(/too many attempts/i)).toHaveCount(0);

    return orgIdFromUrl(page.url());
}

export function orgIdFromUrl(url: string): string {
    const orgId = url.match(/\/console\/([^/?#]+)/)?.[1];
    if (!orgId || AUTH_SEGMENTS.has(orgId) || !/^[0-9a-f-]{20,}$/i.test(orgId)) {
        throw new Error(`Could not parse org id from ${url}`);
    }
    return orgId;
}

export async function logoutConsole(page: Page): Promise<void> {
    await page.getByRole('button', { name: /sign out/i }).click();
    await page.waitForURL(/\/console\/login/);
}
