<?php

namespace Tests\Concerns;

use App\Models\BillingPlan;
use App\Models\User;
use App\Services\OrganizationService;
use Database\Seeders\BillingPlanSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

trait CreatesBillingFixtures
{
    protected function seedBillingPlans(): void
    {
        $this->seed(BillingPlanSeeder::class);
    }

    protected function createOwnerWithOrganization(string $orgName = 'Acme Auth'): array
    {
        $user = User::factory()->create();
        $organization = app(OrganizationService::class)->create($user, $orgName);

        return [$user, $organization];
    }

    protected function plan(string $slug): BillingPlan
    {
        return BillingPlan::query()->where('slug', $slug)->firstOrFail();
    }

    protected function makeTempEnv(string $contents = "APP_KEY=\n"): string
    {
        $path = storage_path('framework/testing/dodo-env-'.uniqid('', true));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);

        return $path;
    }

    protected function dodoHttpFakeForProductCreate(): void
    {
        Http::fake([
            'test.dodopayments.com/products*' => function (Request $request) {
                if ($request->method() === 'GET') {
                    return Http::response(['items' => []], 200);
                }

                $name = $request['name'] ?? 'Product';
                $slug = match (true) {
                    str_contains((string) $name, 'Starter') => 'starter',
                    str_contains((string) $name, 'Growth') => 'growth',
                    str_contains((string) $name, 'Scale') => 'scale',
                    default => 'other',
                };

                return Http::response([
                    'product_id' => 'pdt_test_'.$slug,
                    'name' => $name,
                    'metadata' => $request['metadata'] ?? [],
                ], 200);
            },
            'test.dodopayments.com/webhooks*' => function (Request $request) {
                if ($request->method() === 'POST') {
                    return Http::response([
                        'id' => 'ep_test_webhook',
                        'url' => $request['url'] ?? null,
                    ], 200);
                }

                if (str_ends_with($request->url(), '/secret')) {
                    return Http::response([
                        'secret' => 'whsec_dGVzdF9zZWNyZXQ=',
                    ], 200);
                }

                return Http::response([], 404);
            },
            'test.dodopayments.com/checkouts' => Http::response([
                'checkout_url' => 'https://test.dodopayments.com/checkout/sess_test',
                'session_id' => 'sess_test',
            ], 200),
        ]);
    }

    /**
     * @return array{webhook-id: string, webhook-timestamp: string, webhook-signature: string}
     */
    protected function signedWebhookHeaders(string $payload, string $secret, string $webhookId = 'msg_test_1'): array
    {
        $timestamp = (string) time();
        $secretKey = str_starts_with($secret, 'whsec_')
            ? base64_decode(substr($secret, 6), true)
            : $secret;

        $expected = base64_encode(hash_hmac(
            'sha256',
            $webhookId.'.'.$timestamp.'.'.$payload,
            (string) $secretKey,
            true,
        ));

        return [
            'webhook-id' => $webhookId,
            'webhook-timestamp' => $timestamp,
            'webhook-signature' => 'v1,'.$expected,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function postSignedDodoWebhook(array $body, string $secret, string $webhookId = 'msg_test_1'): TestResponse
    {
        $payload = json_encode($body, JSON_THROW_ON_ERROR);
        $headers = $this->signedWebhookHeaders($payload, $secret, $webhookId);

        return $this->call(
            'POST',
            '/api/v1/webhooks/dodo',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_WEBHOOK_ID' => $headers['webhook-id'],
                'HTTP_WEBHOOK_TIMESTAMP' => $headers['webhook-timestamp'],
                'HTTP_WEBHOOK_SIGNATURE' => $headers['webhook-signature'],
            ],
            $payload,
        );
    }
}
