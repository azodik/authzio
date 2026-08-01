<?php

namespace Tests\Feature;

use App\Enums\ConsoleSocialProvider;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\AuditLogger;
use App\Services\Auth\ConsoleSocialAuthService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsoleSocialAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        config([
            'services.console_google.client_id' => 'google-client',
            'services.console_google.client_secret' => 'google-secret',
            'services.console_google.redirect' => 'https://authzio.test/console/auth/google/callback',
            'services.console_github.client_id' => '',
            'services.console_github.client_secret' => '',
        ]);
    }

    private function fakeGoogleUser(
        string $id = 'google-1',
        string $email = 'newuser@gmail.com',
        string $name = 'New User',
    ): SocialiteUser {
        return (new SocialiteUser)->setRaw([
            'sub' => $id,
            'email' => $email,
            'email_verified' => true,
            'name' => $name,
        ])->map([
            'id' => $id,
            'nickname' => 'newuser',
            'name' => $name,
            'email' => $email,
            'avatar' => null,
        ]);
    }

    private function mockGoogleDriver(SocialiteUser $socialUser): void
    {
        $driver = Mockery::mock(AbstractProvider::class);
        $driver->shouldReceive('user')->once()->andReturn($socialUser);
        $driver->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com'));
        $driver->shouldReceive('scopes')->andReturnSelf();

        $service = Mockery::mock(ConsoleSocialAuthService::class, [app(AuditLogger::class)])
            ->makePartial();
        $service->shouldReceive('configureDriver')
            ->with(Mockery::on(fn ($provider) => $provider === ConsoleSocialProvider::Google))
            ->andReturn($driver);
        $this->app->instance(ConsoleSocialAuthService::class, $service);
    }

    #[Test]
    public function social_providers_endpoint_reports_enabled_flags(): void
    {
        $this->getJson('/api/v1/auth/social-providers')
            ->assertOk()
            ->assertJsonPath('providers.google', true)
            ->assertJsonPath('providers.github', false);
    }

    #[Test]
    public function disabled_provider_redirect_returns_404(): void
    {
        $this->get('/console/auth/github/redirect')->assertNotFound();
    }

    #[Test]
    public function login_callback_creates_user_and_session(): void
    {
        $socialUser = $this->fakeGoogleUser();
        $this->mockGoogleDriver($socialUser);

        $this->withSession([
            'authzio_console_social' => [
                'intent' => 'login',
                'provider' => 'google',
                'user_id' => null,
                'nonce' => 'test-nonce',
            ],
        ])
            ->get('/console/auth/google/callback?code=fake')
            ->assertRedirect('/console/');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@gmail.com',
            'name' => 'New User',
        ]);
        $this->assertTrue(
            UserIdentity::query()
                ->where('provider', 'console_google')
                ->where('provider_user_id', 'google-1')
                ->whereNull('organization_id')
                ->exists(),
        );
    }

    #[Test]
    public function login_callback_rejects_existing_email_without_linking(): void
    {
        User::factory()->create([
            'email' => 'newuser@gmail.com',
            'password' => Hash::make('SecurePass123!'),
        ]);

        $socialUser = $this->fakeGoogleUser();
        $this->mockGoogleDriver($socialUser);

        $this->withSession([
            'authzio_console_social' => [
                'intent' => 'login',
                'provider' => 'google',
                'user_id' => null,
                'nonce' => 'test-nonce',
            ],
        ])
            ->get('/console/auth/google/callback?code=fake')
            ->assertRedirect('/console/login?error=link_required');

        $this->assertGuest();
        $this->assertSame(1, User::query()->where('email', 'newuser@gmail.com')->count());
        $this->assertFalse(
            UserIdentity::query()->where('provider', 'console_google')->exists(),
        );
    }

    #[Test]
    public function login_callback_signs_in_existing_identity(): void
    {
        $user = User::factory()->create([
            'email' => 'linked@gmail.com',
            'password' => null,
        ]);
        UserIdentity::query()->create([
            'user_id' => $user->id,
            'organization_id' => null,
            'provider' => 'console_google',
            'provider_user_id' => 'google-1',
            'provider_email' => 'linked@gmail.com',
            'email_verified' => true,
        ]);

        $socialUser = $this->fakeGoogleUser(email: 'linked@gmail.com', name: 'Linked User');
        $this->mockGoogleDriver($socialUser);

        $this->withSession([
            'authzio_console_social' => [
                'intent' => 'login',
                'provider' => 'google',
                'user_id' => null,
                'nonce' => 'test-nonce',
            ],
        ])
            ->get('/console/auth/google/callback?code=fake')
            ->assertRedirect('/console/');

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function authenticated_user_can_link_matching_email(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('SecurePass123!'),
        ]);

        $socialUser = $this->fakeGoogleUser(email: 'owner@example.com', name: 'Owner');
        $this->mockGoogleDriver($socialUser);

        $this->actingAs($user)
            ->withSession([
                'authzio_console_social' => [
                    'intent' => 'link',
                    'provider' => 'google',
                    'user_id' => $user->id,
                    'nonce' => 'test-nonce',
                ],
            ])
            ->get('/console/auth/google/callback?code=fake')
            ->assertRedirect('/console/settings?linked=google');

        $this->assertTrue(
            UserIdentity::query()
                ->where('user_id', $user->id)
                ->where('provider', 'console_google')
                ->exists(),
        );
    }

    #[Test]
    public function link_rejects_email_mismatch(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('SecurePass123!'),
        ]);

        $socialUser = $this->fakeGoogleUser(email: 'other@gmail.com');
        $this->mockGoogleDriver($socialUser);

        $location = (string) $this->actingAs($user)
            ->withSession([
                'authzio_console_social' => [
                    'intent' => 'link',
                    'provider' => 'google',
                    'user_id' => $user->id,
                    'nonce' => 'test-nonce',
                ],
            ])
            ->get('/console/auth/google/callback?code=fake')
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringContainsString('/console/settings?error=link_failed', $location);
        $this->assertFalse(
            UserIdentity::query()->where('user_id', $user->id)->where('provider', 'console_google')->exists(),
        );
    }

    #[Test]
    public function unlink_blocked_when_passwordless_and_only_method(): void
    {
        $user = User::factory()->create([
            'email' => 'socialonly@gmail.com',
            'password' => null,
        ]);
        UserIdentity::query()->create([
            'user_id' => $user->id,
            'organization_id' => null,
            'provider' => 'console_google',
            'provider_user_id' => 'google-1',
            'provider_email' => 'socialonly@gmail.com',
            'email_verified' => true,
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/v1/auth/linked-accounts/google')
            ->assertStatus(422);

        $this->assertTrue(
            UserIdentity::query()->where('user_id', $user->id)->where('provider', 'console_google')->exists(),
        );
    }

    #[Test]
    public function unlink_allowed_when_password_present(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('SecurePass123!'),
        ]);
        UserIdentity::query()->create([
            'user_id' => $user->id,
            'organization_id' => null,
            'provider' => 'console_google',
            'provider_user_id' => 'google-1',
            'provider_email' => 'owner@example.com',
            'email_verified' => true,
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/v1/auth/linked-accounts/google')
            ->assertOk();

        $this->assertFalse(
            UserIdentity::query()->where('user_id', $user->id)->where('provider', 'console_google')->exists(),
        );
    }
}
