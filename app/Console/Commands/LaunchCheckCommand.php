<?php

namespace App\Console\Commands;

use App\Models\BillingPlan;
use App\Models\DodoWebhook;
use Illuminate\Console\Command;

class LaunchCheckCommand extends Command
{
    protected $signature = 'authzio:launch-check';

    protected $description = 'Verify production launch readiness for billing, MFA, and runtime config';

    public function handle(): int
    {
        $failed = 0;

        $checks = [
            'APP_KEY set' => filled(config('app.key')),
            'APP_ENV is production (or staging)' => in_array(app()->environment(), ['production', 'staging'], true)
                || $this->confirmOptionalEnv(),
            'Billing enabled' => (bool) config('billing.enabled', false),
            'Dodo API key configured' => filled(config('billing.dodo.api_key')),
            'Dodo webhook secret configured' => filled(DodoWebhook::activeSecret()),
            'Queue not sync in production' => ! app()->environment('production')
                || config('queue.default') !== 'sync',
            'Session driver not cookie/array in production' => ! app()->environment('production')
                || ! in_array(config('session.driver'), ['cookie', 'array'], true),
            'MFA globally enabled' => (bool) config('authzio.mfa.enabled', true),
            'Paid plans mapped to Dodo products' => $this->paidPlansMapped(),
        ];

        $this->info('Authzio launch checklist');
        $this->newLine();

        foreach ($checks as $label => $ok) {
            if ($ok) {
                $this->line("<fg=green>PASS</>  {$label}");
            } else {
                $this->line("<fg=red>FAIL</>  {$label}");
                $failed++;
            }
        }

        $this->newLine();
        $this->line('Operational reminders (manual):');
        $this->line('  • Run a queue worker: php artisan queue:work');
        $this->line('  • Schedule: php artisan schedule:work (includes billing:apply-cancellations)');
        $this->line('  • Confirm Dodo webhook URL points to /api/v1/webhooks/dodo');
        $this->line('  • Enable authenticator MFA for console admins (Account → Settings)');

        $this->newLine();

        if ($failed > 0) {
            $this->error("{$failed} check(s) failed.");

            return self::FAILURE;
        }

        $this->info('All automated checks passed.');

        return self::SUCCESS;
    }

    private function confirmOptionalEnv(): bool
    {
        // Local/testing runs should not fail the whole checklist.
        return app()->environment(['local', 'testing']);
    }

    private function paidPlansMapped(): bool
    {
        $plans = BillingPlan::query()
            ->where('is_self_serve', true)
            ->where('slug', '!=', 'free')
            ->get(['slug', 'dodo_product_id']);

        if ($plans->isEmpty()) {
            return false;
        }

        return $plans->every(
            static fn (BillingPlan $plan): bool => filled($plan->dodo_product_id),
        );
    }
}
