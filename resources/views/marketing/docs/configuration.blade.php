@extends('layouts.docs')

@section('docs')
<h1>Configuration</h1>
<p class="lead">
    Authzio follows Laravel conventions. Start from <code>.env.example</code> and adjust for your host.
</p>

<h2>Application</h2>
<table>
    <thead>
        <tr><th>Variable</th><th>Purpose</th></tr>
    </thead>
    <tbody>
        <tr><td><code>APP_NAME</code></td><td>Display name (default Authzio)</td></tr>
        <tr><td><code>APP_URL</code></td><td>Public base URL (e.g. <code>https://authzio.test</code>)</td></tr>
        <tr><td><code>APP_KEY</code></td><td>Encryption key — never commit</td></tr>
    </tbody>
</table>

<h2>Database</h2>
<p>PostgreSQL is required for production-shaped installs.</p>
<pre><code>DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=authzio
DB_USERNAME=...
DB_PASSWORD=...</code></pre>

<p>
    Transaction-mode poolers (PgBouncer, Neon, RDS Proxy, Supabase pooler) are supported on Laravel 13.17+.
    Point <code>DB_HOST</code> at the pooler, then:
</p>
<pre><code>DB_POOLED=true
DB_DIRECT_HOST=127.0.0.1
DB_DIRECT_PORT=5432
DB_DIRECT_DATABASE="${DB_DATABASE}"
DB_DIRECT_USERNAME="${DB_USERNAME}"
DB_DIRECT_PASSWORD="${DB_PASSWORD}"</code></pre>
<p>
    Laravel enables emulated prepares on the pooled connection and routes migrations / schema commands to the direct endpoint.
</p>

<h2>Sanctum / console SPA</h2>
<p>
    The React console authenticates against the same origin via Sanctum.
    Ensure <code>SANCTUM_STATEFUL_DOMAINS</code> matches your host
    (Herd usually works with defaults for <code>authzio.test</code>).
    Keep <code>SESSION_DOMAIN</code> empty/<code>null</code> unless you intentionally share
    cookies across subdomains — wrong values create duplicate cookies and can trigger
    HTTP <code>431 Request Header Fields Too Large</code>.
</p>
<p>
    On Laravel Cloud, set <code>SESSION_DRIVER=database</code> or <code>redis</code>
    (never <code>cookie</code>). The cookie driver stores session payloads under random
    40-character cookie names that accumulate until the browser cannot send requests.
    If you are already stuck with 431, open
    <a href="/cookie-reset.html"><code>/cookie-reset.html</code></a> or clear site cookies
    for your domain, then redeploy with a non-cookie session driver.
</p>

<h2>Mail</h2>
<p>
    Invitations, email OTP, and transactional mail use Laravel’s mailer.
    Configure <code>MAIL_*</code> for SMTP or your provider.
</p>

<h2>Observability (Nightwatch)</h2>
<p>
    Optional <a href="https://nightwatch.laravel.com" rel="noopener noreferrer" target="_blank">Laravel Nightwatch</a>
    monitoring is included. Create an application in Nightwatch, then:
</p>
<pre><code>NIGHTWATCH_TOKEN=your-token
NIGHTWATCH_REQUEST_SAMPLE_RATE=0.1
LOG_CHANNEL=stack
LOG_STACK=laravel-cloud-socket,nightwatch</code></pre>
<p>Self-host / Docker / VPS: use <code>LOG_STACK=single,nightwatch</code> instead of <code>laravel-cloud-socket</code>.</p>
<p>
    Run the agent in production (<code>php artisan nightwatch:agent</code>).
    Docker Compose and <code>deploy.sh</code> start it via Supervisor automatically.
</p>

<h2>Object storage (logos &amp; avatars)</h2>
<p>
    Application logos and console avatars upload via Laravel’s filesystem.
    Install <code>league/flysystem-aws-s3-v3</code> (included) for S3 / Laravel Cloud buckets.
</p>
<pre><code># Local (default) — then: php artisan storage:link
FILESYSTEM_DISK=local
# AUTHZIO_ASSETS_DISK=assets

# Laravel Cloud: attach a public bucket; platform injects AWS_* / FILESYSTEM_DISK.
# For public URLs, copy AWS_URL from the bucket settings if not injected.
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=auto
AWS_BUCKET=
AWS_URL=
AWS_ENDPOINT=</code></pre>
<p>
    Uploads: <code>POST /api/v1/organizations/{org}/applications/{app}/logo</code>
    and <code>POST /api/v1/auth/avatar</code> (multipart field <code>logo</code> / <code>avatar</code>).
</p>

<h2>Domains</h2>
<table>
    <thead>
        <tr><th>Variable</th><th>Purpose</th></tr>
    </thead>
    <tbody>
        <tr>
            <td><code>AUTHZIO_DOMAIN_ROOT</code></td>
            <td>
                Root for Authzio-managed subdomains (e.g. <code>acme.authzio.com</code>).
                Use <code>authzio.test</code> locally; set <code>authzio.com</code> on Authzio Cloud production.
                Changing this updates stored subdomain hosts when Domains is loaded.
            </td>
        </tr>
        <tr>
            <td><code>AUTHZIO_CUSTOM_DOMAIN_CNAME_TARGET</code></td>
            <td>
                Hostname customers CNAME to for custom domains (default <code>customers.authzio.com</code>).
                Must be the Cloudflare for SaaS CNAME target on your <code>authzio.com</code> zone.
            </td>
        </tr>
        <tr>
            <td><code>CLOUDFLARE_CUSTOM_HOSTNAMES_ENABLED</code></td>
            <td>
                When true (with API token + zone id), adding a custom domain creates a Cloudflare Custom Hostname,
                shows ownership/SSL DNS records, and Verify polls until hostname + SSL are active.
            </td>
        </tr>
        <tr>
            <td><code>CLOUDFLARE_API_TOKEN</code> / <code>CLOUDFLARE_ZONE_ID</code></td>
            <td>Cloudflare credentials for the <code>authzio.com</code> zone (Custom Hostnames edit permission).</td>
        </tr>
        <tr>
            <td><code>AUTHZIO_DOMAIN_DNS_VERIFY</code></td>
            <td>Live DNS TXT check when verifying custom domains without Cloudflare SaaS (default true).</td>
        </tr>
    </tbody>
</table>

<h2>Authenticator MFA</h2>
<pre><code>AUTHZIO_MFA_ENABLED=true
AUTHZIO_MFA_ISSUER="${APP_NAME}"
AUTHZIO_MFA_REQUIRED_FOR_ADMINS=false</code></pre>
<p>
    Set <code>AUTHZIO_MFA_ENABLED=false</code> only if you intentionally disable TOTP MFA globally.
    Console enrollment is under <strong>Account → Settings</strong> (Authenticator section).
    See <a href="{{ route('docs', ['page' => 'authentication']) }}">User authentication</a>.
</p>

<h2>Cloud billing</h2>
<p>Self-hosted installs can turn off cloud billing:</p>
<pre><code>AUTHZIO_BILLING_ENABLED=false</code></pre>

<p>When enabled on Authzio Cloud, set Dodo Payments keys from <code>.env.example</code> (<code>DODO_*</code>), then run <code>php artisan setup:dodo</code> to create or sync products and prices.</p>

<h2>Launch readiness</h2>
<p>
    Before going live on Authzio Cloud (or a production self-host with billing), run:
</p>
<pre><code>php artisan authzio:launch-check</code></pre>
<p>
    Checks <code>APP_KEY</code>, billing/Dodo config, queue and session drivers, MFA enabled, and paid plan product mapping.
    Still run a queue worker and confirm the Dodo webhook URL points at <code>POST /api/v1/webhooks/dodo</code>.
</p>

<h2>Social login (organization)</h2>
<p>
    Google and GitHub credentials are stored per organization in the console
    (<strong>Social login</strong>), not only in <code>.env</code>, so each tenant can bring their own OAuth apps.
</p>

<h2>Enterprise SSO (organization)</h2>
<p>
    On Growth and higher, configure OIDC IdP connections under <strong>Enterprise SSO</strong>
    (issuer discovery, client credentials, optional email domains). See
    <a href="{{ route('docs', ['page' => 'authentication']) }}">User authentication</a>.
</p>

<h2>Marketing SEO endpoints</h2>
<p>
    These are Laravel routes (see <code>SeoController</code>), not static files under <code>public/</code>:
</p>
<ul>
    <li><code>/sitemap.xml</code> — URLs for home, pricing, legal pages, and docs</li>
    <li><code>/robots.txt</code> — crawl rules and sitemap pointer</li>
    <li><code>/llms.txt</code> — short index for LLM crawlers</li>
</ul>
<p>
    Legal pages (<code>/privacy</code>, <code>/terms</code>, <code>/cookies</code>) are included in the sitemap automatically.
</p>

<h2>Testing pyramid</h2>
<p>
    <strong>CI</strong> runs PHPUnit only (<code>composer test</code> — unit + Feature).
    <strong>Local browser E2E</strong> uses Playwright with Mailpit (verification, invites, org console flows).
    E2E is not run on GitHub Actions.
</p>
<pre><code>docker compose -f docker-compose.e2e.yml up -d
php artisan authzio:e2e-prepare
npm run test:e2e:install
npm run test:e2e</code></pre>
<p>
    Config template: <code>.env.e2e.example</code> (SQLite file DB, SMTP to Mailpit on port 1025).
    Domains E2E covers the Domains UI only (no live Cloudflare provisioning).
</p>
@endsection