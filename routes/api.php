<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\DodoWebhookController;
use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\EmailProviderController;
use App\Http\Controllers\Api\EmailTemplateController;
use App\Http\Controllers\Api\EndUserController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\LocaleController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\MfaController;
use App\Http\Controllers\Api\OAuthClientController;
use App\Http\Controllers\Api\OidcSettingsController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\OverviewController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SocialProviderController;
use App\Http\Controllers\Api\SsoConnectionController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('webhooks/dodo', DodoWebhookController::class)
        ->middleware('throttle:api');

    // Locale catalogs and release meta are public (no session/cookies required).
    Route::middleware(['throttle:api'])->group(function (): void {
        Route::get('meta', [MetaController::class, 'show']);
        Route::get('locales/{locale}', [LocaleController::class, 'show']);
    });

    Route::middleware(['web', 'throttle:auth'])->group(function (): void {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('auth/reset-password', [AuthController::class, 'resetPassword']);
        Route::post('auth/email/verify', [AuthController::class, 'verifyEmail']);
        Route::post('auth/mfa/challenge', [MfaController::class, 'challenge']);
        Route::get('auth/social-providers', [AuthController::class, 'socialProviders']);
        Route::get('invitations/{token}', [InvitationController::class, 'show']);
    });

    Route::middleware(['web', 'auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('auth/mfa', [MfaController::class, 'status']);
        Route::get('auth/linked-accounts', [AuthController::class, 'linkedAccounts']);
        Route::get('workspace', [WorkspaceController::class, 'show']);
    });

    // Logout must work even when the session is already gone / cookies are bloated,
    // so we can still expire Set-Cookie headers from the server.
    Route::middleware(['web', 'demo.policy', 'throttle:api'])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
    });

    Route::middleware(['web', 'auth:sanctum', 'demo.policy', 'throttle:api'])->group(function (): void {
        Route::patch('auth/preferences', [AuthController::class, 'updatePreferences']);
        Route::post('auth/avatar', [AuthController::class, 'uploadAvatar']);
        Route::delete('auth/avatar', [AuthController::class, 'destroyAvatar']);
        Route::post('auth/email/resend-confirmation', [AuthController::class, 'resendConfirmation'])
            ->middleware('throttle:6,1');
        Route::post('auth/mfa/setup', [MfaController::class, 'beginSetup']);
        Route::post('auth/mfa/confirm', [MfaController::class, 'confirmSetup']);
        Route::post('auth/mfa/disable', [MfaController::class, 'disable']);
        Route::post('auth/mfa/recovery-codes', [MfaController::class, 'regenerateRecoveryCodes']);
        Route::delete('auth/linked-accounts/{provider}', [AuthController::class, 'unlinkAccount'])
            ->whereIn('provider', ['google', 'github']);
        Route::get('invitations', [InvitationController::class, 'index']);
        Route::post('invitations/{token}/accept', [InvitationController::class, 'accept']);

        Route::apiResource('organizations', OrganizationController::class)->only(['index', 'store', 'show']);

        Route::prefix('organizations/{organization}')->group(function (): void {
            Route::get('overview/stats', [OverviewController::class, 'stats']);

            Route::get('members', [MemberController::class, 'index']);
            Route::post('invitations', [MemberController::class, 'invite']);
            Route::post('invitations/{invitation}/resend', [MemberController::class, 'resendInvitation']);
            Route::delete('invitations/{invitation}', [MemberController::class, 'revokeInvitation']);
            Route::patch('members/{member}/role', [MemberController::class, 'updateRole']);
            Route::delete('members/{member}', [MemberController::class, 'destroy']);

            Route::get('roles', [RoleController::class, 'index']);
            Route::post('roles', [RoleController::class, 'store']);
            Route::put('roles/{role}', [RoleController::class, 'update']);
            Route::delete('roles/{role}', [RoleController::class, 'destroy']);

            Route::get('end-users', [EndUserController::class, 'index']);

            Route::get('domains', [DomainController::class, 'index']);
            Route::post('domains', [DomainController::class, 'store']);
            Route::put('domains/subdomain', [DomainController::class, 'updateSubdomain']);
            Route::post('domains/{domain}/verify', [DomainController::class, 'verify']);
            Route::delete('domains/{domain}', [DomainController::class, 'destroy']);

            Route::apiResource('applications', OAuthClientController::class)
                ->parameters(['applications' => 'oauthClient'])
                ->only(['index', 'store', 'show', 'update', 'destroy']);
            Route::post('applications/{oauthClient}/logo', [OAuthClientController::class, 'uploadLogo']);
            Route::delete('applications/{oauthClient}/logo', [OAuthClientController::class, 'destroyLogo']);

            Route::get('email-templates', [EmailTemplateController::class, 'index']);
            Route::post('email-templates/{emailTemplate}/preview', [EmailTemplateController::class, 'preview']);
            Route::put('email-templates/{emailTemplate}', [EmailTemplateController::class, 'update']);

            Route::get('email-provider', [EmailProviderController::class, 'show']);
            Route::put('email-provider', [EmailProviderController::class, 'upsert']);
            Route::post('email-provider/test', [EmailProviderController::class, 'test']);

            Route::get('billing', [BillingController::class, 'show']);
            Route::post('billing/checkout', [BillingController::class, 'checkout']);
            Route::post('billing/preview-change', [BillingController::class, 'previewChange']);
            Route::get('billing/invoices', [BillingController::class, 'invoices']);
            Route::get('billing/invoices/{paymentId}', [BillingController::class, 'downloadInvoice']);

            Route::get('oidc', [OidcSettingsController::class, 'show']);
            Route::post('oidc/keys/generate', [OidcSettingsController::class, 'generate']);
            Route::post('oidc/keys/import', [OidcSettingsController::class, 'import']);

            Route::get('social-providers', [SocialProviderController::class, 'index']);
            Route::post('social-providers', [SocialProviderController::class, 'upsert']);

            Route::get('sso-connections', [SsoConnectionController::class, 'index']);
            Route::post('sso-connections', [SsoConnectionController::class, 'store']);
            Route::post('sso-connections/discover', [SsoConnectionController::class, 'discover']);
            Route::put('sso-connections/{ssoConnection}', [SsoConnectionController::class, 'update']);
            Route::delete('sso-connections/{ssoConnection}', [SsoConnectionController::class, 'destroy']);

            Route::get('audit-logs', [AuditLogController::class, 'index']);
        });
    });
});
