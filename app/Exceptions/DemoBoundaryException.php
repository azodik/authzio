<?php

namespace App\Exceptions;

use App\Services\Demo\DemoCapability;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DemoBoundaryException extends Exception
{
    public function __construct(
        public readonly DemoCapability $capability,
        string $message = '',
    ) {
        /** @var array<string, string> $messages */
        $messages = config('demo.messages', []);
        $fallback = $messages[$capability->value]
            ?? $messages['default']
            ?? 'This action is not available on the demo account.';

        parent::__construct($message !== '' ? $message : $fallback);
    }

    public function render(Request $request): JsonResponse|Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage(),
                'code' => 'demo_boundary',
                'capability' => $this->capability->value,
            ], 403);
        }

        return response()->view('auth.demo-blocked', [
            'message' => $this->getMessage(),
            'capability' => $this->capability->value,
        ], 403);
    }
}
