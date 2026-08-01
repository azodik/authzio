<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}" class="{{ $theme_class ?? 'theme-light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
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
    <div class="preview-badge">{{ __('Preview · not live') }}</div>
    <div class="{{ $layout_class ?? 'layout layout--form-right' }}">
        @include('auth.partials.hosted-brand-panel')
        <main class="main">
            <div class="form-wrap">
                @include('auth.partials.locale-switcher')
                @include('auth.partials.hosted-mobile-brand')

                <h1>{{ $client->login_headline ?: __('Sign in') }}</h1>
                <p class="lede">{{ $client->login_description ?: __('Continue to :app', ['app' => $client->name]) }}</p>

                <form onsubmit="event.preventDefault()">
                    <label class="field">{{ __('Email') }}
                        <input type="email" value="alex@example.com" autocomplete="username">
                    </label>
                    <label class="field">{{ __('Password') }}
                        <input type="password" value="••••••••••••" autocomplete="current-password">
                    </label>

                    @if ($client->show_forgot_password_link ?? true)
                        <div class="row-links"><a href="#">{{ __('Forgot password?') }}</a></div>
                    @endif

                    @if ($client->require_legal_accept)
                        <label class="legal">
                            <input type="checkbox" checked>
                            <span>
                                {{ __('I agree to the') }}
                                @if ($client->terms_url)<a href="{{ $client->terms_url }}" target="_blank" rel="noreferrer">{{ __('Terms') }}</a>@else {{ __('Terms') }} @endif
                                {{ __('and') }}
                                @if ($client->privacy_url)<a href="{{ $client->privacy_url }}" target="_blank" rel="noreferrer">{{ __('Privacy Policy') }}</a>@else {{ __('Privacy Policy') }} @endif
                            </span>
                        </label>
                    @endif

                    <button class="primary" type="submit">{{ $client->login_button_label ?: __('Continue') }}</button>
                </form>

                <div class="footer">
                    @if ($client->show_signup_link)
                        <p class="meta">{{ __('Need an account?') }} <a href="#">{{ __('Sign up') }}</a></p>
                    @endif
                </div>
            </div>
        </main>
    </div>
</body>
</html>
