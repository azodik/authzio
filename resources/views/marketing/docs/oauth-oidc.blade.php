@extends('layouts.docs')

@section('docs')
<h1>OAuth &amp; OpenID Connect</h1>
<p class="lead">
    Connect your products as OAuth clients. Authzio issues tokens for people (authorization code + PKCE) and for services (client credentials).
</p>

<h2>Issuer</h2>
<p>
    Each <strong>organization</strong> is an OIDC issuer. The issuer URL follows the organization host —
    Authzio subdomain or a verified custom domain.
    <strong>Applications are OAuth clients under that issuer</strong>; they do not receive a separate issuer URL.
</p>

<h2>Endpoints</h2>
<table>
    <thead>
        <tr><th>Endpoint</th><th>Path</th></tr>
    </thead>
    <tbody>
        <tr><td>Discovery</td><td><code>GET /.well-known/openid-configuration</code></td></tr>
        <tr><td>JWKS</td><td><code>GET /.well-known/jwks.json</code></td></tr>
        <tr><td>Authorize</td><td><code>GET/POST /oauth/authorize</code></td></tr>
        <tr><td>Token</td><td><code>POST /api/oauth/token</code></td></tr>
        <tr><td>UserInfo</td><td><code>GET/POST /api/oauth/userinfo</code></td></tr>
        <tr><td>Revoke</td><td><code>POST /api/oauth/revoke</code></td></tr>
        <tr><td>Introspect</td><td><code>POST /api/oauth/introspect</code></td></tr>
    </tbody>
</table>

<h2>Flows</h2>
<ul>
    <li>Authorization code with PKCE (<code>S256</code>)</li>
    <li>Refresh tokens (<code>offline_access</code>)</li>
    <li>Client credentials</li>
    <li>Token revoke and introspect</li>
</ul>
<p>ID tokens are signed with <strong>RS256</strong>. Manage keys under console <strong>OIDC / JWKS</strong>.</p>

<h2>Hosted authorize</h2>
<p>
    End users complete sign-in on <code>/oauth/authorize</code> with the methods enabled for that application
    (password, email OTP, social, enterprise SSO, passkeys). After authentication:
</p>
<ul>
    <li>If the user has authenticator MFA enabled, they complete a TOTP / recovery-code challenge first</li>
    <li>If the app’s security policy requires MFA and the user is not enrolled, authorize fails with a clear error</li>
    <li>Authzio redirects only to a <strong>registered</strong> <code>redirect_uri</code> (unregistered URIs are rejected)</li>
</ul>
<p>
    Passkey sign-in uses <code>/oauth/passkey/options</code> then assertion verify on the authorize flow.
</p>

<h2>Applications</h2>
<p>
    Create OAuth clients in the console: redirect URIs, grant types, confidential vs public,
    branding, login methods, and security policy (e.g. require MFA). Use the live login preview while you tune UX.
    All clients in an organization share the same issuer, discovery document, and JWKS.
</p>
@endsection
