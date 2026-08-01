<?php

namespace App\Jobs;

use App\Services\Billing\DodoWebhookProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessDodoWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120, 300];

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $webhookId,
        public string $eventType,
        public array $payload,
        public array $headers = [],
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('dodo-webhook-'.$this->webhookId))
                ->releaseAfter(15)
                ->expireAfter(180),
        ];
    }

    public function handle(DodoWebhookProcessor $processor): void
    {
        $processor->process(
            $this->webhookId,
            $this->eventType,
            $this->payload,
            $this->headers,
        );
    }
}
