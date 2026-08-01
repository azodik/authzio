import { test } from '@playwright/test';

/**
 * Billing E2E always uses real Dodo test mode.
 * Full Free → Starter → Growth → Scale → downgrade → Free lives in billing-dodo-real.spec.ts.
 */
test.describe('console billing', () => {
    test('moved to billing-dodo-real (real Dodo)', async () => {
        test.skip(true, 'Replaced by e2e/billing-dodo-real.spec.ts');
    });
});
