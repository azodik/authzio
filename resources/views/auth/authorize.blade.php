<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" class="{{ $theme_class ?? 'theme-light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $client->login_headline ?: __('Sign in') }} — {{ $client->name }}</title>
    <style>
        :root {
            --primary: {{ $client->primary_color ?: '#0F766E' }};
            --bg: {{ $client->background_color ?: '#F3F4F6' }};
        }
    </style>
    @include('auth.partials.hosted-login-styles')
</head>
<body>
@php
    $q = $query;
    $showPassword = $methods['password'] ?? true;
    $showOtp = $methods['email_otp'] ?? false;
    $showPasskey = $methods['passkey'] ?? false;
    $otpAwaitingCode = (bool) session('otp_sent')
        || old('mode') === 'email_otp_verify'
        || (is_string(session('authzio_otp_login_email')) && session('authzio_otp_login_email') !== '');
    // After sending a code, land on the email-code step in verify mode.
    $defaultPanel = session('otp_sent') && $showOtp
        ? 'otp'
        : ($showPassword ? 'password' : ($showOtp ? 'otp' : 'passkey'));
    $methodCount = (int) $showPassword + (int) $showOtp + (int) $showPasskey;
    $otpEmailValue = old('email', session('authzio_otp_login_email'));
@endphp
<div class="{{ $layout_class ?? 'layout layout--form-right' }}">
    @include('auth.partials.hosted-brand-panel')

    <main class="main">
        <div class="form-wrap">
            @include('auth.partials.locale-switcher')
            @include('auth.partials.hosted-mobile-brand')

            <h1>{{ $client->login_headline ?: __('Sign in') }}</h1>
            <p class="lede">{{ $client->login_description ?: __('Continue to :app', ['app' => $client->name]) }}</p>

            @if ($errors->any())
                <p class="alert error" role="alert">{{ $errors->first() }}</p>
            @endif
            @if (session('otp_sent'))
                <p class="alert ok">{{ __('We sent a 6-digit code to your email.') }}</p>
            @endif
            @if (session('status'))
                <p class="alert ok">{{ session('status') }}</p>
            @endif

            @if (count($socialProviders) > 0 || count($ssoConnections ?? []) > 0)
                <div class="social">
                    @foreach ($ssoConnections ?? [] as $sso)
                        <a class="ghost" href="{{ url('/oauth/sso/'.$sso['id'].'/redirect').'?'.http_build_query($q) }}">
                            {{ __('Continue with :provider', ['provider' => $sso['name']]) }}
                        </a>
                    @endforeach
                    @foreach ($socialProviders as $provider)
                        <a class="ghost" href="{{ url('/oauth/social/'.$provider['provider'].'/redirect').'?'.http_build_query($q) }}">
                            {{ __('Continue with :provider', ['provider' => $provider['label']]) }}
                        </a>
                    @endforeach
                </div>
                @if ($methodCount > 0)
                    <div class="divider">{{ __('or') }}</div>
                @endif
            @endif

            @if ($methodCount > 1)
                <div class="methods" role="tablist" aria-label="{{ __('Sign-in methods') }}">
                    @if ($showPassword)
                        <button type="button" class="{{ $defaultPanel === 'password' ? 'active' : '' }}" data-tab="password" role="tab" aria-selected="{{ $defaultPanel === 'password' ? 'true' : 'false' }}">{{ __('Password') }}</button>
                    @endif
                    @if ($showOtp)
                        <button type="button" class="{{ $defaultPanel === 'otp' ? 'active' : '' }}" data-tab="otp" role="tab" aria-selected="{{ $defaultPanel === 'otp' ? 'true' : 'false' }}">{{ __('Email code') }}</button>
                    @endif
                    @if ($showPasskey)
                        <button type="button" class="{{ $defaultPanel === 'passkey' ? 'active' : '' }}" data-tab="passkey" role="tab" aria-selected="{{ $defaultPanel === 'passkey' ? 'true' : 'false' }}">{{ __('Passkey') }}</button>
                    @endif
                </div>
            @endif

            @if ($showPassword)
            <div class="step {{ $defaultPanel === 'password' ? 'active' : '' }}" data-panel="password">
                <form method="post" action="{{ url('/oauth/authorize') }}">
                    @csrf
                    @foreach ($q as $key => $value)
                        @if (is_scalar($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <input type="hidden" name="mode" value="password">
                    <label class="field">{{ __('Email') }}
                        <input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="you@company.com">
                    </label>
                    <label class="field">{{ __('Password') }}
                        <input type="password" name="password" required autocomplete="current-password">
                    </label>
                    @if ($client->show_forgot_password_link ?? true)
                        <div class="row-links">
                            <a href="{{ url('/oauth/forgot-password').'?'.http_build_query($q) }}">{{ __('Forgot password?') }}</a>
                        </div>
                    @endif
                    @include('auth.partials.legal', ['client' => $client])
                    <button class="primary" type="submit">{{ $client->login_button_label ?: __('Continue') }}</button>
                </form>
            </div>
            @endif

            @if ($showOtp)
            <div class="step {{ $defaultPanel === 'otp' ? 'active' : '' }}" data-panel="otp">
                {{-- Step 1: request a code --}}
                <div id="otp-send-step" @if ($otpAwaitingCode) hidden @endif>
                    <form method="post" action="{{ url('/oauth/authorize') }}">
                        @csrf
                        @foreach ($q as $key => $value)
                            @if (is_scalar($value))
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <input type="hidden" name="mode" value="email_otp_send">
                        <label class="field">{{ __('Email') }}
                            <input type="email" name="email" id="otp-email" value="{{ $otpEmailValue }}" required placeholder="you@company.com" autocomplete="username">
                        </label>
                        <p class="hint">{{ __('We’ll email you a one-time code. No password needed.') }}</p>
                        @include('auth.partials.legal', ['client' => $client])
                        <button class="primary" type="submit">{{ __('Send code') }}</button>
                    </form>
                </div>

                {{-- Step 2: enter the code (only after send) --}}
                <div id="otp-verify-step" @if (! $otpAwaitingCode) hidden @endif>
                    <form method="post" action="{{ url('/oauth/authorize') }}">
                        @csrf
                        @foreach ($q as $key => $value)
                            @if (is_scalar($value))
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <input type="hidden" name="mode" value="email_otp_verify">
                        <input type="hidden" name="email" id="otp-email-verify" value="{{ $otpEmailValue }}">
                        <label class="field">{{ __('6-digit code') }}
                            <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required placeholder="000000" autocomplete="one-time-code" @if ($otpAwaitingCode) autofocus @endif>
                        </label>
                        <p class="hint">
                            {{ __('Sent to') }} <strong id="otp-email-display">{{ $otpEmailValue ?: __('your email') }}</strong>.
                            <button type="button" class="text-link" id="otp-change-email">{{ __('Use a different email') }}</button>
                        </p>
                        <button class="primary" type="submit">{{ __('Verify & continue') }}</button>
                    </form>
                    <form method="post" action="{{ url('/oauth/authorize') }}" style="margin-top:10px">
                        @csrf
                        @foreach ($q as $key => $value)
                            @if (is_scalar($value))
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <input type="hidden" name="mode" value="email_otp_send">
                        <input type="hidden" name="email" value="{{ $otpEmailValue }}">
                        <button class="ghost" type="submit">{{ __('Resend code') }}</button>
                    </form>
                </div>
            </div>
            @endif

            @if ($showPasskey)
            <div class="step {{ $defaultPanel === 'passkey' ? 'active' : '' }}" data-panel="passkey">
                <label class="field">{{ __('Email') }} <span style="font-weight:500;color:var(--faint)">({{ __('optional') }})</span>
                    <input type="email" id="passkey-email" placeholder="you@company.com">
                </label>
                <p class="hint">{{ __('Use Face ID, Touch ID, or a security key.') }}</p>
                @include('auth.partials.legal', ['client' => $client])
                <button class="primary" type="button" id="passkey-btn">{{ __('Continue with passkey') }}</button>
                <p id="passkey-error" class="alert error" hidden></p>
            </div>
            @endif

            <div class="footer">
                @if ($client->show_signup_link)
                    <p class="meta">{{ __('Need an account?') }} <a href="#">{{ __('Sign up') }}</a></p>
                @endif
                @if (count($scopes) > 0)
                    <p class="fine">{{ __('This app is requesting:') }} {{ implode(', ', $scopes) }}</p>
                @endif
            </div>
        </div>
    </main>
</div>

<script>
(() => {
    document.querySelectorAll('[data-tab]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-tab]').forEach((b) => {
                b.classList.remove('active');
                b.setAttribute('aria-selected', 'false');
            });
            document.querySelectorAll('[data-panel]').forEach((p) => p.classList.remove('active'));
            btn.classList.add('active');
            btn.setAttribute('aria-selected', 'true');
            document.querySelector(`[data-panel="${btn.dataset.tab}"]`)?.classList.add('active');
        });
    });

    const otpEmail = document.getElementById('otp-email');
    const otpVerify = document.getElementById('otp-email-verify');
    const otpDisplay = document.getElementById('otp-email-display');
    const sendStep = document.getElementById('otp-send-step');
    const verifyStep = document.getElementById('otp-verify-step');

    otpEmail?.addEventListener('input', () => {
        if (otpVerify) otpVerify.value = otpEmail.value;
        if (otpDisplay) otpDisplay.textContent = otpEmail.value || 'your email';
    });

    document.getElementById('otp-change-email')?.addEventListener('click', () => {
        if (sendStep) sendStep.hidden = false;
        if (verifyStep) verifyStep.hidden = true;
        otpEmail?.focus();
    });

    function bufferToBase64url(buffer) {
        const bytes = new Uint8Array(buffer);
        let str = '';
        bytes.forEach((b) => { str += String.fromCharCode(b); });
        return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
    }

    function base64urlToBuffer(value) {
        const pad = '='.repeat((4 - (value.length % 4)) % 4);
        const base64 = (value + pad).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(base64);
        const buffer = new ArrayBuffer(raw.length);
        const view = new Uint8Array(buffer);
        for (let i = 0; i < raw.length; i++) view[i] = raw.charCodeAt(i);
        return buffer;
    }

    document.getElementById('passkey-btn')?.addEventListener('click', async () => {
        const errorEl = document.getElementById('passkey-error');
        errorEl.hidden = true;
        try {
            const email = document.getElementById('passkey-email')?.value || '';
            const params = new URLSearchParams({ ...@json($q), email });
            const optionsRes = await fetch(`/oauth/passkey/options?${params.toString()}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const options = await optionsRes.json();
            if (!optionsRes.ok) throw new Error(options.message || 'Unable to start passkey login');

            const publicKey = options.publicKey;
            publicKey.challenge = base64urlToBuffer(publicKey.challenge);
            if (publicKey.allowCredentials) {
                publicKey.allowCredentials = publicKey.allowCredentials.map((c) => ({
                    ...c,
                    id: base64urlToBuffer(c.id),
                }));
            }

            const cred = await navigator.credentials.get({ publicKey });
            if (!cred) throw new Error('Passkey cancelled');

            const payload = {
                id: cred.id,
                rawId: bufferToBase64url(cred.rawId),
                type: cred.type,
                response: {
                    clientDataJSON: bufferToBase64url(cred.response.clientDataJSON),
                    authenticatorData: bufferToBase64url(cred.response.authenticatorData),
                    signature: bufferToBase64url(cred.response.signature),
                    userHandle: cred.response.userHandle ? bufferToBase64url(cred.response.userHandle) : null,
                },
            };

            const verifyRes = await fetch('/oauth/passkey/verify', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            const body = await verifyRes.json();
            if (!verifyRes.ok) throw new Error(body.message || body.errors?.passkey?.[0] || 'Passkey failed');
            window.location.href = body.redirect;
        } catch (err) {
            errorEl.textContent = err.message || 'Passkey login failed';
            errorEl.hidden = false;
        }
    });
})();
</script>
</body>
</html>
