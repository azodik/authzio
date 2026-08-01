@extends('layouts.docs')

@section('docs')
<h1>Organizations</h1>
<p class="lead">
    Organizations are the tenant boundary: people who administer Authzio, applications that serve end users,
    billing / MAU, and the OIDC issuer they share.
</p>

<h2>What an organization owns</h2>
<ul>
    <li><strong>Issuer &amp; domains</strong> — Authzio subdomain (and custom domain on paid plans)</li>
    <li><strong>Members &amp; roles</strong> — who can administer the console for this tenant</li>
    <li><strong>Applications</strong> — OAuth clients under this issuer</li>
    <li><strong>Billing &amp; MAU</strong> — plan and monthly active users for <em>this</em> org only</li>
    <li><strong>Email</strong> — templates and delivery (Authzio mail on Free; BYO on paid)</li>
    <li><strong>Signing keys</strong> — JWKS shared by all apps in the org</li>
</ul>

<h2>Creating an organization</h2>
<p>
    On create, Authzio provisions the subdomain, system roles (Owner / Admin / Member),
    Free subscription, email templates, and a signing key. The creator becomes the Owner.
    Applications are created separately afterward.
</p>

<h2>Members &amp; invitations</h2>
<p>
    Invite teammates by email and assign a role (Admin, Member, or a custom role — not Owner).
    Invites are managed on the org <strong>Members</strong> page.
</p>
<ul>
    <li><strong>Pending</strong> — outstanding invites; owners/admins with invite permission can <strong>Resend</strong> (new token + expiry) or <strong>Revoke</strong></li>
    <li><strong>Invitation history</strong> — accepted and revoked invites (recent rows)</li>
    <li><strong>Active members</strong> — after accept, the person appears here and leaves Pending</li>
</ul>

<h3>Accepting an invite</h3>
<p>The invitee must use the invited email address. They can:</p>
<ol>
    <li>Open the email link at <code>/console/invites/&#123;token&#125;</code>, then sign in or create an account and accept</li>
    <li>Sign in to the console and accept from <strong>Invitations for you</strong> (Organizations, Overview, or the empty home when they have no organization yet)</li>
</ol>
<p>
    Register → verify email → return to the invite is supported: the console remembers the invite path across login, register, MFA, and email verification.
</p>

<h2>RBAC</h2>
<p>
    Permissions are scoped to the organization. Custom roles can grant whole
    <strong>permission groups</strong> (Members, Applications, Billing, …) or individual actions.
    The Owner role bypasses all checks.
</p>

<h2>Domains</h2>
<p>
    Each organization gets an Authzio subdomain (for example <code>acme.authzio.com</code>) that is verified automatically.
    On Starter and higher you can attach a <strong>custom domain</strong> (for example <code>id.example.com</code>).
</p>
<ol>
    <li>
        Add the hostname in the console under <strong>Domains</strong>.
    </li>
    <li>
        On Authzio Cloud (Cloudflare for SaaS enabled), publish the DNS records shown in the console:
        <ul>
            <li><strong>CNAME</strong> the hostname to <code>customers.authzio.com</code> (or your configured SaaS target)</li>
            <li><strong>TXT</strong> ownership / SSL validation records Cloudflare returns</li>
        </ul>
        Self-host without Cloudflare SaaS uses Authzio TXT ownership plus a CNAME/A to your Authzio host.
    </li>
    <li>
        Wait for DNS, then click <strong>Verify</strong>. With Cloudflare SaaS, Authzio marks the domain verified when
        the custom hostname and certificate are <code>active</code>.
    </li>
</ol>
<p>
    Free plan includes the Authzio subdomain only. Custom domains require Starter or higher.
    Pointing a Cloudflare zone at another Cloudflare SaaS CNAME without a Custom Hostname causes Error 1014 —
    Authzio Cloud creates that hostname for you when SaaS is configured.
</p>

<h2>Applications</h2>
<p>
    Applications are OAuth clients owned by the organization. They share the org issuer URL —
    they do not get a separate issuer. Free plan includes <strong>one</strong> application; paid plans allow more.
</p>

<h2>MAU across many orgs and apps</h2>
<p>
    MAU is metered <strong>per organization</strong>. Every app under that org shares one pool and plan limit.
    Creating more organizations does not merge usage — each org has its own subscription and MAU counter.
</p>

<h2>Audit</h2>
<p>
    Administrative and authentication events land in audit logs so you can see who changed what —
    and when end users authenticated.
</p>
@endsection
