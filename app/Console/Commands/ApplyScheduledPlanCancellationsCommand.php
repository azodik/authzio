<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingService;
use Illuminate\Console\Command;

class ApplyScheduledPlanCancellationsCommand extends Command
{
    protected $signature = 'billing:apply-cancellations';

    protected $description = 'Move organizations to Free when a scheduled plan cancellation period has ended';

    public function handle(BillingService $billing): int
    {
        $applied = $billing->applyDueCancellations();

        $this->info("Applied {$applied} scheduled cancellation(s).");

        return self::SUCCESS;
    }
}
