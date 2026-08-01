<?php

namespace Tests\Unit;

use App\Enums\EmailTemplateSlug;
use App\Services\Mail\EmailHtml;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailHtmlTest extends TestCase
{
    #[Test]
    public function wrap_uses_app_name_footer_and_logos_with_dark_mode_support(): void
    {
        config(['app.name' => 'Authzio', 'app.url' => 'https://authzio.test']);

        $html = EmailHtml::wrap(EmailHtml::paragraph('Hello'), 'Authzio');

        $this->assertStringContainsString('© '.date('Y').' Authzio', $html);
        $this->assertStringNotContainsString('Azodik Consulting', $html);
        $this->assertStringNotContainsString('Developed in India', $html);
        $this->assertStringContainsString('/images/email-logo.png', $html);
        $this->assertStringContainsString('/images/email-logo-dark.png', $html);
        $this->assertStringContainsString('color-scheme', $html);
        $this->assertStringContainsString('prefers-color-scheme: dark', $html);
    }

    #[Test]
    public function organization_wrap_uses_text_brand_without_authzio_logo(): void
    {
        config(['app.name' => 'Authzio', 'app.url' => 'https://authzio.test']);

        $html = EmailHtml::wrap(EmailHtml::paragraph('Hello'), 'Acme Corp', includeLogo: false);

        $this->assertStringContainsString('Acme Corp', $html);
        $this->assertStringContainsString('© '.date('Y').' Acme Corp', $html);
        $this->assertStringNotContainsString('/images/email-logo.png', $html);
        $this->assertStringNotContainsString('/images/email-logo-dark.png', $html);
        $this->assertStringNotContainsString('email-logo', $html);
    }

    #[Test]
    public function system_templates_render_placeholders_and_do_not_embed_full_document(): void
    {
        $html = EmailTemplateSlug::EmailVerification->defaultHtml();

        $this->assertStringContainsString('{{verification_code}}', $html);
        $this->assertStringContainsString('{{verify_url}}', $html);
        $this->assertStringContainsString('{{product_name}}', $html);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $html);
        $this->assertStringNotContainsString('Azodik Consulting', $html);
    }

    #[Test]
    public function plan_emails_include_billing_cta_placeholder(): void
    {
        foreach ([
            EmailTemplateSlug::PlanUpgraded,
            EmailTemplateSlug::PlanDowngraded,
            EmailTemplateSlug::PlanCancelled,
            EmailTemplateSlug::MauWarning,
            EmailTemplateSlug::MauLimitReached,
            EmailTemplateSlug::ApplicationWarning,
            EmailTemplateSlug::ApplicationLimitReached,
            EmailTemplateSlug::EmailUsageWarning,
            EmailTemplateSlug::EmailUsageLimitReached,
        ] as $slug) {
            $html = $slug->defaultHtml();
            $this->assertStringContainsString('{{billing_url}}', $html, $slug->value);
        }
    }
}
