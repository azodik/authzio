<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\Billing\BillingNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class CheckMauUsageAlertsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public string $organizationId,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('mau-alerts-'.$this->organizationId))
                ->dontRelease()
                ->expireAfter(60),
        ];
    }

    public function handle(BillingNotifier $notifier): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        $notifier->checkUsageThresholds($organization);
    }
}
