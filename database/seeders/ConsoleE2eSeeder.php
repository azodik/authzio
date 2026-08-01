<?php

namespace Database\Seeders;

use App\Enums\ApplicationType;
use App\Enums\EmailProviderDriver;
use App\Enums\UsageEventType;
use App\Models\BillingPlan;
use App\Models\OAuthClient;
use App\Models\Organization;
use App\Models\OrganizationEmailProvider;
use App\Models\UsageEvent;
use App\Models\User;
use App\Services\Auth\LoginMethods;
use App\Services\OrganizationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Console operator fixtures for Playwright E2E.
 *
 * Email: e2e-owner@authzio.test
 * Password: E2eTestPass123!
 */
class ConsoleE2eSeeder extends Seeder
{
    public const OWNER_EMAIL = 'e2e-owner@authzio.test';

    public const OWNER_PASSWORD = 'E2eTestPass123!';

    public const ORG_NAME = 'E2E Org';

    public const ORG_SLUG = 'e2e-org';

    public const OIDC_USER_EMAIL = 'e2e-oidc-user@authzio.test';

    public const OIDC_USER_PASSWORD = 'E2eTestPass123!';

    /** Dedicated end-user for hosted password-reset E2E (mutates password). */
    public const OIDC_RESET_USER_EMAIL = 'e2e-oidc-reset@authzio.test';

    public const OIDC_RESET_USER_PASSWORD = 'E2eTestPass123!';

    public const OIDC_CLIENT_ID = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeee1';

    public const OIDC_REDIRECT_URI = 'http://127.0.0.1:8000/__oidc_e2e_callback';

    public function run(): void
    {
        $this->call(BillingPlanSeeder::class);

        // Free plan normally allows 1 app; E2E seeds an OAuth client and still creates apps in UI.
        BillingPlan::query()->where('slug', 'free')->update([
            'application_limit' => 10,
        ]);

        /** @var OrganizationService $organizations */
        $organizations = app(OrganizationService::class);
        $organizations->syncPermissionCatalog();

        $owner = User::query()->updateOrCreate(
            ['email' => self::OWNER_EMAIL],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'E2E Owner',
                'password' => Hash::make(self::OWNER_PASSWORD),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $organization = Organization::query()->where('slug', self::ORG_SLUG)->first();
        if ($organization === null) {
            $organization = $organizations->create($owner, self::ORG_NAME, self::ORG_SLUG);
        }

        $endUser = User::query()->updateOrCreate(
            ['email' => self::OIDC_USER_EMAIL],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'E2E OIDC User',
                'password' => Hash::make(self::OIDC_USER_PASSWORD),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => self::OIDC_RESET_USER_EMAIL],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'E2E OIDC Reset User',
                'password' => Hash::make(self::OIDC_RESET_USER_PASSWORD),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $client = OAuthClient::query()->updateOrCreate(
            ['id' => self::OIDC_CLIENT_ID],
            [
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
                'name' => 'E2E OAuth App',
                'application_type' => ApplicationType::Spa,
                'description' => 'Hosted login fixture for Playwright',
                'redirect_uris' => [self::OIDC_REDIRECT_URI],
                'grant_types' => ['authorization_code', 'refresh_token'],
                'is_confidential' => false,
                'login_headline' => 'Sign in',
                'login_description' => 'E2E OAuth relying party',
                'login_button_label' => 'Continue',
                'show_signup_link' => false,
                'show_forgot_password_link' => true,
                'primary_color' => '#0F766E',
                'background_color' => '#F4F7F6',
                'login_methods' => array_merge(LoginMethods::defaults(), [
                    'password' => true,
                    'passkey' => false,
                    'email_otp' => true,
                    'require_verified_email' => false,
                ]),
                'require_legal_accept' => false,
            ],
        );

        // Mailpit SMTP so org-scoped mail still works after billing E2E upgrades the plan.
        OrganizationEmailProvider::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'driver' => EmailProviderDriver::Smtp,
                'credentials' => [
                    'host' => '127.0.0.1',
                    'port' => 1025,
                    'encryption' => null,
                    'username' => null,
                    'password' => null,
                ],
                'from_address' => 'noreply@authzio.test',
                'from_name' => self::ORG_NAME,
                'is_active' => true,
                'verified_at' => now(),
                'last_error' => null,
            ],
        );

        $this->seedUsageFixtures($organization);

        $fixturesPath = storage_path('app/e2e-fixtures.json');
        File::ensureDirectoryExists(dirname($fixturesPath));
        File::put($fixturesPath, json_encode([
            'organization_id' => $organization->id,
            'organization_slug' => $organization->slug,
            'owner_email' => self::OWNER_EMAIL,
            'oauth_client_id' => $client->id,
            'oauth_redirect_uri' => self::OIDC_REDIRECT_URI,
            'oidc_user_email' => self::OIDC_USER_EMAIL,
            'oidc_user_password' => self::OIDC_USER_PASSWORD,
            'oidc_user_id' => $endUser->id,
            'oidc_reset_user_email' => self::OIDC_RESET_USER_EMAIL,
            'oidc_reset_user_password' => self::OIDC_RESET_USER_PASSWORD,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->command?->info('Console E2E fixtures ready.');
        $this->command?->line('  Owner: '.self::OWNER_EMAIL.' / '.self::OWNER_PASSWORD);
        $this->command?->line('  Org:   '.$organization->id.' ('.self::ORG_SLUG.')');
        $this->command?->line('  OAuth: '.$client->id);
        $this->command?->line('  Fixtures: storage/app/e2e-fixtures.json');
    }

    private function seedUsageFixtures(Organization $organization): void
    {
        $today = now()->toDateString();
        $month = now()->format('Y-m');

        DB::table('email_usage_daily')->upsert(
            [
                'organization_id' => $organization->id,
                'day' => $today,
                'count' => 42,
            ],
            ['organization_id', 'day'],
            ['count' => 42],
        );

        DB::table('email_usage_monthly')->upsert(
            [
                'organization_id' => $organization->id,
                'year_month' => $month,
                'count' => 420,
            ],
            ['organization_id', 'year_month'],
            ['count' => 420],
        );

        foreach (range(1, 3) as $index) {
            UsageEvent::query()->create([
                'organization_id' => $organization->id,
                'event_type' => UsageEventType::UserAuthenticated,
                'subject_key' => 'e2e-user-'.$index,
                'occurred_on' => $today,
                'created_at' => now(),
            ]);
        }
    }
}
