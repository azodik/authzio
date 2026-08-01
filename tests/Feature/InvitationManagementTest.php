<?php

namespace Tests\Feature;

use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Services\Mail\TransactionalMailer;
use App\Services\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class InvitationManagementTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBillingPlans();
        Http::fake();
    }

    #[Test]
    public function members_index_includes_pending_and_history(): void
    {
        [$owner, $organization] = $this->createOwnerWithOrganization('Invite Org');
        $memberRole = $organization->roles()->where('slug', 'member')->firstOrFail();

        $pending = OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
            'email' => 'pending@example.com',
            'role_id' => $memberRole->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        $accepted = OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
            'email' => 'accepted@example.com',
            'role_id' => $memberRole->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'accepted_at' => now()->subDay(),
        ]);

        $revoked = OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
            'email' => 'revoked@example.com',
            'role_id' => $memberRole->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'revoked_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($owner)
            ->getJson("/api/v1/organizations/{$organization->id}/members")
            ->assertOk()
            ->assertJsonStructure([
                'members',
                'invitations',
                'invitation_history',
            ]);

        $this->assertSame([$pending->id], collect($response->json('invitations'))->pluck('id')->all());
        $historyIds = collect($response->json('invitation_history'))->pluck('id')->all();
        $this->assertContains($accepted->id, $historyIds);
        $this->assertContains($revoked->id, $historyIds);
        $this->assertSame('pending', $response->json('invitations.0.status'));
        $this->assertArrayNotHasKey('token', $response->json('invitations.0'));
    }

    #[Test]
    public function owner_can_invite_via_api_then_resend_and_revoke(): void
    {
        [$owner, $organization] = $this->createOwnerWithOrganization('Resend Org');
        $memberRole = $organization->roles()->where('slug', 'member')->firstOrFail();

        $mailer = \Mockery::mock(TransactionalMailer::class);
        $mailer->shouldReceive('sendPlatform')->twice();
        $this->app->instance(TransactionalMailer::class, $mailer);

        $created = $this->actingAs($owner)
            ->postJson("/api/v1/organizations/{$organization->id}/invitations", [
                'email' => 'resend@example.com',
                'role_id' => $memberRole->id,
            ])
            ->assertCreated();

        $invitationId = $created->json('data.id');
        $this->assertNotEmpty($invitationId);

        $before = OrganizationInvitation::query()->findOrFail($invitationId);
        $oldToken = $before->token;

        $this->actingAs($owner)
            ->postJson("/api/v1/organizations/{$organization->id}/invitations/{$invitationId}/resend")
            ->assertOk()
            ->assertJsonPath('message', 'Invitation resent.');

        $before->refresh();
        $this->assertNotSame($oldToken, $before->token);
        $this->assertTrue($before->expires_at->isFuture());
        $this->assertNull($before->revoked_at);

        $this->actingAs($owner)
            ->deleteJson("/api/v1/organizations/{$organization->id}/invitations/{$invitationId}")
            ->assertOk();

        $before->refresh();
        $this->assertNotNull($before->revoked_at);
        $this->assertSame('revoked', $before->status);

        $this->actingAs($owner)
            ->postJson("/api/v1/organizations/{$organization->id}/invitations/{$invitationId}/resend")
            ->assertStatus(422);

        $this->actingAs($owner)
            ->deleteJson("/api/v1/organizations/{$organization->id}/invitations/{$invitationId}")
            ->assertStatus(422);
    }

    #[Test]
    public function outsider_cannot_manage_organization_invitations(): void
    {
        [$owner, $organization] = $this->createOwnerWithOrganization('Private Org');
        $memberRole = $organization->roles()->where('slug', 'member')->firstOrFail();
        $outsider = User::factory()->create();

        $invitation = OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
            'email' => 'someone@example.com',
            'role_id' => $memberRole->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($outsider)
            ->postJson("/api/v1/organizations/{$organization->id}/invitations/{$invitation->id}/resend")
            ->assertForbidden();

        $this->actingAs($outsider)
            ->deleteJson("/api/v1/organizations/{$organization->id}/invitations/{$invitation->id}")
            ->assertForbidden();

        $this->actingAs($outsider)
            ->getJson("/api/v1/organizations/{$organization->id}/members")
            ->assertForbidden();
    }

    #[Test]
    public function invitee_can_list_and_accept_their_pending_invitations(): void
    {
        [$owner, $organization] = $this->createOwnerWithOrganization('Mine Org');
        $memberRole = $organization->roles()->where('slug', 'member')->firstOrFail();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);

        $invitation = OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
            'email' => 'invitee@example.com',
            'role_id' => $memberRole->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(3),
        ]);

        $list = $this->actingAs($invitee)
            ->getJson('/api/v1/invitations')
            ->assertOk();

        $this->assertCount(1, $list->json('data'));
        $this->assertSame($invitation->id, $list->json('data.0.id'));
        $this->assertSame($invitation->token, $list->json('data.0.token'));
        $this->assertSame('pending', $list->json('data.0.status'));
        $this->assertSame($organization->id, $list->json('data.0.organization.id'));

        $this->actingAs($invitee)
            ->postJson("/api/v1/invitations/{$invitation->token}/accept")
            ->assertOk()
            ->assertJsonPath('organization_id', $organization->id);

        $this->assertTrue(
            $organization->members()->where('user_id', $invitee->id)->exists(),
        );

        $this->actingAs($invitee)
            ->getJson('/api/v1/invitations')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $members = $this->actingAs($owner)
            ->getJson("/api/v1/organizations/{$organization->id}/members")
            ->assertOk();

        $this->assertTrue(
            collect($members->json('members'))->contains(
                fn (array $member): bool => ($member['user']['email'] ?? null) === 'invitee@example.com',
            ),
        );
        $this->assertContains(
            $invitation->id,
            collect($members->json('invitation_history'))->pluck('id')->all(),
        );
    }

    #[Test]
    public function mine_matches_email_case_insensitively_and_skips_expired(): void
    {
        [$owner, $organization] = $this->createOwnerWithOrganization('Case Org');
        $memberRole = $organization->roles()->where('slug', 'member')->firstOrFail();
        $invitee = User::factory()->create(['email' => 'Person@Example.com']);

        $live = OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
            'email' => 'person@example.com',
            'role_id' => $memberRole->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(2),
        ]);

        $this->actingAs($invitee)
            ->getJson('/api/v1/invitations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $live->id)
            ->assertJsonPath('data.0.email', 'person@example.com');

        $live->update(['expires_at' => now()->subDay()]);

        $this->actingAs($invitee)
            ->getJson('/api/v1/invitations')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function accept_requires_invited_email_and_public_show_works(): void
    {
        [$owner, $organization] = $this->createOwnerWithOrganization('Show Org');
        $memberRole = $organization->roles()->where('slug', 'member')->firstOrFail();
        $token = Str::random(64);

        OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
            'email' => 'invitee@example.com',
            'role_id' => $memberRole->id,
            'token' => $token,
            'expires_at' => now()->addDays(5),
        ]);

        $this->getJson("/api/v1/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.email', 'invitee@example.com')
            ->assertJsonPath('data.is_pending', true)
            ->assertJsonPath('data.organization.id', $organization->id);

        $wrongUser = User::factory()->create(['email' => 'wrong@example.com']);

        $this->actingAs($wrongUser)
            ->postJson("/api/v1/invitations/{$token}/accept")
            ->assertStatus(422);

        $this->actingAs($wrongUser)
            ->getJson('/api/v1/invitations')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function cannot_accept_revoked_invitation(): void
    {
        [$owner, $organization] = $this->createOwnerWithOrganization('Dead Org');
        $memberRole = $organization->roles()->where('slug', 'member')->firstOrFail();
        $invitee = User::factory()->create(['email' => 'done@example.com']);

        $revokedToken = Str::random(64);
        OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
            'email' => 'done@example.com',
            'role_id' => $memberRole->id,
            'token' => $revokedToken,
            'expires_at' => now()->addDays(5),
            'revoked_at' => now(),
        ]);

        $this->actingAs($invitee)
            ->postJson("/api/v1/invitations/{$revokedToken}/accept")
            ->assertStatus(422);
    }

    #[Test]
    public function cannot_accept_already_accepted_invitation(): void
    {
        [$owner, $organization] = $this->createOwnerWithOrganization('Accepted Org');
        $memberRole = $organization->roles()->where('slug', 'member')->firstOrFail();
        $invitee = User::factory()->create(['email' => 'again@example.com']);

        $acceptedToken = Str::random(64);
        $invitation = OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'invited_by' => $owner->id,
            'email' => 'again@example.com',
            'role_id' => $memberRole->id,
            'token' => $acceptedToken,
            'expires_at' => now()->addDays(5),
        ]);

        app(OrganizationService::class)->acceptInvitation($invitation, $invitee);

        $this->actingAs($invitee)
            ->postJson("/api/v1/invitations/{$acceptedToken}/accept")
            ->assertStatus(422);
    }
}
