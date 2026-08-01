@extends('layouts.marketing')

@section('title', $docsMetaTitle ?? (($docsTitle ?? 'Documentation').' — Authzio Docs'))
@section('meta_description', $docsMetaDescription ?? 'Authzio documentation: install, configure, authenticate users, OAuth 2.1, OpenID Connect, organizations, billing, and community support.')
@section('og_title', $docsMetaTitle ?? (($docsTitle ?? 'Documentation').' — Authzio Docs'))
@section('og_description', $docsMetaDescription ?? 'Authzio documentation for open-source identity, OAuth 2.1, and OpenID Connect.')
@section('canonical', $docsCanonical ?? url()->current())

@push('head')
@if (! empty($docsBreadcrumbSchema))
<script type="application/ld+json">
{!! json_encode($docsBreadcrumbSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) !!}
</script>
@endif
@endpush

@section('content')
<div class="border-b border-mist bg-fog/60">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-3 sm:px-8">
        <p class="text-sm text-ink-soft/70">
            <a href="{{ route('docs') }}" class="font-medium text-ink hover:text-teal">Docs</a>
            <span class="mx-2 text-ink-soft/40">/</span>
            <span>{{ $docsTitle }}</span>
        </p>
        <x-github-link class="hidden sm:inline-flex" icon-class="size-5" />
    </div>
</div>

<div class="mx-auto flex max-w-6xl flex-col gap-10 px-5 py-12 sm:px-8 lg:flex-row lg:gap-14 lg:py-16">
    <aside class="w-full shrink-0 lg:w-56">
        <details class="group lg:hidden" id="docs-mobile-nav">
            <summary class="flex cursor-pointer list-none items-center justify-between border border-mist bg-paper-elevated px-4 py-3 text-sm font-medium text-ink">
                <span>Docs menu · {{ $docsTitle }}</span>
                <span class="text-ink-soft/50 group-open:hidden">+</span>
                <span class="hidden text-ink-soft/50 group-open:inline">−</span>
            </summary>
            <nav class="mt-2 space-y-5 border border-t-0 border-mist bg-paper-elevated p-4" aria-label="Documentation mobile">
                @php
                    $groups = collect($docsNav)->groupBy('group');
                @endphp
                @foreach ($groups as $group => $items)
                    <div>
                        <p class="text-xs font-semibold tracking-[0.12em] text-ink-soft/45 uppercase">{{ $group }}</p>
                        <ul class="mt-2 space-y-0.5">
                            @foreach ($items as $item)
                                @php
                                    $href = $item['slug'] === 'index' ? route('docs') : route('docs', ['page' => $item['slug']]);
                                    $active = $docsSlug === $item['slug'];
                                @endphp
                                <li>
                                    <a
                                        href="{{ $href }}"
                                        @class([
                                            'block rounded-sm px-2 py-2.5 text-sm transition-colors',
                                            'bg-teal/10 font-medium text-teal-deep' => $active,
                                            'text-ink-soft/75 hover:bg-mist/60 hover:text-ink' => ! $active,
                                        ])
                                    >
                                        {{ $item['title'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>
        </details>

        <nav class="sticky top-20 hidden space-y-6 lg:block" aria-label="Documentation">
            @php
                $groups = collect($docsNav)->groupBy('group');
            @endphp
            @foreach ($groups as $group => $items)
                <div>
                    <p class="text-xs font-semibold tracking-[0.12em] text-ink-soft/45 uppercase">{{ $group }}</p>
                    <ul class="mt-2 space-y-0.5">
                        @foreach ($items as $item)
                            @php
                                $href = $item['slug'] === 'index' ? route('docs') : route('docs', ['page' => $item['slug']]);
                                $active = $docsSlug === $item['slug'];
                            @endphp
                            <li>
                                <a
                                    href="{{ $href }}"
                                    @class([
                                        'block rounded-sm px-2 py-2 text-sm transition-colors',
                                        'bg-teal/10 font-medium text-teal-deep' => $active,
                                        'text-ink-soft/75 hover:bg-mist/60 hover:text-ink' => ! $active,
                                    ])
                                >
                                    {{ $item['title'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div class="space-y-2 border-t border-mist pt-5">
                <a
                    href="https://github.com/azodik/authzio/issues/new/choose"
                    class="block text-sm font-medium text-teal hover:text-teal-deep"
                    rel="noopener"
                >
                    Raise an issue →
                </a>
                <a
                    href="https://github.com/sponsors/azodik"
                    class="block text-sm text-ink-soft/70 hover:text-ink"
                    rel="noopener"
                >
                    Sponsor Azodik
                </a>
            </div>
        </nav>
    </aside>

    <article class="min-w-0 flex-1 docs-prose">
        @yield('docs')
    </article>
</div>
@endsection

@push('head')
<style>
    .docs-prose h1 {
        font-family: var(--font-display, ui-sans-serif);
        font-size: clamp(1.85rem, 3vw, 2.4rem);
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--color-ink, #0f1720);
        line-height: 1.2;
    }
    .docs-prose h2 {
        font-family: var(--font-display, ui-sans-serif);
        font-size: 1.35rem;
        font-weight: 650;
        margin-top: 2.25rem;
        margin-bottom: 0.75rem;
        color: var(--color-ink, #0f1720);
    }
    .docs-prose h3 {
        font-size: 1.05rem;
        font-weight: 600;
        margin-top: 1.5rem;
        margin-bottom: 0.5rem;
        color: var(--color-ink, #0f1720);
    }
    .docs-prose p, .docs-prose li {
        color: color-mix(in srgb, var(--color-ink-soft, #334155) 80%, transparent);
        line-height: 1.7;
        font-size: 0.975rem;
    }
    .docs-prose p + p { margin-top: 1rem; }
    .docs-prose ul { margin: 0.75rem 0 1rem; padding-left: 1.25rem; list-style: disc; }
    .docs-prose ol { margin: 0.75rem 0 1rem; padding-left: 1.25rem; list-style: decimal; }
    .docs-prose li + li { margin-top: 0.35rem; }
    .docs-prose code {
        font-size: 0.85em;
        color: var(--color-teal-deep, #0a5c5c);
        background: color-mix(in srgb, var(--color-fog, #f1f5f4) 90%, white);
        padding: 0.1em 0.35em;
        border-radius: 0.2rem;
    }
    .docs-prose pre {
        margin: 1rem 0 1.25rem;
        padding: 1rem 1.1rem;
        overflow-x: auto;
        background: #12202a;
        color: #e8eef2;
        font-size: 0.82rem;
        line-height: 1.55;
        border-radius: 0.25rem;
    }
    .docs-prose pre code {
        background: transparent;
        color: inherit;
        padding: 0;
    }
    .docs-prose a {
        color: var(--color-teal, #0b6e6e);
        font-weight: 500;
        text-underline-offset: 2px;
    }
    .docs-prose a:hover { text-decoration: underline; }
    .docs-prose table {
        width: 100%;
        margin: 1rem 0 1.5rem;
        border-collapse: collapse;
        font-size: 0.9rem;
        display: block;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .docs-prose th, .docs-prose td {
        border: 1px solid color-mix(in srgb, var(--color-mist, #d8e0de) 100%, transparent);
        padding: 0.55rem 0.75rem;
        text-align: left;
    }
    .docs-prose th {
        background: color-mix(in srgb, var(--color-fog, #f1f5f4) 100%, transparent);
        font-weight: 600;
        color: var(--color-ink, #0f1720);
    }
    .docs-prose .lead {
        font-size: 1.1rem;
        line-height: 1.65;
        margin-top: 1rem;
        margin-bottom: 1.5rem;
        color: color-mix(in srgb, var(--color-ink-soft, #334155) 75%, transparent);
    }
    .docs-prose .callout {
        margin: 1.25rem 0;
        padding: 0.9rem 1rem;
        border-left: 3px solid var(--color-teal, #0b6e6e);
        background: color-mix(in srgb, var(--color-fog, #f1f5f4) 100%, transparent);
        font-size: 0.92rem;
    }
</style>
@endpush
