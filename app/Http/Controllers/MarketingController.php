<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MarketingController extends Controller
{
    /**
     * @return list<array{slug: string, title: string, group: string}>
     */
    public static function docsNav(): array
    {
        return [
            ['slug' => 'index', 'title' => 'Introduction', 'group' => 'Start'],
            ['slug' => 'installation', 'title' => 'Installation', 'group' => 'Start'],
            ['slug' => 'configuration', 'title' => 'Configuration', 'group' => 'Start'],
            ['slug' => 'docker', 'title' => 'Docker', 'group' => 'Start'],
            ['slug' => 'console', 'title' => 'Console', 'group' => 'Product'],
            ['slug' => 'authentication', 'title' => 'User authentication', 'group' => 'Product'],
            ['slug' => 'oauth-oidc', 'title' => 'OAuth & OIDC', 'group' => 'Product'],
            ['slug' => 'organizations', 'title' => 'Organizations', 'group' => 'Product'],
            ['slug' => 'billing', 'title' => 'Billing & MAU', 'group' => 'Product'],
            ['slug' => 'faq', 'title' => 'FAQ', 'group' => 'Community'],
            ['slug' => 'support', 'title' => 'Issues & support', 'group' => 'Community'],
        ];
    }

    /**
     * SEO copy per docs page (title segment + meta description).
     *
     * @return array<string, array{title: string, description: string}>
     */
    public static function docsSeo(): array
    {
        return [
            'index' => [
                'title' => 'Authzio documentation — open-source identity provider',
                'description' => 'Learn Authzio: self-hosted OAuth 2.1 and OpenID Connect, organizations, RBAC, passkeys, MFA, and Authzio Cloud MAU billing.',
            ],
            'installation' => [
                'title' => 'Install Authzio — self-hosted identity provider',
                'description' => 'Install Authzio on Laravel with Composer or Docker. Requirements, first boot, and how to open the console.',
            ],
            'configuration' => [
                'title' => 'Configure Authzio — env, domains, mail',
                'description' => 'Configure Authzio environment variables, domain root, mailers, and production settings for OIDC and hosted login.',
            ],
            'docker' => [
                'title' => 'Run Authzio with Docker',
                'description' => 'Deploy Authzio with Docker Compose: PostgreSQL, Redis, workers, and production hardening tips.',
            ],
            'console' => [
                'title' => 'Authzio console — orgs, apps, and access',
                'description' => 'Use the Authzio console to manage organizations, OAuth applications, members, invitations, roles, and OIDC settings.',
            ],
            'authentication' => [
                'title' => 'User authentication — email, passkeys, MFA',
                'description' => 'Configure email OTP, passwords, passkeys, social login, and OIDC enterprise SSO in Authzio.',
            ],
            'oauth-oidc' => [
                'title' => 'OAuth 2.1 & OpenID Connect with Authzio',
                'description' => 'Set up OAuth 2.1 and OpenID Connect clients, redirect URIs, scopes, and discovery endpoints in Authzio.',
            ],
            'organizations' => [
                'title' => 'Organizations & RBAC in Authzio',
                'description' => 'Model multi-tenant organizations, member invitations (resend, revoke, history), roles, and permissions with Authzio RBAC.',
            ],
            'billing' => [
                'title' => 'Authzio Cloud billing & MAU',
                'description' => 'Authzio Cloud MAU plans from Free through Scale, upgrades, Free at period end, invoices, and alerts.',
            ],
            'faq' => [
                'title' => 'Authzio FAQ — billing, MAU, SSO, invitations, self-hosting',
                'description' => 'Answers about Authzio Cloud plans, MAU limits, team invitations, enterprise SSO, legal pages, and free self-hosting.',
            ],
            'support' => [
                'title' => 'Authzio support & GitHub issues',
                'description' => 'How to report bugs, ask questions, and contact Azodik for security-sensitive Authzio findings.',
            ],
        ];
    }

    public function home(): View
    {
        $base = rtrim((string) config('marketing.url'), '/');

        return view('marketing.home', [
            'softwareSchema' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Organization',
                        '@id' => $base.'/#organization',
                        'name' => config('marketing.organization'),
                        'url' => config('marketing.organization_url'),
                        'logo' => [
                            '@type' => 'ImageObject',
                            'url' => $base.'/images/logo-mark.png',
                        ],
                        'sameAs' => array_values(array_filter([
                            config('marketing.github'),
                            config('marketing.organization_url'),
                            config('marketing.linkedin'),
                            config('marketing.instagram'),
                        ])),
                    ],
                    [
                        '@type' => 'WebSite',
                        '@id' => $base.'/#website',
                        'url' => $base.'/',
                        'name' => config('marketing.brand'),
                        'description' => config('marketing.tagline'),
                        'publisher' => ['@id' => $base.'/#organization'],
                        'inLanguage' => 'en-US',
                    ],
                    [
                        '@type' => 'SoftwareApplication',
                        '@id' => $base.'/#software',
                        'name' => 'Authzio',
                        'applicationCategory' => 'SecurityApplication',
                        'applicationSubCategory' => 'Identity and Access Management',
                        'operatingSystem' => 'Any',
                        'description' => 'Open-source identity and access management focused on people and the apps they use — OAuth 2.1, OpenID Connect, organizations, passkeys, MFA, and RBAC. Self-host or use Authzio Cloud.',
                        'url' => $base.'/',
                        'downloadUrl' => config('marketing.github'),
                        'offers' => [
                            '@type' => 'Offer',
                            'price' => '0',
                            'priceCurrency' => 'USD',
                            'description' => 'Free to self-host. Authzio Cloud billed by MAU.',
                            'url' => $base.'/pricing',
                        ],
                        'creator' => ['@id' => $base.'/#organization'],
                        'codeRepository' => config('marketing.github'),
                        'license' => 'https://opensource.org/licenses/MIT',
                        'image' => $base.config('marketing.og_image'),
                        'screenshot' => $base.'/images/demo/console-tour-light.gif',
                        'featureList' => [
                            'OAuth 2.1 and OpenID Connect',
                            'Hosted login and email authentication',
                            'Passkeys and MFA',
                            'Organizations and RBAC',
                            'Self-host or managed Authzio Cloud',
                        ],
                        'keywords' => implode(', ', config('marketing.keywords', [])),
                    ],
                    [
                        '@type' => 'FAQPage',
                        '@id' => $base.'/#faq',
                        'mainEntity' => [
                            [
                                '@type' => 'Question',
                                'name' => 'Is self-hosting free?',
                                'acceptedAnswer' => [
                                    '@type' => 'Answer',
                                    'text' => 'Yes. Self-hosting is free forever under MIT. Set AUTHZIO_BILLING_ENABLED=false when you run your own infrastructure. Authzio Cloud is optional and billed by monthly active users (MAU).',
                                ],
                            ],
                            [
                                '@type' => 'Question',
                                'name' => 'Does Authzio support OAuth 2.1 and OpenID Connect?',
                                'acceptedAnswer' => [
                                    '@type' => 'Answer',
                                    'text' => 'Yes. Authzio provides OAuth 2.1 and OpenID Connect for applications, including discovery endpoints, clients, PKCE, and hosted login.',
                                ],
                            ],
                            [
                                '@type' => 'Question',
                                'name' => 'Is Authzio an Auth0 or Keycloak alternative?',
                                'acceptedAnswer' => [
                                    '@type' => 'Answer',
                                    'text' => 'Authzio is an open-source identity provider you can self-host or run as managed Authzio Cloud — built for teams that want OIDC and access control without a proprietary black box.',
                                ],
                            ],
                            [
                                '@type' => 'Question',
                                'name' => 'What is an MAU on Authzio Cloud?',
                                'acceptedAnswer' => [
                                    '@type' => 'Answer',
                                    'text' => 'A monthly active user is a distinct person with a qualifying auth event in the calendar month (console login, end-user authenticate, or token issued), deduped per user per day.',
                                ],
                            ],
                            [
                                '@type' => 'Question',
                                'name' => 'Will I know before I hit my MAU limit?',
                                'acceptedAnswer' => [
                                    '@type' => 'Answer',
                                    'text' => 'Yes. Owners and admins get emails at 80%, 90%, and 100% of the MAU limit — once each per calendar month by default. Application and platform email quotas use the same thresholds.',
                                ],
                            ],
                            [
                                '@type' => 'Question',
                                'name' => 'Can I try Authzio before installing?',
                                'acceptedAnswer' => [
                                    '@type' => 'Answer',
                                    'text' => 'Yes. Use the shared read-only demo console at '.$base.'/demo to explore organizations, applications, and OIDC settings — no install required.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function pricing(): View
    {
        $base = rtrim((string) config('marketing.url'), '/');

        return view('marketing.pricing', [
            'productSchema' => [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => 'Authzio Cloud',
                'description' => 'Managed Authzio identity platform billed monthly by MAU. Self-host remains free.',
                'brand' => [
                    '@type' => 'Brand',
                    'name' => 'Authzio',
                ],
                'url' => $base.'/pricing',
                'offers' => [
                    [
                        '@type' => 'Offer',
                        'name' => 'Self-hosted',
                        'price' => '0',
                        'priceCurrency' => 'USD',
                        'description' => 'Run Authzio on your infrastructure at no cost.',
                    ],
                    [
                        '@type' => 'Offer',
                        'name' => 'Authzio Cloud Free',
                        'price' => '0',
                        'priceCurrency' => 'USD',
                        'description' => '1,000 MAU · 1 application · managed JWKS.',
                    ],
                    [
                        '@type' => 'Offer',
                        'name' => 'Authzio Cloud Starter',
                        'price' => '5',
                        'priceCurrency' => 'USD',
                        'description' => '5,000 MAU · 5 apps · custom domains · BYO email.',
                    ],
                    [
                        '@type' => 'Offer',
                        'name' => 'Authzio Cloud Growth',
                        'price' => '20',
                        'priceCurrency' => 'USD',
                        'description' => '50,000 MAU · unlimited apps · OIDC enterprise SSO.',
                    ],
                    [
                        '@type' => 'Offer',
                        'name' => 'Authzio Cloud Scale',
                        'price' => '99',
                        'priceCurrency' => 'USD',
                        'description' => '250,000 MAU · custom JWKS · onboarding / SLA options.',
                    ],
                ],
            ],
        ]);
    }

    public function demo(): View
    {
        return view('marketing.demo', [
            'demoEmail' => 'demo@authzio.com',
            'demoPassword' => 'AuthzioDemo2026!',
        ]);
    }

    /**
     * @return list<array{slug: string, title: string, route: string}>
     */
    public static function legalNav(): array
    {
        return [
            ['slug' => 'privacy', 'title' => 'Privacy Policy', 'route' => 'privacy'],
            ['slug' => 'terms', 'title' => 'Terms of Service', 'route' => 'terms'],
            ['slug' => 'cookies', 'title' => 'Cookie Policy', 'route' => 'cookies'],
        ];
    }

    /**
     * @return array<string, array{title: string, meta_title: string, description: string}>
     */
    public static function legalSeo(): array
    {
        return [
            'privacy' => [
                'title' => 'Privacy Policy',
                'meta_title' => 'Privacy Policy — Authzio',
                'description' => 'How Azodik Consulting Private Limited collects and uses information for Authzio and Authzio Cloud.',
            ],
            'terms' => [
                'title' => 'Terms of Service',
                'meta_title' => 'Terms of Service — Authzio',
                'description' => 'Terms for using Authzio websites, open-source software, and Authzio Cloud.',
            ],
            'cookies' => [
                'title' => 'Cookie Policy',
                'meta_title' => 'Cookie Policy — Authzio',
                'description' => 'How Authzio uses cookies and similar technologies on marketing pages and Authzio Cloud.',
            ],
        ];
    }

    public function privacy(): View
    {
        return $this->legal('privacy');
    }

    public function terms(): View
    {
        return $this->legal('terms');
    }

    public function cookies(): View
    {
        return $this->legal('cookies');
    }

    private function legal(string $slug): View
    {
        $seo = self::legalSeo()[$slug] ?? null;
        if ($seo === null) {
            throw new NotFoundHttpException;
        }

        return view('marketing.legal.'.$slug, [
            'legalNav' => self::legalNav(),
            'legalSlug' => $slug,
            'legalTitle' => $seo['title'],
            'legalMetaTitle' => $seo['meta_title'],
            'legalMetaDescription' => $seo['description'],
            'legalCanonical' => route($slug),
            'legalUpdated' => 'July 29, 2026',
        ]);
    }

    public function docs(?string $page = null): View
    {
        $slug = $page === null || $page === '' ? 'index' : $page;
        $nav = self::docsNav();
        $allowed = array_column($nav, 'slug');

        if (! in_array($slug, $allowed, true)) {
            throw new NotFoundHttpException;
        }

        $view = $slug === 'index' ? 'marketing.docs.index' : 'marketing.docs.'.$slug;

        if (! view()->exists($view)) {
            throw new NotFoundHttpException;
        }

        $current = collect($nav)->firstWhere('slug', $slug);
        $seo = self::docsSeo()[$slug] ?? [
            'title' => ($current['title'] ?? 'Documentation').' — Authzio Docs',
            'description' => 'Authzio documentation for open-source identity, OAuth 2.1, and OpenID Connect.',
        ];

        $canonical = $slug === 'index'
            ? route('docs')
            : route('docs', ['page' => $slug]);

        $base = rtrim((string) config('marketing.url'), '/');
        $breadcrumb = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => $base.'/',
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Docs',
                    'item' => $base.'/docs',
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $current['title'] ?? 'Documentation',
                    'item' => $canonical,
                ],
            ],
        ];

        return view($view, [
            'docsNav' => $nav,
            'docsSlug' => $slug,
            'docsTitle' => $current['title'] ?? 'Documentation',
            'docsMetaTitle' => $seo['title'],
            'docsMetaDescription' => $seo['description'],
            'docsCanonical' => $canonical,
            'docsBreadcrumbSchema' => $breadcrumb,
        ]);
    }
}
