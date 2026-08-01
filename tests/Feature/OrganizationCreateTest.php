<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\AuthzioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class OrganizationCreateTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBillingPlans();
    }

    #[Test]
    public function user_creates_organization_with_chosen_slug(): void
    {
        [$user] = $this->createOwnerWithOrganization('Existing');

        // Owner already has one org from helper; create a second with explicit slug.
        $this->actingAs($user)
            ->postJson('/api/v1/organizations', [
                'name' => 'Acme Inc',
                'slug' => 'acme-inc',
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'acme-inc')
            ->assertJsonPath('data.subdomain', 'acme-inc');

        $this->assertDatabaseHas('organizations', [
            'slug' => 'acme-inc',
            'subdomain' => 'acme-inc',
            'name' => 'Acme Inc',
        ]);
    }

    #[Test]
    public function slug_is_required_and_must_be_unique(): void
    {
        [$user] = $this->createOwnerWithOrganization('Existing');

        Organization::query()->where('slug', 'existing')->update([
            'slug' => 'taken-slug',
            'subdomain' => 'taken-slug',
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/organizations', [
                'name' => 'No Slug',
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson('/api/v1/organizations', [
                'name' => 'Conflict',
                'slug' => 'taken-slug',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function reserved_slugs_are_rejected(): void
    {
        [$user] = $this->createOwnerWithOrganization('Existing');

        $this->actingAs($user)
            ->postJson('/api/v1/organizations', [
                'name' => 'Console Org',
                'slug' => 'console',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function signup_does_not_attach_demo_organization(): void
    {
        $this->seed(AuthzioSeeder::class);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'accepted_terms' => true,
        ])->assertCreated();

        $user = User::query()->where('email', 'newuser@example.com')->firstOrFail();
        $this->assertCount(0, $user->organizations);
        $this->assertTrue(Organization::query()->where('slug', 'demo-org')->exists());
    }
}
