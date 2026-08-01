<?php

namespace Database\Seeders;

use App\Models\BillingPlan;
use Illuminate\Database\Seeder;

class BillingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'free',
                'name' => 'Free',
                'description' => 'For evaluation and small projects.',
                'mau_limit' => 1_000,
                'application_limit' => 1,
                'allows_custom_domains' => false,
                'allows_email_customization' => false,
                'allows_login_customization' => true,
                'allows_custom_jwks' => false,
                'allows_custom_email_provider' => false,
                'allows_sso' => false,
                'email_daily_limit' => 100,
                'email_monthly_limit' => 3000,
                'price_cents_monthly' => 0,
                'dodo_product_id' => null,
                'is_public' => true,
                'is_self_serve' => true,
                'sort_order' => 10,
                'features' => [
                    '1,000 MAU',
                    '1 application',
                    'Managed OIDC JWKS (auto-generated)',
                    'Authzio subdomain only',
                ],
            ],
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'description' => 'Ship production login with custom domains and branded email.',
                'mau_limit' => 5_000,
                'application_limit' => 5,
                'allows_custom_domains' => true,
                'allows_email_customization' => true,
                'allows_login_customization' => true,
                'allows_custom_jwks' => false,
                'allows_custom_email_provider' => true,
                'allows_sso' => false,
                'email_daily_limit' => null,
                'email_monthly_limit' => null,
                'price_cents_monthly' => 500,
                'dodo_product_id' => config('billing.dodo.products.starter'),
                'is_public' => true,
                'is_self_serve' => true,
                'sort_order' => 20,
                'features' => [
                    '5,000 MAU',
                    '5 applications',
                    'Custom domains',
                    'Email customization + BYO email',
                ],
            ],
            [
                'slug' => 'growth',
                'name' => 'Growth',
                'description' => 'Higher MAU ceilings and enterprise OIDC SSO for multi-product teams.',
                'mau_limit' => 50_000,
                'application_limit' => null,
                'allows_custom_domains' => true,
                'allows_email_customization' => true,
                'allows_login_customization' => true,
                'allows_custom_jwks' => false,
                'allows_custom_email_provider' => true,
                'allows_sso' => true,
                'email_daily_limit' => null,
                'email_monthly_limit' => null,
                'price_cents_monthly' => 2_000,
                'dodo_product_id' => config('billing.dodo.products.growth'),
                'is_public' => true,
                'is_self_serve' => true,
                'sort_order' => 30,
                'features' => [
                    '50,000 MAU',
                    'Unlimited applications',
                    'Everything in Starter',
                    'Usage analytics',
                    'OIDC enterprise SSO',
                ],
            ],
            [
                'slug' => 'scale',
                'name' => 'Scale',
                'description' => 'Large tenants with custom signing keys and onboarding.',
                'mau_limit' => 250_000,
                'application_limit' => null,
                'allows_custom_domains' => true,
                'allows_email_customization' => true,
                'allows_login_customization' => true,
                'allows_custom_jwks' => true,
                'allows_custom_email_provider' => true,
                'allows_sso' => true,
                'email_daily_limit' => null,
                'email_monthly_limit' => null,
                'price_cents_monthly' => 9_900,
                'dodo_product_id' => config('billing.dodo.products.scale'),
                'is_public' => true,
                'is_self_serve' => true,
                'sort_order' => 40,
                'features' => [
                    '250,000 MAU',
                    'Everything in Growth',
                    'Import custom JWKS / signing keys',
                    'Dedicated onboarding',
                    'SLA options',
                ],
            ],
            [
                'slug' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'Custom MAU, contracts, and support.',
                'mau_limit' => 1_000_000,
                'application_limit' => null,
                'allows_custom_domains' => true,
                'allows_email_customization' => true,
                'allows_login_customization' => true,
                'allows_custom_jwks' => true,
                'allows_custom_email_provider' => true,
                'allows_sso' => true,
                'email_daily_limit' => null,
                'email_monthly_limit' => null,
                'price_cents_monthly' => 0,
                'dodo_product_id' => null,
                'is_public' => true,
                'is_self_serve' => false,
                'sort_order' => 50,
                'features' => [
                    'Custom MAU',
                    'Bring-your-own JWKS',
                    'SAML / OIDC federation',
                    'Contact sales',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            BillingPlan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                $plan,
            );
        }
    }
}
