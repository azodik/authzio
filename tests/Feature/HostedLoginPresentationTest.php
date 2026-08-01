<?php

namespace Tests\Feature;

use App\Enums\ApplicationType;
use App\Models\OAuthClient;
use App\Services\Auth\HostedLoginPresentation;
use Database\Seeders\BillingPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class HostedLoginPresentationTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BillingPlanSeeder::class);
    }

    #[Test]
    public function update_persists_login_layout_and_theme(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $client = OAuthClient::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'name' => 'Layout App',
            'application_type' => ApplicationType::Spa,
            'redirect_uris' => ['https://app.example.com/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'is_confidential' => false,
        ]);

        $this->actingAs($user)
            ->putJson("/api/v1/organizations/{$organization->id}/applications/{$client->id}", [
                'login_layout' => 'centered',
                'login_theme' => 'dark',
                'default_locale' => 'fr',
                'allow_locale_switch' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.login_layout', 'centered')
            ->assertJsonPath('data.login_theme', 'dark')
            ->assertJsonPath('data.default_locale', 'fr');

        $client->refresh();
        $this->assertSame('centered', $client->login_layout);
        $this->assertSame('dark', $client->login_theme);
    }

    #[Test]
    public function update_rejects_invalid_layout_and_theme(): void
    {
        [$user, $organization] = $this->createOwnerWithOrganization();
        $client = OAuthClient::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'name' => 'Layout App',
            'application_type' => ApplicationType::Spa,
            'redirect_uris' => ['https://app.example.com/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'is_confidential' => false,
        ]);

        $this->actingAs($user)
            ->putJson("/api/v1/organizations/{$organization->id}/applications/{$client->id}", [
                'login_layout' => 'sideways',
                'login_theme' => 'neon',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['login_layout', 'login_theme']);
    }

    #[Test]
    public function authorize_view_includes_layout_and_theme_classes(): void
    {
        [, $organization] = $this->createOwnerWithOrganization();
        $client = OAuthClient::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Theme App',
            'application_type' => ApplicationType::Spa,
            'redirect_uris' => ['https://app.example.com/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'is_confidential' => false,
            'login_layout' => 'form_left',
            'login_theme' => 'dark',
            'default_locale' => 'de',
            'allow_locale_switch' => true,
            'login_methods' => [
                'password' => true,
                'google' => false,
                'github' => false,
                'passkey' => false,
                'email_otp' => false,
                'sync_profile' => true,
                'require_verified_email' => false,
                'allow_unverified_email_with_otp' => false,
            ],
        ]);

        $challenge = rtrim(strtr(base64_encode(hash('sha256', 'verifier', true)), '+/', '-_'), '=');

        $this->withHeaders(['X-Authzio-Organization' => $organization->id])
            ->get('/oauth/authorize?'.http_build_query([
                'client_id' => $client->id,
                'redirect_uri' => 'https://app.example.com/callback',
                'response_type' => 'code',
                'scope' => 'openid email',
                'state' => 'layout',
                'code_challenge' => $challenge,
                'code_challenge_method' => 'S256',
                'ui_locales' => 'fr',
            ]))
            ->assertOk()
            ->assertSee('layout--form-left', false)
            ->assertSee('theme-dark', false)
            ->assertSee('lang="fr"', false)
            ->assertSee('id="hosted-locale"', false);
    }

    #[Test]
    public function presentation_falls_back_to_default_locale_when_switch_disabled(): void
    {
        [, $organization] = $this->createOwnerWithOrganization();
        $client = OAuthClient::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Locale App',
            'application_type' => ApplicationType::Spa,
            'redirect_uris' => ['https://app.example.com/callback'],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'is_confidential' => false,
            'default_locale' => 'es',
            'allow_locale_switch' => false,
        ]);

        $presentation = app(HostedLoginPresentation::class);
        $request = Request::create('/oauth/authorize', 'GET', ['ui_locales' => 'fr']);
        $data = $presentation->apply($request, $client);

        $this->assertSame('es', $data['locale']);
        $this->assertFalse($data['allow_locale_switch']);
    }
}
