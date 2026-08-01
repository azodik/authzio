@extends('layouts.marketing')

@section('title', 'Authzio pricing — free self-host & Cloud MAU plans')
@section('meta_description', 'Authzio is free to self-host. Authzio Cloud MAU plans: Free, Starter ($5), Growth ($20), Scale ($99), and Enterprise for managed OIDC.')
@section('og_title', 'Authzio pricing — free self-host & Cloud MAU')
@section('og_description', 'Self-host Authzio at no cost, or run Authzio Cloud with transparent monthly active user pricing from $5/mo.')
@section('canonical', route('pricing'))

@push('head')
<script type="application/ld+json">
{!! json_encode($productSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) !!}
</script>
@endpush

@section('content')
<section class="mkt-surface border-b border-mist">
    <div class="mkt-shell py-16 sm:py-20 lg:py-24">
        <div class="max-w-2xl">
            <h1 class="font-display text-4xl font-bold tracking-tight text-ink sm:text-5xl">
                Free to self-host. Cloud when you want ops handled.
            </h1>
            <p class="mkt-lede">
                Run Authzio on your infrastructure at no cost, or use Authzio Cloud with transparent monthly active user billing.
            </p>
        </div>

        <div class="mt-14 grid gap-10 border-t border-mist pt-12 lg:grid-cols-2 lg:gap-16">
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">Self-hosted</h2>
                <p class="mt-3 font-display text-4xl font-bold tracking-tight text-ink">$0</p>
                <p class="mt-2 text-sm text-ink-soft/60">Forever · your infrastructure</p>
                <ul class="mt-8 space-y-3 text-[0.95rem] text-ink-soft/80">
                    <li>Full source on GitHub</li>
                    <li>Docker and Compose</li>
                    <li>Disable cloud billing with one env flag</li>
                    <li>Community support</li>
                </ul>
                <a href="{{ route('docs') }}" class="mkt-btn-dark mt-10">
                    Install guide
                </a>
            </div>
            <div class="border-t border-mist pt-10 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-16">
                <h2 class="font-display text-2xl font-semibold text-ink">Authzio Cloud</h2>
                <p class="mt-3 font-display text-4xl font-bold tracking-tight text-ink">MAU</p>
                <p class="mt-2 text-sm text-ink-soft/60">Monthly subscription · hosted checkout</p>
                <ul class="mt-8 space-y-3 text-[0.95rem] text-ink-soft/80">
                    <li>Distinct users who authenticate each month</li>
                    <li>Usage dashboard in the console</li>
                    <li>Hosted checkout and subscription webhooks</li>
                    <li>Managed hosting and upgrades</li>
                </ul>
                <a href="{{ route('console') }}" class="mkt-btn-primary mt-10">
                    Open console
                </a>
            </div>
        </div>
    </div>
</section>

<section class="bg-paper-elevated">
    <div class="mkt-shell py-16 sm:py-20">
        <h2 class="mkt-title">Cloud plans</h2>
        <p class="mkt-lede">
            MAU = unique users with a qualifying auth event in the calendar month, deduped per user per day.
        </p>

        <div class="mt-12 grid gap-0 border-t border-mist sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['name' => 'Free', 'price' => '$0', 'note' => '1,000 MAU'],
                ['name' => 'Starter', 'price' => '$5', 'suffix' => '/mo', 'note' => '5,000 MAU'],
                ['name' => 'Growth', 'price' => '$20', 'suffix' => '/mo', 'note' => '50,000 MAU'],
                ['name' => 'Scale', 'price' => '$99', 'suffix' => '/mo', 'note' => '250,000 MAU'],
            ] as $plan)
                <article class="border-b border-mist py-8 sm:border-r sm:px-6 sm:first:pl-0 xl:border-b-0 xl:last:border-r-0">
                    <h3 class="font-display text-lg font-semibold text-ink">{{ $plan['name'] }}</h3>
                    <p class="mt-3 font-display text-3xl font-bold tracking-tight text-ink">
                        {{ $plan['price'] }}@isset($plan['suffix'])<span class="text-base font-normal text-ink-soft/50">{{ $plan['suffix'] }}</span>@endisset
                    </p>
                    <p class="mt-2 text-sm text-ink-soft/55">{{ $plan['note'] }}</p>
                </article>
            @endforeach
        </div>

        <p class="mt-10 text-sm text-ink-soft/60">
            Enterprise: custom MAU and contracts —
            <a href="https://azodik.com" class="mkt-link" rel="noopener">contact Azodik</a>.
        </p>
    </div>
</section>
@endsection
