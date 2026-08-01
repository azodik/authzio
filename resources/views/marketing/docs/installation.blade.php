@extends('layouts.docs')

@section('docs')
<h1>Installation</h1>
<p class="lead">
    Three ways to run Authzio locally: Laravel Herd, plain PHP (no Docker), or
    <a href="{{ route('docs', ['page' => 'docker']) }}">Docker Compose</a>.
</p>

<h2>Requirements</h2>
<ul>
    <li>PHP 8.5+</li>
    <li>Composer 2</li>
    <li>Node.js 24+</li>
    <li>PostgreSQL 15+</li>
    <li>Redis (optional locally)</li>
</ul>

<h2>Laravel Herd (macOS)</h2>
<pre><code>git clone https://github.com/azodik/authzio.git
cd authzio
herd link authzio
herd secure authzio
composer install
cp .env.example .env
php artisan key:generate
# Create Postgres database `authzio`
php artisan migrate --seed
npm install
npm run dev</code></pre>

<table>
    <thead>
        <tr><th>Surface</th><th>URL</th></tr>
    </thead>
    <tbody>
        <tr><td>Marketing</td><td><code>https://authzio.test</code></td></tr>
        <tr><td>Docs</td><td><code>https://authzio.test/docs</code></td></tr>
        <tr><td>Console</td><td><code>https://authzio.test/console</code></td></tr>
    </tbody>
</table>

<h2>Without Docker (PHP + web server)</h2>
<p>
    Same Composer / migrate / npm steps as above. Point Nginx or Apache
    <strong>document root</strong> at <code>public/</code>, set <code>DB_*</code> in
    <code>.env</code>, and prefer HTTPS for OAuth cookies.
</p>
<pre><code>composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm install && npm run build</code></pre>

<p>For Docker, see <a href="{{ route('docs', ['page' => 'docker']) }}">Docker</a>.</p>

<h2>Demo account (optional)</h2>
<pre><code>php artisan authzio:setup --with-demo</code></pre>
<ul>
    <li>Email: <code>demo@authzio.com</code></li>
    <li>Password: <code>AuthzioDemo2026!</code></li>
</ul>
<p>
    Read-only console user. Open
    <a href="{{ route('demo') }}">Demo page</a>, then <strong>Open console login</strong>
    (<code>/console/login?demo=1</code>) to pre-fill the demo email. A plain
    <code>/console/login</code> visit does not auto-fill credentials.
</p>

<div class="callout">
    Remove or change demo credentials before any shared or production environment.
</div>

<h2>Before production</h2>
<pre><code>php artisan authzio:launch-check</code></pre>
<p>
    Confirms billing/MFA/runtime basics. Details:
    <a href="{{ route('docs', ['page' => 'configuration']) }}">Configuration</a>.
</p>
@endsection
