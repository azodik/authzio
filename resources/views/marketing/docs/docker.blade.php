@extends('layouts.docs')

@section('docs')
<h1>Docker</h1>
<p class="lead">
    Run Authzio with Nginx, PHP-FPM, PostgreSQL, and Redis via Compose.
    Images are Linux multi-arch (<code>amd64</code> + <code>arm64</code>) and work on
    Mac, Linux, and Windows through Docker Desktop.
</p>

<h2>Quick start</h2>
<pre><code>export APP_KEY=base64:$(openssl rand -base64 32)
docker compose up --build</code></pre>

<p>App: <code>http://localhost:8080</code></p>

<h2>Build the image yourself</h2>
<pre><code># Current machine architecture
docker build -t authzio:local .

# Multi-arch (needs buildx; does not push)
docker buildx build \
  --platform linux/amd64,linux/arm64 \
  -t authzio:local \
  --load=false \
  .</code></pre>

<p>
    CI runs format → typecheck → tests on every PR and push to <code>main</code>.
    Images bake SemVer + build number + commit (<code>AUTHZIO_VERSION</code>,
    <code>AUTHZIO_BUILD</code>, <code>AUTHZIO_COMMIT</code> / <code>build-info.json</code>).
    A GitHub Release (multi-arch Docker archives) is created when you bump
    <code>VERSION</code> (and matching <code>package.json</code>) or push a <code>vX.Y.Z</code> tag.
    Merges to <code>main</code> push a multi-arch image to GitHub Container Registry
    (<code>ghcr.io/&lt;owner&gt;/&lt;repo&gt;:version</code>) — see README
    <strong>Version &amp; releases</strong>.
</p>

<h2>Production notes</h2>
<ul>
    <li>Set a strong <code>APP_KEY</code>; never reuse demo secrets.</li>
    <li>Put TLS in front of the container.</li>
    <li>Configure mail, Redis, and queues for real traffic.</li>
    <li>Compose defaults are for local use (<code>APP_DEBUG</code>, open Redis) — harden before production.</li>
</ul>
@endsection
