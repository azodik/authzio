@extends('layouts.docs')

@section('docs')
<h1>Issues &amp; support</h1>
<p class="lead">
    Authzio is open source. We use GitHub for bugs, ideas, and discussion — and Azodik’s Sponsor and Support channels when you want to help fund the work.
</p>

<h2>Raise an issue</h2>
<ol>
    <li>Search <a href="https://github.com/azodik/authzio/issues" rel="noopener">existing issues</a> so we do not duplicate work.</li>
    <li>Open a <a href="https://github.com/azodik/authzio/issues/new/choose" rel="noopener">new issue</a>.</li>
    <li>Include version or commit, environment (Herd, Docker, OS), and clear steps to reproduce.</li>
    <li>Do <strong>not</strong> paste secrets, private keys, or production tokens.</li>
</ol>

<div class="callout">
    Security-sensitive findings: contact
    <strong>Azodik Consulting Private Limited</strong> via
    <a href="https://azodik.com" rel="noopener">azodik.com</a>
    instead of filing a public issue.
</div>

<h2>Sponsor Authzio</h2>
<p>
    <strong>Self-hosting stays free forever.</strong> Sponsorship is optional — it keeps that promise sustainable.
    If you run Authzio in production, you already depend on security fixes, dependency updates, and regular releases.
    That work is not funded by a proprietary license.
</p>
<ul>
    <li><strong>Keep self-host free</strong> — MIT, no forced Cloud upsell on core IAM</li>
    <li><strong>Fund security &amp; maintenance</strong> — CVE response, release engineering, review time</li>
    <li><strong>Shape the roadmap</strong> — help prioritize SSO, DX, and self-host ergonomics</li>
</ul>
<p>
    Prefer managed hosting instead? Use <a href="{{ url('/pricing') }}">Authzio Cloud</a>.
    Otherwise, support the open-source project here:
</p>
<p>
    <a class="mkt-btn-primary inline-flex" href="https://github.com/sponsors/azodik" rel="noopener">Become a sponsor</a>
    <span class="ml-3 text-sm text-ink-soft/70">
        <a href="https://github.com/sponsors/azodik" rel="noopener">github.com/sponsors/azodik</a>
    </span>
</p>

<h2>Support</h2>
<p>
    Azodik Consulting Private Limited has Support enabled on GitHub.
    Start from the organization:
    <a href="https://github.com/azodik" rel="noopener">github.com/azodik</a>
</p>

<h2>Repository</h2>
<p class="flex flex-wrap items-center gap-3">
    <x-github-link icon-class="size-5" label="View on GitHub" class="text-teal hover:text-teal-deep" />
</p>
<p class="mt-4">
    MIT License · Copyright © Azodik Consulting Private Limited · Developed in India
</p>
@endsection
