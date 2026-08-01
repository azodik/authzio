<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApplicationType;
use App\Enums\AuditAction;
use App\Http\Controllers\Concerns\EnsuresOrganizationMembership;
use App\Http\Controllers\Concerns\HandlesDemoSoftMutations;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOAuthClientRequest;
use App\Http\Requests\UpdateOAuthClientRequest;
use App\Http\Requests\UploadApplicationLogoRequest;
use App\Models\EmailTemplate;
use App\Models\OAuthClient;
use App\Models\Organization;
use App\Services\AuditLogger;
use App\Services\Auth\LoginMethods;
use App\Services\Billing\BillingNotifier;
use App\Services\Billing\PlanEntitlements;
use App\Services\Demo\DemoCapability;
use App\Services\Demo\DemoOverlay;
use App\Services\EmailTemplateRenderer;
use App\Services\Storage\AssetStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OAuthClientController extends Controller
{
    use EnsuresOrganizationMembership;
    use HandlesDemoSoftMutations;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PlanEntitlements $entitlements,
        private readonly BillingNotifier $billingNotifier,
        private readonly EmailTemplateRenderer $emailRenderer,
        private readonly AssetStorage $assets,
        private readonly DemoOverlay $demoOverlay,
    ) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->organizationForUser($request, $organization);

        $clients = OAuthClient::query()
            ->where('organization_id', $organization->id)
            ->whereNull('revoked_at')
            ->orderBy('name')
            ->get()
            ->makeHidden(['secret'])
            ->map(fn (OAuthClient $client) => $this->presentClient($request, $client));

        return response()->json([
            'organization' => $organization->only(['id', 'name', 'slug']),
            'data' => $clients,
            'entitlements' => $this->entitlements->forOrganization($organization, $request->user()),
            'application_types' => collect(ApplicationType::cases())->map(fn (ApplicationType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'grant_types' => $type->defaultGrantTypes(),
                'is_confidential' => $type->isConfidentialByDefault(),
                'requires_redirect_uris' => $type !== ApplicationType::Machine,
            ]),
        ]);
    }

    public function show(Request $request, Organization $organization, OAuthClient $oauthClient): JsonResponse
    {
        $this->organizationForUser($request, $organization);
        $this->assertClientInOrganization($oauthClient, $organization);
        abort_if($oauthClient->isRevoked(), 404);

        $templates = $organization->emailTemplates()->orderBy('name')->get();
        $presented = $this->presentClient($request, $oauthClient);
        $this->applyOverlayToModel($request, $oauthClient);

        return response()->json([
            'organization' => $organization->only(['id', 'name', 'slug']),
            'data' => $presented,
            'entitlements' => $this->entitlements->forOrganization($organization, $request->user()),
            'preview_url' => route('login.preview', $oauthClient),
            'defaults' => [
                'password_policy' => $oauthClient->resolvedPasswordPolicy(),
                'security_policy' => $oauthClient->resolvedSecurityPolicy(),
                'login_methods' => $oauthClient->resolvedLoginMethods(),
            ],
            'email_templates' => $templates->map(function ($template) use ($request, $organization, $oauthClient) {
                $this->applyEmailTemplateOverlay($request, $template);
                $preview = $this->emailRenderer->preview($template, $organization, $oauthClient);

                return [
                    'id' => $template->id,
                    'slug' => $template->slug,
                    'name' => $template->name,
                    'subject' => $template->subject,
                    'preview_subject' => $preview['subject'],
                    'preview_html' => $preview['html'],
                ];
            }),
        ]);
    }

    public function store(StoreOAuthClientRequest $request, Organization $organization): JsonResponse
    {
        $this->organizationForUser($request, $organization);

        if ($this->isDemoSoft($request, DemoCapability::ApplicationCreate)) {
            return $this->demoSoftResponse([
                'data' => [
                    'id' => (string) Str::uuid(),
                    'name' => $request->validated('name'),
                    'application_type' => $request->validated('application_type'),
                    'redirect_uris' => $request->validated('redirect_uris') ?? [],
                ],
                'client_id' => 'demo-soft-client',
                'client_secret' => null,
                'warning' => 'Demo session only — application was not persisted.',
                'message' => 'Saved for this demo session.',
            ], 201);
        }

        $this->entitlements->assertCanCreateApplication($organization);

        $type = ApplicationType::from($request->validated('application_type'));
        $isConfidential = $request->has('is_confidential')
            ? $request->boolean('is_confidential')
            : $type->isConfidentialByDefault();

        $grantTypes = $request->validated('grant_types') ?? $type->defaultGrantTypes();
        $redirectUris = $request->validated('redirect_uris') ?? [];
        $plainSecret = $isConfidential ? Str::random(64) : null;

        $client = OAuthClient::create([
            'organization_id' => $organization->id,
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'application_type' => $type,
            'description' => $request->validated('description'),
            'secret' => $plainSecret !== null ? Hash::make($plainSecret) : null,
            'redirect_uris' => $redirectUris,
            'grant_types' => $grantTypes,
            'is_confidential' => $isConfidential,
            'is_first_party' => $request->boolean('is_first_party'),
            'login_headline' => 'Sign in',
            'login_description' => 'Use your account to continue to '.$request->validated('name').'.',
            'login_button_label' => 'Continue',
            'show_signup_link' => true,
            'show_forgot_password_link' => true,
            'primary_color' => '#0F766E',
            'background_color' => '#F4F7F6',
            'password_policy' => [
                'min_length' => (int) config('authzio.password.min_length', 12),
                'require_mixed_case' => (bool) config('authzio.password.require_mixed_case', true),
                'require_numbers' => (bool) config('authzio.password.require_numbers', true),
                'require_symbols' => (bool) config('authzio.password.require_symbols', true),
            ],
            'security_policy' => [
                'mfa_required' => false,
                'session_lifetime_minutes' => (int) config('authzio.session.lifetime_minutes', 120),
                'single_device' => (bool) config('authzio.session.single_device', false),
            ],
            'login_methods' => LoginMethods::defaults(),
            'require_legal_accept' => false,
        ]);

        $this->auditLogger->log(
            AuditAction::OauthClientCreated,
            $request->user(),
            $organization,
            OAuthClient::class,
            $client->id,
            ['name' => $client->name, 'application_type' => $type->value],
        );

        $this->billingNotifier->checkApplicationThresholds($organization->fresh());

        return response()->json([
            'data' => $client->makeHidden(['secret']),
            'client_id' => $client->id,
            'client_secret' => $plainSecret,
            'warning' => $plainSecret !== null
                ? 'Copy the client secret now. It will not be shown again.'
                : null,
        ], 201);
    }

    public function update(
        UpdateOAuthClientRequest $request,
        Organization $organization,
        OAuthClient $oauthClient,
    ): JsonResponse {
        $this->organizationForUser($request, $organization);
        $this->assertClientInOrganization($oauthClient, $organization);
        abort_if($oauthClient->isRevoked(), 404);

        $brandingKeys = [
            'logo_url',
            'primary_color',
            'background_color',
            'login_headline',
            'login_description',
            'login_button_label',
            'show_signup_link',
            'show_forgot_password_link',
            'default_locale',
            'allow_locale_switch',
            'login_layout',
            'login_theme',
            'password_policy',
            'security_policy',
            'login_methods',
            'terms_url',
            'privacy_url',
            'require_legal_accept',
        ];

        $payload = $request->validated();
        $touchesBranding = collect($brandingKeys)->contains(fn (string $key) => array_key_exists($key, $payload));

        if ($touchesBranding) {
            $this->entitlements->assertLoginCustomization($organization);
        }

        if ($this->isDemoSoft($request, DemoCapability::ApplicationUpdate)) {
            $this->demoOverlay->put($request, $this->demoOverlay->applicationKey($oauthClient->id), $payload);
            $presented = $this->presentClient($request, $oauthClient);

            return $this->demoSoftResponse([
                'data' => $presented,
                'preview_url' => route('login.preview', $oauthClient),
            ]);
        }

        $oauthClient->update($payload);

        $this->auditLogger->log(
            AuditAction::OauthClientUpdated,
            $request->user(),
            $organization,
            OAuthClient::class,
            $oauthClient->id,
            ['name' => $oauthClient->name],
        );

        return response()->json([
            'data' => $oauthClient->fresh()?->makeHidden(['secret']),
            'preview_url' => route('login.preview', $oauthClient),
        ]);
    }

    public function uploadLogo(
        UploadApplicationLogoRequest $request,
        Organization $organization,
        OAuthClient $oauthClient,
    ): JsonResponse {
        $this->organizationForUser($request, $organization);
        $this->assertClientInOrganization($oauthClient, $organization);
        abort_if($oauthClient->isRevoked(), 404);

        $this->entitlements->assertLoginCustomization($organization);

        $file = $request->file('logo');
        abort_unless($file !== null, 422);

        $previous = $this->presentClient($request, $oauthClient)['logo_url'] ?? $oauthClient->logo_url;

        $url = $this->assets->storePublicImage(
            $file,
            'logos/'.$organization->id.'/'.$oauthClient->id,
            is_string($previous) ? $previous : null,
        );

        if ($this->isDemoSoft($request, DemoCapability::ApplicationLogo)) {
            $this->demoOverlay->put($request, $this->demoOverlay->applicationKey($oauthClient->id), [
                'logo_url' => $url,
            ]);

            return $this->demoSoftResponse([
                'data' => $this->presentClient($request, $oauthClient),
                'preview_url' => route('login.preview', $oauthClient),
            ]);
        }

        $oauthClient->update(['logo_url' => $url]);

        $this->auditLogger->log(
            AuditAction::OauthClientUpdated,
            $request->user(),
            $organization,
            OAuthClient::class,
            $oauthClient->id,
            ['name' => $oauthClient->name, 'logo_uploaded' => true],
        );

        return response()->json([
            'data' => $oauthClient->fresh()?->makeHidden(['secret']),
            'preview_url' => route('login.preview', $oauthClient),
        ]);
    }

    public function destroyLogo(
        Request $request,
        Organization $organization,
        OAuthClient $oauthClient,
    ): JsonResponse {
        $this->organizationForUser($request, $organization);
        $this->assertClientInOrganization($oauthClient, $organization);
        abort_if($oauthClient->isRevoked(), 404);

        $this->entitlements->assertLoginCustomization($organization);

        if ($this->isDemoSoft($request, DemoCapability::ApplicationLogo)) {
            $this->demoOverlay->put($request, $this->demoOverlay->applicationKey($oauthClient->id), [
                'logo_url' => null,
            ]);

            return $this->demoSoftResponse([
                'data' => $this->presentClient($request, $oauthClient),
                'preview_url' => route('login.preview', $oauthClient),
            ]);
        }

        $this->assets->deleteManagedUrl($oauthClient->logo_url);
        $oauthClient->update(['logo_url' => null]);

        $this->auditLogger->log(
            AuditAction::OauthClientUpdated,
            $request->user(),
            $organization,
            OAuthClient::class,
            $oauthClient->id,
            ['name' => $oauthClient->name, 'logo_removed' => true],
        );

        return response()->json([
            'data' => $oauthClient->fresh()?->makeHidden(['secret']),
            'preview_url' => route('login.preview', $oauthClient),
        ]);
    }

    public function destroy(Request $request, Organization $organization, OAuthClient $oauthClient): JsonResponse
    {
        $this->organizationForUser($request, $organization);
        $this->assertClientInOrganization($oauthClient, $organization);

        $oauthClient->update([
            'revoked_at' => now(),
        ]);

        $this->auditLogger->log(
            AuditAction::OauthClientRevoked,
            $request->user(),
            $organization,
            OAuthClient::class,
            $oauthClient->id,
            ['name' => $oauthClient->name],
        );

        return response()->json([
            'message' => 'OAuth client revoked successfully.',
        ]);
    }

    private function assertClientInOrganization(OAuthClient $oauthClient, Organization $organization): void
    {
        abort_unless($oauthClient->organization_id === $organization->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentClient(Request $request, OAuthClient $oauthClient): array
    {
        /** @var array<string, mixed> $base */
        $base = $oauthClient->makeHidden(['secret'])->toArray();

        return $this->demoOverlay->merge(
            $request,
            $this->demoOverlay->applicationKey($oauthClient->id),
            $base,
        );
    }

    private function applyOverlayToModel(Request $request, OAuthClient $oauthClient): void
    {
        $overlay = $this->demoOverlay->get($request, $this->demoOverlay->applicationKey($oauthClient->id));
        if ($overlay === null) {
            return;
        }

        $oauthClient->fill($overlay);
    }

    private function applyEmailTemplateOverlay(Request $request, EmailTemplate $template): void
    {
        $overlay = $this->demoOverlay->get($request, $this->demoOverlay->emailTemplateKey((string) $template->id));
        if ($overlay === null) {
            return;
        }

        $template->fill($overlay);
    }
}
