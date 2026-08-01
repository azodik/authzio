<?php

namespace Tests\Unit;

use App\Services\Billing\DodoWebhookProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DodoWebhookProcessorSignatureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_verifies_v1_signatures_with_whsec_secret(): void
    {
        $secret = 'whsec_'.base64_encode('raw-test-secret');
        config(['billing.dodo.webhook_secret' => $secret]);

        $webhookId = 'msg_1';
        $timestamp = (string) time();
        $payload = '{"type":"subscription.active"}';
        $signed = base64_encode(hash_hmac(
            'sha256',
            $webhookId.'.'.$timestamp.'.'.$payload,
            'raw-test-secret',
            true,
        ));

        $processor = app(DodoWebhookProcessor::class);

        $this->assertTrue($processor->verifySignature(
            $payload,
            $webhookId,
            $timestamp,
            'v1,'.$signed,
        ));
    }

    #[Test]
    public function it_rejects_stale_timestamps(): void
    {
        $secret = 'whsec_'.base64_encode('raw-test-secret');
        config(['billing.dodo.webhook_secret' => $secret]);

        $webhookId = 'msg_1';
        $timestamp = (string) (time() - 600);
        $payload = '{"type":"subscription.active"}';
        $signed = base64_encode(hash_hmac(
            'sha256',
            $webhookId.'.'.$timestamp.'.'.$payload,
            'raw-test-secret',
            true,
        ));

        $processor = app(DodoWebhookProcessor::class);

        $this->assertFalse($processor->verifySignature(
            $payload,
            $webhookId,
            $timestamp,
            'v1,'.$signed,
        ));
    }

    #[Test]
    public function it_rejects_tampered_payloads(): void
    {
        $secret = 'whsec_'.base64_encode('raw-test-secret');
        config(['billing.dodo.webhook_secret' => $secret]);

        $processor = app(DodoWebhookProcessor::class);

        $this->assertFalse($processor->verifySignature(
            '{"type":"subscription.active"}',
            'msg_1',
            (string) time(),
            'v1,not-a-real-signature',
        ));
    }
}
