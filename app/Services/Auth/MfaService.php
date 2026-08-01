<?php

namespace App\Services\Auth;

use App\Enums\AuditAction;
use App\Models\MfaRecoveryCode;
use App\Models\User;
use App\Services\AuditLogger;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;

class MfaService
{
    private const RECOVERY_CODE_COUNT = 10;

    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function isGloballyEnabled(): bool
    {
        return (bool) config('authzio.mfa.enabled', true);
    }

    /**
     * Start authenticator enrollment. Secret is stored but MFA stays disabled until confirm.
     *
     * @return array{secret: string, otpauth_url: string, qr_svg: string}
     */
    public function beginSetup(User $user): array
    {
        $this->assertEnabled();

        if ($user->mfa_enabled) {
            throw ValidationException::withMessages([
                'mfa' => [__('Authenticator MFA is already enabled.')],
            ]);
        }

        $secret = $this->google2fa->generateSecretKey(32);

        DB::transaction(function () use ($user, $secret): void {
            MfaRecoveryCode::query()->where('user_id', $user->id)->delete();

            $user->forceFill([
                'mfa_secret' => $secret,
                'mfa_enabled' => false,
                'mfa_confirmed_at' => null,
            ])->save();
        });

        $otpauthUrl = $this->google2fa->getQRCodeUrl(
            (string) config('authzio.mfa.issuer', config('app.name', 'Authzio')),
            $user->email,
            $secret,
        );

        return [
            'secret' => $secret,
            'otpauth_url' => $otpauthUrl,
            'qr_svg' => $this->qrSvg($otpauthUrl),
        ];
    }

    /**
     * Confirm setup with a TOTP code and issue recovery codes (plaintext shown once).
     *
     * @return list<string>
     */
    public function confirmSetup(User $user, string $code): array
    {
        $this->assertEnabled();

        if ($user->mfa_enabled) {
            throw ValidationException::withMessages([
                'mfa' => [__('Authenticator MFA is already enabled.')],
            ]);
        }

        $secret = $user->mfa_secret;
        if (! is_string($secret) || $secret === '') {
            throw ValidationException::withMessages([
                'mfa' => [__('Start authenticator setup before confirming.')],
            ]);
        }

        if (! $this->verifyTotp($secret, $code)) {
            throw ValidationException::withMessages([
                'code' => [__('Invalid authenticator code.')],
            ]);
        }

        $plainCodes = $this->generateRecoveryCodes();

        DB::transaction(function () use ($user, $plainCodes): void {
            $this->replaceRecoveryCodes($user, $plainCodes);

            $user->forceFill([
                'mfa_enabled' => true,
                'mfa_confirmed_at' => now(),
            ])->save();
        });

        $this->auditLogger->log(
            AuditAction::MfaEnabled,
            $user,
            resourceType: User::class,
            resourceId: (string) $user->id,
        );

        return $plainCodes;
    }

    /**
     * Verify a TOTP or recovery code for an MFA-enabled user.
     */
    public function verify(User $user, string $code): bool
    {
        if (! $user->mfa_enabled) {
            return true;
        }

        $normalized = $this->normalizeCode($code);
        $secret = $user->mfa_secret;

        if (is_string($secret) && $secret !== '' && preg_match('/^\d{6}$/', $normalized) === 1) {
            if ($this->verifyTotp($secret, $normalized)) {
                return true;
            }
        }

        return $this->consumeRecoveryCode($user, $normalized);
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user, string $code): array
    {
        $this->assertEnabled();
        $this->assertMfaEnabled($user);

        if (! $this->verify($user, $code)) {
            throw ValidationException::withMessages([
                'code' => [__('Invalid authenticator or recovery code.')],
            ]);
        }

        $plainCodes = $this->generateRecoveryCodes();
        $this->replaceRecoveryCodes($user, $plainCodes);

        return $plainCodes;
    }

    public function disable(User $user, string $code): void
    {
        $this->assertMfaEnabled($user);

        if (! $this->verify($user, $code)) {
            throw ValidationException::withMessages([
                'code' => [__('Invalid authenticator or recovery code.')],
            ]);
        }

        DB::transaction(function () use ($user): void {
            MfaRecoveryCode::query()->where('user_id', $user->id)->delete();

            $user->forceFill([
                'mfa_enabled' => false,
                'mfa_secret' => null,
                'mfa_confirmed_at' => null,
            ])->save();
        });

        $this->auditLogger->log(
            AuditAction::MfaDisabled,
            $user,
            resourceType: User::class,
            resourceId: (string) $user->id,
        );
    }

    public function remainingRecoveryCodeCount(User $user): int
    {
        return MfaRecoveryCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->count();
    }

    private function verifyTotp(string $secret, string $code): bool
    {
        try {
            return $this->google2fa->verifyKey($secret, $code, 1);
        } catch (\Throwable) {
            return false;
        }
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $candidate = strtoupper(str_replace([' ', '-'], '', $code));
        if (strlen($candidate) < 8) {
            return false;
        }

        $codes = MfaRecoveryCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->get();

        foreach ($codes as $recoveryCode) {
            if (Hash::check($candidate, $recoveryCode->code_hash)) {
                $recoveryCode->forceFill(['used_at' => now()])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $raw = strtoupper(Str::random(8));
            $codes[] = substr($raw, 0, 4).'-'.substr($raw, 4, 4);
        }

        return $codes;
    }

    /**
     * @param  list<string>  $plainCodes
     */
    private function replaceRecoveryCodes(User $user, array $plainCodes): void
    {
        MfaRecoveryCode::query()->where('user_id', $user->id)->delete();

        foreach ($plainCodes as $plain) {
            MfaRecoveryCode::query()->create([
                'user_id' => $user->id,
                'code_hash' => Hash::make(strtoupper(str_replace([' ', '-'], '', $plain))),
            ]);
        }
    }

    private function normalizeCode(string $code): string
    {
        return trim($code);
    }

    private function qrSvg(string $otpauthUrl): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(220),
            new SvgImageBackEnd,
        );
        $writer = new Writer($renderer);

        return $writer->writeString($otpauthUrl);
    }

    private function assertEnabled(): void
    {
        if (! $this->isGloballyEnabled()) {
            throw new RuntimeException('MFA is disabled on this Authzio instance.');
        }
    }

    private function assertMfaEnabled(User $user): void
    {
        if (! $user->mfa_enabled) {
            throw ValidationException::withMessages([
                'mfa' => [__('Authenticator MFA is not enabled.')],
            ]);
        }
    }
}
