<aside class="brand" aria-hidden="false">
    <div class="brand-top">
        @if ($client->logo_url)
            <div class="brand-mark"><img src="{{ $client->logo_url }}" alt=""></div>
        @else
            <div class="brand-mark">{{ strtoupper(substr($client->name, 0, 1)) }}</div>
        @endif
        <h2>{{ $client->name }}</h2>
        <p>{{ $brandDescription ?? ($client->login_description ?: __('Sign in to continue to your account securely.')) }}</p>
    </div>
    <div class="brand-bottom">{{ __('Hosted authentication') }}</div>
</aside>
