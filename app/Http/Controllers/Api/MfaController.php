<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Auth\MfaService;
use App\Services\Billing\UsageTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class MfaController extends Controller
{
    public function __construct(
        private readonly MfaService $mfa,
        private readonly AuditLogger $auditLogger,
        private readonly UsageTracker $usageTracker,
    ) {}

    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'enabled' => (bool) $user->mfa_enabled,
            'confirmed_at' => $user->mfa_confirmed_at?->toIso8601String(),
            'recovery_codes_remaining' => $user->mfa_enabled
                ? $this->mfa->remainingRecoveryCodeCount($user)
                : 0,
            'globally_enabled' => $this->mfa->isGloballyEnabled(),
        ]);
    }

    public function beginSetup(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $setup = $this->mfa->beginSetup($user);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'mfa' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'secret' => $setup['secret'],
            'otpauth_url' => $setup['otpauth_url'],
            'qr_svg' => $setup['qr_svg'],
        ]);
    }

    public function confirmSetup(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        try {
            $recoveryCodes = $this->mfa->confirmSetup($user, $validated['code']);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'mfa' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'enabled' => true,
            'recovery_codes' => $recoveryCodes,
            'warning' => 'Store these recovery codes now. They will not be shown again.',
            'user' => $user->fresh()?->load('organizations'),
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $this->mfa->disable($user, $validated['code']);

        return response()->json([
            'enabled' => false,
            'user' => $user->fresh()?->load('organizations'),
        ]);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        try {
            $codes = $this->mfa->regenerateRecoveryCodes($user, $validated['code']);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'mfa' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'recovery_codes' => $codes,
            'warning' => 'Store these recovery codes now. Previous codes no longer work.',
            'recovery_codes_remaining' => count($codes),
        ]);
    }

    /**
     * Complete console login after password step when MFA is required.
     */
    public function challenge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $userId = $request->session()->get('authzio_pending_mfa_user_id');
        if (! is_int($userId) && ! is_string($userId)) {
            throw ValidationException::withMessages([
                'code' => [__('No MFA challenge is pending. Sign in again.')],
            ]);
        }

        $user = User::query()->find($userId);
        if ($user === null || ! $user->is_active || ! $user->mfa_enabled) {
            $request->session()->forget([
                'authzio_pending_mfa_user_id',
                'authzio_pending_mfa_remember',
            ]);

            throw ValidationException::withMessages([
                'code' => [__('No MFA challenge is pending. Sign in again.')],
            ]);
        }

        if (! $this->mfa->verify($user, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => [__('Invalid authenticator or recovery code.')],
            ]);
        }

        $remember = (bool) $request->session()->pull('authzio_pending_mfa_remember', false);
        $request->session()->forget('authzio_pending_mfa_user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->auditLogger->log(AuditAction::Login, $user);
        $this->usageTracker->recordConsoleLogin($user);

        $user->load('organizations');

        return response()->json([
            'user' => $user,
        ]);
    }
}
