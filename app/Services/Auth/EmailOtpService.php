<?php

namespace App\Services\Auth;

use App\Enums\EmailOtpPurpose;
use App\Enums\EmailTemplateSlug;
use App\Models\EmailOtp;
use App\Models\OAuthClient;
use App\Models\Organization;
use App\Models\User;
use App\Services\Mail\TransactionalMailer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailOtpService
{
    public function __construct(
        private readonly TransactionalMailer $mailer,
    ) {}

    public function send(
        string $email,
        EmailOtpPurpose $purpose,
        ?Organization $organization = null,
        ?OAuthClient $client = null,
        ?User $user = null,
    ): void {
        $email = Str::lower(trim($email));
        $code = (string) random_int(100000, 999999);

        EmailOtp::query()
            ->where('email', $email)
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        EmailOtp::query()->create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'organization_id' => $organization?->id,
            'client_id' => $client?->id,
            'user_id' => $user?->id,
            'attempts' => 0,
            'expires_at' => now()->addMinutes((int) config('authzio.otp.ttl_minutes', 10)),
            'created_at' => now(),
        ]);

        $slug = $purpose === EmailOtpPurpose::Login
            ? EmailTemplateSlug::EmailOtp
            : EmailTemplateSlug::EmailVerification;

        $variables = [
            'otp_code' => $code,
            'mfa_code' => $code,
            'application_name' => $client?->name ?? $organization?->name ?? (string) config('app.name', 'Authzio'),
            'verify_url' => url('/oauth/verify-email'),
            'user_name' => $user?->name ?? 'there',
        ];

        if ($organization !== null) {
            $this->mailer->sendOrganization($organization, $email, $slug, $variables);
        } else {
            $this->mailer->sendPlatform($email, $slug, $variables);
        }
    }

    public function verify(
        string $email,
        string $code,
        EmailOtpPurpose $purpose,
    ): EmailOtp {
        $email = Str::lower(trim($email));

        $otp = EmailOtp::query()
            ->where('email', $email)
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->first();

        if ($otp === null || $otp->isExpired()) {
            throw ValidationException::withMessages([
                'code' => ['Verification code expired or not found. Request a new one.'],
            ]);
        }

        if ($otp->attempts >= (int) config('authzio.otp.max_attempts', 5)) {
            throw ValidationException::withMessages([
                'code' => ['Too many attempts. Request a new code.'],
            ]);
        }

        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code_hash)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code.'],
            ]);
        }

        $otp->update(['consumed_at' => now()]);

        return $otp->fresh() ?? $otp;
    }
}
