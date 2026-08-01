@extends('layouts.legal')

@section('legal')
<p>
    This Privacy Policy explains how <strong>{{ config('marketing.organization') }}</strong>
    (“Azodik”, “we”, “us”) collects, uses, and shares information when you use
    <strong>Authzio</strong> websites, documentation, and Authzio Cloud
    (together, the “Services”).
</p>
<p>
    Self-hosted Authzio you run on your own infrastructure is governed by your own
    policies for end-user data. This policy covers our marketing site, console accounts,
    and managed Authzio Cloud.
</p>

<h2>1. Information we collect</h2>
<ul>
    <li><strong>Account data</strong> — name, email, organization details, and authentication events for console users.</li>
    <li><strong>Billing data</strong> — plan selection, usage metrics (such as monthly active users), invoices, and payment status processed by our payment provider.</li>
    <li><strong>Customer content on Authzio Cloud</strong> — configuration and identity data you store in your Authzio Cloud organizations (applications, users, templates, domains, and related settings).</li>
    <li><strong>Technical data</strong> — IP address, browser type, device information, approximate location, and logs needed to operate and secure the Services.</li>
    <li><strong>Communications</strong> — messages you send us (support, sales, security reports).</li>
    <li><strong>Cookies and similar technologies</strong> — see our <a href="{{ route('cookies') }}">Cookie Policy</a>.</li>
</ul>

<h2>2. How we use information</h2>
<ul>
    <li>Provide, operate, and improve Authzio and Authzio Cloud.</li>
    <li>Authenticate users, prevent abuse, and keep the Services secure.</li>
    <li>Meter usage, bill subscriptions, and send transactional notices (plan changes, MAU alerts, invoices).</li>
    <li>Respond to support and security inquiries.</li>
    <li>Understand aggregate product and marketing performance where analytics are enabled.</li>
    <li>Comply with law and enforce our <a href="{{ route('terms') }}">Terms of Service</a>.</li>
</ul>

<h2>3. Sharing</h2>
<p>We do not sell personal information. We may share information with:</p>
<ul>
    <li><strong>Service providers</strong> that help us host, send email, process payments, or analyze traffic, under contractual confidentiality and purpose limits.</li>
    <li><strong>Professional advisors</strong> (legal, accounting) when reasonably necessary.</li>
    <li><strong>Authorities</strong> when required by law or to protect rights, safety, and security.</li>
    <li><strong>Successors</strong> in connection with a merger, acquisition, or asset sale, subject to this policy’s protections.</li>
</ul>
<p>
    On Authzio Cloud, your organization’s admins control access to that organization’s data.
    End-user identity data you process for your applications remains under your instructions as the controller (or equivalent).
</p>

<h2>4. International transfers</h2>
<p>
    We may process information in India and other countries where we or our providers operate.
    Where required, we use appropriate safeguards for cross-border transfers.
</p>

<h2>5. Retention</h2>
<p>
    We retain information for as long as your account is active and as needed to provide the Services,
    meet legal obligations, resolve disputes, and enforce agreements. You may request deletion of
    console account data subject to legal and operational retention needs.
</p>

<h2>6. Security</h2>
<p>
    We use administrative, technical, and organizational measures designed to protect information.
    No method of transmission or storage is completely secure; please use strong credentials and
    report suspected incidents promptly.
</p>

<h2>7. Your choices and rights</h2>
<p>
    Depending on where you live, you may have rights to access, correct, delete, or restrict
    processing of personal information, or to object to certain processing. Contact us using the
    details below. You may also unsubscribe from non-essential marketing emails where applicable.
</p>

<h2>8. Children</h2>
<p>
    The Services are not directed to children under 16, and we do not knowingly collect personal
    information from them.
</p>

<h2>9. Changes</h2>
<p>
    We may update this policy from time to time. We will post the revised version with an updated
    “Last updated” date. Material changes may be communicated through the console or email when appropriate.
</p>

<h2>10. Contact</h2>
<p>
    {{ config('marketing.organization') }}<br>
    <a href="{{ config('marketing.organization_url') }}" rel="noopener">{{ config('marketing.organization_url') }}</a><br>
    Product: <a href="{{ route('home') }}">Authzio</a> ·
    Source: <a href="{{ config('marketing.github') }}" rel="noopener">GitHub</a>
</p>
@endsection
