import { defineConfig, devices } from '@playwright/test';

/**
 * Local browser E2E only — not run in GitHub Actions.
 * CI runs PHPUnit (unit + Feature). Prepare with:
 *   docker compose -f docker-compose.e2e.yml up -d
 *   php artisan authzio:e2e-prepare
 *   npm run test:e2e
 */
const e2eBaseURL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000';

export default defineConfig({
    testDir: './e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: 0,
    workers: 1,
    reporter: [['list'], ['html', { open: 'never' }]],
    timeout: 90_000,
    expect: { timeout: 15_000 },
    use: {
        ...devices['Desktop Chrome'],
        baseURL: e2eBaseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    webServer: process.env.PLAYWRIGHT_SKIP_WEBSERVER
        ? undefined
        : {
              command: 'php artisan serve --host=127.0.0.1 --port=8000',
              url: e2eBaseURL,
              reuseExistingServer: true,
              timeout: 120_000,
          },
});
