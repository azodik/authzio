<?php

namespace App\Services\Auth;

use App\Enums\EmailTemplateSlug;
use App\Models\EmailVerificationToken;
use App\Models\User;
use App\Services\Mail\TransactionalMailer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailVerificationService
{
    public function __construct(
        private readonly TransactionalMailer $mailer,
    ) {}

    /**
     * @return array{token: string, code: string}
     */
    public function issue(User $user): array
    {
        $token = Str::random(64);
        $code = (string) random_int(100000, 999999);

        EmailVerificationToken::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        EmailVerificationToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addHours(24),
        ]);

        $verifyUrl = rtrim((string) config('app.url'), '/').'/console/verify-email?token='.$token;

        $this->mailer->sendPlatform($user->email, EmailTemplateSlug::EmailVerification, [
            'user_name' => $user->name,
            'verify_url' => $verifyUrl,
            'verification_code' => $code,
        ], $user->preferred_locale ?? 'en');

        return ['token' => $token, 'code' => $code];
    }

    public function verifyToken(string $token): User
    {
        $record = EmailVerificationToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->latest()
            ->first();

        if ($record === null || ! $record->isValid()) {
            throw ValidationException::withMessages([
                'token' => [__('This verification link is invalid or has expired.')],
            ]);
        }

        return $this->consume($record);
    }

    public function verifyCode(User $user, string $code): User
    {
        $record = EmailVerificationToken::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if ($record === null || ! $record->isValid() || $record->code_hash === null) {
            throw ValidationException::withMessages([
                'code' => [__('This verification code is invalid or has expired.')],
            ]);
        }

        if (! Hash::check($code, $record->code_hash)) {
            throw ValidationException::withMessages([
                'code' => [__('Incorrect verification code.')],
            ]);
        }

        return $this->consume($record);
    }

    private function consume(EmailVerificationToken $record): User
    {
        $record->update(['consumed_at' => now()]);
        $user = $record->user;
        $wasUnverified = $user->email_verified_at === null;
        $user->forceFill(['email_verified_at' => now()])->save();

        if ($wasUnverified) {
            $this->mailer->sendPlatform($user->email, EmailTemplateSlug::Welcome, [
                'user_name' => $user->name,
            ], $user->preferred_locale ?? 'en');
        }

        return $user->fresh() ?? $user;
    }
}
