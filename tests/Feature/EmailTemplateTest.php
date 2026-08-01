<?php

namespace Tests\Feature;

use App\Enums\EmailTemplateSlug;
use App\Enums\SubscriptionStatus;
use App\Models\EmailTemplate;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBillingPlans();
    }

    #[Test]
    public function index_lists_customizable_templates_with_previews(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/organizations/{$organization->id}/email-templates")
            ->assertOk()
            ->assertJsonStructure([
                'organization' => ['id', 'name', 'slug'],
                'data',
                'entitlements',
                'variables',
                'previews',
            ]);

        $slugs = collect($response->json('data'))->pluck('slug')->sort()->values()->all();
        $expected = collect(EmailTemplateSlug::organizationCustomizable())
            ->map(fn (EmailTemplateSlug $slug): string => $slug->value)
            ->sort()
            ->values()
            ->all();

        $this->assertSame($expected, $slugs);
        $this->assertCount(count($expected), $response->json('previews'));
    }

    #[Test]
    public function free_plan_cannot_update_email_templates(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $template = $organization->emailTemplates()->where('slug', EmailTemplateSlug::EmailOtp->value)->firstOrFail();

        $this->actingAs($user)
            ->putJson("/api/v1/organizations/{$organization->id}/email-templates/{$template->id}", [
                'subject' => 'Your {{application_name}} sign-in code',
                'body_html' => '<p>{{otp_code}}</p>',
                'is_active' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email_templates']);
    }

    #[Test]
    public function paid_plan_can_update_email_otp_template(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $this->upgradeToStarter($organization);

        $template = $organization->emailTemplates()->where('slug', EmailTemplateSlug::EmailOtp->value)->firstOrFail();

        $bodyHtml = <<<'HTML'
<h1 class="email-heading" style="margin:0 0 16px;font-size:22px;line-height:1.3;font-weight:700;letter-spacing:-0.02em;color:#14201E;">Your sign-in code</h1><p class="email-text" style="margin:0 0 14px;font-size:16px;line-height:1.65;color:#14201E;">Use this code to sign in to <strong>{{application_name}}</strong>:</p><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:20px 0;">
  <tr>
    <td class="email-code" align="center" style="padding:18px 16px;border-radius:10px;background:#F0F5F4;border:1px solid #d8e0de;">
      <span style="font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:28px;font-weight:700;letter-spacing:0.28em;color:#0B6E6E;">{{otp_code}}</span>
    </td>
  </tr>
</table><p class="email-muted" style="margin:16px 0 0;font-size:13px;line-height:1.55;color:#66706c;">This code expires in a few minutes. If you did not request it, you can ignore this email.</p>
HTML;

        $response = $this->actingAs($user)
            ->putJson("/api/v1/organizations/{$organization->id}/email-templates/{$template->id}", [
                'subject' => 'Your {{application_name}} sign-in code',
                'body_html' => $bodyHtml,
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.subject', 'Your {{application_name}} sign-in code')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonStructure(['preview' => ['subject', 'html', 'variables']]);

        $this->assertStringContainsString('847291', (string) $response->json('preview.html'));
        $this->assertDatabaseHas('email_templates', [
            'id' => $template->id,
            'subject' => 'Your {{application_name}} sign-in code',
        ]);
    }

    #[Test]
    public function preview_renders_draft_without_saving(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $template = $organization->emailTemplates()->where('slug', EmailTemplateSlug::EmailOtp->value)->firstOrFail();
        $originalSubject = $template->subject;

        $this->actingAs($user)
            ->postJson("/api/v1/organizations/{$organization->id}/email-templates/{$template->id}/preview", [
                'subject' => 'Draft {{application_name}}',
                'body_html' => '<p>Code: {{otp_code}}</p>',
            ])
            ->assertOk()
            ->assertJsonPath('data.subject', 'Draft '.$organization->name)
            ->assertJsonPath('data.html', fn ($html): bool => is_string($html) && str_contains($html, '847291'));

        $this->assertSame($originalSubject, $template->fresh()?->subject);
    }

    #[Test]
    public function cannot_update_non_customizable_template(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $this->upgradeToStarter($organization);

        $platform = EmailTemplate::query()->create([
            'organization_id' => $organization->id,
            'slug' => EmailTemplateSlug::Welcome->value,
            'name' => 'Welcome',
            'subject' => 'Welcome',
            'body_html' => '<p>Hi</p>',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->putJson("/api/v1/organizations/{$organization->id}/email-templates/{$platform->id}", [
                'subject' => 'Hacked',
                'body_html' => '<p>nope</p>',
                'is_active' => true,
            ])
            ->assertNotFound();
    }

    private function upgradeToStarter(Organization $organization): void
    {
        $starter = $this->plan('starter');
        OrganizationSubscription::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'billing_plan_id' => $starter->id,
                'status' => SubscriptionStatus::Active,
                'current_period_start' => now()->startOfMonth(),
                'current_period_end' => now()->endOfMonth(),
            ],
        );
        $organization->unsetRelation('subscription');
    }
}
