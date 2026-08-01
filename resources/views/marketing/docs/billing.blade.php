@extends('layouts.docs')

@section('docs')
<h1>Billing &amp; MAU</h1>
<p class="lead">
    Self-hosting is free forever. Authzio Cloud meters <strong>monthly active users (MAU)</strong> and bills via hosted checkout.
    Plan changes and usage thresholds email your billing contacts automatically.
</p>

<h2>What counts as MAU</h2>
<p>
    Distinct users (<code>subject_key</code>) with a qualifying event in the calendar month
    (timezone: <code>AUTHZIO_MAU_TIMEZONE</code>, default UTC).
</p>
<ul>
    <li><code>console.login</code></li>
    <li><code>user.authenticated</code></li>
    <li><code>token.issued</code></li>
</ul>
<p>
    MAU is <strong>per organization</strong>. All applications under an org share that org’s MAU pool and plan limit.
    Orgs do not share MAU with each other. Applications do not have separate MAU meters.
</p>
<p>Events are deduped per organization / subject / day / event type.</p>

<h2>Plans (seeded)</h2>
<table>
    <thead>
        <tr><th>Plan</th><th>Price</th><th>MAU</th><th>Highlights</th></tr>
    </thead>
    <tbody>
        <tr><td>Free</td><td>$0</td><td>1,000</td><td>1 app; managed JWKS; Authzio subdomain</td></tr>
        <tr><td>Starter</td><td>$5/mo</td><td>5,000</td><td>5 apps; custom domains; email customization; BYO email</td></tr>
        <tr><td>Growth</td><td>$20/mo</td><td>50,000</td><td>Unlimited apps; OIDC enterprise SSO; usage analytics</td></tr>
        <tr><td>Scale</td><td>$99/mo</td><td>250,000</td><td>Custom JWKS; dedicated onboarding; SLA options</td></tr>
        <tr><td>Enterprise</td><td>Custom</td><td>Custom</td><td>Contact Azodik Consulting Private Limited</td></tr>
    </tbody>
</table>
<p>
    Custom JWKS import starts at <strong>Scale</strong>. OIDC enterprise SSO starts at <strong>Growth</strong>.
    Public pricing: <a href="{{ route('pricing') }}">/pricing</a>.
</p>

<h2>Plan changes</h2>
<ul>
    <li>
        <strong>Free → paid</strong> — hosted checkout for the full plan price.
    </li>
    <li>
        <strong>Upgrade (paid → higher paid)</strong> — charges the <strong>price difference</strong> only.
        The console shows the amount before you confirm. The local plan updates only after payment
        <strong>succeeds</strong> (via webhook). Extra bank authentication (3DS) may be required.
        Retries resume the pending payment; Authzio does not start a second charge for the same upgrade.
    </li>
    <li>
        <strong>Downgrade (paid → lower paid)</strong> — scheduled for the <strong>next billing date</strong>.
        No immediate charge; confirm in the console first.
    </li>
    <li>
        <strong>Paid → Free</strong> — cancels renewal at the <strong>end of the current period</strong>.
        You keep paid features until then; there is no refund for unused days.
    </li>
    <li>
        <strong>Same plan again</strong> — rejected with an error (no second checkout or charge).
    </li>
</ul>

<h2>Multi-org tip</h2>
<p>
    Many orgs under one Authzio user account = many independent Free/paid plans and MAU pools.
    Many apps inside one org = one shared MAU pool for that org.
</p>

<h2>Billing emails</h2>
<p>
    Authzio emails the organization <code>billing_email</code> plus active <strong>owners</strong> and <strong>admins</strong> when:
</p>
<ul>
    <li>The plan is <strong>upgraded</strong></li>
    <li>The plan is <strong>downgraded</strong> (including moving back to Free)</li>
    <li>The subscription is <strong>cancelled</strong> or expired</li>
    <li>MAU, applications, or platform email usage crosses <strong>80%</strong>, <strong>90%</strong>, or <strong>100%</strong></li>
</ul>
<p>
    Each threshold is emailed at most <strong>once per organization per period</strong>
    (calendar month for MAU / apps / monthly email; calendar day for daily email).
    System templates: <code>mau_warning</code>, <code>mau_limit_reached</code>,
    <code>application_warning</code>, <code>application_limit_reached</code>,
    <code>email_usage_warning</code>, <code>email_usage_limit_reached</code>
    (plus plan change templates). These stay Authzio-owned.
</p>
<pre><code>AUTHZIO_USAGE_ALERT_THRESHOLDS=80,90,100</code></pre>

<h2>Self-hosted</h2>
<pre><code>AUTHZIO_BILLING_ENABLED=false</code></pre>
<p>When billing is disabled, plan and usage emails are not sent.</p>

<h2>Checkout &amp; webhooks (operators)</h2>
<p>
    Authzio Cloud uses Dodo Payments for checkout and plan changes. Configure with
    <code>php artisan setup:dodo</code> (syncs product prices/descriptions).
    New paid subscriptions use hosted checkout. Upgrades on an existing subscription use
    Dodo <code>change-plan</code> with <code>difference_immediately</code> and
    <code>prevent_change</code> so the price gap is charged without applying the new product
    until payment succeeds; Authzio grants the plan from <code>payment.succeeded</code>.
    Paid downgrades schedule for the next billing date; Free uses cancel-at-period-end.
    Process webhooks with a queue worker: <code>POST /api/v1/webhooks/dodo</code>
    → <code>php artisan queue:work</code>.
</p>
@endsection
