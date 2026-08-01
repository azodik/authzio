<?php

namespace App\Services\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\BillingPlan;
use App\Models\DodoWebhook;
use App\Models\DodoWebhookEvent;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DodoWebhookProcessor
{
    public function __construct(
        private readonly DodoPaymentsClient $dodo,
    ) {}

    public function verifySignature(string $payload, string $webhookId, string $timestamp, string $signatureHeader): bool
    {
        $secret = DodoWebhook::activeSecret() ?? '';

        if ($secret === '') {
            return false;
        }

        $secretKey = str_starts_with($secret, 'whsec_')
            ? base64_decode(substr($secret, 6), true)
            : $secret;

        if ($secretKey === false || $secretKey === '') {
            return false;
        }

        // Reject stale deliveries (5 minutes skew).
        if (ctype_digit($timestamp)) {
            $age = abs(time() - (int) $timestamp);
            if ($age > 300) {
                return false;
            }
        }

        $signedContent = $webhookId.'.'.$timestamp.'.'.$payload;
        $expected = base64_encode(hash_hmac('sha256', $signedContent, $secretKey, true));

        foreach (explode(' ', $signatureHeader) as $part) {
            $value = str_contains($part, ',')
                ? (explode(',', $part, 2)[1] ?? '')
                : (str_contains($part, '=') ? explode('=', $part, 2)[1] : $part);

            if ($value !== '' && hash_equals($expected, $value)) {
                return true;
            }

            // Some payloads use v1,<sig>
            if (str_starts_with($part, 'v1,')) {
                $sig = substr($part, 3);
                if (hash_equals($expected, $sig)) {
                    return true;
                }
            }
        }

        return hash_equals($expected, $signatureHeader);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function process(string $webhookId, string $eventType, array $payload, array $headers = []): void
    {
        $existing = DodoWebhookEvent::query()->where('webhook_id', $webhookId)->first();
        if ($existing?->processed_at !== null) {
            return;
        }

        $endpoint = DodoWebhook::activeForEnvironment();

        $event = $existing ?? DodoWebhookEvent::query()->create([
            'dodo_webhook_id' => $endpoint?->id,
            'webhook_id' => $webhookId,
            'event_type' => $eventType,
            'payload' => $payload,
            'headers' => $headers !== [] ? $headers : null,
        ]);

        if ($existing !== null && $headers !== []) {
            $existing->update([
                'headers' => $headers,
                'dodo_webhook_id' => $existing->dodo_webhook_id ?? $endpoint?->id,
            ]);
        }

        try {
            $notify = null;

            DB::transaction(function () use ($eventType, $payload, &$notify): void {
                if (str_starts_with($eventType, 'subscription.')) {
                    $notify = $this->handleSubscription($eventType, $payload);
                }

                if (str_starts_with($eventType, 'payment.')) {
                    $this->handlePayment($eventType, $payload);
                }
            });

            if (is_array($notify)) {
                try {
                    app(BillingNotifier::class)->notifyPlanChange(
                        $notify['organization'],
                        $notify['previous_plan'],
                        $notify['new_plan'],
                        $notify['status'],
                    );
                } catch (\Throwable $mailException) {
                    Log::error('Billing plan email failed after webhook', [
                        'webhook_id' => $webhookId,
                        'event_type' => $eventType,
                        'error' => $mailException->getMessage(),
                    ]);
                }
            }

            $event->update([
                'processed_at' => now(),
                'processing_error' => null,
            ]);

            $endpoint?->forceFill(['last_delivered_at' => now()])->save();
        } catch (\Throwable $exception) {
            $event->update([
                'processing_error' => $exception->getMessage(),
            ]);

            Log::error('Dodo webhook processing failed', [
                'webhook_id' => $webhookId,
                'event_type' => $eventType,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{organization: Organization, previous_plan: ?BillingPlan, new_plan: BillingPlan, status: SubscriptionStatus}|null
     */
    private function handleSubscription(string $eventType, array $payload): ?array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];

        $organizationId = Arr::get($metadata, 'organization_id');
        $planId = Arr::get($metadata, 'billing_plan_id');
        $subscriptionId = Arr::get($data, 'subscription_id') ?? Arr::get($data, 'id');
        $customerId = Arr::get($data, 'customer_id') ?? Arr::get($data, 'customer.id');

        if (! is_string($organizationId) || $organizationId === '') {
            Log::warning('Dodo subscription webhook missing organization_id metadata', [
                'event_type' => $eventType,
            ]);

            return null;
        }

        $organization = Organization::query()->find($organizationId);
        if ($organization === null) {
            return null;
        }

        $existingSubscription = $organization->subscription;
        $previousPlan = $existingSubscription?->plan;
        $existingMetadata = is_array($existingSubscription?->metadata)
            ? $existingSubscription->metadata
            : [];

        // After upgrade-via-checkout, cancel of the *old* subscription must not wipe the org to Free.
        if (
            in_array($eventType, ['subscription.cancelled', 'subscription.expired'], true)
            && is_string($subscriptionId)
            && $subscriptionId !== ''
            && $existingSubscription !== null
            && filled($existingSubscription->dodo_subscription_id)
            && $existingSubscription->dodo_subscription_id !== $subscriptionId
        ) {
            Log::info('Ignoring Dodo cancel/expire for replaced subscription', [
                'organization_id' => $organization->id,
                'event_type' => $eventType,
                'cancelled_subscription_id' => $subscriptionId,
                'current_subscription_id' => $existingSubscription->dodo_subscription_id,
            ]);

            return null;
        }

        $webhookPlan = $this->resolvePlanFromWebhook($planId, $data);
        $freePlan = BillingPlan::query()->where('slug', 'free')->first();

        $appliesPaidPlan = in_array($eventType, [
            'subscription.active',
            'subscription.renewed',
            'subscription.plan_changed',
        ], true);

        // Dodo emits plan_changed when cancel_at_next_billing_date is toggled — keep the paid
        // plan and only record the scheduled cancellation.
        $schedulesCancelAtPeriodEnd = $eventType === 'subscription.plan_changed'
            && (bool) Arr::get($data, 'cancel_at_next_billing_date', false);

        if ($schedulesCancelAtPeriodEnd) {
            $appliesPaidPlan = false;
        }

        // Paid→paid upgrades only apply locally from payment.succeeded (BillingService).
        // Ignore subscription.* plan flips while an upgrade charge is still pending.
        $pendingUpgradeAwaitingPayment = ($existingMetadata['pending_plan_kind'] ?? null) === 'upgrade'
            && (bool) ($existingMetadata['pending_requires_payment'] ?? false);

        if ($pendingUpgradeAwaitingPayment && $appliesPaidPlan) {
            Log::info('Deferring subscription plan webhook until upgrade payment succeeds', [
                'organization_id' => $organization->id,
                'event_type' => $eventType,
                'pending_plan_slug' => $existingMetadata['pending_plan_slug'] ?? null,
            ]);

            // Fully no-op: do not clear pending_* metadata via the plan_changed cancel path.
            return null;
        }

        $hadConfirmedPaidSubscription = $existingSubscription !== null
            && filled($existingSubscription->dodo_subscription_id)
            && $previousPlan !== null
            && $previousPlan->slug !== 'free'
            && in_array($existingSubscription->status, [
                SubscriptionStatus::Active,
                SubscriptionStatus::PastDue,
                SubscriptionStatus::OnHold,
                SubscriptionStatus::Trialing,
            ], true);

        // Failed / cancelled initial checkout must never grant a paid plan.
        if ($eventType === 'subscription.failed' && ! $hadConfirmedPaidSubscription) {
            if ($existingSubscription === null) {
                if ($freePlan === null) {
                    throw new RuntimeException('No billing plans configured.');
                }

                OrganizationSubscription::query()->create([
                    'organization_id' => $organization->id,
                    'billing_plan_id' => $freePlan->id,
                    'status' => SubscriptionStatus::Active,
                    'current_period_start' => now()->startOfMonth(),
                    'current_period_end' => now()->endOfMonth(),
                    'metadata' => [
                        'last_event' => $eventType,
                        'failed_subscription_id' => is_string($subscriptionId) ? $subscriptionId : null,
                    ],
                ]);

                return null;
            }

            $existingSubscription->update([
                'status' => SubscriptionStatus::Active,
                'dodo_checkout_session_id' => null,
                'metadata' => array_merge($existingMetadata, [
                    'last_event' => $eventType,
                    'pending_plan_id' => null,
                    'failed_subscription_id' => is_string($subscriptionId) ? $subscriptionId : null,
                ]),
            ]);

            return null;
        }

        $plan = $appliesPaidPlan
            ? ($webhookPlan ?? $previousPlan ?? $freePlan)
            : ($previousPlan ?? $webhookPlan ?? $freePlan);

        if ($plan === null) {
            throw new RuntimeException('No billing plans configured.');
        }

        $status = match ($eventType) {
            'subscription.active', 'subscription.renewed', 'subscription.plan_changed' => SubscriptionStatus::Active,
            'subscription.on_hold' => SubscriptionStatus::OnHold,
            'subscription.failed' => SubscriptionStatus::PastDue,
            'subscription.cancelled' => SubscriptionStatus::Cancelled,
            'subscription.expired' => SubscriptionStatus::Expired,
            default => $existingSubscription?->status ?? SubscriptionStatus::Active,
        };

        if ($schedulesCancelAtPeriodEnd) {
            $status = SubscriptionStatus::Active;
        }

        $metadataUpdate = array_merge($existingMetadata, [
            'last_event' => $eventType,
        ]);

        $previousDodoSubscriptionId = $existingMetadata['previous_dodo_subscription_id'] ?? null;

        if ($appliesPaidPlan) {
            $metadataUpdate['pending_plan_id'] = null;
            $metadataUpdate['pending_plan_slug'] = null;
            $metadataUpdate['pending_plan_kind'] = null;
            $metadataUpdate['pending_requires_payment'] = false;
            $metadataUpdate['scheduled_plan_change_at'] = null;
            $metadataUpdate['previous_dodo_subscription_id'] = null;
            $metadataUpdate['upgrade_via_checkout'] = null;
        }

        $attributes = [
            'status' => $status,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'cancelled_at' => $status === SubscriptionStatus::Cancelled ? now() : null,
            'metadata' => $metadataUpdate,
        ];

        if ($appliesPaidPlan) {
            $attributes['billing_plan_id'] = $plan->id;
            $attributes['dodo_customer_id'] = is_string($customerId) ? $customerId : $existingSubscription?->dodo_customer_id;
            $attributes['dodo_subscription_id'] = is_string($subscriptionId) ? $subscriptionId : $existingSubscription?->dodo_subscription_id;
            $attributes['dodo_checkout_session_id'] = null;
            $metadataUpdate['cancel_at_period_end'] = false;
            $metadataUpdate['cancels_at'] = null;
            $metadataUpdate['pending_plan_slug'] = null;
        } elseif (in_array($eventType, ['subscription.cancelled', 'subscription.expired'], true)) {
            // Only drop to Free when the cancelled/expired subscription is the one we currently
            // track. Cancels for a replaced prior subscription (upgrade-via-checkout) must no-op.
            if (
                ! is_string($subscriptionId)
                || $subscriptionId === ''
                || $existingSubscription?->dodo_subscription_id !== $subscriptionId
            ) {
                Log::info('Skipping Free downgrade for non-current Dodo subscription cancel/expire', [
                    'organization_id' => $organization->id,
                    'event_type' => $eventType,
                    'event_subscription_id' => $subscriptionId,
                    'current_subscription_id' => $existingSubscription?->dodo_subscription_id,
                ]);

                return null;
            }

            if ($freePlan === null) {
                throw new RuntimeException('No billing plans configured.');
            }

            $attributes['billing_plan_id'] = $freePlan->id;
            $attributes['status'] = SubscriptionStatus::Active;
            $attributes['dodo_subscription_id'] = null;
            $attributes['dodo_checkout_session_id'] = null;
            $attributes['cancelled_at'] = now();
            $metadataUpdate['cancel_at_period_end'] = false;
            $metadataUpdate['cancels_at'] = null;
            $metadataUpdate['pending_plan_id'] = null;
            $metadataUpdate['pending_plan_slug'] = null;
            $metadataUpdate['pending_plan_kind'] = null;
            $metadataUpdate['pending_requires_payment'] = false;
            $metadataUpdate['switched_to_free_at'] = now()->toIso8601String();
            if (is_string($customerId)) {
                $attributes['dodo_customer_id'] = $customerId;
            }
        } elseif ($hadConfirmedPaidSubscription) {
            // Renewal failure / hold on an existing paid sub — keep plan, update status only.
            $attributes['billing_plan_id'] = $existingSubscription->billing_plan_id;
            if (is_string($customerId)) {
                $attributes['dodo_customer_id'] = $customerId;
            }
            if (is_string($subscriptionId)) {
                $attributes['dodo_subscription_id'] = $subscriptionId;
            }

            // Scheduled cancel toggle (plan_changed with cancel_at_next_billing_date).
            if ($schedulesCancelAtPeriodEnd || $eventType === 'subscription.plan_changed') {
                $cancelAtNext = $schedulesCancelAtPeriodEnd
                    || (bool) Arr::get($data, 'cancel_at_next_billing_date', false);
                $metadataUpdate['cancel_at_period_end'] = $cancelAtNext;
                if ($cancelAtNext) {
                    $nextBilling = Arr::get($data, 'next_billing_date');
                    $metadataUpdate['cancels_at'] = is_string($nextBilling) ? $nextBilling : (
                        $existingSubscription->current_period_end?->toIso8601String()
                    );
                    $metadataUpdate['pending_plan_slug'] = 'free';
                    if ($freePlan !== null) {
                        $metadataUpdate['pending_plan_id'] = $freePlan->id;
                    }
                } else {
                    $metadataUpdate['cancels_at'] = null;
                    $metadataUpdate['pending_plan_id'] = null;
                    $metadataUpdate['pending_plan_slug'] = null;
                }
            }
        } else {
            $attributes['billing_plan_id'] = $existingSubscription?->billing_plan_id ?? $plan->id;
        }

        $attributes['metadata'] = $metadataUpdate;

        OrganizationSubscription::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            $attributes,
        );

        if (
            $appliesPaidPlan
            && is_string($previousDodoSubscriptionId)
            && $previousDodoSubscriptionId !== ''
            && $previousDodoSubscriptionId !== ($attributes['dodo_subscription_id'] ?? null)
            && $this->dodo->isConfigured()
        ) {
            try {
                $this->dodo->cancelSubscriptionNow($previousDodoSubscriptionId);
            } catch (RuntimeException $exception) {
                Log::warning('Unable to cancel previous Dodo subscription after upgrade checkout', [
                    'organization_id' => $organization->id,
                    'previous_subscription_id' => $previousDodoSubscriptionId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $appliedPlan = BillingPlan::query()->find($attributes['billing_plan_id']) ?? $plan;
        $planChanged = $previousPlan === null || $previousPlan->id !== $appliedPlan->id;

        $shouldNotify = match ($eventType) {
            'subscription.active' => true,
            'subscription.plan_changed' => ! $schedulesCancelAtPeriodEnd && $planChanged,
            // Dodo may send renewed before/instead of active on first payment.
            'subscription.renewed' => $planChanged,
            'subscription.cancelled', 'subscription.expired' => true,
            default => false,
        };

        if (! $shouldNotify) {
            return null;
        }

        return [
            'organization' => $organization->fresh() ?? $organization,
            'previous_plan' => $previousPlan,
            'new_plan' => $appliedPlan,
            'status' => $status,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handlePayment(string $eventType, array $payload): void
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $organizationId = Arr::get($metadata, 'organization_id');
        $paymentId = Arr::get($data, 'payment_id') ?? Arr::get($data, 'id');

        if (! is_string($organizationId) || $organizationId === '') {
            return;
        }

        $organization = Organization::query()->find($organizationId);
        if ($organization === null) {
            return;
        }

        if (! in_array($eventType, ['payment.succeeded', 'payment.failed'], true)) {
            return;
        }

        // Upgrades apply only from verified payment webhooks (not from subscription.plan_changed).
        app(BillingService::class)->finalizeUpgradeAfterRemotePayment(
            $organization,
            is_string($paymentId) ? $paymentId : null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvePlanFromWebhook(mixed $planId, array $data): ?BillingPlan
    {
        if (is_string($planId) && $planId !== '') {
            $plan = BillingPlan::query()->find($planId);
            if ($plan !== null) {
                return $plan;
            }
        }

        $productId = Arr::get($data, 'product_id');
        if (is_string($productId) && $productId !== '') {
            return BillingPlan::query()->where('dodo_product_id', $productId)->first();
        }

        return null;
    }
}
