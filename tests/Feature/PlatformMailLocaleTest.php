<?php

namespace Tests\Feature;

use App\Enums\EmailTemplateSlug;
use App\Enums\SupportedLocale;
use App\Jobs\SendRenderedMailJob;
use App\Models\User;
use App\Services\Mail\TransactionalMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlatformMailLocaleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function system_email_uses_recipient_preferred_locale_for_subject_and_body(): void
    {
        Bus::fake([SendRenderedMailJob::class]);

        $user = User::factory()->create([
            'email' => 'locale-user@example.com',
            'preferred_locale' => SupportedLocale::Hi->value,
            'name' => 'Locale User',
        ]);

        app(TransactionalMailer::class)->sendPlatform(
            $user->email,
            EmailTemplateSlug::MauWarning,
            [
                'organization_name' => 'Acme',
                'plan_name' => 'Starter',
                'mau_count' => '8',
                'mau_limit' => '10',
                'utilization_percent' => '80.0',
                'threshold_percent' => '80',
                'billing_url' => 'http://localhost/console/org-id/billing',
                'user_name' => $user->name,
            ],
        );

        Bus::assertDispatched(SendRenderedMailJob::class, function (SendRenderedMailJob $job) use ($user): bool {
            $this->assertSame($user->email, $job->to);
            $this->assertStringContainsString('80%', $job->subject);
            $this->assertStringContainsString('सीमा', $job->subject);
            $this->assertStringContainsString('lang="hi"', $job->html);
            $this->assertStringContainsString('MAU सीमा निकट', $job->html);
            $this->assertStringContainsString('प्लान अपग्रेड करें', $job->html);

            return true;
        });
    }

    #[Test]
    public function system_email_defaults_to_english_without_preferred_locale(): void
    {
        Bus::fake([SendRenderedMailJob::class]);

        app(TransactionalMailer::class)->sendPlatform(
            'nobody-locale@example.com',
            EmailTemplateSlug::MauLimitReached,
            [
                'organization_name' => 'Acme',
                'plan_name' => 'Starter',
                'mau_count' => '10',
                'mau_limit' => '10',
                'utilization_percent' => '100.0',
                'threshold_percent' => '100',
                'billing_url' => 'http://localhost/console/org-id/billing',
            ],
        );

        Bus::assertDispatched(SendRenderedMailJob::class, function (SendRenderedMailJob $job): bool {
            $this->assertStringContainsString('lang="en"', $job->html);
            $this->assertStringContainsString('MAU limit reached', $job->html);
            $this->assertStringContainsString('Upgrade now', $job->html);

            return true;
        });
    }

    #[Test]
    public function explicit_locale_argument_overrides_preferred_locale(): void
    {
        Bus::fake([SendRenderedMailJob::class]);

        $user = User::factory()->create([
            'email' => 'fr-override@example.com',
            'preferred_locale' => SupportedLocale::De->value,
        ]);

        app(TransactionalMailer::class)->sendPlatform(
            $user->email,
            EmailTemplateSlug::MauWarning,
            [
                'organization_name' => 'Acme',
                'plan_name' => 'Starter',
                'mau_count' => '8',
                'mau_limit' => '10',
                'utilization_percent' => '80.0',
                'threshold_percent' => '80',
                'billing_url' => 'http://localhost/console/org-id/billing',
            ],
            locale: SupportedLocale::Fr->value,
        );

        Bus::assertDispatched(SendRenderedMailJob::class, function (SendRenderedMailJob $job): bool {
            $this->assertStringContainsString('lang="fr"', $job->html);
            $this->assertStringContainsString('Limite MAU approchée', $job->html);
            $this->assertStringContainsString('Mettre à niveau', $job->html);

            return true;
        });
    }
}
