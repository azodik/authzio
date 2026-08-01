<?php

namespace Tests\Feature;

use App\Models\BillingPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class SetupDodoCommandTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.currency' => 'USD',
            'billing.dodo.api_key' => 'test_api_key',
            'billing.dodo.base_url' => 'https://test.dodopayments.com',
            'billing.dodo.environment' => 'test_mode',
            'billing.dodo.products.starter' => null,
            'billing.dodo.products.growth' => null,
            'billing.dodo.products.scale' => null,
            'billing.dodo.webhook_secret' => null,
            'app.url' => 'https://authzio.test',
        ]);
    }

    #[Test]
    public function it_fails_without_api_key(): void
    {
        config(['billing.dodo.api_key' => '']);
        $envPath = $this->makeTempEnv();

        $this->artisan('setup:dodo', ['--env-file' => $envPath, '--skip-seed' => true])
            ->assertFailed();

        File::delete($envPath);
    }

    #[Test]
    public function it_creates_products_writes_env_and_syncs_plans(): void
    {
        $this->dodoHttpFakeForProductCreate();
        $envPath = $this->makeTempEnv("APP_KEY=base64:test\n");

        $this->artisan('setup:dodo', ['--env-file' => $envPath])
            ->assertSuccessful();

        $env = File::get($envPath);
        $this->assertStringContainsString('DODO_PRODUCT_STARTER=pdt_test_starter', $env);
        $this->assertStringContainsString('DODO_PRODUCT_GROWTH=pdt_test_growth', $env);
        $this->assertStringContainsString('DODO_PRODUCT_SCALE=pdt_test_scale', $env);
        $this->assertStringContainsString('DODO_PAYMENTS_RETURN_URL=https://authzio.test/console/{organization_id}/billing', $env);

        $this->assertSame('pdt_test_starter', BillingPlan::query()->where('slug', 'starter')->value('dodo_product_id'));
        $this->assertSame('pdt_test_growth', BillingPlan::query()->where('slug', 'growth')->value('dodo_product_id'));
        $this->assertSame('pdt_test_scale', BillingPlan::query()->where('slug', 'scale')->value('dodo_product_id'));

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/products')) {
                return false;
            }

            $body = $request->data();

            return ($body['tax_category'] ?? null) === 'saas'
                && ($body['price']['type'] ?? null) === 'recurring_price'
                && ($body['price']['currency'] ?? null) === 'USD'
                && ($body['metadata']['authzio_plan_slug'] ?? null) !== null;
        });

        File::delete($envPath);
    }

    #[Test]
    public function it_reuses_configured_product_ids_and_syncs_prices(): void
    {
        Http::fake([
            'test.dodopayments.com/products*' => function (Request $request) {
                if ($request->method() === 'GET') {
                    return Http::response(['items' => []], 200);
                }

                if ($request->method() === 'PATCH') {
                    return Http::response(['product_id' => basename(parse_url($request->url(), PHP_URL_PATH) ?: '')], 200);
                }

                return Http::response([], 404);
            },
        ]);

        config([
            'billing.dodo.products.starter' => 'pdt_existing_starter',
            'billing.dodo.products.growth' => 'pdt_existing_growth',
            'billing.dodo.products.scale' => 'pdt_existing_scale',
        ]);

        $envPath = $this->makeTempEnv("APP_KEY=base64:test\n");

        $this->artisan('setup:dodo', ['--env-file' => $envPath])
            ->assertSuccessful();

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?: '', '/products'));

        Http::assertSent(function (Request $request): bool {
            if ($request->method() !== 'PATCH') {
                return false;
            }

            $body = $request->data();

            return str_contains($request->url(), '/products/pdt_existing_starter')
                && ($body['price']['price'] ?? null) === 500
                && ($body['name'] ?? null) === 'Authzio Starter';
        });

        $this->assertSame('pdt_existing_starter', BillingPlan::query()->where('slug', 'starter')->value('dodo_product_id'));

        File::delete($envPath);
    }

    #[Test]
    public function it_finds_existing_dodo_products_by_metadata(): void
    {
        Http::fake([
            'test.dodopayments.com/products*' => function (Request $request) {
                if ($request->method() === 'GET') {
                    return Http::response([
                        'items' => [
                            [
                                'product_id' => 'pdt_found_starter',
                                'name' => 'Authzio Starter',
                                'metadata' => ['authzio_plan_slug' => 'starter'],
                            ],
                            [
                                'product_id' => 'pdt_found_growth',
                                'name' => 'Authzio Growth',
                                'metadata' => ['authzio_plan_slug' => 'growth'],
                            ],
                            [
                                'product_id' => 'pdt_found_scale',
                                'name' => 'Authzio Scale',
                                'metadata' => ['authzio_plan_slug' => 'scale'],
                            ],
                        ],
                    ], 200);
                }

                if ($request->method() === 'PATCH') {
                    return Http::response(['product_id' => basename(parse_url($request->url(), PHP_URL_PATH) ?: '')], 200);
                }

                return Http::response([], 404);
            },
        ]);

        $envPath = $this->makeTempEnv("APP_KEY=base64:test\n");

        $this->artisan('setup:dodo', ['--env-file' => $envPath])
            ->assertSuccessful();

        $env = File::get($envPath);
        $this->assertStringContainsString('DODO_PRODUCT_STARTER=pdt_found_starter', $env);
        $this->assertStringContainsString('DODO_PRODUCT_GROWTH=pdt_found_growth', $env);
        $this->assertStringContainsString('DODO_PRODUCT_SCALE=pdt_found_scale', $env);

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?: '', '/products'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && str_contains($request->url(), '/products/pdt_found_growth')
            && ($request['price']['price'] ?? null) === 2000);

        File::delete($envPath);
    }

    #[Test]
    public function it_registers_webhook_and_saves_secret(): void
    {
        $this->dodoHttpFakeForProductCreate();
        $envPath = $this->makeTempEnv("APP_KEY=base64:test\n");

        $this->artisan('setup:dodo', [
            '--env-file' => $envPath,
            '--webhook' => 'https://583b-103-207-171-139.ngrok-free.app/api/v1/webhooks/dodo',
        ])->assertSuccessful();

        $env = File::get($envPath);
        $this->assertStringContainsString('DODO_PAYMENTS_WEBHOOK_SECRET=whsec_dGVzdF9zZWNyZXQ=', $env);

        $this->assertDatabaseHas('dodo_webhooks', [
            'dodo_webhook_id' => 'ep_test_webhook',
            'url' => 'https://583b-103-207-171-139.ngrok-free.app/api/v1/webhooks/dodo',
            'is_active' => true,
            'environment' => 'test_mode',
        ]);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/webhooks')
                && ($request['url'] ?? null) === 'https://583b-103-207-171-139.ngrok-free.app/api/v1/webhooks/dodo';
        });

        File::delete($envPath);
    }

    #[Test]
    public function it_rejects_non_https_webhook_urls(): void
    {
        $this->dodoHttpFakeForProductCreate();
        $envPath = $this->makeTempEnv("APP_KEY=base64:test\n");

        $this->artisan('setup:dodo', [
            '--env-file' => $envPath,
            '--webhook' => 'http://insecure.example/api/v1/webhooks/dodo',
        ])->assertSuccessful();

        $this->assertStringNotContainsString('DODO_PAYMENTS_WEBHOOK_SECRET=', File::get($envPath));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/webhooks'));

        File::delete($envPath);
    }
}
