<?php

namespace Tests\Feature;

use App\Enums\ApplicationType;
use App\Enums\EmailTemplateSlug;
use App\Enums\UsageEventType;
use App\Jobs\CheckMauUsageAlertsJob;
use App\Models\BillingPlan;
use App\Models\OAuthClient;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\UsageEvent;
use App\Models\User;
use App\Services\Billing\BillingNotifier;
use App\Services\Billing\UsageTracker;
use App\Services\Mail\EmailUsageTracker;
use App\Services\Mail\TransactionalMailer;
use Carbon\CarbonImmutable;
use Database\Seeders\BillingPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class MauUsageAlertTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BillingPlanSeeder::class);
        config([
            'billing.enabled' => true,
            'billing.alerts.thresholds' => [80, 90, 100],
            'billing.mau.timezone' => 'UTC',
        ]);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function orgWithMauLimit(int $mauLimit): array
    {
        [$owner, $organization] = $this->createOwnerWithOrganization();

        BillingPlan::query()->whereKey($organization->subscription->billing_plan_id)
            ->update(['mau_limit' => $mauLimit]);

        $organization->unsetRelation('subscription');
        $organization->load('subscription.plan');

        return [$owner, $organization];
    }

    private function seedMau(Organization $organization, int $count): void
    {
        $day = CarbonImmutable::now('UTC')->toDateString();

        for ($i = 1; $i <= $count; $i++) {
            UsageEvent::query()->create([
                'id' => (string) Str::uuid(),
                'organization_id' => $organization->id,
                'subject_key' => 'mau-subject-'.$i,
                'event_type' => UsageEventType::UserAuthenticated,
                'occurred_on' => $day,
                'metadata' => null,
            ]);
        }
    }

    /**
     * @return list<EmailTemplateSlug>
     */
    private function collectSlugs(Organization $organization): array
    {
        $sent = [];

        $this->mock(TransactionalMailer::class, function (MockInterface $mock) use (&$sent): void {
            $mock->shouldReceive('sendPlatform')
                ->andReturnUsing(function (string $to, EmailTemplateSlug $slug) use (&$sent): void {
                    $sent[] = $slug;
                });
        });

        app(BillingNotifier::class)->checkUsageThresholds(
            $organization->fresh(['subscription.plan', 'members.role', 'members.user']),
        );

        return $sent;
    }

    #[Test]
    public function mau_sends_separate_emails_at_eighty_ninety_and_one_hundred(): void
    {
        [$owner, $organization] = $this->orgWithMauLimit(10);
        unset($owner);

        $this->seedMau($organization, 8);
        $this->assertSame([EmailTemplateSlug::MauWarning], $this->collectSlugs($organization));

        $this->app->forgetInstance(TransactionalMailer::class);
        $this->app->forgetInstance(BillingNotifier::class);
        $this->seedMauSubjects($organization, 9, 9);
        $this->assertSame([EmailTemplateSlug::MauWarning], $this->collectSlugs($organization));

        $this->app->forgetInstance(TransactionalMailer::class);
        $this->app->forgetInstance(BillingNotifier::class);
        $this->seedMauSubjects($organization, 10, 10);
        $this->assertSame([EmailTemplateSlug::MauLimitReached], $this->collectSlugs($organization));

        $metadata = $organization->subscription()->first()?->fresh()->metadata ?? [];
        $month = now('UTC')->format('Y-m');
        $this->assertNotEmpty($metadata['usage_alerts'][$month]['mau_80_sent_at'] ?? null);
        $this->assertNotEmpty($metadata['usage_alerts'][$month]['mau_90_sent_at'] ?? null);
        $this->assertNotEmpty($metadata['usage_alerts'][$month]['mau_100_sent_at'] ?? null);
    }

    #[Test]
    public function jumping_straight_to_limit_sends_all_three_threshold_emails(): void
    {
        [, $organization] = $this->orgWithMauLimit(10);
        $this->seedMau($organization, 10);

        $this->assertSame([
            EmailTemplateSlug::MauWarning,
            EmailTemplateSlug::MauWarning,
            EmailTemplateSlug::MauLimitReached,
        ], $this->collectSlugs($organization));
    }

    #[Test]
    public function owners_admins_and_billing_email_receive_limit_email_not_regular_members(): void
    {
        [$owner, $organization] = $this->orgWithMauLimit(5);

        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $adminRole = Role::query()
            ->where('organization_id', $organization->id)
            ->where('slug', 'admin')
            ->firstOrFail();

        OrganizationMember::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $admin->id,
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        $member = User::factory()->create(['email' => 'member@example.com']);
        $memberRole = Role::query()
            ->where('organization_id', $organization->id)
            ->where('slug', 'member')
            ->firstOrFail();

        OrganizationMember::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'role_id' => $memberRole->id,
            'status' => 'active',
        ]);

        $organization->update(['billing_email' => 'billing@example.com']);
        $this->seedMau($organization, 5);

        $sent = [];
        $this->mock(TransactionalMailer::class, function (MockInterface $mock) use (&$sent): void {
            $mock->shouldReceive('sendPlatform')
                ->andReturnUsing(function (string $to, EmailTemplateSlug $slug) use (&$sent): void {
                    if ($slug === EmailTemplateSlug::MauLimitReached) {
                        $sent[] = strtolower($to);
                    }
                });
        });

        app(BillingNotifier::class)->checkUsageThresholds(
            $organization->fresh(['subscription.plan', 'members.role', 'members.user']),
        );

        sort($sent);
        $expected = [
            'admin@example.com',
            'billing@example.com',
            strtolower($owner->email),
        ];
        sort($expected);
        $this->assertSame($expected, $sent);
    }

    #[Test]
    public function each_mau_threshold_is_sent_only_once_per_month(): void
    {
        [, $organization] = $this->orgWithMauLimit(10);
        $this->seedMau($organization, 8);

        $calls = 0;
        $this->mock(TransactionalMailer::class, function (MockInterface $mock) use (&$calls): void {
            $mock->shouldReceive('sendPlatform')
                ->andReturnUsing(function () use (&$calls): void {
                    $calls++;
                });
        });

        $notifier = app(BillingNotifier::class);
        $notifier->checkUsageThresholds($organization->fresh(['subscription.plan']));
        $notifier->checkUsageThresholds($organization->fresh(['subscription.plan']));

        $this->assertSame(1, $calls);
    }

    #[Test]
    public function usage_event_dispatches_alert_job_that_emails_owner(): void
    {
        [$owner, $organization] = $this->orgWithMauLimit(10);
        $this->seedMau($organization, 7);

        Bus::fake([CheckMauUsageAlertsJob::class]);

        app(UsageTracker::class)->record(
            $organization,
            UsageEventType::UserAuthenticated,
            'mau-subject-8',
        );

        Bus::assertDispatched(CheckMauUsageAlertsJob::class);

        $sent = [];
        $this->mock(TransactionalMailer::class, function (MockInterface $mock) use (&$sent): void {
            $mock->shouldReceive('sendPlatform')
                ->andReturnUsing(function (string $to, EmailTemplateSlug $slug) use (&$sent): void {
                    $sent[] = ['to' => strtolower($to), 'slug' => $slug];
                });
        });

        (new CheckMauUsageAlertsJob($organization->id))->handle(app(BillingNotifier::class));

        $this->assertSame([
            ['to' => strtolower($owner->email), 'slug' => EmailTemplateSlug::MauWarning],
        ], $sent);
    }

    #[Test]
    public function application_thresholds_email_at_eighty_ninety_and_one_hundred(): void
    {
        [$owner, $organization] = $this->createOwnerWithOrganization();
        unset($owner);

        BillingPlan::query()->whereKey($organization->subscription->billing_plan_id)
            ->update(['application_limit' => 10]);

        for ($i = 1; $i <= 8; $i++) {
            $this->createApp($organization, 'App '.$i);
        }

        $this->assertSame(
            [EmailTemplateSlug::ApplicationWarning],
            $this->collectApplicationSlugs($organization),
        );

        $this->app->forgetInstance(TransactionalMailer::class);
        $this->app->forgetInstance(BillingNotifier::class);
        $this->createApp($organization, 'App 9');
        $this->assertSame(
            [EmailTemplateSlug::ApplicationWarning],
            $this->collectApplicationSlugs($organization),
        );

        $this->app->forgetInstance(TransactionalMailer::class);
        $this->app->forgetInstance(BillingNotifier::class);
        $this->createApp($organization, 'App 10');
        $this->assertSame(
            [EmailTemplateSlug::ApplicationLimitReached],
            $this->collectApplicationSlugs($organization),
        );
    }

    #[Test]
    public function platform_email_daily_thresholds_email_owners(): void
    {
        [$owner, $organization] = $this->createOwnerWithOrganization();

        BillingPlan::query()->whereKey($organization->subscription->billing_plan_id)
            ->update([
                'email_daily_limit' => 10,
                'email_monthly_limit' => 10_000,
            ]);

        DB::table('email_usage_daily')->insert([
            'organization_id' => $organization->id,
            'day' => now()->toDateString(),
            'count' => 7,
        ]);
        DB::table('email_usage_monthly')->insert([
            'organization_id' => $organization->id,
            'year_month' => now()->format('Y-m'),
            'count' => 7,
        ]);

        $sent = [];
        $this->mock(TransactionalMailer::class, function (MockInterface $mock) use (&$sent): void {
            $mock->shouldReceive('sendPlatform')
                ->andReturnUsing(function (string $to, EmailTemplateSlug $slug) use (&$sent): void {
                    $sent[] = $slug;
                });
        });

        // Increment to 8/10 = 80%.
        app(EmailUsageTracker::class)->increment($organization->fresh(['subscription.plan']));

        $this->assertContains(EmailTemplateSlug::EmailUsageWarning, $sent);
        $this->assertSame(strtolower($owner->email), strtolower($owner->email));
    }

    #[Test]
    public function no_quota_emails_when_billing_disabled(): void
    {
        [, $organization] = $this->orgWithMauLimit(5);
        $this->seedMau($organization, 5);

        config(['billing.enabled' => false]);

        $calls = 0;
        $this->mock(TransactionalMailer::class, function (MockInterface $mock) use (&$calls): void {
            $mock->shouldReceive('sendPlatform')->andReturnUsing(function () use (&$calls): void {
                $calls++;
            });
        });

        app(BillingNotifier::class)->checkUsageThresholds($organization->fresh(['subscription.plan']));

        $this->assertSame(0, $calls);
    }

    private function seedMauSubjects(Organization $organization, int $from, int $to): void
    {
        $day = CarbonImmutable::now('UTC')->toDateString();

        for ($i = $from; $i <= $to; $i++) {
            UsageEvent::query()->create([
                'id' => (string) Str::uuid(),
                'organization_id' => $organization->id,
                'subject_key' => 'mau-subject-'.$i,
                'event_type' => UsageEventType::UserAuthenticated,
                'occurred_on' => $day,
                'metadata' => null,
            ]);
        }
    }

    private function createApp(Organization $organization, string $name): void
    {
        OAuthClient::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'application_type' => ApplicationType::Spa,
            'redirect_uris' => ['https://app.example.com/callback'],
            'grant_types' => ['authorization_code'],
            'is_confidential' => false,
            'login_methods' => [],
        ]);
    }

    /**
     * @return list<EmailTemplateSlug>
     */
    private function collectApplicationSlugs(Organization $organization): array
    {
        $sent = [];

        $this->mock(TransactionalMailer::class, function (MockInterface $mock) use (&$sent): void {
            $mock->shouldReceive('sendPlatform')
                ->andReturnUsing(function (string $to, EmailTemplateSlug $slug) use (&$sent): void {
                    $sent[] = $slug;
                });
        });

        app(BillingNotifier::class)->checkApplicationThresholds(
            $organization->fresh(['subscription.plan', 'members.role', 'members.user']),
        );

        return $sent;
    }
}
