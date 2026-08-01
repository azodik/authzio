@extends('layouts.marketing')

@section('title', 'Authzio — Open-source identity provider (OAuth 2.1 & OIDC)')
@section('meta_description', 'Self-host Authzio free or use Authzio Cloud: open-source IAM with OAuth 2.1, OpenID Connect, passkeys, MFA, organizations, and RBAC. An Auth0 / Keycloak-style alternative you can own.')
@section('og_title', 'Authzio — Open-source identity provider')
@section('og_description', 'OAuth 2.1, OpenID Connect, passkeys, and RBAC — self-host free or run managed Authzio Cloud.')
@section('canonical', url('/'))
@section('og_image_alt', 'Authzio open-source identity and access management')

@push('head')
<script type="application/ld+json">
{!! json_encode($softwareSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) !!}
</script>
@endpush

@section('content')
{{-- Hero: brand-first, no image — atmosphere + type --}}
<section class="relative overflow-hidden border-b border-mist">
    <div class="mkt-hero-plane absolute inset-0" aria-hidden="true"></div>
    <div class="mkt-hero-grid absolute inset-0 opacity-[0.35]" aria-hidden="true"></div>

    <div class="relative mkt-shell flex min-h-[min(72vh,40rem)] flex-col justify-center py-20 sm:py-24 lg:py-28">
        <div class="max-w-3xl">
            <p class="reveal font-display text-[3.5rem] font-extrabold leading-none tracking-tight text-ink sm:text-6xl lg:text-[5rem]">
                Authzio
            </p>
            <div class="mkt-rule mt-6 h-0.5 w-16 bg-teal sm:w-20"></div>
            <h1 class="reveal reveal-delay-1 mt-7 max-w-2xl font-display text-2xl font-semibold leading-[1.18] tracking-tight text-ink sm:text-3xl lg:text-[2.35rem]">
                Authentication you can host, audit, and own.
            </h1>
            <p class="reveal reveal-delay-2 mt-5 max-w-xl text-base leading-relaxed text-ink-soft/80 sm:text-lg">
                Open-source identity with OAuth&nbsp;2.1, OpenID Connect, passkeys, and hosted login — control without a black box.
            </p>
            <div class="reveal reveal-delay-3 mt-10 flex flex-wrap items-center gap-3">
                <a href="{{ route('console') }}" class="mkt-btn-primary">
                    Start free
                </a>
                <a href="{{ route('demo') }}" class="mkt-btn-secondary">
                    Try the demo
                </a>
            </div>
        </div>
    </div>
</section>

{{-- Product proof --}}
<section id="demo" class="border-b border-mist bg-paper-elevated">
    <div class="mkt-shell py-16 sm:py-20 lg:py-28">
        <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-xl">
                <h2 class="mkt-title">The console, as teams use it.</h2>
                <p class="mkt-lede">
                    Organizations, applications, OIDC endpoints, members, and roles — browse a shared read-only workspace.
                </p>
            </div>
            <a href="{{ route('demo') }}" class="mkt-btn-dark shrink-0 self-start lg:self-auto">
                Demo credentials
            </a>
        </div>

        <figure class="mkt-screen mt-12 reveal-on-scroll">
            <img
                src="{{ asset('images/demo/console-tour-light.gif') }}"
                alt="Authzio console walkthrough: organization overview, applications, OIDC endpoints, members, and roles"
                width="960"
                height="600"
                class="h-auto w-full"
                loading="lazy"
                decoding="async"
            >
            <figcaption class="sr-only">
                Animated tour of the Authzio admin console in light theme.
            </figcaption>
        </figure>
    </div>
</section>

{{-- Capabilities --}}
<section id="features" class="border-b border-mist bg-paper">
    <div class="mkt-shell py-16 sm:py-20 lg:py-28">
        <div class="max-w-2xl">
            <h2 class="mkt-title">What Authzio covers.</h2>
            <p class="mkt-lede">
                Hosted login for people, standards for apps, and an admin console for the rest.
            </p>
        </div>

        <div class="mt-16 grid gap-x-10 gap-y-0 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['n' => '01', 'title' => 'Sign-in that fits the product', 'body' => 'Password, email OTP, social login, passkeys, MFA, and recovery — with sessions and devices under your policies.'],
                ['n' => '02', 'title' => 'OAuth 2.1 and OpenID Connect', 'body' => 'Authorization code with PKCE, client credentials, refresh, introspection, discovery, JWKS, and UserInfo.'],
                ['n' => '03', 'title' => 'Organizations and RBAC', 'body' => 'Multi-tenant orgs with invitations, teams, permissions, and resource-scoped access.'],
                ['n' => '04', 'title' => 'Customer identity providers', 'body' => 'OIDC enterprise SSO so customers can keep the IdP they already run — with SAML federation on Enterprise.'],
                ['n' => '05', 'title' => 'Audit you can trust', 'body' => 'Trails for logins, role changes, token issuance, and admin actions — built for review, not vanity dashboards.'],
                ['n' => '06', 'title' => 'API for every console action', 'body' => 'REST surfaces for automation, plus webhooks when state changes in the wild.'],
            ] as $i => $feature)
                <article class="group border-t border-mist py-8 {{ $i < 3 ? 'lg:border-t-0 lg:pt-0' : '' }}">
                    <p class="font-display text-sm font-semibold tracking-[0.14em] text-teal/70 transition-colors group-hover:text-teal">
                        {{ $feature['n'] }}
                    </p>
                    <h3 class="mt-3 font-display text-lg font-semibold tracking-tight text-ink sm:text-xl">
                        {{ $feature['title'] }}
                    </h3>
                    <p class="mt-3 max-w-sm text-[0.95rem] leading-relaxed text-ink-soft/70">
                        {{ $feature['body'] }}
                    </p>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- Deploy paths --}}
<section id="deploy" class="relative overflow-hidden border-b border-mist bg-fog/60">
    <div class="pointer-events-none absolute -right-24 top-0 h-72 w-72 rounded-full bg-teal/[0.06] blur-3xl" aria-hidden="true"></div>
    <div class="mkt-shell py-16 sm:py-20 lg:py-28">
        <div class="max-w-2xl">
            <h2 class="mkt-title">Run it yourself, or let us.</h2>
            <p class="mkt-lede">
                Same Authzio either way — Laravel on your metal, or Authzio Cloud with MAU billing.
            </p>
        </div>

        <div class="mt-14 grid gap-12 lg:grid-cols-2 lg:gap-0">
            <div class="lg:pr-16">
                <p class="mkt-kicker">Self-hosted</p>
                <h3 class="mt-3 font-display text-2xl font-semibold tracking-tight text-ink">
                    Your infrastructure. MIT forever.
                </h3>
                <p class="mt-4 text-[0.95rem] leading-relaxed text-ink-soft/70">
                    Composer, Docker, or Compose. PostgreSQL and Redis. Disable cloud billing with one env flag when you stay on-prem.
                </p>
                <ul class="mt-8 space-y-3 text-sm text-ink-soft/80">
                    <li class="flex gap-3">
                        <span class="mt-2 size-1 shrink-0 bg-teal" aria-hidden="true"></span>
                        <span>PHP 8.5 · Laravel 13</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-2 size-1 shrink-0 bg-teal" aria-hidden="true"></span>
                        <span>Multi-arch Linux images</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-2 size-1 shrink-0 bg-teal" aria-hidden="true"></span>
                        <span>Full source on GitHub</span>
                    </li>
                </ul>
                <a href="{{ route('docs', ['page' => 'installation']) }}" class="mkt-link mt-9 inline-flex items-center gap-1.5">
                    Installation guide
                    <span aria-hidden="true">→</span>
                </a>
            </div>

            <div class="border-t border-mist pt-12 lg:border-l lg:border-t-0 lg:pl-16 lg:pt-0">
                <p class="mkt-kicker">Authzio Cloud</p>
                <h3 class="mt-3 font-display text-2xl font-semibold tracking-tight text-ink">
                    Managed ops. Pay for MAU.
                </h3>
                <p class="mt-4 text-[0.95rem] leading-relaxed text-ink-soft/70">
                    Create an organization, add apps, and ship. We run the stack; you pay for monthly active users through Dodo Payments.
                </p>
                <ul class="mt-8 space-y-3 text-sm text-ink-soft/80">
                    <li class="flex gap-3">
                        <span class="mt-2 size-1 shrink-0 bg-teal" aria-hidden="true"></span>
                        <span>Hosted checkout and webhooks</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-2 size-1 shrink-0 bg-teal" aria-hidden="true"></span>
                        <span>Usage in the console</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-2 size-1 shrink-0 bg-teal" aria-hidden="true"></span>
                        <span>Plans from Free through Scale</span>
                    </li>
                </ul>
                <a href="{{ route('pricing') }}" class="mkt-link mt-9 inline-flex items-center gap-1.5">
                    See pricing
                    <span aria-hidden="true">→</span>
                </a>
            </div>
        </div>
    </div>
</section>

{{-- FAQ — keep visible Q&A for SEO + FAQPage schema alignment --}}
<section id="faq" class="border-b border-mist bg-paper-elevated">
    <div class="mkt-shell py-16 sm:py-20 lg:py-28">
        <div class="max-w-2xl">
            <h2 class="mkt-title">Questions teams ask first.</h2>
            <p class="mkt-lede">
                Self-hosting, Authzio Cloud MAU, and how billing stays predictable.
            </p>
        </div>

        <div class="mt-12 max-w-3xl divide-y divide-mist border-y border-mist">
            @foreach ([
                [
                    'q' => 'Is self-hosting free?',
                    'a' => 'Yes. Self-hosting is free forever under MIT. Set AUTHZIO_BILLING_ENABLED=false when you run your own infrastructure.',
                ],
                [
                    'q' => 'Does Authzio support OAuth 2.1 and OpenID Connect?',
                    'a' => 'Yes. Authzio provides OAuth 2.1 and OpenID Connect for applications, including discovery endpoints, clients, PKCE, and hosted login.',
                ],
                [
                    'q' => 'Is Authzio an Auth0 or Keycloak alternative?',
                    'a' => 'Authzio is an open-source identity provider you can self-host or run as managed Authzio Cloud — built for teams that want OIDC and access control without a proprietary black box.',
                ],
                [
                    'q' => 'What is an MAU on Authzio Cloud?',
                    'a' => 'A monthly active user is a distinct person with a qualifying auth event in the calendar month (console login, end-user authenticate, or token issued), deduped per user per day.',
                ],
                [
                    'q' => 'Will I know before I hit my MAU limit?',
                    'a' => 'Yes. Owners and admins get emails at 80%, 90%, and 100% of the MAU limit — once each per calendar month by default. Application and platform email quotas use the same thresholds.',
                ],
                [
                    'q' => 'Can I try Authzio before installing?',
                    'a' => 'Yes. Use the shared read-only demo console to explore organizations, applications, and OIDC settings — no install required.',
                ],
            ] as $item)
                <details class="group">
                    <summary class="cursor-pointer list-none py-5 font-display text-base font-semibold text-ink marker:content-none [&::-webkit-details-marker]:hidden">
                        <span class="flex items-center justify-between gap-6">
                            <span>{{ $item['q'] }}</span>
                            <span class="mkt-faq-icon shrink-0 text-ink-soft/40 transition group-open:rotate-45 group-open:text-teal" aria-hidden="true">+</span>
                        </span>
                    </summary>
                    <p class="pb-5 pr-12 text-[0.95rem] leading-relaxed text-ink-soft/75">
                        {{ $item['a'] }}
                    </p>
                </details>
            @endforeach
        </div>

        <p class="mt-8 text-sm text-ink-soft/65">
            More in the
            <a href="{{ route('docs', ['page' => 'faq']) }}" class="mkt-link">docs FAQ</a>
            and
            <a href="{{ route('docs', ['page' => 'billing']) }}" class="mkt-link">billing guide</a>.
        </p>
    </div>
</section>

{{-- Closing --}}
<section class="mkt-surface relative overflow-hidden">
    <div class="pointer-events-none absolute inset-y-0 left-0 w-1 bg-teal" aria-hidden="true"></div>
    <div class="mkt-shell flex flex-col gap-10 py-16 sm:flex-row sm:items-end sm:justify-between sm:py-24">
        <div class="max-w-xl">
            <p class="font-display text-4xl font-bold tracking-tight text-ink sm:text-5xl">
                Authzio
            </p>
            <p class="mt-5 text-lg leading-relaxed text-ink-soft/75">
                Open-source identity. Cloud when you want ops handled.
            </p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('console') }}" class="mkt-btn-primary">
                Open console
            </a>
            <a href="{{ route('docs') }}" class="mkt-btn-secondary">
                Read the docs
            </a>
        </div>
    </div>
</section>
@endsection
