<?php

namespace Tests\Feature;

use App\Enums\EmailTemplateSlug;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

/**
 * Light A–Z smoke of console API surfaces under /api/v1.
 * Asserts routes resolve without 500s for an authenticated org owner.
 */
class ApiSmokeTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBillingPlans();
        [$this->user, $this->organization] = $this->createOwnerWithOrganization('Smoke Org');
        $this->actingAs($this->user);
    }

    #[Test]
    public function auth_and_workspace_endpoints_respond(): void
    {
        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->getJson('/api/v1/auth/mfa')->assertOk();
        $this->getJson('/api/v1/workspace')->assertOk();
        $this->getJson('/api/v1/locales/en')->assertOk();
        $this->patchJson('/api/v1/auth/preferences', [
            'preferred_locale' => 'en',
        ])->assertOk();
    }

    #[Test]
    public function organizations_crud_surfaces_respond(): void
    {
        $this->getJson('/api/v1/organizations')->assertOk();
        $this->getJson("/api/v1/organizations/{$this->organization->id}")->assertOk();
        $this->getJson("/api/v1/organizations/{$this->organization->id}/overview/stats")->assertOk();
        $this->getJson("/api/v1/organizations/{$this->organization->id}/audit-logs")->assertOk();
        $this->getJson("/api/v1/organizations/{$this->organization->id}/end-users")->assertOk();
    }

    #[Test]
    public function members_roles_invitations_respond(): void
    {
        $org = $this->organization->id;
        $memberRole = $this->organization->roles()->where('slug', 'member')->firstOrFail();

        $this->getJson("/api/v1/organizations/{$org}/members")
            ->assertOk()
            ->assertJsonStructure(['members', 'invitations', 'invitation_history']);
        $this->getJson("/api/v1/organizations/{$org}/roles")->assertOk();
        $this->getJson('/api/v1/invitations')->assertOk();

        $invite = $this->postJson("/api/v1/organizations/{$org}/invitations", [
            'email' => 'invitee@example.com',
            'role_id' => $memberRole->id,
        ]);
        $this->assertContains($invite->status(), [201, 200], 'invite should succeed');

        $invitationId = $invite->json('data.id');
        if (is_string($invitationId) && $invitationId !== '') {
            $resend = $this->postJson("/api/v1/organizations/{$org}/invitations/{$invitationId}/resend");
            $this->assertContains($resend->status(), [200, 201], 'resend should succeed');

            $revoke = $this->deleteJson("/api/v1/organizations/{$org}/invitations/{$invitationId}");
            $this->assertContains($revoke->status(), [200, 204], 'revoke should succeed');
        }

        $roleCreate = $this->postJson("/api/v1/organizations/{$org}/roles", [
            'name' => 'Custom Role',
            'permissions' => ['members.read'],
        ]);
        $this->assertContains($roleCreate->status(), [201, 200], 'role create should succeed');
    }

    #[Test]
    public function domains_applications_oidc_respond(): void
    {
        $org = $this->organization->id;

        $this->getJson("/api/v1/organizations/{$org}/domains")->assertOk();
        $this->getJson("/api/v1/organizations/{$org}/applications")->assertOk();
        $this->getJson("/api/v1/organizations/{$org}/oidc")->assertOk();

        $app = $this->postJson("/api/v1/organizations/{$org}/applications", [
            'name' => 'Smoke App',
            'application_type' => 'web',
            'redirect_uris' => ['https://example.com/callback'],
        ]);
        $this->assertContains($app->status(), [201, 200], 'application create should succeed: '.$app->getContent());

        $clientId = $app->json('data.id');
        $this->getJson("/api/v1/organizations/{$org}/applications/{$clientId}")->assertOk();
        $this->putJson("/api/v1/organizations/{$org}/applications/{$clientId}", [
            'name' => 'Smoke App Updated',
            'redirect_uris' => ['https://example.com/callback'],
        ])->assertOk();
    }

    #[Test]
    public function email_templates_and_provider_respond(): void
    {
        $org = $this->organization->id;

        $this->getJson("/api/v1/organizations/{$org}/email-templates")->assertOk();
        $this->getJson("/api/v1/organizations/{$org}/email-provider")->assertOk();

        $template = $this->organization->emailTemplates()
            ->where('slug', EmailTemplateSlug::EmailOtp->value)
            ->firstOrFail();

        $this->postJson("/api/v1/organizations/{$org}/email-templates/{$template->id}/preview", [
            'subject' => 'Preview {{application_name}}',
            'body_html' => '<p>{{otp_code}}</p>',
        ])->assertOk();

        // Free plan: update is entitlement-blocked (422), must not 500.
        $this->putJson("/api/v1/organizations/{$org}/email-templates/{$template->id}", [
            'subject' => 'Your {{application_name}} sign-in code',
            'body_html' => '<p>{{otp_code}}</p>',
            'is_active' => true,
        ])->assertStatus(422);

        $this->upgradeToStarter($this->organization);

        $this->putJson("/api/v1/organizations/{$org}/email-templates/{$template->id}", [
            'subject' => 'Your {{application_name}} sign-in code',
            'body_html' => '<h1>Code</h1><p>{{otp_code}}</p>',
            'is_active' => true,
        ])->assertOk();
    }

    #[Test]
    public function billing_social_sso_respond(): void
    {
        $org = $this->organization->id;

        $this->getJson("/api/v1/organizations/{$org}/billing")->assertOk();
        $this->getJson("/api/v1/organizations/{$org}/billing/invoices")->assertOk();
        $this->getJson("/api/v1/organizations/{$org}/social-providers")->assertOk();
        $this->getJson("/api/v1/organizations/{$org}/sso-connections")->assertOk();

        $preview = $this->postJson("/api/v1/organizations/{$org}/billing/preview-change", [
            'plan_slug' => 'starter',
        ]);
        $this->assertContains($preview->status(), [200, 422], 'billing preview should not 500');

        $discover = $this->postJson("/api/v1/organizations/{$org}/sso-connections/discover", [
            'organization_id' => $org,
            'issuer' => 'https://example.com',
        ]);
        $this->assertContains($discover->status(), [200, 422], 'sso discover should not 500');
    }

    #[Test]
    public function public_auth_endpoints_respond_without_500(): void
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);
        $this->assertNotSame(500, $login->status());

        $forgot = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);
        $this->assertNotSame(500, $forgot->status());

        $invite = $this->getJson('/api/v1/invitations/not-a-real-token');
        $this->assertNotSame(500, $invite->status());
    }

    private function upgradeToStarter(Organization $organization): void
    {
        OrganizationSubscription::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'billing_plan_id' => $this->plan('starter')->id,
                'status' => SubscriptionStatus::Active,
                'current_period_start' => now()->startOfMonth(),
                'current_period_end' => now()->endOfMonth(),
            ],
        );
        $organization->unsetRelation('subscription');
    }
}
