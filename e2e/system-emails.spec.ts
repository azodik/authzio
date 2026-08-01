import { expect, test } from '@playwright/test';
import { clearMailbox, countEmails, waitForEmail } from './helpers/mailpit';

const TEMPLATES: Array<{ slug: string; subjectIncludes: string; bodyIncludes: string }> = [
    { slug: 'welcome', subjectIncludes: 'Welcome', bodyIncludes: 'Welcome aboard' },
    { slug: 'invite_member', subjectIncludes: 'invited', bodyIncludes: 'invited you' },
    { slug: 'magic_link', subjectIncludes: 'sign-in link', bodyIncludes: 'Sign in' },
    { slug: 'password_reset', subjectIncludes: 'Reset', bodyIncludes: 'reset your' },
    { slug: 'password_changed', subjectIncludes: 'password was changed', bodyIncludes: 'password was changed' },
    { slug: 'email_verification', subjectIncludes: 'Verify', bodyIncludes: '123456' },
    { slug: 'mfa_code', subjectIncludes: 'security code', bodyIncludes: '654321' },
    { slug: 'email_otp', subjectIncludes: 'verification code', bodyIncludes: '111222' },
    { slug: 'plan_upgraded', subjectIncludes: 'upgraded', bodyIncludes: 'Starter' },
    { slug: 'plan_downgraded', subjectIncludes: 'plan was changed', bodyIncludes: 'Starter' },
    { slug: 'plan_cancelled', subjectIncludes: 'cancelled', bodyIncludes: 'cancelled' },
    { slug: 'mau_warning', subjectIncludes: '80%', bodyIncludes: 'monthly active users' },
    { slug: 'mau_limit_reached', subjectIncludes: 'MAU limit reached', bodyIncludes: 'MAU limit reached' },
    { slug: 'application_warning', subjectIncludes: 'application limit', bodyIncludes: 'applications' },
    {
        slug: 'application_limit_reached',
        subjectIncludes: 'Application limit reached',
        bodyIncludes: 'Application limit reached',
    },
    { slug: 'email_usage_warning', subjectIncludes: 'email send limit', bodyIncludes: 'daily platform email' },
    {
        slug: 'email_usage_limit_reached',
        subjectIncludes: 'Email send limit reached',
        bodyIncludes: 'Email send limit reached',
    },
];

const LOCALES = ['en', 'de', 'es', 'fr', 'hi'] as const;

test.describe('all system emails', () => {
    test('sends every platform template for every locale to Mailpit', async ({ request }) => {
        // Sets epoch for wait/count; with E2E_KEEP_MAILPIT=1 does not delete Mailpit.
        await clearMailbox();

        for (const locale of LOCALES) {
            const to = `e2e-system-mail-${locale}@authzio.test`;
            const response = await request.post('/__e2e/system-mail/send-all', {
                data: { to, locale },
            });
            expect(response.ok(), `${locale}: ${await response.text()}`).toBeTruthy();

            const payload = (await response.json()) as {
                data: { to: string; locale: string; sent: Array<{ slug: string }> };
            };
            expect(payload.data.locale).toBe(locale);
            expect(payload.data.sent).toHaveLength(TEMPLATES.length);

            const sample = await waitForEmail(to, {
                bodyIncludes: `lang="${locale}"`,
            });
            expect(sample.HTML).toContain(`lang="${locale}"`);
            expect(await countEmails(to)).toBeGreaterThanOrEqual(TEMPLATES.length);
        }

        // Full English assertion pass (subjects + bodies).
        for (const template of TEMPLATES) {
            const message = await waitForEmail('e2e-system-mail-en@authzio.test', {
                subjectIncludes: template.subjectIncludes,
                bodyIncludes: template.bodyIncludes,
            });
            expect(message.HTML, template.slug).toContain('lang="en"');
        }
    });
});
