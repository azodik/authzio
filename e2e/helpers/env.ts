export const E2E_OWNER_EMAIL = 'e2e-owner@authzio.test';
export const E2E_OWNER_PASSWORD = 'E2eTestPass123!';
export const E2E_PASSWORD = 'E2eTestPass123!';

export const mailpitApiBase = (): string =>
    (process.env.MAILPIT_URL ?? 'http://127.0.0.1:8025').replace(/\/$/, '');

export function uniqueEmail(prefix: string): string {
    const stamp = Date.now().toString(36);
    const rand = Math.random().toString(36).slice(2, 8);
    return `${prefix}-${stamp}-${rand}@authzio.test`;
}
