@extends('layouts.docs')

@section('docs')
<h1>FAQ</h1>
<p class="lead">
    Short answers about billing, plans, MFA, invitations, emails, domains, SSO, and self-hosting.
</p>

<h2>Is MAU per app or per organization?</h2>
<p>
    <strong>Per organization.</strong> All applications in an org share one MAU pool and that org’s plan limit.
    Multiple organizations each have their own subscription and MAU counter — they are not combined
    across your Authzio account.
</p>

<h2>What are the Cloud plans?</h2>
<p>
    Free ($0 · 1,000 MAU · 1 app), Starter ($5/mo · 5,000 MAU · 5 apps · custom domains · BYO email),
    Growth ($20/mo · 50,000 MAU · unlimited apps · OIDC enterprise SSO),
    Scale ($99/mo · 250,000 MAU · custom JWKS), and Enterprise (custom).
    See <a href="{{ route('pricing') }}">pricing</a> and
    <a href="{{ route('docs', ['page' => 'billing']) }}">Billing &amp; MAU</a>.
</p>

<h2>What happens if I switch from a paid plan to Free?</h2>
<p>
    Authzio does <strong>not</strong> drop you to Free immediately. Renewal is cancelled at the
    <strong>end of the current billing period</strong>. You keep paid features until that date, then the
    org moves to Free. There is no refund for unused days — you keep using them through period end.
</p>

<h2>What happens when I upgrade to a higher plan?</h2>
<p>
    From Free (or your first paid purchase), you complete <strong>hosted checkout</strong> for the full plan price.
    If you already have an active paid subscription, Authzio charges only the
    <strong>price difference</strong> (for example Starter $5 → Growth $20 charges about $15).
    The console confirm dialog shows that amount before you continue.
    Your organization stays on the current plan until payment <strong>succeeds</strong>; then the higher plan is applied.
    Your bank may ask for extra authentication (3DS). You will not be charged twice for the same upgrade —
    retries resume the pending payment instead of starting a new one.
</p>

<h2>What happens when I downgrade to a lower paid plan?</h2>
<p>
    Paid-to-paid downgrades (for example Growth → Starter) are scheduled for your
    <strong>next billing date</strong>. You keep the current plan until then; there is no immediate charge.
    The console shows a confirm dialog before the change is scheduled.
</p>

<h2>Can I buy the same plan again?</h2>
<p>
    No. If the organization is already on that plan (and not cancelling at period end),
    Authzio returns an error instead of opening another checkout or charge.
</p>

<h2>Do I get an email when I upgrade or downgrade?</h2>
<p>
    Yes. Owners, admins, and the organization billing email are notified when the plan actually changes
    (after a successful upgrade payment, when a scheduled downgrade takes effect, or when Free starts
    after a scheduled cancel). System billing mail always uses the Authzio
    platform mailer — not your org’s BYO provider.
</p>

<h2>Will I be notified before I hit my MAU limit?</h2>
<p>
    Yes. By default owners/admins (and <code>billing_email</code>) get emails at
    <strong>80%</strong>, <strong>90%</strong>, and <strong>100%</strong> of the MAU limit.
    Application counts and platform email send caps use the same thresholds.
    Each threshold is emailed at most once per period (calendar month for MAU/apps/monthly email;
    calendar day for daily email).
</p>

<h2>Who receives billing emails?</h2>
<p>
    The organization <code>billing_email</code> (if set), plus every active owner and admin.
</p>

<h2>Can I download invoices?</h2>
<p>
    Yes. On the Billing page, succeeded charges are listed with a PDF download
    when billing is configured and a customer/subscription is linked.
</p>

<h2>Can I customize billing / system emails?</h2>
<p>
    End-user auth emails (OTP, password reset for your apps) can be customized on paid plans under
    <strong>Email</strong>. Authzio Cloud system mail (welcome, verification, billing alerts, console invites)
    uses platform templates and is not edited per organization.
</p>

<h2>Are custom domains on Free?</h2>
<p>
    No. Custom domains require Starter or higher. Free includes an Authzio subdomain only.
</p>

<h2>How do I verify a custom domain?</h2>
<p>
    In <strong>Domains</strong>, Authzio shows the exact DNS records. On Authzio Cloud with Cloudflare for SaaS,
    add the <strong>CNAME</strong> to <code>customers.authzio.com</code> plus any ownership/SSL <strong>TXT</strong> records,
    then click <strong>Verify</strong>. Self-host without SaaS uses Authzio TXT ownership and a CNAME/A to your host.
</p>

<h2>How do team invitations work?</h2>
<p>
    Org admins invite by email on <strong>Members</strong>. Pending invites can be <strong>resent</strong> or <strong>revoked</strong>;
    accepted/revoked invites appear under invitation history.
    Invitees open the email link (<code>/console/invites/&#123;token&#125;</code>) or accept from
    <strong>Invitations for you</strong> after signing in with the invited address.
    See <a href="{{ route('docs', ['page' => 'organizations']) }}">Organizations</a>.
</p>

<h2>Does Authzio support MFA?</h2>
<p>
    Yes. Authenticator apps (TOTP) with recovery codes for console operators and for end users on hosted login.
    Apps can require MFA under Application → Security. Configure with <code>AUTHZIO_MFA_*</code>
    (see <a href="{{ route('docs', ['page' => 'authentication']) }}">User authentication</a>).
</p>

<h2>When is enterprise SSO available?</h2>
<p>
    <strong>OIDC enterprise SSO</strong> (connect Okta, Azure AD, Google Workspace, and other OIDC IdPs)
    is included on <strong>Growth</strong> and higher. Configure connections under
    <strong>Enterprise SSO</strong> in the console. Social login (Google, GitHub, …) is available on all plans.
</p>

<h2>How do I check production readiness?</h2>
<p>
    Run <code>php artisan authzio:launch-check</code>. It verifies app key, billing/Dodo mapping, queue/session drivers, and MFA config.
</p>

<h2>When can I import custom JWKS?</h2>
<p>
    Custom / imported signing keys require <strong>Scale</strong> or Enterprise.
    Free, Starter, and Growth use managed (auto-generated) JWKS.
</p>

<h2>Where are Privacy and Terms?</h2>
<p>
    <a href="{{ route('privacy') }}">Privacy Policy</a>,
    <a href="{{ route('terms') }}">Terms of Service</a>, and
    <a href="{{ route('cookies') }}">Cookie Policy</a>.
    Creating a console account requires accepting Privacy and Terms.
</p>

<h2>How do I set up Dodo Payments?</h2>
<p>
    Add <code>DODO_PAYMENTS_API_KEY</code> to <code>.env</code>, then run
    <code>php artisan setup:dodo</code> (creates products and syncs prices/descriptions).
    For local webhooks, tunnel your app and pass
    <code>--webhook=https://…/api/v1/webhooks/dodo</code>.
    <code>npm run setup</code> / <code>authzio:setup</code> does <strong>not</strong> configure Dodo.
</p>

<h2>Is self-hosting billed?</h2>
<p>
    No. Self-hosting is free. Set <code>AUTHZIO_BILLING_ENABLED=false</code> to turn off Cloud metering and billing emails.
</p>

<h2>Where do I raise a bug or security issue?</h2>
<p>
    Public bugs: <a href="{{ route('docs', ['page' => 'support']) }}">Issues &amp; support</a> (GitHub Issues).
    Security-sensitive findings: prefer a private report via
    <a href="https://azodik.com" rel="noopener">azodik.com</a> (see also <code>SECURITY.md</code> in the repo).
</p>
@endsection
