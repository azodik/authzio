@extends('layouts.legal')

@section('legal')
<p>
    These Terms of Service (“Terms”) govern access to and use of <strong>Authzio</strong>
    websites, documentation, and Authzio Cloud operated by
    <strong>{{ config('marketing.organization') }}</strong> (“Azodik”, “we”, “us”).
    By using the Services, you agree to these Terms.
</p>

<h2>1. Services</h2>
<p>
    Authzio is an open-source identity and access management platform. You may
    <strong>self-host</strong> Authzio under the MIT license on GitHub, or use
    <strong>Authzio Cloud</strong>, our managed offering billed by monthly active users (MAU)
    and plan entitlements.
</p>
<p>
    Self-hosted deployments are provided as software; you are responsible for your infrastructure,
    security, backups, and compliance. Authzio Cloud is a hosted service subject to these Terms and our
    <a href="{{ route('privacy') }}">Privacy Policy</a>.
</p>

<h2>2. Accounts and organizations</h2>
<ul>
    <li>You must provide accurate registration information and keep credentials secure.</li>
    <li>You are responsible for activity under your account and organizations you administer.</li>
    <li>You must be old enough to form a binding contract in your jurisdiction.</li>
    <li>We may suspend or terminate accounts that violate these Terms or pose security or abuse risks.</li>
</ul>

<h2>3. Acceptable use</h2>
<p>You agree not to:</p>
<ul>
    <li>Violate law or others’ rights.</li>
    <li>Probe, scan, or attack the Services without authorization.</li>
    <li>Interfere with or disrupt Authzio Cloud or other customers.</li>
    <li>Resell Authzio Cloud in a misleading way or misrepresent affiliation with Azodik.</li>
    <li>Upload malware or use the Services to send unlawful or abusive communications.</li>
    <li>Circumvent MAU metering, plan limits, or payment obligations on Authzio Cloud.</li>
</ul>

<h2>4. Customer content and applications</h2>
<p>
    You retain rights to configuration and identity data you submit to Authzio Cloud (“Customer Content”).
    You grant us a limited license to host, process, and display Customer Content solely to provide the Services.
    You are responsible for your applications’ end users, consent, notices, and lawful processing.
</p>

<h2>5. Plans, billing, and MAU</h2>
<ul>
    <li>Authzio Cloud plans, MAU limits, and features are described on the <a href="{{ route('pricing') }}">pricing</a> page and in the console.</li>
    <li>Paid plans renew monthly unless cancelled. Downgrades to Free typically take effect at period end; you keep paid access until then without a refund for unused days.</li>
    <li>MAU is measured per organization as described in our docs. Over-limit usage may require an upgrade.</li>
    <li>Fees are charged through our payment provider. Taxes may apply.</li>
    <li>We may change prices with notice for subsequent billing periods.</li>
</ul>

<h2>6. Open-source software</h2>
<p>
    The Authzio source code is available under the MIT License. These Terms do not limit rights granted
    by that license for self-hosted use of the software itself. Trademarks and the Authzio Cloud service
    remain ours.
</p>

<h2>7. Intellectual property</h2>
<p>
    Azodik and its licensors own the Services, branding, and related IP (excluding Customer Content and
    open-source components under their licenses). You may not copy or misuse our marks except as allowed by law
    or written permission.
</p>

<h2>8. Third-party services</h2>
<p>
    The Services may integrate with third parties (payment processors, email providers, identity providers).
    Their terms and privacy policies apply to those services. We are not responsible for third-party acts or omissions.
</p>

<h2>9. Disclaimers</h2>
<p>
    THE SERVICES ARE PROVIDED “AS IS” AND “AS AVAILABLE.” TO THE MAXIMUM EXTENT PERMITTED BY LAW,
    WE DISCLAIM WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, AND NON-INFRINGEMENT.
    We do not warrant uninterrupted or error-free operation.
</p>

<h2>10. Limitation of liability</h2>
<p>
    TO THE MAXIMUM EXTENT PERMITTED BY LAW, AZODIK AND ITS AFFILIATES WILL NOT BE LIABLE FOR INDIRECT,
    INCIDENTAL, SPECIAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES, OR LOST PROFITS, REVENUE, OR DATA.
    OUR TOTAL LIABILITY FOR CLAIMS ARISING OUT OF THE SERVICES IN ANY TWELVE-MONTH PERIOD IS LIMITED TO
    THE AMOUNTS YOU PAID US FOR AUTHZIO CLOUD IN THAT PERIOD (OR USD $100 IF YOU PAID NOTHING).
</p>

<h2>11. Indemnity</h2>
<p>
    You will defend and indemnify Azodik against claims arising from your Customer Content, your applications,
    your end users, or your violation of these Terms or law.
</p>

<h2>12. Termination</h2>
<p>
    You may stop using the Services at any time and cancel paid plans as described in the console.
    We may suspend or end access for breach, non-payment, or risk to the platform. Provisions that by nature
    should survive (including IP, billing amounts owed, disclaimers, and liability limits) will survive.
</p>

<h2>13. Changes</h2>
<p>
    We may update these Terms by posting a revised version. Continued use after the effective date constitutes acceptance.
    If you disagree, stop using the Services.
</p>

<h2>14. Governing law</h2>
<p>
    These Terms are governed by the laws of India, without regard to conflict-of-law rules.
    Courts in India have exclusive jurisdiction, except where mandatory consumer protections require otherwise.
</p>

<h2>15. Contact</h2>
<p>
    {{ config('marketing.organization') }}<br>
    <a href="{{ config('marketing.organization_url') }}" rel="noopener">{{ config('marketing.organization_url') }}</a><br>
    Product: <a href="{{ route('home') }}">Authzio</a>
</p>
@endsection
