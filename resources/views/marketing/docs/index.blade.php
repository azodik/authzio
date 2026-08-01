@extends('layouts.docs')

@section('docs')
<h1>Authzio documentation</h1>
<p class="lead">
    Guides for installing Authzio, signing people in with care, connecting your apps with OAuth&nbsp;2.1 and OpenID Connect, and running the console — self-hosted or on Authzio Cloud.
</p>

<div class="callout">
    Authzio is developed by <strong>Azodik Consulting Private Limited</strong>
    in India. Source on GitHub:
    <span class="inline-flex align-middle ml-1">
        <x-github-link icon-class="size-4" class="text-teal hover:text-teal-deep" />
    </span>
</div>

<h2>Start here</h2>
<ul>
    <li><a href="{{ route('docs', ['page' => 'installation']) }}">Installation</a> — Herd, plain PHP, first login</li>
    <li><a href="{{ route('docs', ['page' => 'configuration']) }}">Configuration</a> — environment variables; local E2E / Mailpit</li>
    <li><a href="{{ route('docs', ['page' => 'docker']) }}">Docker</a> — Compose and multi-arch images</li>
</ul>

<h2>Product</h2>
<ul>
    <li><a href="{{ route('docs', ['page' => 'console']) }}">Console</a> — organizations and day-to-day ops</li>
    <li><a href="{{ route('docs', ['page' => 'authentication']) }}">User authentication</a> — password, social, OTP, passkeys, MFA</li>
    <li><a href="{{ route('docs', ['page' => 'oauth-oidc']) }}">OAuth &amp; OIDC</a> — clients, PKCE, tokens, discovery, JWKS</li>
    <li><a href="{{ route('docs', ['page' => 'organizations']) }}">Organizations</a> — members, invitations, domains, apps</li>
    <li><a href="{{ route('docs', ['page' => 'billing']) }}">Billing &amp; MAU</a> — cloud metering, alerts, and plans</li>
</ul>

<h2>Community</h2>
<ul>
    <li><a href="{{ route('docs', ['page' => 'faq']) }}">FAQ</a> — billing emails, MAU limits, SSO, self-hosting</li>
    <li><a href="{{ route('docs', ['page' => 'support']) }}">Issues &amp; support</a> — GitHub Issues, Sponsor, and Support</li>
</ul>

<h2>Legal</h2>
<ul>
    <li><a href="{{ route('privacy') }}">Privacy Policy</a></li>
    <li><a href="{{ route('terms') }}">Terms of Service</a></li>
    <li><a href="{{ route('cookies') }}">Cookie Policy</a></li>
</ul>

<p class="mt-8">
    Source and releases:
    <a href="https://github.com/azodik/authzio" rel="noopener">github.com/azodik/authzio</a>.
</p>
@endsection
