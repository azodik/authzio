<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDodoWebhookJob;
use App\Services\Billing\DodoWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DodoWebhookController extends Controller
{
    public function __construct(
        private readonly DodoWebhookProcessor $processor,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $webhookId = (string) $request->header('webhook-id', '');
        $timestamp = (string) $request->header('webhook-timestamp', '');
        $signature = (string) $request->header('webhook-signature', '');

        if ($webhookId === '' || $timestamp === '' || $signature === '') {
            return response()->json(['message' => 'Missing webhook signature headers.'], 400);
        }

        if (! $this->processor->verifySignature($payload, $webhookId, $timestamp, $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        /** @var array<string, mixed> $body */
        $body = $request->json()->all();
        $eventType = (string) ($body['type'] ?? $body['event_type'] ?? 'unknown');
        $id = $webhookId !== '' ? $webhookId : (string) ($body['id'] ?? uniqid('local_', true));

        ProcessDodoWebhookJob::dispatch($id, $eventType, $body, [
            'webhook-id' => $webhookId,
            'webhook-timestamp' => $timestamp,
            'webhook-signature' => $signature,
        ]);

        return response()->json(['received' => true]);
    }
}
