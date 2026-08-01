<?php

namespace App\Services\Billing;

use App\Models\BillingPlan;
use App\Models\OAuthClient;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PlanEntitlements
{
    public function __construct(
        private readonly BillingService $billingService,
    ) {}

    /**
     * @return array{
     *     plan_slug: string,
     *     plan_name: string,
     *     is_free: bool,
     *     application_limit: int|null,
     *     application_count: int,
     *     can_create_application: bool,
     *     allows_custom_domains: bool,
     *     allows_email_customization: bool,
     *     allows_login_customization: bool,
     *     allows_custom_jwks: bool,
     *     allows_custom_email_provider: bool,
     *     allows_sso: bool,
     *     email_daily_limit: int|null,
     *     email_monthly_limit: int|null,
     *     demo_facade?: bool
     * }
     */
    public function forOrganization(Organization $organization, ?User $viewer = null): array
    {
        $viewer ??= auth()->user() instanceof User ? auth()->user() : null;

        $subscription = $this->billingService->ensureSubscription($organization)->loadMissing('plan');
        $plan = $subscription->plan;
        $applicationCount = OAuthClient::query()
            ->where('organization_id', $organization->id)
            ->whereNull('revoked_at')
            ->count();

        $facade = $organization->isDemo()
            && $viewer !== null
            && $viewer->isDemo();

        if ($facade) {
            $growth = BillingPlan::query()
                ->where('slug', (string) config('demo.entitlement_plan_slug', 'growth'))
                ->first();
            if ($growth !== null) {
                $plan = $growth;
            }
        }

        $limit = $plan->application_limit;
        $canCreate = $limit === null || $applicationCount < $limit;

        return [
            'plan_slug' => $plan->slug,
            'plan_name' => $plan->name,
            'is_free' => $plan->slug === 'free',
            'application_limit' => $limit,
            'application_count' => $applicationCount,
            'can_create_application' => $canCreate,
            // Domains stay locked at the demo policy boundary even when the façade
            // otherwise mirrors Growth entitlements in the console.
            'allows_custom_domains' => $facade ? false : (bool) $plan->allows_custom_domains,
            'allows_email_customization' => (bool) $plan->allows_email_customization,
            'allows_login_customization' => (bool) $plan->allows_login_customization,
            'allows_custom_jwks' => (bool) $plan->allows_custom_jwks,
            'allows_custom_email_provider' => (bool) $plan->allows_custom_email_provider,
            'allows_sso' => (bool) $plan->allows_sso,
            'email_daily_limit' => $plan->email_daily_limit !== null ? (int) $plan->email_daily_limit : null,
            'email_monthly_limit' => $plan->email_monthly_limit !== null ? (int) $plan->email_monthly_limit : null,
            'demo_facade' => $facade,
        ];
    }

    public function assertCanCreateApplication(Organization $organization): void
    {
        $entitlements = $this->forOrganization($organization);
        if ($entitlements['can_create_application']) {
            return;
        }

        $limit = $entitlements['application_limit'] ?? 0;
        throw ValidationException::withMessages([
            'application' => [__('Your plan allows :limit application(s). Upgrade to add more.', ['limit' => $limit])],
        ]);
    }

    public function assertCustomDomains(Organization $organization): void
    {
        if ($this->forOrganization($organization)['allows_custom_domains']) {
            return;
        }

        throw ValidationException::withMessages([
            'domain' => [__('Custom domains require a paid plan.')],
        ]);
    }

    public function assertEmailCustomization(Organization $organization): void
    {
        if ($this->forOrganization($organization)['allows_email_customization']) {
            return;
        }

        throw ValidationException::withMessages([
            'email_templates' => [__('Editable email templates require a paid plan.')],
        ]);
    }

    public function assertLoginCustomization(Organization $organization): void
    {
        if ($this->forOrganization($organization)['allows_login_customization']) {
            return;
        }

        throw ValidationException::withMessages([
            'branding' => [__('Login customization is not available on this plan.')],
        ]);
    }

    public function assertCustomJwks(Organization $organization): void
    {
        if ($this->forOrganization($organization)['allows_custom_jwks']) {
            return;
        }

        throw ValidationException::withMessages([
            'jwks' => [__('Custom JWKS requires a paid plan.')],
        ]);
    }

    public function assertCustomEmailProvider(Organization $organization): void
    {
        if ($this->forOrganization($organization)['allows_custom_email_provider']) {
            return;
        }

        throw ValidationException::withMessages([
            'email_provider' => [__('Custom email providers require a paid plan. Free plans use Authzio mail with daily and monthly caps.')],
        ]);
    }

    public function assertSso(Organization $organization): void
    {
        if ($this->forOrganization($organization)['allows_sso']) {
            return;
        }

        throw ValidationException::withMessages([
            'sso' => [__('Enterprise SSO requires the Growth plan or higher.')],
        ]);
    }
}
