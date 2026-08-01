<?php

namespace Database\Seeders;

use App\Enums\ApplicationType;
use App\Models\OAuthClient;
use App\Models\Organization;
use App\Models\User;
use App\Services\Auth\LoginMethods;
use App\Services\OrganizationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthzioSeeder extends Seeder
{
    /**
     * Demo console credentials:
     * Email: demo@authzio.com
     * Password: AuthzioDemo2026!
     */
    public function run(): void
    {
        $this->call(BillingPlanSeeder::class);

        /** @var OrganizationService $organizationService */
        $organizationService = app(OrganizationService::class);
        $organizationService->syncPermissionCatalog();

        $demoUser = User::query()->updateOrCreate(
            ['email' => 'demo@authzio.com'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Demo User',
                'password' => Hash::make('AuthzioDemo2026!'),
                'email_verified_at' => now(),
                'is_active' => true,
                'is_demo' => true,
                'preferred_locale' => 'en',
                'theme' => 'light',
            ],
        );
        $demoUser->forceFill(['is_demo' => true])->save();

        if ($demoUser === null) {
            return;
        }

        if (! Organization::query()->where('slug', 'demo-org')->exists()) {
            $organization = $organizationService->create($demoUser, 'Demo Org', 'demo-org');
            $organization->update([
                'billing_email' => 'billing@authzio.com',
                'is_demo' => true,
            ]);
        }

        $organization = Organization::query()->where('slug', 'demo-org')->first();
        if ($organization === null) {
            return;
        }

        $organization->forceFill(['is_demo' => true])->save();

        OAuthClient::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'Acme Storefront',
            ],
            [
                'user_id' => $demoUser->id,
                'application_type' => ApplicationType::Spa,
                'description' => 'Demo SPA for exploring Authzio',
                'secret' => null,
                'redirect_uris' => ['https://app.example.com/callback'],
                'grant_types' => ApplicationType::Spa->defaultGrantTypes(),
                'is_confidential' => false,
                'is_first_party' => true,
                'login_headline' => 'Welcome back',
                'login_description' => 'Sign in to continue to Acme Storefront.',
                'login_button_label' => 'Continue',
                'show_signup_link' => true,
                'show_forgot_password_link' => true,
                'primary_color' => '#0F766E',
                'background_color' => '#F4F7F6',
                'password_policy' => [
                    'min_length' => (int) config('authzio.password.min_length', 12),
                    'require_mixed_case' => (bool) config('authzio.password.require_mixed_case', true),
                    'require_numbers' => (bool) config('authzio.password.require_numbers', true),
                    'require_symbols' => (bool) config('authzio.password.require_symbols', true),
                ],
                'security_policy' => [
                    'mfa_required' => false,
                    'session_lifetime_minutes' => (int) config('authzio.session.lifetime_minutes', 120),
                    'single_device' => (bool) config('authzio.session.single_device', false),
                ],
                'login_methods' => LoginMethods::defaults(),
                'require_legal_accept' => false,
            ],
        );
    }
}
