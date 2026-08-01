<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A6565">
    <meta name="color-scheme" content="light">
    <meta name="robots" content="@yield('robots', 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1')">
    <meta name="author" content="{{ config('marketing.organization') }}">
    <meta name="keywords" content="{{ implode(', ', config('marketing.keywords', [])) }}">
    <meta name="application-name" content="Authzio">
    <meta name="referrer" content="strict-origin-when-cross-origin">

    @php
        $defaultTitle = 'Authzio — Open-source identity provider (OAuth 2.1 & OIDC)';
        $defaultDescription = 'Open-source IAM with OAuth 2.1, OpenID Connect, passkeys, MFA, organizations, and RBAC. Self-host free or use Authzio Cloud — Auth0/Keycloak-style identity you can own.';
        $pageTitle = trim($__env->yieldContent('title', $defaultTitle));
        $pageDescription = trim($__env->yieldContent('meta_description', $defaultDescription));
        $canonical = trim($__env->yieldContent('canonical', url()->current()));
        $ogTitle = trim($__env->yieldContent('og_title', $pageTitle));
        $ogDescription = trim($__env->yieldContent('og_description', $pageDescription));
        $ogImage = trim($__env->yieldContent('og_image', asset(ltrim((string) config('marketing.og_image'), '/'))));
        $ogImageAlt = trim($__env->yieldContent('og_image_alt', 'Authzio — open-source identity and access management'));
    @endphp

    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <link rel="canonical" href="{{ $canonical }}">
    <link rel="alternate" type="application/xml" title="Sitemap" href="{{ route('sitemap') }}">
    <link rel="alternate" type="text/plain" title="LLMs" href="{{ route('llms') }}">

    @if (filled(config('marketing.google_site_verification')))
        <meta name="google-site-verification" content="{{ config('marketing.google_site_verification') }}">
    @endif
    @if (filled(config('marketing.facebook_domain_verification')))
        <meta name="facebook-domain-verification" content="{{ config('marketing.facebook_domain_verification') }}">
    @endif
    @if (filled(config('marketing.bing_site_verification')))
        <meta name="msvalidate.01" content="{{ config('marketing.bing_site_verification') }}">
    @endif

    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:locale" content="{{ config('marketing.locale') }}">
    <meta property="og:site_name" content="Authzio">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:secure_url" content="{{ $ogImage }}">
    <meta property="og:image:width" content="{{ config('marketing.og_image_width') }}">
    <meta property="og:image:height" content="{{ config('marketing.og_image_height') }}">
    <meta property="og:image:alt" content="{{ $ogImageAlt }}">

    <meta name="twitter:card" content="summary_large_image">
    @if (filled(config('marketing.twitter')))
        <meta name="twitter:site" content="{{ config('marketing.twitter') }}">
    @endif
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <meta name="twitter:image:alt" content="{{ $ogImageAlt }}">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    <meta name="msapplication-TileColor" content="#0A6565">
    <meta name="msapplication-TileImage" content="{{ asset('images/logo-mark-256.png') }}">

    {{-- Ads / analytics: only load when IDs are configured --}}
    @if (filled(config('marketing.gtm_id')))
        <script>
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
            var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
            j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','{{ config('marketing.gtm_id') }}');
        </script>
    @elseif (filled(config('marketing.ga4_id')))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('marketing.ga4_id') }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ config('marketing.ga4_id') }}');
        </script>
    @endif

    @if (filled(config('marketing.meta_pixel_id')))
        <script>
            !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
            n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '{{ config('marketing.meta_pixel_id') }}');
            fbq('track', 'PageView');
        </script>
        <noscript>
            <img height="1" width="1" style="display:none"
                 src="https://www.facebook.com/tr?id={{ config('marketing.meta_pixel_id') }}&ev=PageView&noscript=1"
                 alt="">
        </noscript>
    @endif

    @if (filled(config('marketing.linkedin_partner_id')))
        <script>
            _linkedin_partner_id = "{{ config('marketing.linkedin_partner_id') }}";
            window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
            window._linkedin_data_partner_ids.push(_linkedin_partner_id);
        </script>
        <script async src="https://snap.licdn.com/li.lms-analytics/insight.min.js"></script>
    @endif

    @if (filled(config('marketing.reddit_pixel_id')))
        <script>
            !function(w,d){if(!w.rdt){var p=w.rdt=function(){p.sendEvent?p.sendEvent.apply(p,arguments):p.callQueue.push(arguments)};
            p.callQueue=[];var t=d.createElement("script");t.src="https://www.redditstatic.com/ads/pixel.js";t.async=!0;
            var s=d.getElementsByTagName("script")[0];s.parentNode.insertBefore(t,s)}}(window,document);
            rdt('init', '{{ config('marketing.reddit_pixel_id') }}');
            rdt('track', 'PageVisit');
        </script>
    @endif

    @stack('head')

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">
    @if (filled(config('marketing.gtm_id')))
        <noscript>
            <iframe src="https://www.googletagmanager.com/ns.html?id={{ config('marketing.gtm_id') }}"
                    height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe>
        </noscript>
    @endif

    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:bg-ink focus:px-3 focus:py-2 focus:text-paper">
        Skip to content
    </a>

    <header class="sticky top-0 z-40 border-b border-mist/80 bg-paper/80 backdrop-blur-md">
        <div class="mkt-shell flex items-center justify-between gap-4 py-3 sm:py-3.5">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 text-ink" aria-label="Authzio home">
                <img src="{{ asset('images/logo.svg') }}" alt="Authzio" class="size-7" width="40" height="40">
                <span class="font-display text-lg font-bold tracking-tight">Authzio</span>
            </a>

            <nav class="hidden items-center gap-8 text-[0.9rem] text-ink-soft/70 md:flex" aria-label="Primary">
                <a href="{{ route('home') }}#features" class="transition-colors hover:text-ink">Features</a>
                <a href="{{ route('demo') }}" class="transition-colors hover:text-ink">Demo</a>
                <a href="{{ route('home') }}#deploy" class="transition-colors hover:text-ink">Deploy</a>
                <a href="{{ route('docs') }}" class="transition-colors hover:text-ink">Docs</a>
                <a href="{{ route('pricing') }}" class="transition-colors hover:text-ink">Pricing</a>
            </nav>

            <div class="flex items-center gap-2 sm:gap-3">
                <x-github-link class="hidden text-ink-soft/55 transition-colors hover:text-ink sm:inline-flex" icon-class="size-5" />
                <a href="{{ route('console') }}" class="hidden text-sm text-ink-soft/70 transition-colors hover:text-ink sm:inline">
                    Sign in
                </a>
                <a href="{{ route('console') }}" class="inline-flex items-center bg-ink px-3.5 py-2 text-sm font-semibold text-paper transition-colors hover:bg-ink-soft">
                    Get started
                </a>
                <button
                    type="button"
                    id="mobile-nav-toggle"
                    class="inline-flex size-11 items-center justify-center text-ink md:hidden"
                    aria-expanded="false"
                    aria-controls="mobile-nav"
                    aria-label="Open menu"
                >
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>
                </button>
            </div>
        </div>

        <div id="mobile-nav" class="hidden border-t border-mist bg-paper md:hidden" hidden>
            <nav class="mkt-shell flex flex-col gap-0.5 py-3" aria-label="Mobile">
                <a href="{{ route('home') }}#features" class="mobile-nav-link px-2 py-3 text-base text-ink-soft/80 hover:text-ink">Features</a>
                <a href="{{ route('demo') }}" class="mobile-nav-link px-2 py-3 text-base text-ink-soft/80 hover:text-ink">Demo</a>
                <a href="{{ route('home') }}#deploy" class="mobile-nav-link px-2 py-3 text-base text-ink-soft/80 hover:text-ink">Deploy</a>
                <a href="{{ route('docs') }}" class="mobile-nav-link px-2 py-3 text-base text-ink-soft/80 hover:text-ink">Docs</a>
                <a href="{{ route('pricing') }}" class="mobile-nav-link px-2 py-3 text-base text-ink-soft/80 hover:text-ink">Pricing</a>
                <a href="{{ route('console') }}" class="mobile-nav-link px-2 py-3 text-base text-ink-soft/80 hover:text-ink">Sign in</a>
                <div class="mt-2 flex items-center gap-4 border-t border-mist px-2 pt-4">
                    <x-github-link icon-class="size-5" />
                    <a href="https://github.com/sponsors/azodik" class="text-sm text-ink-soft/70 hover:text-ink" rel="noopener">Sponsor</a>
                </div>
            </nav>
        </div>
    </header>

    <div
        id="mobile-nav-backdrop"
        class="fixed inset-0 z-30 hidden bg-ink/30 md:hidden"
        hidden
        aria-hidden="true"
    ></div>

    <main id="main" class="flex-1">
        @yield('content')
    </main>

    <footer class="border-t border-mist bg-paper-elevated">
        <div class="mkt-shell grid gap-12 py-14 md:grid-cols-[1.6fr_1fr_1fr] md:gap-12 lg:py-16">
            <div>
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5 text-ink">
                    <img src="{{ asset('images/logo-mark.svg') }}" alt="Authzio" class="size-8" width="40" height="40">
                    <span class="font-display text-lg font-bold tracking-tight">Authzio</span>
                </a>
                <p class="mt-5 max-w-sm text-sm leading-relaxed text-ink-soft/65">
                    Open-source identity for teams that need OAuth&nbsp;2.1 and OpenID Connect without giving up the stack.
                </p>
            </div>

            <div>
                <p class="text-sm font-semibold text-ink">Product</p>
                <ul class="mt-4 space-y-2.5 text-sm text-ink-soft/70">
                    <li><a href="{{ route('home') }}#features" class="transition-colors hover:text-ink">Features</a></li>
                    <li><a href="{{ route('demo') }}" class="transition-colors hover:text-ink">Demo</a></li>
                    <li><a href="{{ route('pricing') }}" class="transition-colors hover:text-ink">Pricing</a></li>
                    <li><a href="{{ route('console') }}" class="transition-colors hover:text-ink">Cloud console</a></li>
                </ul>
            </div>

            <div>
                <p class="text-sm font-semibold text-ink">Developers</p>
                <ul class="mt-4 space-y-2.5 text-sm text-ink-soft/70">
                    <li><a href="{{ route('docs') }}" class="transition-colors hover:text-ink">Documentation</a></li>
                    <li><a href="{{ route('home') }}#deploy" class="transition-colors hover:text-ink">Self-hosting</a></li>
                    <li><a href="{{ route('sitemap') }}" class="transition-colors hover:text-ink">Sitemap</a></li>
                    <li><a href="https://github.com/azodik/authzio/issues/new/choose" class="transition-colors hover:text-ink" rel="noopener">Raise an issue</a></li>
                    <li><a href="https://github.com/sponsors/azodik" class="transition-colors hover:text-ink" rel="noopener">Sponsor</a></li>
                </ul>
            </div>
        </div>

        <div class="border-t border-mist">
            <div class="mkt-shell flex flex-col gap-4 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
                    <p class="text-xs text-ink-soft/50">
                        &copy; {{ date('Y') }}
                        <a
                            href="{{ config('marketing.organization_url') }}"
                            class="transition-colors hover:text-ink"
                            rel="noopener noreferrer"
                            target="_blank"
                        >Azodik Consulting Private Limited</a>.
                        MIT licensed.
                    </p>
                    <nav class="flex items-center gap-3 text-xs text-ink-soft/55" aria-label="Legal">
                        <a href="{{ route('privacy') }}" class="transition-colors hover:text-ink">Privacy</a>
                        <span class="text-ink-soft/30" aria-hidden="true">·</span>
                        <a href="{{ route('terms') }}" class="transition-colors hover:text-ink">Terms</a>
                        <span class="text-ink-soft/30" aria-hidden="true">·</span>
                        <a href="{{ route('cookies') }}" class="transition-colors hover:text-ink">Cookies</a>
                    </nav>
                </div>
                <div class="flex items-center gap-1" aria-label="Social">
                    <a
                        href="{{ config('marketing.github') }}"
                        class="mkt-social"
                        aria-label="Authzio on GitHub"
                        rel="noopener noreferrer"
                        target="_blank"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5" aria-hidden="true">
                            <path d="M12 0C5.37 0 0 5.37 0 12c0 5.3 3.44 9.8 8.21 11.39.6.11.82-.26.82-.58 0-.28-.01-1.03-.02-2.02-3.34.73-4.04-1.61-4.04-1.61-.55-1.39-1.33-1.76-1.33-1.76-1.09-.74.08-.73.08-.73 1.2.09 1.84 1.24 1.84 1.24 1.07 1.83 2.8 1.3 3.49.99.11-.78.42-1.3.76-1.6-2.67-.3-5.47-1.33-5.47-5.93 0-1.31.47-2.38 1.24-3.22-.12-.3-.54-1.52.12-3.17 0 0 1.01-.32 3.3 1.23a11.5 11.5 0 0 1 6 0c2.29-1.55 3.3-1.23 3.3-1.23.66 1.65.24 2.87.12 3.17.77.84 1.24 1.91 1.24 3.22 0 4.61-2.81 5.62-5.49 5.92.43.37.81 1.1.81 2.22 0 1.6-.01 2.89-.01 3.28 0 .32.22.7.83.58C20.56 21.8 24 17.3 24 12 24 5.37 18.63 0 12 0Z"/>
                        </svg>
                    </a>
                    <a
                        href="{{ config('marketing.instagram') }}"
                        class="mkt-social"
                        aria-label="Azodik on Instagram"
                        rel="noopener noreferrer"
                        target="_blank"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5" aria-hidden="true">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069ZM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0Zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324ZM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881Z"/>
                        </svg>
                    </a>
                    <a
                        href="{{ config('marketing.linkedin') }}"
                        class="mkt-social"
                        aria-label="Azodik on LinkedIn"
                        rel="noopener noreferrer"
                        target="_blank"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5" aria-hidden="true">
                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286ZM5.337 7.433a2.062 2.062 0 1 1-.004-4.125 2.062 2.062 0 0 1 .004 4.125ZM7.119 20.452H3.555V9h3.564v11.452ZM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003Z"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
