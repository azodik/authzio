<?php

namespace App\Support\E2e;

use App\Models\BillingPlan;
use App\Models\Organization;
use App\Services\Billing\DodoPaymentsClient;
use App\Services\Billing\DodoWebhookProcessor;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Local Playwright helper: pull real Dodo test-mode subscription/payment state
 * and apply it through DodoWebhookProcessor (no public webhook tunnel required).
 */
final class E2eDodoSync
{
    public static function enabled(): bool
    {
        return E2eLocal::enabled();
    }

    /**
     * @return array{
     *     configured: bool,
     *     sync_enabled: bool,
     *     products: array{starter: bool, growth: bool, scale: bool}
     * }
     */
    public static function status(): array
    {
        $apiKey = (string) config('billing.dodo.api_key');
        $configured = $apiKey !== ''
            && filled(config('billing.dodo.products.starter'))
            && filled(config('billing.dodo.products.growth'))
            && filled(config('billing.dodo.products.scale'));

        return [
            'configured' => $configured,
            'sync_enabled' => self::enabled(),
            'products' => [
                'starter' => filled(config('billing.dodo.products.starter')),
                'growth' => filled(config('billing.dodo.products.growth')),
                'scale' => filled(config('billing.dodo.products.scale')),
            ],
        ];
    }

    /**
     * @return array{
     *     organization_id: string,
     *     applied: list<string>,
     *     plan_slug: string|null,
     *     dodo_subscription_id: string|null,
     *     pending_plan_slug: string|null,
     *     cancel_at_period_end: bool
     * }
     */
    public static function sync(
        Organization $organization,
        ?string $subscriptionId = null,
        ?string $paymentId = null,
    ): array {
        abort_unless(self::enabled(), 404);

        $dodo = app(DodoPaymentsClient::class);
        abort_unless($dodo->isConfigured(), 422, 'Dodo Payments is not configured.');

        $organization->loadMissing('subscription.plan');
        $subscription = $organization->subscription;
        abort_if($subscription === null, 422, 'Organization has no subscription row.');

        $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];
        $applied = [];
        $processor = app(DodoWebhookProcessor::class);

        $paymentId = $paymentId
            ?: (isset($metadata['pending_payment_id']) && is_string($metadata['pending_payment_id'])
                ? $metadata['pending_payment_id']
                : null);

        $pendingUpgrade = ($metadata['pending_plan_kind'] ?? null) === 'upgrade'
            && (bool) ($metadata['pending_requires_payment'] ?? false);

        if (is_string($paymentId) && $paymentId !== '' && $pendingUpgrade) {
            $payment = $dodo->getPayment($paymentId);
            $status = strtolower((string) ($payment['status'] ?? ''));

            if (in_array($status, ['succeeded', 'success', 'paid'], true)) {
                $processor->process('wh_e2e_sync_pay_'.Str::uuid()->toString(), 'payment.succeeded', [
                    'data' => self::paymentPayload($organization, $payment, $paymentId, $metadata),
                ]);
                $applied[] = 'payment.succeeded';
            }
        }

        $organization->refresh()->loadMissing('subscription.plan');
        $subscription = $organization->subscription;
        abort_if($subscription === null, 422, 'Organization subscription missing after payment sync.');
        $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];

        $subscriptionId = $subscriptionId
            ?: (is_string($subscription->dodo_subscription_id) ? $subscription->dodo_subscription_id : null);

        if ((! is_string($subscriptionId) || $subscriptionId === '')
            && filled($subscription->dodo_customer_id)) {
            $items = $dodo->listSubscriptions((string) $subscription->dodo_customer_id, 'active');
            $subscriptionId = self::pickSubscriptionId($items, $metadata);
        }

        if (is_string($subscriptionId) && $subscriptionId !== '') {
            $remote = $dodo->getSubscription($subscriptionId);
            $remoteStatus = strtolower((string) ($remote['status'] ?? ''));
            $cancelAtPeriodEnd = (bool) Arr::get($remote, 'cancel_at_next_billing_date', false);

            $payload = self::subscriptionPayload($organization, $remote, $subscriptionId, $metadata);

            if (in_array($remoteStatus, ['active', 'trialing'], true)) {
                $localPlanSlug = $subscription->plan?->slug;
                $remotePlan = self::planFromRemote($remote);
                $needsActivate = $subscription->dodo_subscription_id !== $subscriptionId
                    || ($remotePlan !== null && $localPlanSlug === 'free')
                    || ($remotePlan !== null && $localPlanSlug !== $remotePlan->slug
                        && ($metadata['pending_plan_kind'] ?? null) !== 'upgrade');

                if ($needsActivate && ! $pendingUpgrade) {
                    $event = $cancelAtPeriodEnd ? 'subscription.plan_changed' : 'subscription.active';
                    $processor->process('wh_e2e_sync_sub_'.Str::uuid()->toString(), $event, [
                        'data' => $payload,
                    ]);
                    $applied[] = $event;
                } elseif ($cancelAtPeriodEnd && ! (bool) ($metadata['cancel_at_period_end'] ?? false)) {
                    $processor->process('wh_e2e_sync_cancel_'.Str::uuid()->toString(), 'subscription.plan_changed', [
                        'data' => $payload,
                    ]);
                    $applied[] = 'subscription.plan_changed';
                }
            }
        }

        $organization->refresh()->loadMissing('subscription.plan');
        $fresh = $organization->subscription;
        $freshMeta = is_array($fresh?->metadata) ? $fresh->metadata : [];

        return [
            'organization_id' => $organization->id,
            'applied' => $applied,
            'plan_slug' => $fresh?->plan?->slug,
            'dodo_subscription_id' => $fresh?->dodo_subscription_id,
            'pending_plan_slug' => isset($freshMeta['pending_plan_slug']) && is_string($freshMeta['pending_plan_slug'])
                ? $freshMeta['pending_plan_slug']
                : null,
            'cancel_at_period_end' => (bool) ($freshMeta['cancel_at_period_end'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $payment
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private static function paymentPayload(
        Organization $organization,
        array $payment,
        string $paymentId,
        array $metadata,
    ): array {
        $paymentMeta = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];

        return array_merge($payment, [
            'id' => $payment['id'] ?? $paymentId,
            'payment_id' => $payment['payment_id'] ?? $paymentId,
            'status' => $payment['status'] ?? 'succeeded',
            'metadata' => array_merge($paymentMeta, [
                'organization_id' => $organization->id,
                'billing_plan_slug' => $paymentMeta['billing_plan_slug']
                    ?? $metadata['pending_plan_slug']
                    ?? null,
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $remote
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private static function subscriptionPayload(
        Organization $organization,
        array $remote,
        string $subscriptionId,
        array $metadata,
    ): array {
        $remoteMeta = is_array($remote['metadata'] ?? null) ? $remote['metadata'] : [];
        $plan = self::planFromRemote($remote);
        $pendingPlanId = $metadata['pending_plan_id'] ?? null;
        $pendingPlan = is_string($pendingPlanId)
            ? BillingPlan::query()->find($pendingPlanId)
            : null;

        return array_merge($remote, [
            'id' => $remote['id'] ?? $subscriptionId,
            'subscription_id' => $remote['subscription_id'] ?? $subscriptionId,
            'customer_id' => $remote['customer_id'] ?? Arr::get($remote, 'customer.customer_id'),
            'product_id' => $remote['product_id'] ?? null,
            'cancel_at_next_billing_date' => (bool) Arr::get($remote, 'cancel_at_next_billing_date', false),
            'metadata' => array_merge($remoteMeta, [
                'organization_id' => $organization->id,
                'billing_plan_id' => $remoteMeta['billing_plan_id']
                    ?? $plan?->id
                    ?? $pendingPlan?->id,
                'billing_plan_slug' => $remoteMeta['billing_plan_slug']
                    ?? $plan?->slug
                    ?? $pendingPlan?->slug
                    ?? $metadata['pending_plan_slug']
                    ?? null,
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private static function planFromRemote(array $remote): ?BillingPlan
    {
        $productId = $remote['product_id'] ?? null;
        if (! is_string($productId) || $productId === '') {
            return null;
        }

        return BillingPlan::query()->where('dodo_product_id', $productId)->first();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $metadata
     */
    private static function pickSubscriptionId(array $items, array $metadata): ?string
    {
        $pendingPlanId = $metadata['pending_plan_id'] ?? null;
        $pendingPlan = is_string($pendingPlanId)
            ? BillingPlan::query()->find($pendingPlanId)
            : null;
        $wantedProduct = $pendingPlan?->dodo_product_id;

        foreach ($items as $item) {
            $id = $item['subscription_id'] ?? $item['id'] ?? null;
            if (! is_string($id) || $id === '') {
                continue;
            }

            if (is_string($wantedProduct) && ($item['product_id'] ?? null) === $wantedProduct) {
                return $id;
            }
        }

        $first = $items[0] ?? null;
        $id = is_array($first) ? ($first['subscription_id'] ?? $first['id'] ?? null) : null;

        return is_string($id) && $id !== '' ? $id : null;
    }
}
