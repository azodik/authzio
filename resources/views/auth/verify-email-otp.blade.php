<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" class="{{ $theme_class ?? 'theme-light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Verify email') }} — {{ $client->name }}</title>
    <style>
        :root {
            --primary: {{ $client->primary_color ?: '#0F766E' }};
            --bg: {{ $client->background_color ?: '#F3F4F6' }};
        }
    </style>
    @include('auth.partials.hosted-login-styles')
</head>
<body>
<div class="{{ $layout_class ?? 'layout layout--form-right' }}">
    @include('auth.partials.hosted-brand-panel', [
        'brandDescription' => __('Verify your email to continue.'),
    ])
    <main class="main">
        <div class="form-wrap">
            @include('auth.partials.locale-switcher')
            @include('auth.partials.hosted-mobile-brand')

            <h1>{{ __('Verify your email') }}</h1>
            <p class="lede">{{ __('This application requires a verified email. Enter your address and the code we send you.') }}</p>

            @if ($errors->any())
                <p class="alert error" role="alert">{{ $errors->first() }}</p>
            @endif
            @if (session('otp_sent'))
                <p class="alert ok">{{ __('Code sent.') }}</p>
            @endif

            <form method="post" action="{{ url('/oauth/verify-email/send') }}">
                @csrf
                <label class="field">{{ __('Email') }}
                    <input type="email" name="email" value="{{ old('email', $email) }}" required>
                </label>
                <button class="ghost" type="submit">{{ __('Send verification code') }}</button>
            </form>

            <form method="post" action="{{ url('/oauth/verify-email') }}">
                @csrf
                <label class="field">{{ __('Email') }}
                    <input type="email" name="email" value="{{ old('email', $email) }}" required>
                </label>
                <label class="field">{{ __('6-digit code') }}
                    <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
                </label>
                <button class="primary" type="submit">{{ __('Verify & continue') }}</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>
