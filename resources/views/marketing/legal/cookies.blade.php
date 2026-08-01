@extends('layouts.legal')

@section('legal')
<p>
    This Cookie Policy explains how <strong>{{ config('marketing.organization') }}</strong>
    uses cookies and similar technologies on Authzio websites and Authzio Cloud.
    It should be read with our <a href="{{ route('privacy') }}">Privacy Policy</a>.
</p>

<h2>1. What are cookies?</h2>
<p>
    Cookies are small text files stored on your device. We also use related technologies such as
    local storage and pixels that help the site function, keep you signed in, or measure performance.
</p>

<h2>2. How we use cookies</h2>

<h3>Essential</h3>
<ul>
    <li>Session and CSRF cookies required for the console and secure forms.</li>
    <li>Preferences such as locale when you set them in the product.</li>
    <li>Security and abuse-prevention signals.</li>
</ul>
<p>These are necessary for the Services to work and generally cannot be disabled in-product.</p>

<h3>Analytics and advertising (when configured)</h3>
<p>
    If we enable analytics or advertising tags (for example Google Analytics, Tag Manager, or similar),
    those providers may set cookies to understand traffic and campaign performance.
    They only load when the corresponding IDs are configured in our environment.
</p>

<h2>3. Third-party cookies</h2>
<p>
    Third parties may set their own cookies when you interact with embedded content, payment checkout,
    or social links. Their policies govern those cookies.
</p>

<h2>4. Your choices</h2>
<ul>
    <li>Most browsers let you block or delete cookies. Blocking essential cookies may break sign-in or forms.</li>
    <li>Where we use optional analytics or ads, you can use browser controls and industry opt-outs where available.</li>
    <li>For Authzio Cloud checkout, the payment provider may set cookies needed to complete payment securely.</li>
</ul>

<h2>5. Updates</h2>
<p>
    We may update this Cookie Policy when our practices change. The “Last updated” date at the top will change accordingly.
</p>

<h2>6. Contact</h2>
<p>
    {{ config('marketing.organization') }}<br>
    <a href="{{ config('marketing.organization_url') }}" rel="noopener">{{ config('marketing.organization_url') }}</a><br>
    Related: <a href="{{ route('privacy') }}">Privacy</a> · <a href="{{ route('terms') }}">Terms</a>
</p>
@endsection
