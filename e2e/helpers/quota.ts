import type { APIRequestContext } from '@playwright/test';
import { loadE2eFixtures } from './fixtures';

async function postJson(
    request: APIRequestContext,
    path: string,
    body: Record<string, string | number>,
): Promise<void> {
    const response = await request.post(path, { data: body });
    if (!response.ok()) {
        throw new Error(`${path} failed: ${response.status()} ${await response.text()}`);
    }
}

export async function prepareQuotaAlerts(request: APIRequestContext): Promise<string> {
    const fixtures = loadE2eFixtures();
    await postJson(request, '/__e2e/quota/prepare', {
        organization_id: fixtures.organization_id,
    });
    return fixtures.organization_id;
}

export async function seedMauCount(request: APIRequestContext, count: number): Promise<void> {
    const fixtures = loadE2eFixtures();
    await postJson(request, '/__e2e/quota/seed-mau', {
        organization_id: fixtures.organization_id,
        count,
    });
}

export async function seedApplicationCount(request: APIRequestContext, count: number): Promise<void> {
    const fixtures = loadE2eFixtures();
    await postJson(request, '/__e2e/quota/seed-applications', {
        organization_id: fixtures.organization_id,
        count,
    });
}

export async function seedEmailDailyCount(request: APIRequestContext, count: number): Promise<void> {
    const fixtures = loadE2eFixtures();
    await postJson(request, '/__e2e/quota/seed-email-daily', {
        organization_id: fixtures.organization_id,
        count,
    });
}
