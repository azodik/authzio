<?php

namespace App\Services\Billing;

use App\Models\BillingPlan;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DodoPaymentsClient
{
    public function isConfigured(): bool
    {
        return filled(config('billing.dodo.api_key'));
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('billing.dodo.base_url'), '/');
    }

    /**
     * @return array{checkout_url: string, session_id: string}
     */
    public function createCheckoutSession(
        Organization $organization,
        BillingPlan $plan,
        User $actor,
    ): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Dodo Payments is not configured. Set DODO_PAYMENTS_API_KEY.');
        }

        if (! filled($plan->dodo_product_id)) {
            throw new RuntimeException("Plan [{$plan->slug}] has no Dodo product ID configured.");
        }

        $payload = [
            'product_cart' => [
                [
                    'product_id' => $plan->dodo_product_id,
                    'quantity' => 1,
                ],
            ],
            'customer' => [
                'email' => $organization->billing_email ?: $actor->email,
                'name' => $actor->name,
            ],
            'return_url' => $this->checkoutReturnUrl($organization),
            'metadata' => [
                'organization_id' => $organization->id,
                'billing_plan_id' => $plan->id,
                'billing_plan_slug' => $plan->slug,
            ],
        ];

        $response = $this->http()->post('/checkouts', $payload);

        if (! $response->successful()) {
            Log::warning('Dodo checkout session failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException('Unable to create Dodo Payments checkout session.');
        }

        /** @var array{checkout_url?: string, url?: string, session_id?: string, id?: string} $data */
        $data = $response->json();

        $checkoutUrl = $data['checkout_url'] ?? $data['url'] ?? null;
        $sessionId = $data['session_id'] ?? $data['id'] ?? null;

        if (! is_string($checkoutUrl) || ! is_string($sessionId)) {
            throw new RuntimeException('Unexpected Dodo Payments checkout response.');
        }

        return [
            'checkout_url' => $checkoutUrl,
            'session_id' => $sessionId,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listProducts(): array
    {
        $items = [];
        $page = 0;

        do {
            $response = $this->http()->get('/products', [
                'page_size' => 100,
                'page_number' => $page,
                'recurring' => 'true',
            ]);

            if (! $response->successful()) {
                throw new RuntimeException(
                    'Unable to list Dodo products: HTTP '.$response->status().' '.$response->body(),
                );
            }

            /** @var array{items?: list<array<string, mixed>>, data?: list<array<string, mixed>>} $body */
            $body = $response->json() ?? [];
            $batch = $body['items'] ?? $body['data'] ?? [];
            if (! is_array($batch) || $batch === []) {
                break;
            }

            foreach ($batch as $product) {
                if (is_array($product)) {
                    $items[] = $product;
                }
            }

            $page++;
        } while (count($batch) >= 100 && $page < 20);

        return $items;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createProduct(array $payload): array
    {
        $response = $this->http()->post('/products', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Unable to create Dodo product: HTTP '.$response->status().' '.$response->body(),
            );
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateProduct(string $productId, array $payload): array
    {
        $response = $this->http()->patch('/products/'.$productId, $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Unable to update Dodo product ['.$productId.']: HTTP '.$response->status().' '.$response->body(),
            );
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProduct(string $productId): array
    {
        $response = $this->http()->get('/products/'.$productId);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Unable to get Dodo product ['.$productId.']: HTTP '.$response->status().' '.$response->body(),
            );
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createWebhook(array $payload): array
    {
        $response = $this->http()->post('/webhooks', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Unable to create Dodo webhook: HTTP '.$response->status().' '.$response->body(),
            );
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $data;
    }

    public function retrieveWebhookSecret(string $webhookId): ?string
    {
        $response = $this->http()->get('/webhooks/'.$webhookId.'/secret');

        if (! $response->successful()) {
            return null;
        }

        /** @var array{secret?: string, key?: string, webhook_secret?: string} $data */
        $data = $response->json() ?? [];
        $secret = $data['secret'] ?? $data['key'] ?? $data['webhook_secret'] ?? null;

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    /**
     * Preview a plan change charge without committing.
     *
     * @param  'prorated_immediately'|'full_immediately'|'difference_immediately'|'do_not_bill'  $prorationBillingMode
     * @param  'immediately'|'next_billing_date'  $effectiveAt
     * @return array<string, mixed>
     */
    public function previewChangePlan(
        string $subscriptionId,
        string $productId,
        string $prorationBillingMode = 'difference_immediately',
        int $quantity = 1,
        string $effectiveAt = 'immediately',
    ): array {
        $payload = [
            'product_id' => $productId,
            'quantity' => max(1, $quantity),
            'proration_billing_mode' => $prorationBillingMode,
            'effective_at' => $effectiveAt,
        ];

        $response = $this->http()->post(
            '/subscriptions/'.$subscriptionId.'/change-plan/preview',
            $payload,
        );

        if (! $response->successful()) {
            Log::warning('Dodo preview change-plan failed', [
                'subscription_id' => $subscriptionId,
                'product_id' => $productId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException('Unable to preview the Dodo Payments plan change.');
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $data;
    }

    /**
     * Change an existing subscription's product (upgrade/downgrade).
     *
     * @param  'prorated_immediately'|'full_immediately'|'difference_immediately'|'do_not_bill'  $prorationBillingMode
     * @param  'immediately'|'next_billing_date'  $effectiveAt
     * @param  'prevent_change'|'apply_change'  $onPaymentFailure
     * @param  array<string, string|int|float|bool>|null  $metadata
     * @return array<string, mixed>
     */
    public function changePlan(
        string $subscriptionId,
        string $productId,
        string $prorationBillingMode = 'difference_immediately',
        int $quantity = 1,
        string $effectiveAt = 'immediately',
        string $onPaymentFailure = 'prevent_change',
        ?array $metadata = null,
    ): array {
        $payload = [
            'product_id' => $productId,
            'quantity' => max(1, $quantity),
            'proration_billing_mode' => $prorationBillingMode,
            'effective_at' => $effectiveAt,
            'on_payment_failure' => $onPaymentFailure,
        ];

        if ($metadata !== null) {
            $payload['metadata'] = $metadata;
        }

        $response = $this->http()->post('/subscriptions/'.$subscriptionId.'/change-plan', $payload);

        if (! $response->successful()) {
            /** @var array{code?: string, message?: string}|null $body */
            $body = $response->json();
            $dodoMessage = is_array($body) && isset($body['message']) && is_string($body['message'])
                ? $body['message']
                : null;
            $dodoCode = is_array($body) && isset($body['code']) && is_string($body['code'])
                ? $body['code']
                : null;

            Log::warning('Dodo change-plan failed', [
                'subscription_id' => $subscriptionId,
                'product_id' => $productId,
                'status' => $response->status(),
                'body' => $body,
            ]);

            if ($dodoCode === 'PENDING_PLAN_CHANGE_EXISTS' || $dodoCode === 'PREVIOUS_PAYMENT_PENDING') {
                throw new RuntimeException(
                    $dodoMessage
                        ?? 'A previous plan-change payment is still processing. Wait a moment, then try again.',
                );
            }

            throw new RuntimeException(
                $dodoMessage ?? 'Unable to change the Dodo Payments subscription plan.',
            );
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubscription(string $subscriptionId): array
    {
        $response = $this->http()->get('/subscriptions/'.$subscriptionId);

        if (! $response->successful()) {
            Log::warning('Dodo get subscription failed', [
                'subscription_id' => $subscriptionId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException('Unable to retrieve the Dodo Payments subscription.');
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayment(string $paymentId): array
    {
        $response = $this->http()->get('/payments/'.$paymentId);

        if (! $response->successful()) {
            Log::warning('Dodo get payment failed', [
                'payment_id' => $paymentId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException('Unable to retrieve the Dodo Payments charge.');
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $data;
    }

    /**
     * Open a hosted payment-method session (used when an off-session upgrade charge needs 3DS / a new card).
     *
     * @return array{payment_link: string, payment_id: string|null, session_id: string}
     */
    public function createPaymentMethodUpdateSession(string $subscriptionId, string $returnUrl): array
    {
        $response = $this->http()->post('/subscriptions/'.$subscriptionId.'/update-payment-method', [
            'type' => 'new',
            'return_url' => $returnUrl,
        ]);

        if (! $response->successful()) {
            Log::warning('Dodo update-payment-method failed', [
                'subscription_id' => $subscriptionId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException('Unable to open a Dodo Payments authentication session.');
        }

        /** @var array{payment_link?: string, payment_id?: string|null, client_secret?: string|null} $data */
        $data = $response->json() ?? [];
        $paymentLink = $data['payment_link'] ?? null;

        if (! is_string($paymentLink) || $paymentLink === '') {
            throw new RuntimeException('Dodo Payments did not return a payment link for authentication.');
        }

        $paymentId = isset($data['payment_id']) && is_string($data['payment_id']) ? $data['payment_id'] : null;

        return [
            'payment_link' => $paymentLink,
            'payment_id' => $paymentId,
            'session_id' => $paymentId ?? ('pm-update-'.substr(hash('sha256', $paymentLink), 0, 12)),
        ];
    }

    /**
     * Resume a subscription that was set to cancel at the next billing date.
     *
     * @return array<string, mixed>
     */
    public function resumeSubscription(string $subscriptionId): array
    {
        $response = $this->http()->patch('/subscriptions/'.$subscriptionId, [
            'cancel_at_next_billing_date' => false,
        ]);

        if (! $response->successful()) {
            Log::warning('Dodo resume subscription failed', [
                'subscription_id' => $subscriptionId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException('Unable to resume the Dodo Payments subscription.');
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $data;
    }

    /**
     * Schedule cancellation at the next billing date (keeps access until then).
     *
     * @return array<string, mixed>
     */
    public function cancelSubscriptionAtPeriodEnd(string $subscriptionId): array
    {
        $response = $this->http()->patch('/subscriptions/'.$subscriptionId, [
            'cancel_at_next_billing_date' => true,
        ]);

        if (! $response->successful()) {
            Log::warning('Dodo cancel-at-period-end failed', [
                'subscription_id' => $subscriptionId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException('Unable to cancel the Dodo Payments subscription.');
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $data;
    }

    /**
     * End a subscription immediately (no further charges; remaining period forfeited).
     *
     * @return array<string, mixed>
     */
    public function cancelSubscriptionNow(string $subscriptionId): array
    {
        $response = $this->http()->patch('/subscriptions/'.$subscriptionId, [
            'status' => 'cancelled',
        ]);

        if (! $response->successful()) {
            Log::warning('Dodo immediate cancel failed', [
                'subscription_id' => $subscriptionId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException('Unable to cancel the Dodo Payments subscription.');
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSubscriptions(?string $customerId = null, ?string $status = null, int $pageSize = 50): array
    {
        $query = [
            'page_size' => min(100, max(1, $pageSize)),
            'page_number' => 0,
        ];

        if (filled($customerId)) {
            $query['customer_id'] = $customerId;
        }

        if (filled($status)) {
            $query['status'] = $status;
        }

        $response = $this->http()->get('/subscriptions', $query);

        if (! $response->successful()) {
            Log::warning('Dodo list subscriptions failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException('Unable to list subscriptions from Dodo Payments.');
        }

        /** @var array{items?: list<array<string, mixed>>, data?: list<array<string, mixed>>} $body */
        $body = $response->json() ?? [];
        $items = $body['items'] ?? $body['data'] ?? [];

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPayments(?string $customerId = null, ?string $subscriptionId = null, int $pageSize = 50): array
    {
        $query = [
            'page_size' => min(100, max(1, $pageSize)),
            'page_number' => 0,
            'status' => 'succeeded',
        ];

        if (filled($customerId)) {
            $query['customer_id'] = $customerId;
        }

        if (filled($subscriptionId)) {
            $query['subscription_id'] = $subscriptionId;
        }

        $response = $this->http()->get('/payments', $query);

        if (! $response->successful()) {
            Log::warning('Dodo list payments failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException('Unable to list invoices from Dodo Payments.');
        }

        /** @var array{items?: list<array<string, mixed>>} $body */
        $body = $response->json() ?? [];
        $items = $body['items'] ?? [];

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    public function downloadInvoicePdf(string $paymentId): string
    {
        $response = $this->http()
            ->accept('application/pdf')
            ->get('/invoices/payments/'.$paymentId);

        if (! $response->successful()) {
            Log::warning('Dodo invoice download failed', [
                'payment_id' => $paymentId,
                'status' => $response->status(),
            ]);

            throw new RuntimeException('Unable to download invoice from Dodo Payments.');
        }

        return $response->body();
    }

    private function http(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Dodo Payments is not configured. Set DODO_PAYMENTS_API_KEY.');
        }

        return Http::withToken((string) config('billing.dodo.api_key'))
            ->acceptJson()
            ->asJson()
            ->baseUrl($this->baseUrl())
            ->timeout(30);
    }

    private function checkoutReturnUrl(Organization $organization): string
    {
        $configured = (string) config('billing.dodo.return_url');
        $template = filled($configured)
            ? $configured
            : rtrim((string) config('app.url'), '/').'/console/{organization_id}/billing';

        $base = str_replace(
            ['{organization_id}', '{organizationId}'],
            $organization->id,
            $template,
        );

        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.'checkout=pending';
    }
}
