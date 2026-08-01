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

/**
 * Creates a dedicated end-user + SPA client for OIDC E2E verification.
 *
 * Attaches the client to the first organization so localhost issuer resolution works
 * (IssuerResolver falls back to the earliest org when no subdomain/custom domain matches).
 *
 * Email: oidc-e2e@authzio.test
 * Password: SecurePass123!
 */
class OidcE2eSeeder extends Seeder
{
    public const EMAIL = 'oidc-e2e@authzio.test';

    public const PASSWORD = 'SecurePass123!';

    public const REDIRECT_URI = 'http://127.0.0.1:8000/__oidc_e2e_callback';

    public function run(): void
    {
        $this->call(BillingPlanSeeder::class);

        /** @var OrganizationService $organizations */
        $organizations = app(OrganizationService::class);
        $organizations->syncPermissionCatalog();

        $owner = User::query()->updateOrCreate(
            ['email' => 'oidc-owner@authzio.test'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'OIDC Owner',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $organization = Organization::query()->orderBy('created_at')->first();
        if ($organization === null) {
            $organization = $organizations->create($owner, 'OIDC E2E', 'oidc-e2e');
        }

        $endUser = User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'OIDC E2E User',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $client = OAuthClient::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'OIDC E2E RP',
            ],
            [
                'user_id' => $owner->id,
                'application_type' => ApplicationType::Spa,
                'description' => 'Relying party for OIDC browser / Feature E2E',
                'redirect_uris' => [self::REDIRECT_URI, 'https://rp.example.com/oidc/callback'],
                'grant_types' => ['authorization_code', 'refresh_token'],
                'is_confidential' => false,
                'login_headline' => 'Sign in',
                'login_description' => 'OIDC E2E relying party',
                'login_button_label' => 'Continue',
                'show_signup_link' => false,
                'show_forgot_password_link' => true,
                'primary_color' => '#0F766E',
                'background_color' => '#F4F7F6',
                'login_methods' => array_merge(LoginMethods::defaults(), [
                    'password' => true,
                    'passkey' => true,
                    'email_otp' => true,
                    'require_verified_email' => false,
                ]),
                'require_legal_accept' => false,
            ],
        );

        $this->command?->info('OIDC E2E fixtures ready.');
        $this->command?->line('  Org:          '.$organization->id.' (slug: '.$organization->slug.')');
        $this->command?->line('  End user:     '.self::EMAIL.' / '.self::PASSWORD);
        $this->command?->line('  Client ID:    '.$client->id);
        $this->command?->line('  Redirect URI: '.self::REDIRECT_URI);
        $this->command?->line('  End-user ID:  '.$endUser->id);
    }
}
