<?php

namespace App\Services\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\BillingPlan;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BillingService
{
    public function __construct(
        private readonly UsageTracker $usageTracker,
        private readonly DodoPaymentsClient $dodo,
        private readonly BillingNotifier $notifier,
    ) {}

    public function ensureSubscription(Organization $organization): OrganizationSubscription
    {
        $existing = $organization->subscription;

        if ($existing !== null) {
            return $existing;
        }

        $free = BillingPlan::query()->where('slug', 'free')->firstOrFail();

        return OrganizationSubscription::query()->create([
            'organization_id' => $organization->id,
            'billing_plan_id' => $free->id,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Organization $organization): array
    {
        $subscription = $this->ensureSubscription($organization)->load('plan');
        $mau = $this->usageTracker->monthlyActiveUsers($organization);
        $summary = $this->usageTracker->recomputeMonthlySummary($organization);
        $limit = $subscription->plan->mau_limit;
        $utilization = $limit > 0 ? round(($mau / $limit) * 100, 1) : 0.0;
        $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];
        $cancelAtPeriodEnd = (bool) ($metadata['cancel_at_period_end'] ?? false);
        $cancelsAt = $metadata['cancels_at'] ?? null;

        return [
            'subscription' => [
                'id' => $subscription->id,
                'status' => $subscription->status->value,
                'current_period_start' => optional($subscription->current_period_start)?->toIso8601String(),
                'current_period_end' => optional($subscription->current_period_end)?->toIso8601String(),
                'cancelled_at' => optional($subscription->cancelled_at)?->toIso8601String(),
                'dodo_subscription_id' => $subscription->dodo_subscription_id,
                'dodo_customer_id' => $subscription->dodo_customer_id,
                'cancel_at_period_end' => $cancelAtPeriodEnd,
                'cancels_at' => is_string($cancelsAt) ? $cancelsAt : (
                    $cancelAtPeriodEnd && $subscription->current_period_end !== null
                        ? $subscription->current_period_end->toIso8601String()
                        : null
                ),
                'pending_plan_slug' => isset($metadata['pending_plan_slug']) && is_string($metadata['pending_plan_slug'])
                    ? $metadata['pending_plan_slug']
                    : null,
                'pending_plan_kind' => isset($metadata['pending_plan_kind']) && is_string($metadata['pending_plan_kind'])
                    ? $metadata['pending_plan_kind']
                    : null,
                'pending_requires_payment' => (bool) ($metadata['pending_requires_payment'] ?? false),
                'scheduled_plan_change_at' => isset($metadata['scheduled_plan_change_at']) && is_string($metadata['scheduled_plan_change_at'])
                    ? $metadata['scheduled_plan_change_at']
                    : null,
            ],
            'plan' => $subscription->plan,
            'usage' => [
                'mau' => $mau,
                'mau_limit' => $limit,
                'utilization_percent' => $utilization,
                'over_limit' => $mau > $limit,
                'authentication_count' => $summary->authentication_count,
                'year_month' => $summary->year_month,
                'daily' => $this->usageTracker->dailyBreakdown($organization, 30),
            ],
            'plans' => BillingPlan::query()
                ->where('is_public', true)
                ->orderBy('sort_order')
                ->get(),
            'entitlements' => app(PlanEntitlements::class)->forOrganization($organization),
            'downgrade' => $this->downgradePreview($subscription->plan),
            'dodo_configured' => $this->dodo->isConfigured(),
            'billing_enabled' => (bool) config('billing.enabled', true),
        ];
    }

    /**
     * Features / limits the org loses when moving from the current plan to Free.
     *
     * @return array{
     *     from_plan: string,
     *     to_plan: string,
     *     losses: list<string>,
     *     keeps_access_until_period_end: bool
     * }|null
     */
    public function downgradePreview(?BillingPlan $currentPlan): ?array
    {
        if ($currentPlan === null || $currentPlan->slug === 'free') {
            return null;
        }

        $free = BillingPlan::query()->where('slug', 'free')->first();
        if ($free === null) {
            return null;
        }

        $losses = [];

        if ($currentPlan->mau_limit > $free->mau_limit) {
            $losses[] = sprintf(
                'MAU limit drops from %s to %s',
                number_format($currentPlan->mau_limit),
                number_format($free->mau_limit),
            );
        }

        if ($currentPlan->application_limit === null && $free->application_limit !== null) {
            $losses[] = sprintf('Applications limited to %d (currently unlimited)', $free->application_limit);
        } elseif (
            $currentPlan->application_limit !== null
            && $free->application_limit !== null
            && $currentPlan->application_limit > $free->application_limit
        ) {
            $losses[] = sprintf(
                'Application limit drops from %d to %d',
                $currentPlan->application_limit,
                $free->application_limit,
            );
        }

        if ($currentPlan->allows_custom_domains && ! $free->allows_custom_domains) {
            $losses[] = 'Custom domains are disabled';
        }

        if ($currentPlan->allows_email_customization && ! $free->allows_email_customization) {
            $losses[] = 'Editable email templates are disabled';
        }

        if ($currentPlan->allows_custom_jwks && ! $free->allows_custom_jwks) {
            $losses[] = 'Custom JWKS / signing keys are disabled';
        }

        if ($currentPlan->allows_custom_email_provider && ! $free->allows_custom_email_provider) {
            $losses[] = 'Bring-your-own email provider is disabled';
        }

        if ($currentPlan->allows_sso && ! $free->allows_sso) {
            $losses[] = 'Enterprise OIDC SSO is disabled';
        }

        if ($free->email_daily_limit !== null && $currentPlan->email_daily_limit === null) {
            $losses[] = sprintf('Platform email sends capped at %s / day', number_format($free->email_daily_limit));
        }

        if ($free->email_monthly_limit !== null && $currentPlan->email_monthly_limit === null) {
            $losses[] = sprintf('Platform email sends capped at %s / month', number_format($free->email_monthly_limit));
        }

        return [
            'from_plan' => $currentPlan->name,
            'to_plan' => $free->name,
            'losses' => $losses,
            'keeps_access_until_period_end' => true,
        ];
    }

    /**
     * Preview a paid plan change (upgrade/downgrade) before the customer confirms.
     *
     * @return array{
     *     requires_checkout: bool,
     *     is_upgrade: bool,
     *     effective_at: 'immediately'|'next_billing_date'|null,
     *     from_plan: array{slug: string, name: string, price_cents_monthly: int},
     *     to_plan: array{slug: string, name: string, price_cents_monthly: int},
     *     immediate_charge_cents: int|null,
     *     currency: string,
     *     message: string
     * }
     */
    public function previewPlanChange(Organization $organization, BillingPlan $plan): array
    {
        $subscription = $this->ensureSubscription($organization)->load('plan');
        $previousPlan = $subscription->plan;

        if ($previousPlan === null) {
            throw new RuntimeException('No current plan found for this organization.');
        }

        if ($this->hasPendingUpgradePayment($subscription)) {
            throw new RuntimeException(
                'An upgrade payment is still processing. Wait for it to finish before changing plans.',
            );
        }

        if ($previousPlan->id === $plan->id && $plan->slug !== 'free') {
            throw new RuntimeException('You are already on the '.$plan->name.' plan.');
        }

        $from = [
            'slug' => $previousPlan->slug,
            'name' => $previousPlan->name,
            'price_cents_monthly' => $previousPlan->price_cents_monthly,
        ];
        $to = [
            'slug' => $plan->slug,
            'name' => $plan->name,
            'price_cents_monthly' => $plan->price_cents_monthly,
        ];

        if ($plan->slug === 'free') {
            return [
                'requires_checkout' => false,
                'is_upgrade' => false,
                'effective_at' => 'next_billing_date',
                'from_plan' => $from,
                'to_plan' => $to,
                'immediate_charge_cents' => 0,
                'currency' => $plan->currency ?: 'USD',
                'message' => 'Free starts at the end of the current billing period. No immediate charge.',
            ];
        }

        $canChangeExisting = filled($subscription->dodo_subscription_id)
            && $this->dodo->isConfigured()
            && $previousPlan->slug !== 'free'
            && filled($plan->dodo_product_id);

        if (! $canChangeExisting) {
            return [
                'requires_checkout' => true,
                'is_upgrade' => $plan->price_cents_monthly > $previousPlan->price_cents_monthly,
                'effective_at' => null,
                'from_plan' => $from,
                'to_plan' => $to,
                'immediate_charge_cents' => $plan->price_cents_monthly,
                'currency' => $plan->currency ?: 'USD',
                'message' => 'You will continue to hosted checkout to start this plan.',
            ];
        }

        $isUpgrade = $plan->price_cents_monthly > $previousPlan->price_cents_monthly;
        $effectiveAt = $isUpgrade ? 'immediately' : 'next_billing_date';
        $prorationMode = $isUpgrade ? 'difference_immediately' : 'full_immediately';
        $currency = $plan->currency ?: 'USD';
        $fallbackCents = max(0, $plan->price_cents_monthly - $previousPlan->price_cents_monthly);

        $immediateCents = $isUpgrade ? $fallbackCents : 0;
        $message = $isUpgrade
            ? sprintf(
                'You will be charged the difference now and move to %s immediately.',
                $plan->name,
            )
            : sprintf(
                '%s starts on your next billing date. You keep %s until then.',
                $plan->name,
                $previousPlan->name,
            );

        try {
            $preview = $this->dodo->previewChangePlan(
                (string) $subscription->dodo_subscription_id,
                (string) $plan->dodo_product_id,
                $prorationMode,
                1,
                $effectiveAt,
            );

            $summary = is_array($preview['immediate_charge'] ?? null)
                ? ($preview['immediate_charge']['summary'] ?? null)
                : null;

            if (is_array($summary)) {
                if (isset($summary['total_amount']) && is_numeric($summary['total_amount'])) {
                    $immediateCents = (int) $summary['total_amount'];
                }
                if (isset($summary['currency']) && is_string($summary['currency']) && $summary['currency'] !== '') {
                    $currency = $summary['currency'];
                }
            }
        } catch (RuntimeException) {
            // Fall back to local price difference so the confirm dialog still works.
        }

        return [
            'requires_checkout' => false,
            'is_upgrade' => $isUpgrade,
            'effective_at' => $effectiveAt,
            'from_plan' => $from,
            'to_plan' => $to,
            'immediate_charge_cents' => $immediateCents,
            'currency' => $currency,
            'message' => $message,
        ];
    }

    /**
     * Start checkout for a new paid sub, or change plan on an existing Dodo subscription.
     *
     * Upgrades use difference_immediately on the existing subscription. If the bank needs
     * 3DS, recover via the difference payment link or on_hold update-payment-method (dues) —
     * never a second full-price product checkout.
     *
     * @return array{
     *     checkout_url: string,
     *     session_id: string,
     *     mode: 'checkout'|'plan_changed'|'plan_change_pending'|'plan_change_scheduled'|'cancel_at_period_end'|'already_on_plan'
     * }
     */
    public function startCheckout(Organization $organization, BillingPlan $plan, User $actor): array
    {
        $subscription = $this->ensureSubscription($organization)->load('plan');
        $previousPlan = $subscription->plan;
        $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];

        // Only the pending upgrade target may continue (auth / status check). Block Free and
        // other plan switches while an upgrade charge is still outstanding.
        if ($this->hasPendingUpgradePayment($subscription)) {
            $pendingSlug = $metadata['pending_plan_slug'] ?? null;
            if (
                is_string($pendingSlug)
                && $pendingSlug === $plan->slug
                && filled($subscription->dodo_subscription_id)
            ) {
                return $this->continuePendingUpgrade($organization, $subscription, $plan, $actor);
            }

            throw new RuntimeException(
                'An upgrade payment is still processing. Wait for it to finish before changing plans.',
            );
        }

        if ($plan->slug === 'free') {
            return $this->switchToFree($organization, $subscription, $previousPlan);
        }

        if ($previousPlan !== null && $previousPlan->id === $plan->id) {
            if ((bool) ($metadata['cancel_at_period_end'] ?? false)) {
                return $this->resumePaidPlan($subscription);
            }

            throw new RuntimeException('You are already on the '.$plan->name.' plan.');
        }

        $canChangeExisting = filled($subscription->dodo_subscription_id)
            && $this->dodo->isConfigured()
            && $previousPlan !== null
            && $previousPlan->slug !== 'free'
            && filled($plan->dodo_product_id);

        if ($canChangeExisting) {
            return $this->changeExistingPlan($organization, $subscription, $previousPlan, $plan, $actor);
        }

        // Never open a second subscription checkout while one already exists remotely.
        if (filled($subscription->dodo_subscription_id)) {
            throw new RuntimeException(
                'This organization already has an active billing subscription. Refresh billing and try again.',
            );
        }

        // Avoid parallel hosted checkouts that could both be paid.
        if (filled($subscription->dodo_checkout_session_id)) {
            throw new RuntimeException(
                'A checkout is already in progress. Finish that payment or wait a few minutes before starting another.',
            );
        }

        $session = $this->dodo->createCheckoutSession($organization, $plan, $actor);

        $subscription->update([
            'dodo_checkout_session_id' => $session['session_id'],
            'metadata' => array_merge(
                is_array($subscription->metadata) ? $subscription->metadata : [],
                ['pending_plan_id' => $plan->id],
            ),
        ]);

        return [
            ...$session,
            'mode' => 'checkout',
        ];
    }

    /**
     * @return array{
     *     checkout_url: string,
     *     session_id: string,
     *     mode: 'checkout'|'plan_change_pending'|'plan_change_scheduled'|'plan_changed'
     * }
     */
    private function changeExistingPlan(
        Organization $organization,
        OrganizationSubscription $subscription,
        BillingPlan $previousPlan,
        BillingPlan $plan,
        User $actor,
    ): array {
        $dodoSubscriptionId = $subscription->dodo_subscription_id;
        if (! is_string($dodoSubscriptionId) || $dodoSubscriptionId === '') {
            throw new RuntimeException('No Dodo subscription is linked to this organization.');
        }

        if (! filled($plan->dodo_product_id)) {
            throw new RuntimeException("Plan [{$plan->slug}] has no Dodo product ID configured.");
        }

        $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];
        $isUpgrade = $plan->price_cents_monthly > $previousPlan->price_cents_monthly;
        $effectiveAt = $isUpgrade ? 'immediately' : 'next_billing_date';
        // Dodo only allows full_immediately with next_billing_date (scheduled downgrades).
        $prorationMode = $isUpgrade ? 'difference_immediately' : 'full_immediately';

        if ((bool) ($metadata['cancel_at_period_end'] ?? false)) {
            $this->dodo->resumeSubscription($dodoSubscriptionId);
        }

        if (! $isUpgrade) {
            $this->dodo->changePlan(
                $dodoSubscriptionId,
                (string) $plan->dodo_product_id,
                $prorationMode,
                1,
                $effectiveAt,
                'prevent_change',
                [
                    'organization_id' => $organization->id,
                    'billing_plan_id' => $plan->id,
                    'billing_plan_slug' => $plan->slug,
                ],
            );

            $cancelsAt = $subscription->current_period_end ?? now()->addMonth();
            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'cancelled_at' => null,
                'dodo_checkout_session_id' => null,
                'metadata' => array_merge($metadata, [
                    'cancel_at_period_end' => false,
                    'cancels_at' => null,
                    'pending_plan_id' => $plan->id,
                    'pending_plan_slug' => $plan->slug,
                    'pending_plan_kind' => 'downgrade',
                    'pending_requires_payment' => false,
                    'scheduled_plan_change_at' => $cancelsAt->toIso8601String(),
                    'last_plan_change' => 'downgrade_scheduled',
                    'last_plan_change_at' => now()->toIso8601String(),
                ]),
            ]);

            return [
                'checkout_url' => url('/console/'.$organization->id.'/billing'),
                'session_id' => 'plan-change-scheduled',
                'mode' => 'plan_change_scheduled',
            ];
        }

        // Upgrades: start a difference charge on the existing subscription, but never change
        // the local plan here. Authzio only applies the new plan from payment.succeeded webhooks
        // (finalizeUpgradeAfterRemotePayment). prevent_change keeps Dodo on the old product until paid.
        // Never call change-plan twice while a prior upgrade payment is still open.
        $existingPendingPaymentId = isset($metadata['pending_payment_id']) && is_string($metadata['pending_payment_id'])
            ? $metadata['pending_payment_id']
            : null;
        if ($existingPendingPaymentId !== null && $this->paymentChargeStillOpen($existingPendingPaymentId)) {
            return $this->markUpgradePaymentPending(
                $organization,
                $subscription,
                $plan,
                $dodoSubscriptionId,
                $metadata,
                $existingPendingPaymentId,
                allowNewDuesSession: false,
            );
        }

        try {
            $changeResult = $this->dodo->changePlan(
                $dodoSubscriptionId,
                (string) $plan->dodo_product_id,
                'difference_immediately',
                1,
                'immediately',
                'prevent_change',
                [
                    'organization_id' => $organization->id,
                    'billing_plan_id' => $plan->id,
                    'billing_plan_slug' => $plan->slug,
                ],
            );
        } catch (RuntimeException $exception) {
            // Previous charge still open on Dodo — do not start another; surface pending state.
            if ($this->isPreviousUpgradePaymentPendingError($exception)) {
                return $this->markUpgradePaymentPending(
                    $organization,
                    $subscription,
                    $plan,
                    $dodoSubscriptionId,
                    $metadata,
                    $existingPendingPaymentId,
                    allowNewDuesSession: false,
                );
            }

            return $this->markUpgradePaymentPending(
                $organization,
                $subscription,
                $plan,
                $dodoSubscriptionId,
                $metadata,
                null,
                $exception,
                allowNewDuesSession: false,
            );
        }

        $paymentId = isset($changeResult['payment_id']) && is_string($changeResult['payment_id'])
            ? $changeResult['payment_id']
            : null;

        // Fresh change-plan succeeded: allow one on-hold dues session if the payment has no
        // hosted link and is not still open (failed / needs recovery). Retries stay blocked.
        return $this->markUpgradePaymentPending(
            $organization,
            $subscription,
            $plan,
            $dodoSubscriptionId,
            $metadata,
            $paymentId,
            allowNewDuesSession: true,
        );
    }

    /**
     * Record a pending upgrade and optionally redirect to a non-zero dues / 3DS link.
     * Does not change billing_plan_id — that happens only in finalizeUpgradeAfterRemotePayment.
     *
     * @param  array<string, mixed>  $metadata
     * @return array{checkout_url: string, session_id: string, mode: 'checkout'|'plan_change_pending'}
     */
    private function markUpgradePaymentPending(
        Organization $organization,
        OrganizationSubscription $subscription,
        BillingPlan $plan,
        string $dodoSubscriptionId,
        array $metadata,
        ?string $paymentId = null,
        ?RuntimeException $priorException = null,
        bool $allowNewDuesSession = false,
    ): array {
        $payable = $this->resolveUpgradePayableLink(
            $dodoSubscriptionId,
            $paymentId,
            $organization,
            $allowNewDuesSession,
        );

        $subscription->update([
            'cancelled_at' => null,
            'dodo_checkout_session_id' => $payable['session_id'] ?? null,
            'metadata' => array_merge($metadata, [
                'cancel_at_period_end' => false,
                'cancels_at' => null,
                'pending_plan_id' => $plan->id,
                'pending_plan_slug' => $plan->slug,
                'pending_plan_kind' => 'upgrade',
                'pending_requires_payment' => true,
                'scheduled_plan_change_at' => null,
                'pending_payment_id' => $payable['payment_id'] ?? $paymentId,
                'previous_dodo_subscription_id' => null,
                'upgrade_via_checkout' => false,
                'last_plan_change' => $payable !== null ? 'upgrade_auth_required' : 'upgrade_pending',
                'last_plan_change_at' => now()->toIso8601String(),
            ]),
        ]);

        if ($payable !== null) {
            return [
                'checkout_url' => $payable['checkout_url'],
                'session_id' => $payable['session_id'],
                'mode' => 'checkout',
            ];
        }

        if ($priorException !== null && ! $this->isPreviousUpgradePaymentPendingError($priorException)) {
            $detail = $priorException->getMessage();
            throw new RuntimeException(
                'Unable to start the upgrade charge for '.$plan->name.'. Try again in a moment.'
                .($detail !== null && $detail !== '' ? ' ('.$detail.')' : ''),
            );
        }

        return [
            'checkout_url' => url('/console/'.$organization->id.'/billing'),
            'session_id' => 'plan-change-pending',
            'mode' => 'plan_change_pending',
        ];
    }

    /**
     * Find a hosted link that collects the upgrade difference (never a $0 card-only session).
     * Does not create a new dues session unless explicitly allowed — prevents double charges
     * when the user retries "Complete payment".
     *
     * @return array{checkout_url: string, session_id: string, payment_id: string|null}|null
     */
    private function resolveUpgradePayableLink(
        string $dodoSubscriptionId,
        ?string $paymentId,
        Organization $organization,
        bool $allowNewDuesSession = false,
    ): ?array {
        if ($paymentId !== null) {
            $fromPayment = $this->payableLinkFromPayment($paymentId);
            if ($fromPayment !== null) {
                return $fromPayment;
            }

            // Existing charge still open (processing / auth) — never start another.
            if ($this->paymentChargeStillOpen($paymentId)) {
                return null;
            }
        }

        if (! $allowNewDuesSession || ! $this->subscriptionIsOnHold($dodoSubscriptionId)) {
            return null;
        }

        $returnUrl = rtrim((string) config('app.url'), '/')
            .'/console/'.$organization->id.'/billing?checkout=pending';

        try {
            $session = $this->dodo->createPaymentMethodUpdateSession($dodoSubscriptionId, $returnUrl);
        } catch (RuntimeException) {
            return null;
        }

        $sessionPaymentId = $session['payment_id'];
        if (! is_string($sessionPaymentId) || $sessionPaymentId === '') {
            return null;
        }

        if (! $this->dodoPaymentHasPositiveAmount($sessionPaymentId)) {
            Log::warning('Dodo update-payment-method returned a zero-amount session; refusing redirect', [
                'subscription_id' => $dodoSubscriptionId,
                'payment_id' => $sessionPaymentId,
            ]);

            return null;
        }

        return [
            'checkout_url' => $session['payment_link'],
            'session_id' => $session['session_id'],
            'payment_id' => $sessionPaymentId,
        ];
    }

    /**
     * @return array{checkout_url: string, session_id: string, payment_id: string|null}|null
     */
    private function payableLinkFromPayment(string $paymentId): ?array
    {
        try {
            $payment = $this->dodo->getPayment($paymentId);
        } catch (RuntimeException) {
            return null;
        }

        if (! $this->paymentHasPositiveAmount($payment)) {
            return null;
        }

        $link = $payment['payment_link'] ?? $payment['checkout_url'] ?? $payment['url'] ?? null;
        if (! is_string($link) || $link === '' || ! str_starts_with($link, 'http')) {
            return null;
        }

        return [
            'checkout_url' => $link,
            'session_id' => $paymentId,
            'payment_id' => $paymentId,
        ];
    }

    /**
     * Continue a pending upgrade that still needs the difference charge authenticated.
     * Never applies the plan locally — only payment.succeeded webhook does.
     *
     * @return array{checkout_url: string, session_id: string, mode: 'checkout'|'plan_change_pending'}
     */
    public function continuePendingUpgrade(
        Organization $organization,
        OrganizationSubscription $subscription,
        BillingPlan $plan,
        User $actor,
    ): array {
        $dodoSubscriptionId = $subscription->dodo_subscription_id;
        if (! is_string($dodoSubscriptionId) || $dodoSubscriptionId === '') {
            throw new RuntimeException('No Dodo subscription is linked to this organization.');
        }

        $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];
        $pendingPaymentId = isset($metadata['pending_payment_id']) && is_string($metadata['pending_payment_id'])
            ? $metadata['pending_payment_id']
            : null;

        // Succeeded or still open: never start a second charge; wait for webhook / bank.
        if ($pendingPaymentId !== null && $this->paymentChargeStillOpen($pendingPaymentId)) {
            $payable = $this->payableLinkFromPayment($pendingPaymentId);
            if ($payable !== null) {
                return [
                    'checkout_url' => $payable['checkout_url'],
                    'session_id' => $payable['session_id'],
                    'mode' => 'checkout',
                ];
            }

            return [
                'checkout_url' => url('/console/'.$organization->id.'/billing'),
                'session_id' => 'plan-change-pending',
                'mode' => 'plan_change_pending',
            ];
        }

        // Prior charge failed — allow a single on_hold dues session, not another change-plan.
        return $this->markUpgradePaymentPending(
            $organization,
            $subscription,
            $plan,
            $dodoSubscriptionId,
            $metadata,
            $pendingPaymentId,
            allowNewDuesSession: true,
        );
    }

    /**
     * Sole path that applies a paid→paid upgrade locally — called from payment.succeeded /
     * payment.failed webhooks after Dodo confirms the difference charge.
     */
    public function finalizeUpgradeAfterRemotePayment(Organization $organization, ?string $paymentId = null): void
    {
        $subscription = $this->ensureSubscription($organization)->load('plan');
        $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];
        $pendingSlug = $metadata['pending_plan_slug'] ?? null;

        if (! is_string($pendingSlug) || $pendingSlug === '' || ($metadata['pending_plan_kind'] ?? null) !== 'upgrade') {
            return;
        }

        $plan = BillingPlan::query()->where('slug', $pendingSlug)->first();
        $dodoSubscriptionId = $subscription->dodo_subscription_id;

        if ($plan === null || ! is_string($dodoSubscriptionId) || $dodoSubscriptionId === '') {
            return;
        }

        if ($paymentId !== null) {
            if (($metadata['upgrade_finalized_for_payment'] ?? null) === $paymentId) {
                return;
            }

            $metadata['upgrade_finalized_for_payment'] = $paymentId;
            $subscription->update(['metadata' => $metadata]);
            $metadata = is_array($subscription->fresh()?->metadata) ? $subscription->fresh()->metadata : $metadata;
        }

        // Grant locally only after a real (non-zero) charge succeeded — not from product_id alone.
        if ($paymentId !== null && $this->dodoPaymentSucceeded($paymentId)) {
            $this->applyUpgradeLocally($organization, $subscription, $subscription->plan, $plan, $paymentId);

            return;
        }

        if ($paymentId !== null && ! $this->dodoPaymentSucceeded($paymentId)) {
            try {
                $payment = $this->dodo->getPayment($paymentId);
                if (! $this->paymentHasPositiveAmount($payment)) {
                    return;
                }
            } catch (RuntimeException) {
                return;
            }

            $subscription->update([
                'metadata' => array_merge($metadata, [
                    'pending_requires_payment' => true,
                    'scheduled_plan_change_at' => null,
                    'pending_plan_kind' => 'upgrade',
                    'last_plan_change' => 'upgrade_payment_failed',
                    'last_plan_change_at' => now()->toIso8601String(),
                ]),
            ]);
        }
    }

    /**
     * @return array{checkout_url: string, session_id: string, mode: 'plan_changed'}
     */
    private function applyUpgradeLocally(
        Organization $organization,
        OrganizationSubscription $subscription,
        ?BillingPlan $previousPlan,
        BillingPlan $plan,
        ?string $paymentId,
    ): array {
        $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];
        $previousDodoId = $metadata['previous_dodo_subscription_id'] ?? null;

        $subscription->update([
            'billing_plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'cancelled_at' => null,
            'dodo_checkout_session_id' => null,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'metadata' => array_merge($metadata, [
                'cancel_at_period_end' => false,
                'cancels_at' => null,
                'pending_plan_id' => null,
                'pending_plan_slug' => null,
                'pending_plan_kind' => null,
                'pending_requires_payment' => false,
                'scheduled_plan_change_at' => null,
                'pending_payment_id' => $paymentId,
                'previous_dodo_subscription_id' => null,
                'upgrade_via_checkout' => null,
                'last_plan_change' => 'upgrade',
                'last_plan_change_at' => now()->toIso8601String(),
            ]),
        ]);

        if (is_string($previousDodoId) && $previousDodoId !== ''
            && $previousDodoId !== $subscription->dodo_subscription_id
            && $this->dodo->isConfigured()) {
            try {
                $this->dodo->cancelSubscriptionNow($previousDodoId);
            } catch (RuntimeException $exception) {
                Log::warning('Unable to cancel previous Dodo subscription after upgrade', [
                    'organization_id' => $organization->id,
                    'previous_subscription_id' => $previousDodoId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($previousPlan === null || $previousPlan->id !== $plan->id) {
            $this->notifier->notifyPlanChange(
                $organization,
                $previousPlan,
                $plan,
                SubscriptionStatus::Active,
            );
        }

        return [
            'checkout_url' => url('/console/'.$organization->id.'/billing'),
            'session_id' => 'plan-changed',
            'mode' => 'plan_changed',
        ];
    }

    private function subscriptionAlreadyOnPlan(string $dodoSubscriptionId, string $productId): bool
    {
        try {
            $remote = $this->dodo->getSubscription($dodoSubscriptionId);
        } catch (RuntimeException) {
            return false;
        }

        return ($remote['product_id'] ?? null) === $productId;
    }

    private function subscriptionIsOnHold(string $dodoSubscriptionId): bool
    {
        try {
            $remote = $this->dodo->getSubscription($dodoSubscriptionId);
        } catch (RuntimeException) {
            return false;
        }

        $status = $remote['status'] ?? null;

        return is_string($status) && strtolower($status) === 'on_hold';
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function paymentHasPositiveAmount(array $payment): bool
    {
        $amount = $payment['total_amount'] ?? $payment['settlement_amount'] ?? null;

        return is_numeric($amount) && (int) $amount > 0;
    }

    private function dodoPaymentHasPositiveAmount(string $paymentId): bool
    {
        try {
            $payment = $this->dodo->getPayment($paymentId);
        } catch (RuntimeException) {
            return false;
        }

        return $this->paymentHasPositiveAmount($payment);
    }

    private function dodoPaymentSucceeded(string $paymentId): bool
    {
        try {
            $payment = $this->dodo->getPayment($paymentId);
        } catch (RuntimeException) {
            return false;
        }

        $status = $payment['status'] ?? null;

        // Ignore $0 payment-method-only sessions — those are not upgrade charges.
        if (! $this->paymentHasPositiveAmount($payment)) {
            return false;
        }

        return is_string($status) && strtolower($status) === 'succeeded';
    }

    /**
     * Clear a scheduled move to Free and keep the current paid plan.
     *
     * @return array{checkout_url: string, session_id: string, mode: 'plan_changed'}
     */
    private function resumePaidPlan(
        OrganizationSubscription $subscription,
    ): array {
        $dodoSubscriptionId = $subscription->dodo_subscription_id;
        if (filled($dodoSubscriptionId) && $this->dodo->isConfigured()) {
            $this->dodo->resumeSubscription($dodoSubscriptionId);
        }

        $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];
        $subscription->update([
            'cancelled_at' => null,
            'metadata' => array_merge($metadata, [
                'cancel_at_period_end' => false,
                'cancels_at' => null,
                'pending_plan_id' => null,
                'pending_plan_slug' => null,
                'pending_plan_kind' => null,
                'pending_requires_payment' => false,
                'scheduled_plan_change_at' => null,
            ]),
        ]);

        return [
            'checkout_url' => url('/console/'.$subscription->organization_id.'/billing'),
            'session_id' => 'plan-resumed',
            'mode' => 'plan_changed',
        ];
    }

    /**
     * @return list<array{
     *     payment_id: string,
     *     amount_cents: int,
     *     currency: string,
     *     status: string|null,
     *     created_at: string|null,
     *     invoice_url: string|null,
     *     download_path: string
     * }>
     */
    public function listInvoices(Organization $organization): array
    {
        if (! $this->dodo->isConfigured()) {
            return [];
        }

        $subscription = $this->ensureSubscription($organization);
        $customerId = $subscription->dodo_customer_id;
        $subscriptionId = $subscription->dodo_subscription_id;

        if (! filled($customerId) && ! filled($subscriptionId)) {
            return [];
        }

        $payments = $this->dodo->listPayments(
            is_string($customerId) ? $customerId : null,
            is_string($subscriptionId) ? $subscriptionId : null,
        );

        return array_values(array_map(function (array $payment) use ($organization): array {
            $paymentId = (string) ($payment['payment_id'] ?? '');

            return [
                'payment_id' => $paymentId,
                'amount_cents' => (int) ($payment['total_amount'] ?? 0),
                'currency' => (string) ($payment['currency'] ?? 'USD'),
                'status' => isset($payment['status']) && is_string($payment['status']) ? $payment['status'] : null,
                'created_at' => isset($payment['created_at']) && is_string($payment['created_at'])
                    ? $payment['created_at']
                    : null,
                'invoice_url' => isset($payment['invoice_url']) && is_string($payment['invoice_url'])
                    ? $payment['invoice_url']
                    : null,
                'download_path' => "/api/v1/organizations/{$organization->id}/billing/invoices/{$paymentId}",
            ];
        }, array_filter($payments, fn (array $payment): bool => filled($payment['payment_id'] ?? null))));
    }

    public function downloadInvoice(Organization $organization, string $paymentId): string
    {
        if (! $this->dodo->isConfigured()) {
            throw new RuntimeException('Dodo Payments is not configured.');
        }

        $subscription = $this->ensureSubscription($organization);
        if (! filled($subscription->dodo_customer_id) && ! filled($subscription->dodo_subscription_id)) {
            throw new RuntimeException('No billing customer is linked to this organization.');
        }

        $payments = $this->dodo->listPayments(
            is_string($subscription->dodo_customer_id) ? $subscription->dodo_customer_id : null,
            is_string($subscription->dodo_subscription_id) ? $subscription->dodo_subscription_id : null,
        );

        $owned = collect($payments)->contains(
            fn (array $payment): bool => ($payment['payment_id'] ?? null) === $paymentId,
        );

        if (! $owned) {
            throw new RuntimeException('Invoice not found for this organization.');
        }

        return $this->dodo->downloadInvoicePdf($paymentId);
    }

    /**
     * Always schedule Free for the end of the current period — never apply Free immediately
     * while paid time remains. Cancels renewal in Dodo when a remote subscription exists.
     *
     * @return array{
     *     checkout_url: string,
     *     session_id: string,
     *     mode: 'cancel_at_period_end'|'already_on_plan'
     * }
     */
    private function switchToFree(
        Organization $organization,
        OrganizationSubscription $subscription,
        ?BillingPlan $previousPlan,
    ): array {
        $free = BillingPlan::query()->where('slug', 'free')->firstOrFail();
        $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];

        if ($previousPlan?->slug === 'free') {
            return [
                'checkout_url' => url('/console/'.$organization->id.'/billing'),
                'session_id' => 'already-free',
                'mode' => 'already_on_plan',
            ];
        }

        if ((bool) ($metadata['cancel_at_period_end'] ?? false)) {
            return [
                'checkout_url' => url('/console/'.$organization->id.'/billing'),
                'session_id' => 'cancel-at-period-end',
                'mode' => 'cancel_at_period_end',
            ];
        }

        $dodoSubscriptionId = $subscription->dodo_subscription_id;
        $cancelsAt = $subscription->current_period_end;

        if (filled($dodoSubscriptionId) && $this->dodo->isConfigured()) {
            $remote = $this->dodo->cancelSubscriptionAtPeriodEnd($dodoSubscriptionId);
            $cancelsAt = $this->resolveCancelsAt($remote, $subscription) ?? $cancelsAt;
        }

        if ($cancelsAt === null) {
            $cancelsAt = now()->addMonth();
        }

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'cancelled_at' => null,
            'current_period_end' => $cancelsAt,
            // Keep the paid plan until period end.
            'billing_plan_id' => $previousPlan?->id ?? $subscription->billing_plan_id,
            'metadata' => array_merge($metadata, [
                'cancel_at_period_end' => true,
                'cancels_at' => $cancelsAt->toIso8601String(),
                'pending_plan_id' => $free->id,
                'pending_plan_slug' => 'free',
                // Free at period end is not an upgrade — clear payment-required flags.
                'pending_plan_kind' => null,
                'pending_requires_payment' => false,
                'pending_payment_id' => null,
                'scheduled_plan_change_at' => null,
            ]),
        ]);

        return [
            'checkout_url' => url('/console/'.$organization->id.'/billing'),
            'session_id' => 'cancel-at-period-end',
            'mode' => 'cancel_at_period_end',
        ];
    }

    private function hasPendingUpgradePayment(OrganizationSubscription $subscription): bool
    {
        $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];

        return ($metadata['pending_plan_kind'] ?? null) === 'upgrade'
            && (bool) ($metadata['pending_requires_payment'] ?? false);
    }

    /**
     * True while a difference charge must not be replaced by another charge.
     */
    private function paymentChargeStillOpen(string $paymentId): bool
    {
        try {
            $payment = $this->dodo->getPayment($paymentId);
        } catch (RuntimeException) {
            return false;
        }

        if (! $this->paymentHasPositiveAmount($payment)) {
            return false;
        }

        $status = strtolower((string) ($payment['status'] ?? ''));

        return in_array($status, [
            'processing',
            'pending',
            'requires_customer_action',
            'succeeded',
        ], true);
    }

    private function isPreviousUpgradePaymentPendingError(RuntimeException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'still processing')
            || str_contains($message, 'previous_payment_pending')
            || str_contains($message, 'pending_plan_change');
    }

    /**
     * Apply Free for subscriptions whose scheduled cancellation date has passed.
     * Safety net when Dodo webhooks are delayed or for local (non-Dodo) schedules.
     *
     * @return int Number of subscriptions moved to Free
     */
    public function applyDueCancellations(): int
    {
        $free = BillingPlan::query()->where('slug', 'free')->first();
        if ($free === null) {
            return 0;
        }

        $applied = 0;

        OrganizationSubscription::query()
            ->with(['organization', 'plan'])
            ->where('billing_plan_id', '!=', $free->id)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($free, &$applied): void {
                foreach ($subscriptions as $subscription) {
                    $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];
                    if (! (bool) ($metadata['cancel_at_period_end'] ?? false)) {
                        continue;
                    }

                    $previousPlan = $subscription->plan;
                    $organization = $subscription->organization;
                    if ($organization === null) {
                        continue;
                    }

                    $subscription->update([
                        'billing_plan_id' => $free->id,
                        'status' => SubscriptionStatus::Active,
                        'dodo_subscription_id' => null,
                        'dodo_checkout_session_id' => null,
                        'cancelled_at' => now(),
                        'metadata' => array_merge($metadata, [
                            'cancel_at_period_end' => false,
                            'cancels_at' => null,
                            'pending_plan_id' => null,
                            'pending_plan_slug' => null,
                            'switched_to_free_at' => now()->toIso8601String(),
                        ]),
                    ]);

                    $this->notifier->notifyPlanChange(
                        $organization,
                        $previousPlan,
                        $free,
                        SubscriptionStatus::Cancelled,
                    );

                    $applied++;
                }
            });

        return $applied;
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function resolveCancelsAt(array $remote, OrganizationSubscription $subscription): ?Carbon
    {
        foreach (['next_billing_date', 'current_period_end', 'expires_at'] as $key) {
            $value = $remote[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return Carbon::parse($value);
            }
        }

        return $subscription->current_period_end;
    }
}
