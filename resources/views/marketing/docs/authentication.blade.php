@extends('layouts.docs')

@section('docs')
<h1>User authentication</h1>
<p class="lead">
    Authzio is built around the people signing in — clear choices, branded hosted login, and verification when your product needs it.
</p>

<h2>Hosted login</h2>
<p>
    Each application can customize headline, colors, logo, button label, signup link, and legal URLs.
    End users land on your hosted authorize experience, not a generic form.
</p>

<h2>Sign-in methods</h2>
<table>
    <thead>
        <tr><th>Method</th><th>Notes</th></tr>
    </thead>
    <tbody>
        <tr><td>Email &amp; password</td><td>Policies for length, complexity, and reuse controls</td></tr>
        <tr><td>Google / GitHub / social</td><td>Configured under organization Social login</td></tr>
        <tr><td>Enterprise SSO (OIDC)</td><td>Growth+ — connect Okta, Azure AD, Google Workspace, and other OIDC IdPs</td></tr>
        <tr><td>Email OTP</td><td>One-time codes; useful as primary or fallback</td></tr>
        <tr><td>Passkeys</td><td>WebAuthn for phishing-resistant sign-in (options + assertion on hosted login)</td></tr>
    </tbody>
</table>
<p>
    Passwordless paths (email OTP, social, SSO, passkeys) create users without a password.
    Password sign-in remains available when enabled for the application.
</p>

<h2>User-centric options</h2>
<ul>
    <li><strong>Sync profile</strong> — keep name and avatar in step with the social provider when enabled</li>
    <li><strong>Require verified email</strong> — only continue when email is verified</li>
    <li><strong>OTP fallback</strong> — if email is missing or unverified after social login, collect and verify via OTP before issuing an authorization code</li>
</ul>

<p>Configure these per application under the <strong>Authentication</strong> tab in the console.</p>

<h2>Authenticator MFA (TOTP)</h2>
<p>
    Console users can enable an authenticator app (Google Authenticator, 1Password, Authy, etc.) under
    <strong>Account → Settings</strong> (Authenticator section). Setup shows a QR code and secret; confirmation issues
    <strong>10 recovery codes</strong> (shown once; stored hashed). Sign-in then requires a 6-digit TOTP or a recovery code.
    You can regenerate recovery codes later (invalidates previous codes).
</p>
<ul>
    <li><strong>Console</strong> — password login returns <code>mfa_required</code> until the challenge succeeds at <code>/console/mfa</code></li>
    <li><strong>Hosted apps</strong> — users with MFA enabled are challenged before an authorization code is issued</li>
    <li><strong>App policy</strong> — Application → Security → <em>Require MFA</em> blocks users who have not enrolled an authenticator</li>
</ul>
<p>
    API (authenticated console session): <code>GET/POST /api/v1/auth/mfa/*</code>
    (setup, confirm, disable, regenerate recovery codes) and
    <code>POST /api/v1/auth/mfa/challenge</code> during login.
</p>
<p>
    Configure with <code>AUTHZIO_MFA_ENABLED</code>, optional <code>AUTHZIO_MFA_ISSUER</code>
    (label shown in authenticator apps), and <code>AUTHZIO_MFA_REQUIRED_FOR_ADMINS</code>.
    Before production, run <code>php artisan authzio:launch-check</code>.
</p>

<h2>Enterprise SSO (OIDC)</h2>
<p>
    On <strong>Growth</strong> and higher, open <strong>Enterprise SSO</strong> in the console to connect an OIDC identity provider
    (issuer URL with discovery, client ID/secret, optional email-domain allowlist).
    Enabled connections appear on hosted login as <em>Continue with …</em>.
</p>
<p>
    Social providers (Google, GitHub, …) remain separate under <strong>Social login</strong> and are available on all plans.
</p>
@endsection
