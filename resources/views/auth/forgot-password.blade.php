<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" class="{{ $theme_class ?? 'theme-light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Forgot password') }} — {{ $client->name }}</title>
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
        'brandDescription' => __('Reset your password to get back into your account.'),
    ])
    <main class="main">
        <div class="form-wrap">
            @include('auth.partials.locale-switcher')
            @include('auth.partials.hosted-mobile-brand')

            <h1>{{ __('Forgot password') }}</h1>
            <p class="lede">{{ __('Enter your email and we’ll send a reset link if an account exists.') }}</p>

            @if ($errors->any())
                <p class="alert error" role="alert">{{ $errors->first() }}</p>
            @endif

            @if ($sent)
                <p class="alert ok">{{ __('If an account exists for that email, we sent password reset instructions.') }}</p>
                <a class="primary" href="{{ url('/oauth/authorize').'?'.http_build_query($query) }}">{{ __('Back to sign in') }}</a>
            @else
                <form method="post" action="{{ url('/oauth/forgot-password') }}">
                    @csrf
                    @foreach ($query as $key => $value)
                        @if (is_scalar($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <label class="field">{{ __('Email') }}
                        <input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="you@company.com">
                    </label>
                    <button class="primary" type="submit">{{ __('Send reset link') }}</button>
                </form>
                <div class="footer">
                    <p class="meta"><a href="{{ url('/oauth/authorize').'?'.http_build_query($query) }}">{{ __('Back to sign in') }}</a></p>
                </div>
            @endif
        </div>
    </main>
</div>
</body>
</html>
