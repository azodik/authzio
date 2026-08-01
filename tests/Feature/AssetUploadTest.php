<?php

namespace Tests\Feature;

use App\Enums\ApplicationType;
use App\Models\OAuthClient;
use App\Models\User;
use App\Services\Storage\AssetStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class AssetUploadTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBillingPlans();
        config(['authzio.assets.disk' => 'public']);
        Storage::fake('public');
    }

    #[Test]
    public function owner_can_upload_and_remove_application_logo(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $client = $this->makeOAuthClient($user, $organization->id);

        $response = $this->actingAs($user)
            ->post(
                "/api/v1/organizations/{$organization->id}/applications/{$client->id}/logo",
                ['logo' => UploadedFile::fake()->image('logo.png', 120, 120)],
            )
            ->assertOk();

        $logoUrl = $response->json('data.logo_url');
        $this->assertIsString($logoUrl);
        $this->assertNotSame('', $logoUrl);

        $path = app(AssetStorage::class)->pathFromPublicUrl($logoUrl);
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)
            ->deleteJson("/api/v1/organizations/{$organization->id}/applications/{$client->id}/logo")
            ->assertOk()
            ->assertJsonPath('data.logo_url', null);

        Storage::disk('public')->assertMissing($path);
    }

    #[Test]
    public function user_can_upload_and_remove_avatar(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/api/v1/auth/avatar', [
                'avatar' => UploadedFile::fake()->image('avatar.jpg', 96, 96),
            ])
            ->assertOk();

        $avatarUrl = $response->json('user.avatar_url');
        $this->assertIsString($avatarUrl);

        $path = app(AssetStorage::class)->pathFromPublicUrl($avatarUrl);
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)
            ->deleteJson('/api/v1/auth/avatar')
            ->assertOk()
            ->assertJsonPath('user.avatar_url', null);

        Storage::disk('public')->assertMissing($path);
    }

    #[Test]
    public function logo_upload_rejects_non_images(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $client = $this->makeOAuthClient($user, $organization->id);

        $this->actingAs($user)
            ->post(
                "/api/v1/organizations/{$organization->id}/applications/{$client->id}/logo",
                ['logo' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')],
            )
            ->assertStatus(422);
    }

    private function makeOAuthClient(User $user, string $organizationId): OAuthClient
    {
        return OAuthClient::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'name' => 'Demo App',
            'application_type' => ApplicationType::Spa,
            'redirect_uris' => ['https://app.example.com/callback'],
            'grant_types' => ApplicationType::Spa->defaultGrantTypes(),
            'is_confidential' => false,
            'is_first_party' => true,
            'login_methods' => [
                'password' => true,
                'google' => false,
                'github' => false,
                'passkey' => true,
                'email_otp' => true,
                'sync_profile' => true,
                'require_verified_email' => true,
                'allow_unverified_email_with_otp' => true,
            ],
        ]);
    }
}
