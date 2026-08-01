<?php

namespace App\Services\Auth;

use App\Enums\AuditAction;
use App\Enums\SupportedLocale;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Billing\UsageTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConsoleSessionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly UsageTracker $usageTracker,
        private readonly MfaService $mfa,
    ) {}

    /**
     * Establish a console session for an already-authenticated (or about-to-login) user.
     * Caller must Auth::login($user) before calling, or pass $alreadyLoggedIn=false to login here.
     *
     * @return array{status: 'ok'|'mfa'|'inactive', user?: User}
     */
    public function establish(
        Request $request,
        User $user,
        bool $remember = true,
        bool $alreadyLoggedIn = false,
        ?string $locale = null,
    ): array {
        if (! $alreadyLoggedIn) {
            Auth::login($user, $remember);
        }

        $request->session()->regenerate();

        /** @var User $authenticated */
        $authenticated = Auth::user() ?? $user;

        if (! $authenticated->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return ['status' => 'inactive'];
        }

        if ($this->mfa->isGloballyEnabled() && $authenticated->mfa_enabled) {
            $userId = $authenticated->id;

            Auth::logout();
            $request->session()->put('authzio_pending_mfa_user_id', $userId);
            $request->session()->put('authzio_pending_mfa_remember', $remember);

            return ['status' => 'mfa', 'user' => $authenticated];
        }

        if ($locale !== null && in_array($locale, SupportedLocale::values(), true)) {
            $authenticated->preferred_locale = $locale;
        }

        $authenticated->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->auditLogger->log(AuditAction::Login, $authenticated);
        $this->usageTracker->recordConsoleLogin($authenticated);

        $authenticated->load('organizations');

        return ['status' => 'ok', 'user' => $authenticated];
    }
}
