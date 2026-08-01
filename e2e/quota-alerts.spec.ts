import { expect, test } from '@playwright/test';
import { E2E_OWNER_EMAIL } from './helpers/env';
import { clearMailbox, countEmails, waitForEmail } from './helpers/mailpit';
import {
    prepareQuotaAlerts,
    seedApplicationCount,
    seedEmailDailyCount,
    seedMauCount,
} from './helpers/quota';

test.describe('quota threshold emails', () => {
    test.beforeEach(async ({ request }) => {
        await clearMailbox();
        await prepareQuotaAlerts(request);
    });

    test('MAU sends emails at 80%, 90%, and 100%', async ({ request }) => {
        await seedMauCount(request, 8);
        const at80 = await waitForEmail(E2E_OWNER_EMAIL, {
            subjectIncludes: '80%',
            bodyIncludes: 'monthly active users',
        });
        expect(at80.HTML).toMatch(/8\s*of\s*10|8<\/strong> of <strong>10/i);
        expect(at80.HTML).toMatch(/Upgrade/i);

        await seedMauCount(request, 9);
        const at90 = await waitForEmail(E2E_OWNER_EMAIL, {
            subjectIncludes: '90%',
            bodyIncludes: '90%',
        });
        expect(at90.HTML).toMatch(/monthly active users/i);

        await seedMauCount(request, 10);
        const at100 = await waitForEmail(E2E_OWNER_EMAIL, {
            subjectIncludes: 'MAU limit reached',
            bodyIncludes: 'MAU limit reached',
        });
        expect(at100.HTML).toMatch(/10\s*\/\s*10|10<\/strong> \/ <strong>10/i);

        expect(await countEmails(E2E_OWNER_EMAIL, { subjectIncludes: '80%' })).toBeGreaterThanOrEqual(1);
        expect(await countEmails(E2E_OWNER_EMAIL, { subjectIncludes: '90%' })).toBeGreaterThanOrEqual(1);
        expect(await countEmails(E2E_OWNER_EMAIL, { subjectIncludes: 'MAU limit reached' })).toBeGreaterThanOrEqual(1);
    });

    test('application quota sends emails at 80%, 90%, and 100%', async ({ request }) => {
        await seedApplicationCount(request, 8);
        const at80 = await waitForEmail(E2E_OWNER_EMAIL, {
            subjectIncludes: 'application limit',
            bodyIncludes: '80%',
        });
        expect(at80.Subject.toLowerCase()).toContain('80%');
        expect(at80.HTML).toMatch(/application/i);
        expect(at80.HTML).toMatch(/Upgrade/i);

        await seedApplicationCount(request, 9);
        const at90 = await waitForEmail(E2E_OWNER_EMAIL, {
            subjectIncludes: '90%',
            bodyIncludes: 'application',
        });
        expect(at90.HTML).toMatch(/90%/i);

        await seedApplicationCount(request, 10);
        const at100 = await waitForEmail(E2E_OWNER_EMAIL, {
            subjectIncludes: 'Application limit reached',
            bodyIncludes: 'Application limit reached',
        });
        expect(at100.HTML).toMatch(/10\s*\/\s*10|10<\/strong> \/ <strong>10/i);
    });

    test('platform email daily quota sends emails at 80%, 90%, and 100%', async ({ request }) => {
        await seedEmailDailyCount(request, 8);
        const at80 = await waitForEmail(E2E_OWNER_EMAIL, {
            subjectIncludes: '80%',
            bodyIncludes: 'daily platform email',
        });
        expect(at80.HTML).toMatch(/email/i);
        expect(at80.HTML).toMatch(/Upgrade/i);

        await seedEmailDailyCount(request, 9);
        const at90 = await waitForEmail(E2E_OWNER_EMAIL, {
            subjectIncludes: '90%',
            bodyIncludes: 'daily platform email',
        });
        expect(at90.HTML).toMatch(/90%/i);

        await seedEmailDailyCount(request, 10);
        const at100 = await waitForEmail(E2E_OWNER_EMAIL, {
            subjectIncludes: 'Email send limit reached',
            bodyIncludes: 'Email send limit reached',
        });
        expect(at100.HTML).toMatch(/10\s*\/\s*10|10<\/strong> \/ <strong>10/i);
    });
});
