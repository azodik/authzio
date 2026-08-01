<?php

use App\Http\Controllers\ConsoleController;
use App\Http\Controllers\ConsoleSocialController;
use App\Http\Controllers\LoginPreviewController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\SeoController;
use App\Models\Organization;
use App\Support\E2e\E2eDodoSync;
use App\Support\E2e\E2eQuotaAlerts;
use App\Support\E2e\E2eSystemMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/llms.txt', [SeoController::class, 'llms'])->name('llms');

Route::get('/', [MarketingController::class, 'home'])->name('home');
Route::get('/pricing', [MarketingController::class, 'pricing'])->name('pricing');
Route::get('/demo', [MarketingController::class, 'demo'])->name('demo');
Route::get('/privacy', [MarketingController::class, 'privacy'])->name('privacy');
Route::get('/terms', [MarketingController::class, 'terms'])->name('terms');
Route::get('/cookies', [MarketingController::class, 'cookies'])->name('cookies');
Route::get('/docs/{page?}', [MarketingController::class, 'docs'])
    ->where('page', '[a-z0-9\-]+')
    ->name('docs');

Route::get('/preview/login/{oauthClient}', LoginPreviewController::class)
    ->name('login.preview');

// Local OIDC E2E catch URL — displays authorization code for browser verification.
if (app()->environment(['local', 'testing'])) {
    Route::get('/__oidc_e2e_callback', function (Request $request) {
        return response(
            '<!DOCTYPE html><html><body>'
            .'<h1>OIDC E2E callback</h1>'
            .'<p data-testid="oidc-code">code='.e($request->query('code')).'</p>'
            .'<p data-testid="oidc-state">state='.e($request->query('state')).'</p>'
            .'</body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    })->name('oidc.e2e.callback');
}

// Playwright quota-alert fixtures — send real platform emails to Mailpit.
if (E2eQuotaAlerts::enabled()) {
    Route::post('/__e2e/quota/prepare', function (Request $request) {
        $organization = Organization::query()->findOrFail((string) $request->input('organization_id'));

        return response()->json(['data' => E2eQuotaAlerts::prepare($organization)]);
    })->name('e2e.quota.prepare');

    Route::post('/__e2e/quota/seed-mau', function (Request $request) {
        $organization = Organization::query()->findOrFail((string) $request->input('organization_id'));
        $count = (int) $request->input('count', 0);

        return response()->json(['data' => E2eQuotaAlerts::seedMau($organization, $count)]);
    })->name('e2e.quota.seed-mau');

    Route::post('/__e2e/quota/seed-applications', function (Request $request) {
        $organization = Organization::query()->findOrFail((string) $request->input('organization_id'));
        $count = (int) $request->input('count', 0);

        return response()->json(['data' => E2eQuotaAlerts::seedApplications($organization, $count)]);
    })->name('e2e.quota.seed-applications');

    Route::post('/__e2e/quota/seed-email-daily', function (Request $request) {
        $organization = Organization::query()->findOrFail((string) $request->input('organization_id'));
        $count = (int) $request->input('count', 0);

        return response()->json(['data' => E2eQuotaAlerts::seedEmailDaily($organization, $count)]);
    })->name('e2e.quota.seed-email-daily');
}

// Playwright: send every platform system email template to Mailpit.
if (E2eSystemMail::enabled()) {
    Route::post('/__e2e/system-mail/send-all', function (Request $request) {
        $to = $request->input('to');
        $locale = $request->input('locale');

        return response()->json([
            'data' => E2eSystemMail::sendAll(
                is_string($to) ? $to : null,
                is_string($locale) ? $locale : null,
            ),
        ]);
    })->name('e2e.system-mail.send-all');
}

// Playwright: sync real Dodo test-mode subscription/payment into local billing state.
if (E2eDodoSync::enabled()) {
    Route::get('/__e2e/dodo/status', function () {
        return response()->json(['data' => E2eDodoSync::status()]);
    })->name('e2e.dodo.status');

    Route::post('/__e2e/dodo/sync', function (Request $request) {
        $organization = Organization::query()->findOrFail((string) $request->input('organization_id'));
        $subscriptionId = $request->input('subscription_id');
        $paymentId = $request->input('payment_id');

        return response()->json([
            'data' => E2eDodoSync::sync(
                $organization,
                is_string($subscriptionId) ? $subscriptionId : null,
                is_string($paymentId) ? $paymentId : null,
            ),
        ]);
    })->name('e2e.dodo.sync');
}

Route::middleware('web')->prefix('console/auth')->group(function (): void {
    Route::get('{provider}/redirect', [ConsoleSocialController::class, 'redirect'])
        ->whereIn('provider', ['google', 'github'])
        ->name('console.auth.social.redirect');
    Route::get('{provider}/callback', [ConsoleSocialController::class, 'callback'])
        ->whereIn('provider', ['google', 'github'])
        ->name('console.auth.social.callback');
});

Route::get('/console/{any?}', [ConsoleController::class, 'show'])
    ->where('any', '.*')
    ->name('console');
