<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsoleAuthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_succeeds_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('SecurePass123!'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'SecurePass123!',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'owner@example.com');

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertNotNull($user->last_login_at);
    }

    #[Test]
    public function login_rejects_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('SecurePass123!'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'WrongPassword1!',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertGuest();
    }

    #[Test]
    public function login_rejects_deactivated_accounts(): void
    {
        User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => Hash::make('SecurePass123!'),
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'disabled@example.com',
            'password' => 'SecurePass123!',
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['This account has been deactivated.']);

        $this->assertGuest();
    }

    #[Test]
    public function logout_clears_session(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('SecurePass123!'),
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertGuest();
    }

    #[Test]
    public function me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    #[Test]
    public function me_returns_authenticated_user(): void
    {
        $user = User::factory()->create([
            'email' => 'me@example.com',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'me@example.com');
    }

    #[Test]
    public function register_rejects_weak_password(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Weak User',
            'email' => 'weak@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'accepted_terms' => true,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function forgot_password_does_not_reveal_whether_email_exists(): void
    {
        User::factory()->create(['email' => 'known@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'known@example.com',
        ])->assertOk()->assertJsonStructure(['message']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'unknown@example.com',
        ])->assertOk()->assertJsonStructure(['message']);
    }
}
