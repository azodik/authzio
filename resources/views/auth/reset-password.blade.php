<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" class="{{ $theme_class ?? 'theme-light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Reset password') }} — {{ $client->name }}</title>
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
        'brandDescription' => __('Choose a strong password, then continue signing in.'),
    ])
    <main class="main">
        <div class="form-wrap">
            @include('auth.partials.locale-switcher')
            @include('auth.partials.hosted-mobile-brand')

            <h1>{{ __('New password') }}</h1>
            <p class="lede">{{ __('Update your password for :app.', ['app' => $client->name]) }}</p>

            @if ($errors->any())
                <p class="alert error" role="alert">{{ $errors->first() }}</p>
            @endif

            <form method="post" action="{{ url('/oauth/reset-password') }}">
                @csrf
                @foreach ($query as $key => $value)
                    @if (is_scalar($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <input type="hidden" name="token" value="{{ $token }}">
                <label class="field">{{ __('Email') }}
                    <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username">
                </label>
                <label class="field">{{ __('New password') }}
                    <input type="password" name="password" required autocomplete="new-password">
                </label>
                <label class="field">{{ __('Confirm password') }}
                    <input type="password" name="password_confirmation" required autocomplete="new-password">
                </label>
                <button class="primary" type="submit">{{ __('Update password') }}</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>
