@extends('layouts.marketing')

@section('title', 'Try Authzio demo — live OIDC console')
@section('meta_description', 'Explore Authzio’s open-source identity console with a shared demo. Try login customization, browse OIDC settings, and tour the workspace — without installing.')
@section('og_title', 'Try the Authzio demo console')
@section('og_description', 'Sign in with shared demo credentials and explore organizations, apps, and OIDC settings. Session changes are temporary; identity and domains stay locked.')
@section('canonical', route('demo'))

@section('content')
<section class="mkt-surface border-b border-mist">
    <div class="mkt-shell py-16 sm:py-20 lg:py-24">
        <div class="max-w-2xl">
            <h1 class="font-display text-4xl font-bold tracking-tight text-ink sm:text-5xl">
                Try Authzio without installing.
            </h1>
            <p class="mkt-lede">
                Sign in with the shared demo account. Explore the console freely —
                many edits apply for your session only. Identity, domains, billing checkout,
                and hosted OAuth stay locked.
            </p>
        </div>

        <figure class="mkt-frame mt-12">
            <img
                src="{{ asset('images/demo/console-tour-light.gif') }}"
                alt="Authzio console walkthrough from the demo account: overview, applications, OIDC, members, and roles"
                width="960"
                height="600"
                class="h-auto w-full"
                loading="eager"
                decoding="async"
            >
        </figure>
    </div>
</section>

<section class="border-b border-mist bg-paper-elevated">
    <div class="mkt-shell grid gap-12 py-16 sm:py-20 lg:grid-cols-2 lg:gap-16">
        <div>
            <h2 class="font-display text-2xl font-semibold tracking-tight text-ink">Credentials</h2>
            <dl class="mt-8 space-y-5 text-sm">
                <div>
                    <dt class="text-ink-soft/55">Email</dt>
                    <dd class="mt-1 font-mono text-base text-ink">{{ $demoEmail }}</dd>
                </div>
                <div>
                    <dt class="text-ink-soft/55">Password</dt>
                    <dd class="mt-1 font-mono text-base text-ink">{{ $demoPassword }}</dd>
                </div>
            </dl>
            <a href="{{ url('/console/login?demo=1') }}" class="mkt-btn-primary mt-10">
                Open console login
            </a>
            <p class="mt-4 max-w-sm text-xs leading-relaxed text-ink-soft/55">
                Shared with other visitors. Do not enter real secrets or production data while signed in as demo.
            </p>
        </div>

        <div class="border-t border-mist pt-10 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-16">
            <h2 class="font-display text-2xl font-semibold tracking-tight text-ink">Allowed</h2>
            <ul class="mt-6 space-y-3 text-[0.95rem] leading-relaxed text-ink-soft/75">
                <li>Browse organizations, applications, OIDC, members, and roles</li>
                <li>Try login layout, theme, branding, and email template edits (session-scoped)</li>
                <li>Open live login previews for demo apps</li>
                <li>Change console locale and theme preferences</li>
            </ul>
            <h3 class="mt-10 font-display text-lg font-semibold text-ink">Locked</h3>
            <ul class="mt-4 space-y-3 text-[0.95rem] leading-relaxed text-ink-soft/75">
                <li>Password, profile, avatar, and MFA changes</li>
                <li>Custom domain create / verify / delete</li>
                <li>Billing checkout</li>
                <li>Hosted login and OAuth as the demo user</li>
            </ul>
        </div>
    </div>
</section>

<section class="bg-paper">
    <div class="mkt-shell py-14">
        <p class="text-sm leading-relaxed text-ink-soft/65">
            Ready for your own instance?
            <a href="{{ url('/console/register') }}" class="font-medium text-teal">Create a free account</a>
            or follow the docs to self-host.
        </p>
    </div>
</section>
@endsection
