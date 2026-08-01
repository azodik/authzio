@extends('layouts.docs')

@section('docs')
<h1>Console</h1>
<p class="lead">
    The Authzio console is where operators manage organizations, applications, members, and settings that shape how people sign in.
</p>

<h2>Open the console</h2>
<p>After install: <code>/console</code> on your Authzio host.</p>

<h2>Navigation (Global → Org → App)</h2>
<p>Exactly one sidebar mode at a time:</p>
<ul>
    <li><strong>Global</strong> — <code>/console/organizations</code>, <code>/console/settings</code> (account)</li>
    <li><strong>Organization</strong> — <code>/console/&#123;orgId&#125;/…</code> (Back to account + org menus)</li>
    <li><strong>Application</strong> — <code>/console/&#123;orgId&#125;/&#123;appId&#125;</code> (Back to organization + app menus)</li>
</ul>

<h2>Typical path</h2>
<ol>
    <li>Register / sign in (verify email if prompted)</li>
    <li>Create an organization (or accept an invite from email / <strong>Invitations for you</strong>)</li>
    <li>Invite members and assign roles</li>
    <li>Create an application (OAuth client)</li>
    <li>Configure login, domains, email, and OIDC</li>
    <li>Watch Billing for MAU on that organization</li>
</ol>

<h2>What you manage</h2>
<ul>
    <li><strong>Organizations</strong> — tenants that own apps, domains, and billing; also lists invitations addressed to you</li>
    <li><strong>Members &amp; invites</strong> — pending invites (resend / revoke), invitation history, and active members</li>
    <li><strong>Roles &amp; permissions</strong> — RBAC with permission groups</li>
    <li><strong>Applications</strong> — OAuth clients, branding, login methods, policies</li>
    <li><strong>End-users</strong> — people who signed into your apps</li>
    <li><strong>Domains</strong> — Authzio subdomain and custom hosts (paid)</li>
    <li><strong>Email templates &amp; delivery</strong> — transactional mail</li>
    <li><strong>OIDC / JWKS</strong> — signing keys and discovery for the org issuer</li>
    <li><strong>Social login</strong> — Google / GitHub credentials for the org</li>
    <li><strong>Enterprise SSO</strong> — OIDC IdP connections (Growth+)</li>
    <li><strong>Billing</strong> — plan, MAU usage, upgrades/downgrades, and checkout (cloud)</li>
    <li><strong>Account settings</strong> — profile, theme, and authenticator MFA (TOTP + recovery codes)</li>
</ul>

<h2>Demo login</h2>
<p>
    Optional shared demo account: see <a href="{{ route('demo') }}">Try the demo</a>.
    That page links to <code>/console/login?demo=1</code> so the demo email is pre-filled.
    Ordinary console login does not auto-fill demo credentials.
</p>

<div class="callout">
    Free: 1 app; managed JWKS; Authzio subdomain; Authzio mail caps.
    Starter ($5): up to 5 apps, custom domains, email edits, BYO email.
    Growth ($20): unlimited apps, OIDC enterprise SSO.
    Scale ($99): custom JWKS import, onboarding / SLA options.
</div>

<p>
    Console registration requires accepting the
    <a href="{{ route('privacy') }}">Privacy Policy</a> and
    <a href="{{ route('terms') }}">Terms of Service</a>.
</p>
@endsection
