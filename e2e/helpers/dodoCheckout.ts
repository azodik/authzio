import type { APIRequestContext, Page } from '@playwright/test';
import { expect } from '@playwright/test';

const TEST_CARD = {
    number: '4242424242424242',
    expiry: '06/32',
    cvv: '123',
};

export type DodoStatus = {
    configured: boolean;
    sync_enabled: boolean;
    products: { starter: boolean; growth: boolean; scale: boolean };
};

export async function fetchDodoStatus(request: APIRequestContext): Promise<DodoStatus> {
    const response = await request.get('/__e2e/dodo/status');
    if (!response.ok()) {
        return {
            configured: false,
            sync_enabled: false,
            products: { starter: false, growth: false, scale: false },
        };
    }

    const payload = (await response.json()) as { data: DodoStatus };
    return payload.data;
}

export async function syncDodoBilling(
    request: APIRequestContext,
    organizationId: string,
    options: { subscriptionId?: string | null; paymentId?: string | null } = {},
): Promise<void> {
    const response = await request.post('/__e2e/dodo/sync', {
        data: {
            organization_id: organizationId,
            subscription_id: options.subscriptionId ?? undefined,
            payment_id: options.paymentId ?? undefined,
        },
    });
    if (!response.ok()) {
        throw new Error(`Dodo sync failed: ${response.status()} ${await response.text()}`);
    }
}

async function fillFirstVisible(
    page: Page,
    selectors: string[],
    value: string,
): Promise<boolean> {
    for (const selector of selectors) {
        const locator = page.locator(selector).first();
        if (await locator.isVisible().catch(() => false)) {
            await locator.fill(value);
            return true;
        }
    }

    for (const frame of page.frames()) {
        for (const selector of selectors) {
            const locator = frame.locator(selector).first();
            if (await locator.isVisible().catch(() => false)) {
                await locator.fill(value);
                return true;
            }
        }
    }

    return false;
}

async function clickPay(page: Page): Promise<void> {
    const candidates = [
        page.getByRole('button', { name: /pay|subscribe|complete|confirm|submit|continue/i }).last(),
        page.locator('button[type="submit"]').last(),
    ];

    for (const candidate of candidates) {
        if (await candidate.isVisible().catch(() => false)) {
            await candidate.click();
            return;
        }
    }

    throw new Error('Could not find Dodo checkout pay/submit button');
}

/**
 * Complete Dodo hosted checkout / payment page with the US Visa test card.
 */
export async function completeDodoHostedPayment(page: Page): Promise<{
    subscriptionId: string | null;
    paymentId: string | null;
}> {
    await page.waitForURL(/dodopayments\.com|checkout/i, { timeout: 60_000 }).catch(() => undefined);

    // Already back on Authzio (e.g. zero-auth or immediate return).
    if (/\/console\//.test(page.url()) && !/dodopayments\.com/i.test(page.url())) {
        return idsFromUrl(page.url());
    }

    const numberFilled = await fillFirstVisible(page, [
        'input[name="cardNumber"]',
        'input[autocomplete="cc-number"]',
        'input[placeholder*="card number" i]',
        'input[placeholder*="Card number" i]',
        'input[id*="cardNumber" i]',
        'input[name="number"]',
    ], TEST_CARD.number);

    if (!numberFilled) {
        // Some Dodo checkouts use a single combined field or already-saved method.
        const anyCard = page.getByLabel(/card number/i).first();
        if (await anyCard.isVisible().catch(() => false)) {
            await anyCard.fill(TEST_CARD.number);
        }
    }

    await fillFirstVisible(page, [
        'input[name="cardExpiry"]',
        'input[autocomplete="cc-exp"]',
        'input[placeholder*="MM" i]',
        'input[name="expiry"]',
        'input[id*="expir" i]',
    ], TEST_CARD.expiry);

    await fillFirstVisible(page, [
        'input[name="cardCvc"]',
        'input[name="cvc"]',
        'input[autocomplete="cc-csc"]',
        'input[placeholder*="CVC" i]',
        'input[placeholder*="CVV" i]',
        'input[id*="cvc" i]',
    ], TEST_CARD.cvv);

    await fillFirstVisible(page, [
        'input[name="cardholderName"]',
        'input[autocomplete="cc-name"]',
        'input[placeholder*="name" i]',
        'input[name="name"]',
    ], 'E2E Dodo Tester');

    await clickPay(page);

    await page.waitForURL(/\/console\//, { timeout: 120_000 });
    return idsFromUrl(page.url());
}

function idsFromUrl(url: string): { subscriptionId: string | null; paymentId: string | null } {
    const parsed = new URL(url);
    return {
        subscriptionId: parsed.searchParams.get('subscription_id'),
        paymentId: parsed.searchParams.get('payment_id'),
    };
}

export async function waitForPlanCurrent(
    page: Page,
    planName: RegExp,
    options: { timeoutMs?: number } = {},
): Promise<void> {
    const timeout = options.timeoutMs ?? 60_000;
    const card = page.locator('article').filter({ hasText: planName });
    await expect(card.getByText(/current/i)).toBeVisible({ timeout });
}

export async function upgradeToPlan(page: Page, planName: RegExp): Promise<void> {
    const card = page.locator('article').filter({ hasText: planName });
    await card.getByRole('button', { name: /^upgrade$/i }).click();
}

export async function confirmUpgradeDialog(page: Page): Promise<void> {
    await expect(page.getByRole('heading', { name: /upgrade to/i })).toBeVisible({ timeout: 15_000 });
    await page.getByRole('button', { name: /confirm and continue/i }).click();
}
