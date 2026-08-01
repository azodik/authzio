<?php

use App\Enums\SocialProvider;
use App\Http\Controllers\Oidc\AuthorizeController;
use App\Http\Controllers\Oidc\HostedPasswordController;
use App\Http\Controllers\Oidc\TokenController;
use App\Http\Controllers\Oidc\WellKnownController;
use Illuminate\Support\Facades\Route;

Route::get('/.well-known/openid-configuration', [WellKnownController::class, 'openidConfiguration'])
    ->name('oidc.discovery');

Route::get('/.well-known/jwks.json', [WellKnownController::class, 'jwks'])
    ->name('oidc.jwks');

Route::get('/oauth/authorize', [AuthorizeController::class, 'show'])->name('oauth.authorize');
Route::post('/oauth/authorize', [AuthorizeController::class, 'store'])->name('oauth.authorize.submit');

Route::get('/oauth/forgot-password', [HostedPasswordController::class, 'showForgot'])->name('oauth.forgot-password');
Route::post('/oauth/forgot-password', [HostedPasswordController::class, 'sendForgot'])->name('oauth.forgot-password.send');
Route::get('/oauth/reset-password', [HostedPasswordController::class, 'showReset'])->name('oauth.reset-password');
Route::post('/oauth/reset-password', [HostedPasswordController::class, 'reset'])->name('oauth.reset-password.submit');

Route::get('/oauth/verify-email', [AuthorizeController::class, 'verifyEmailForm'])->name('oauth.verify-email');
Route::post('/oauth/verify-email', [AuthorizeController::class, 'verifyEmail'])->name('oauth.verify-email.submit');
Route::post('/oauth/verify-email/send', [AuthorizeController::class, 'resendVerifyEmail'])->name('oauth.verify-email.send');

Route::get('/oauth/mfa', [AuthorizeController::class, 'mfaForm'])->name('oauth.mfa');
Route::post('/oauth/mfa', [AuthorizeController::class, 'mfaVerify'])->name('oauth.mfa.verify');

Route::get('/oauth/social/{provider}/redirect', [AuthorizeController::class, 'socialRedirect'])
    ->whereIn('provider', SocialProvider::values())
    ->name('oauth.social.redirect');
Route::get('/oauth/social/{provider}/callback', [AuthorizeController::class, 'socialCallback'])
    ->whereIn('provider', SocialProvider::values())
    ->name('oauth.social.callback');

Route::get('/oauth/sso/{connection}/redirect', [AuthorizeController::class, 'ssoRedirect'])
    ->name('oauth.sso.redirect');
Route::get('/oauth/sso/{connection}/callback', [AuthorizeController::class, 'ssoCallback'])
    ->name('oauth.sso.callback');

Route::get('/oauth/passkey/options', [AuthorizeController::class, 'passkeyOptions'])->name('oauth.passkey.options');
Route::post('/oauth/passkey/verify', [AuthorizeController::class, 'passkeyVerify'])->name('oauth.passkey.verify');

Route::post('/api/oauth/token', [TokenController::class, 'token'])->name('oauth.token');
Route::match(['get', 'post'], '/api/oauth/userinfo', [TokenController::class, 'userinfo'])->name('oauth.userinfo');
Route::post('/api/oauth/revoke', [TokenController::class, 'revoke'])->name('oauth.revoke');
Route::post('/api/oauth/introspect', [TokenController::class, 'introspect'])->name('oauth.introspect');
