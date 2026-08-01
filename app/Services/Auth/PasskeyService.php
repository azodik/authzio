<?php

namespace App\Services\Auth;

use App\Models\Passkey;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;

class PasskeyService
{
    public function webAuthn(string $rpId, string $rpName = 'Authzio'): WebAuthn
    {
        return new WebAuthn($rpName, $rpId, null, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function registrationOptions(User $user, string $rpId): array
    {
        $webAuthn = $this->webAuthn($rpId);
        $userId = substr(hash('sha256', (string) $user->uuid, true), 0, 32);

        $createArgs = $webAuthn->getCreateArgs(
            $userId,
            $user->email,
            $user->name,
            60,
            false,
            'preferred',
        );

        session([
            'webauthn_register_challenge' => (string) $webAuthn->getChallenge(),
            'webauthn_register_user_id' => $user->id,
        ]);

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) json_encode($createArgs), true);

        return $payload;
    }

    /**
     * @param  array{id: string, rawId: string, type: string, response: array{clientDataJSON: string, attestationObject: string}}  $credential
     */
    public function register(User $user, array $credential, string $rpId, ?string $name = null): Passkey
    {
        $webAuthn = $this->webAuthn($rpId);
        $challenge = session('webauthn_register_challenge');

        if (! is_string($challenge) || $challenge === '') {
            throw ValidationException::withMessages([
                'passkey' => ['Registration challenge expired. Try again.'],
            ]);
        }

        try {
            $data = $webAuthn->processCreate(
                $this->decode($credential['response']['clientDataJSON']),
                $this->decode($credential['response']['attestationObject']),
                $challenge,
                false,
                true,
                false,
            );
        } catch (WebAuthnException $exception) {
            throw ValidationException::withMessages([
                'passkey' => [$exception->getMessage()],
            ]);
        }

        session()->forget(['webauthn_register_challenge', 'webauthn_register_user_id']);

        return Passkey::query()->create([
            'user_id' => $user->id,
            'name' => $name ?: 'Passkey',
            'credential_id' => $credential['id'],
            'public_key' => $data->credentialPublicKey,
            'sign_count' => $data->signatureCounter,
            'attestation_format' => $data->attestationFormat ?? null,
            'aaguid' => isset($data->AAGUID) ? bin2hex((string) $data->AAGUID) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function authenticationOptions(string $rpId, ?string $email = null): array
    {
        $webAuthn = $this->webAuthn($rpId);
        $ids = [];

        if ($email !== null && $email !== '') {
            $user = User::query()->where('email', Str::lower($email))->first();
            if ($user !== null) {
                foreach ($user->passkeys as $passkey) {
                    $ids[] = $this->decode($passkey->credential_id);
                }
            }
        }

        $getArgs = $webAuthn->getGetArgs($ids, 60, true, true, true, true, true, 'preferred');

        session([
            'webauthn_login_challenge' => (string) $webAuthn->getChallenge(),
        ]);

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) json_encode($getArgs), true);

        return $payload;
    }

    /**
     * @param  array{id: string, rawId: string, type: string, response: array{clientDataJSON: string, authenticatorData: string, signature: string, userHandle?: string|null}}  $credential
     */
    public function authenticate(array $credential, string $rpId): User
    {
        $webAuthn = $this->webAuthn($rpId);
        $challenge = session('webauthn_login_challenge');

        if (! is_string($challenge) || $challenge === '') {
            throw ValidationException::withMessages([
                'passkey' => ['Login challenge expired. Try again.'],
            ]);
        }

        $passkey = Passkey::query()->where('credential_id', $credential['id'])->first();

        if ($passkey === null) {
            throw ValidationException::withMessages([
                'passkey' => ['Unknown passkey.'],
            ]);
        }

        try {
            $webAuthn->processGet(
                $this->decode($credential['response']['clientDataJSON']),
                $this->decode($credential['response']['authenticatorData']),
                $this->decode($credential['response']['signature']),
                $passkey->public_key,
                $challenge,
                $passkey->sign_count,
                false,
                true,
            );
        } catch (WebAuthnException $exception) {
            throw ValidationException::withMessages([
                'passkey' => [$exception->getMessage()],
            ]);
        }

        $passkey->update([
            'sign_count' => $passkey->sign_count + 1,
            'last_used_at' => now(),
        ]);

        session()->forget('webauthn_login_challenge');

        $user = $passkey->user;
        if ($user === null || ! $user->is_active) {
            throw ValidationException::withMessages([
                'passkey' => ['Passkey account is inactive.'],
            ]);
        }

        Auth::login($user, false);
        request()->session()->regenerate();

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        return $user;
    }

    private function decode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        if ($decoded === false) {
            throw ValidationException::withMessages([
                'passkey' => ['Invalid passkey payload encoding.'],
            ]);
        }

        return $decoded;
    }
}
