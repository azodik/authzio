<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" class="{{ $theme_class ?? 'theme-light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Authenticator') }} — {{ $client->name }}</title>
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
        'brandDescription' => __('Confirm it’s you with an authenticator code.'),
    ])
    <main class="main">
        <div class="form-wrap">
            @include('auth.partials.locale-switcher')
            @include('auth.partials.hosted-mobile-brand')

            <h1>{{ __('Authenticator code') }}</h1>
            <p class="lede">{{ __('Enter the 6-digit code from your authenticator app, or one of your recovery codes.') }}</p>

            @if ($errors->any())
                <p class="alert error" role="alert">{{ $errors->first() }}</p>
            @endif

            <form method="post" action="{{ url('/oauth/mfa') }}">
                @csrf
                @foreach ($query as $key => $value)
                    @if (is_scalar($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <label class="field">{{ __('Code') }}
                    <input type="text" name="code" inputmode="text" autocomplete="one-time-code" required autofocus>
                </label>
                <button class="primary" type="submit">{{ __('Continue') }}</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>
