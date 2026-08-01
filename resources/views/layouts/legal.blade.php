@extends('layouts.marketing')

@section('title', $legalMetaTitle)
@section('meta_description', $legalMetaDescription)
@section('og_title', $legalMetaTitle)
@section('og_description', $legalMetaDescription)
@section('canonical', $legalCanonical)

@section('content')
<section class="mkt-surface border-b border-mist">
    <div class="mkt-shell py-12 sm:py-16">
        <p class="text-sm text-ink-soft/60">
            <a href="{{ route('home') }}" class="hover:text-ink">Home</a>
            <span class="mx-2 text-ink-soft/35">/</span>
            <span>{{ $legalTitle }}</span>
        </p>
        <h1 class="mt-4 font-display text-4xl font-bold tracking-tight text-ink sm:text-5xl">
            {{ $legalTitle }}
        </h1>
        <p class="mt-3 text-sm text-ink-soft/55">
            Last updated {{ $legalUpdated }} · {{ config('marketing.organization') }}
        </p>
    </div>
</section>

<section class="bg-paper">
    <div class="mkt-shell py-12 sm:py-16 lg:flex lg:gap-14">
        <aside class="mb-10 w-full shrink-0 lg:mb-0 lg:w-52">
            <p class="text-xs font-semibold tracking-[0.12em] text-ink-soft/45 uppercase">Legal</p>
            <nav class="mt-3 space-y-0.5" aria-label="Legal">
                @foreach ($legalNav as $item)
                    <a
                        href="{{ route($item['route']) }}"
                        class="block px-3 py-2 text-sm {{ $legalSlug === $item['slug'] ? 'bg-fog font-medium text-ink' : 'text-ink-soft/70 hover:text-ink' }}"
                    >
                        {{ $item['title'] }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <article class="legal-prose min-w-0 flex-1 max-w-3xl">
            @yield('legal')
        </article>
    </div>
</section>
@endsection
