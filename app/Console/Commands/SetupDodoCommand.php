<?php

namespace App\Console\Commands;

use App\Models\BillingPlan;
use App\Models\DodoWebhook;
use App\Services\Billing\DodoPaymentsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SetupDodoCommand extends Command
{
    protected $signature = 'setup:dodo
                            {--force : Recreate products even when DODO_PRODUCT_* is already set}
                            {--skip-seed : Do not re-seed / update billing_plans rows}
                            {--webhook= : Public HTTPS URL for Dodo webhooks (e.g. https://xxx.ngrok.app/api/v1/webhooks/dodo)}
                            {--env-file= : Path to .env file (defaults to project .env)}';

    protected $description = 'Create Starter/Growth/Scale products in Dodo Payments, write product IDs to .env, and sync billing plans';

    /**
     * @var array<string, array{name: string, description: string, price_cents: int, env_key: string}>
     */
    private array $catalog = [
        'starter' => [
            'name' => 'Authzio Starter',
            'description' => '5,000 MAU · 5 apps · custom domains · branded email',
            'price_cents' => 500,
            'env_key' => 'DODO_PRODUCT_STARTER',
        ],
        'growth' => [
            'name' => 'Authzio Growth',
            'description' => '50,000 MAU · unlimited apps · OIDC enterprise SSO',
            'price_cents' => 2_000,
            'env_key' => 'DODO_PRODUCT_GROWTH',
        ],
        'scale' => [
            'name' => 'Authzio Scale',
            'description' => '250,000 MAU · custom JWKS · dedicated onboarding',
            'price_cents' => 9_900,
            'env_key' => 'DODO_PRODUCT_SCALE',
        ],
    ];

    public function handle(DodoPaymentsClient $dodo): int
    {
        $this->components->info('Dodo Payments setup');

        $envPath = $this->resolveEnvPath();
        if (! File::exists($envPath)) {
            $this->components->error('.env is missing. Run php artisan authzio:setup first.');

            return self::FAILURE;
        }

        $apiKey = (string) config('billing.dodo.api_key');
        if ($apiKey === '') {
            $this->components->error('Set DODO_PAYMENTS_API_KEY in .env, then re-run setup:dodo.');

            return self::FAILURE;
        }

        $environment = (string) (config('billing.dodo.environment') ?: 'test_mode');
        if (! in_array($environment, ['test_mode', 'live_mode'], true)) {
            $environment = 'test_mode';
        }

        $expectedBase = $environment === 'live_mode'
            ? 'https://live.dodopayments.com'
            : 'https://test.dodopayments.com';

        $currentBase = rtrim((string) config('billing.dodo.base_url'), '/');
        if ($currentBase === '' || str_contains($currentBase, 'api.dodopayments.com')) {
            $this->writeEnv($envPath, 'DODO_PAYMENTS_BASE_URL', $expectedBase);
            config(['billing.dodo.base_url' => $expectedBase]);
            $this->components->twoColumnDetail('DODO_PAYMENTS_BASE_URL', $expectedBase);
        }

        $this->writeEnv($envPath, 'DODO_PAYMENTS_ENVIRONMENT', $environment);
        $this->writeEnv(
            $envPath,
            'DODO_PAYMENTS_RETURN_URL',
            rtrim((string) config('app.url'), '/').'/console/{organization_id}/billing',
        );

        $this->components->twoColumnDetail('Environment', $environment);
        $this->components->twoColumnDetail('API', $dodo->baseUrl());

        try {
            $existing = $dodo->listProducts();
        } catch (\Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $productIds = [];

        foreach ($this->catalog as $slug => $definition) {
            $envKey = $definition['env_key'];
            $configured = trim((string) (config('billing.dodo.products.'.$slug) ?: $this->envValue($envPath, $envKey) ?? ''));

            if ($configured !== '' && ! $this->option('force')) {
                try {
                    $this->syncProduct($dodo, $configured, $slug, $definition);
                } catch (\Throwable $exception) {
                    $this->components->error($definition['name'].': '.$exception->getMessage());

                    return self::FAILURE;
                }

                $productIds[$slug] = $configured;
                $this->components->twoColumnDetail($definition['name'], 'updated '.$configured);

                continue;
            }

            $matched = $this->findExistingProduct($existing, $slug, $definition['name']);
            if ($matched !== null && ! $this->option('force')) {
                try {
                    $this->syncProduct($dodo, $matched, $slug, $definition);
                } catch (\Throwable $exception) {
                    $this->components->error($definition['name'].': '.$exception->getMessage());

                    return self::FAILURE;
                }

                $productIds[$slug] = $matched;
                $this->writeEnv($envPath, $envKey, $matched);
                config(["billing.dodo.products.{$slug}" => $matched]);
                $this->components->twoColumnDetail($definition['name'], 'updated '.$matched);

                continue;
            }

            try {
                $created = $dodo->createProduct($this->productPayload($slug, $definition));
            } catch (\Throwable $exception) {
                $this->components->error($definition['name'].': '.$exception->getMessage());

                return self::FAILURE;
            }

            $productId = $created['product_id'] ?? $created['id'] ?? null;
            if (! is_string($productId) || $productId === '') {
                $this->components->error($definition['name'].': unexpected create response.');

                return self::FAILURE;
            }

            $productIds[$slug] = $productId;
            $this->writeEnv($envPath, $envKey, $productId);
            config(["billing.dodo.products.{$slug}" => $productId]);
            $this->components->twoColumnDetail($definition['name'], 'created '.$productId);
        }

        if (! $this->option('skip-seed')) {
            $this->components->task('Syncing billing_plans.dodo_product_id', function () use ($productIds): void {
                Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\BillingPlanSeeder',
                    '--force' => true,
                ]);

                foreach ($productIds as $slug => $productId) {
                    BillingPlan::query()->where('slug', $slug)->update([
                        'dodo_product_id' => $productId,
                    ]);
                }
            });
        }

        $webhookOption = $this->option('webhook');
        if (is_string($webhookOption) && $webhookOption !== '') {
            $this->configureWebhook($dodo, $envPath, $webhookOption);
        } else {
            $this->newLine();
            $this->components->warn('Webhook not configured by this command.');
            $this->line('  For local tunnels:');
            $this->line('    php artisan setup:dodo --webhook=https://<tunnel>/api/v1/webhooks/dodo');
            $this->line('  Or create the endpoint in the Dodo dashboard and set DODO_PAYMENTS_WEBHOOK_SECRET.');
        }

        $this->newLine();
        $this->components->info('Done. Product IDs:');
        foreach ($productIds as $slug => $id) {
            $this->components->twoColumnDetail(strtoupper($slug), $id);
        }

        $this->newLine();
        $this->line('Open Billing in the console and upgrade an org to test checkout.');

        return self::SUCCESS;
    }

    /**
     * @param  array{name: string, description: string, price_cents: int, env_key: string}  $definition
     */
    private function syncProduct(DodoPaymentsClient $dodo, string $productId, string $slug, array $definition): void
    {
        $dodo->updateProduct($productId, [
            'name' => $definition['name'],
            'description' => $definition['description'],
            'price' => [
                'type' => 'recurring_price',
                'price' => $definition['price_cents'],
                'currency' => strtoupper((string) config('billing.currency', 'USD')),
                'discount' => 0,
                'purchasing_power_parity' => false,
                'payment_frequency_count' => 1,
                'payment_frequency_interval' => 'Month',
                'subscription_period_count' => 1,
                'subscription_period_interval' => 'Month',
                'trial_period_days' => 0,
            ],
            'metadata' => [
                'authzio_plan_slug' => $slug,
                'authzio_product' => 'true',
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    private function findExistingProduct(array $products, string $slug, string $name): ?string
    {
        foreach ($products as $product) {
            $metadata = $product['metadata'] ?? [];
            $metaSlug = is_array($metadata) ? ($metadata['authzio_plan_slug'] ?? null) : null;
            $productName = $product['name'] ?? null;
            $id = $product['product_id'] ?? $product['id'] ?? null;

            if (! is_string($id) || $id === '') {
                continue;
            }

            if ($metaSlug === $slug || $productName === $name) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param  array{name: string, description: string, price_cents: int, env_key: string}  $definition
     * @return array<string, mixed>
     */
    private function productPayload(string $slug, array $definition): array
    {
        $currency = strtoupper((string) config('billing.currency', 'USD'));

        return [
            'name' => $definition['name'],
            'description' => $definition['description'],
            'tax_category' => 'saas',
            'price' => [
                'type' => 'recurring_price',
                'price' => $definition['price_cents'],
                'currency' => $currency,
                'discount' => 0,
                'purchasing_power_parity' => false,
                'payment_frequency_count' => 1,
                'payment_frequency_interval' => 'Month',
                'subscription_period_count' => 1,
                'subscription_period_interval' => 'Month',
                'trial_period_days' => 0,
            ],
            'metadata' => [
                'authzio_plan_slug' => $slug,
                'authzio_product' => 'true',
            ],
        ];
    }

    private function configureWebhook(DodoPaymentsClient $dodo, string $envPath, string $url): void
    {
        $url = trim($url);
        if (! Str::startsWith($url, 'https://')) {
            $this->components->error('Webhook URL must be HTTPS.');

            return;
        }

        $filterTypes = [
            'subscription.active',
            'subscription.renewed',
            'subscription.on_hold',
            'subscription.failed',
            'subscription.cancelled',
            'subscription.expired',
            'payment.succeeded',
            'payment.failed',
        ];

        try {
            $webhook = $dodo->createWebhook([
                'url' => $url,
                'description' => 'Authzio billing subscriptions',
                'filter_types' => $filterTypes,
                'metadata' => [
                    'authzio' => 'true',
                ],
            ]);
        } catch (\Throwable $exception) {
            $this->components->error('Webhook: '.$exception->getMessage());

            return;
        }

        $webhookId = $webhook['id'] ?? $webhook['webhook_id'] ?? null;
        $this->components->twoColumnDetail(
            'Webhook',
            is_string($webhookId) ? $webhookId : 'created',
        );

        $secret = null;
        if (is_string($webhookId)) {
            $secret = $dodo->retrieveWebhookSecret($webhookId);
        }

        $secret = $secret
            ?? (is_string($webhook['secret'] ?? null) ? $webhook['secret'] : null)
            ?? (is_string($webhook['webhook_secret'] ?? null) ? $webhook['webhook_secret'] : null);

        $environment = (string) (config('billing.dodo.environment') ?: 'test_mode');

        DodoWebhook::query()
            ->where('environment', $environment)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $attributes = [
            'url' => $url,
            'secret' => is_string($secret) && $secret !== '' ? $secret : null,
            'environment' => $environment,
            'description' => 'Authzio billing subscriptions',
            'filter_types' => $filterTypes,
            'metadata' => [
                'authzio' => true,
                'created_via' => 'setup:dodo',
            ],
            'is_active' => true,
        ];

        $record = is_string($webhookId) && $webhookId !== ''
            ? DodoWebhook::query()->updateOrCreate(
                ['dodo_webhook_id' => $webhookId],
                $attributes,
            )
            : DodoWebhook::query()->updateOrCreate(
                [
                    'url' => $url,
                    'environment' => $environment,
                ],
                $attributes,
            );

        $this->components->twoColumnDetail('dodo_webhooks', $record->id);

        if (is_string($secret) && $secret !== '') {
            $this->writeEnv($envPath, 'DODO_PAYMENTS_WEBHOOK_SECRET', $secret);
            config(['billing.dodo.webhook_secret' => $secret]);
            $this->components->twoColumnDetail('DODO_PAYMENTS_WEBHOOK_SECRET', 'saved to .env + database');
        } else {
            $this->components->warn(
                'Webhook row saved without secret. Copy the signing secret from the Dodo dashboard into DODO_PAYMENTS_WEBHOOK_SECRET (or update dodo_webhooks.secret).',
            );
        }
    }

    private function resolveEnvPath(): string
    {
        $custom = $this->option('env-file');
        if (is_string($custom) && $custom !== '') {
            return $custom;
        }

        return base_path('.env');
    }

    private function envValue(string $envPath, string $key): ?string
    {
        $contents = File::get($envPath);
        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches)) {
            return null;
        }

        $value = trim($matches[1]);
        if (Str::startsWith($value, '"') && Str::endsWith($value, '"')) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }

    private function writeEnv(string $envPath, string $key, string $value): void
    {
        $contents = File::get($envPath);
        $needsQuotes = str_contains($value, ' ') || str_contains($value, '#') || str_contains($value, '"');
        $encoded = $needsQuotes ? '"'.str_replace('"', '\\"', $value).'"' : $value;
        $line = $key.'='.$encoded;

        if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $contents) === 1) {
            $contents = preg_replace('/^'.preg_quote($key, '/').'=.*/m', $line, $contents) ?? $contents;
        } else {
            $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        File::put($envPath, $contents);
    }
}
