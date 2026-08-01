<?php

namespace App\Jobs;

use App\Models\OrganizationEmailProvider;
use App\Services\Mail\TransactionalMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SendRenderedMailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 60, 120];

    public function __construct(
        public string $to,
        public string $subject,
        public string $html,
        public ?string $organizationEmailProviderId = null,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        // Sync driver cannot re-run released jobs; overlapping locks flake local/E2E mail.
        if ($this->organizationEmailProviderId === null || config('queue.default') === 'sync') {
            return [];
        }

        return [
            (new WithoutOverlapping('org-mail-'.$this->organizationEmailProviderId))
                ->releaseAfter(10)
                ->expireAfter(120),
        ];
    }

    public function handle(TransactionalMailer $mailer): void
    {
        if ($this->organizationEmailProviderId === null) {
            $mailer->deliverPlatform($this->to, $this->subject, $this->html);

            return;
        }

        $provider = OrganizationEmailProvider::query()->findOrFail($this->organizationEmailProviderId);
        $mailer->deliverViaProvider($provider, $this->to, $this->subject, $this->html);
    }
}
