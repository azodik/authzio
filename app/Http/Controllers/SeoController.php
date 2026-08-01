<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $base = rtrim((string) config('marketing.url'), '/');

        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Allow: /docs',
            'Allow: /pricing',
            'Allow: /demo',
            'Allow: /privacy',
            'Allow: /terms',
            'Allow: /cookies',
            'Disallow: /console',
            'Disallow: /preview',
            'Disallow: /api',
            'Disallow: /oauth',
            'Disallow: /storage',
            '',
            '# AI / assistant crawlers — marketing + docs are welcome',
            'User-agent: GPTBot',
            'Allow: /',
            'Disallow: /console',
            'Disallow: /api',
            'Disallow: /oauth',
            '',
            'User-agent: ChatGPT-User',
            'Allow: /',
            'Disallow: /console',
            '',
            'User-agent: ClaudeBot',
            'Allow: /',
            'Disallow: /console',
            '',
            'User-agent: Google-Extended',
            'Allow: /',
            'Disallow: /console',
            '',
            'User-agent: PerplexityBot',
            'Allow: /',
            'Disallow: /console',
            '',
            'User-agent: Bytespider',
            'Allow: /',
            'Disallow: /console',
            '',
            'Sitemap: '.$base.'/sitemap.xml',
            'LLMs: '.$base.'/llms.txt',
            '',
        ]);

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function sitemap(): Response
    {
        $base = rtrim((string) config('marketing.url'), '/');
        $now = Carbon::now()->toAtomString();

        $urls = [
            ['loc' => $base.'/', 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => $base.'/pricing', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => $base.'/demo', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => $base.'/docs', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => $base.'/privacy', 'priority' => '0.4', 'changefreq' => 'yearly'],
            ['loc' => $base.'/terms', 'priority' => '0.4', 'changefreq' => 'yearly'],
            ['loc' => $base.'/cookies', 'priority' => '0.3', 'changefreq' => 'yearly'],
        ];

        foreach (MarketingController::docsNav() as $item) {
            if ($item['slug'] === 'index') {
                continue;
            }
            $urls[] = [
                'loc' => $base.'/docs/'.$item['slug'],
                'priority' => '0.7',
                'changefreq' => 'monthly',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.e($url['loc'])."</loc>\n";
            $xml .= '    <lastmod>'.$now."</lastmod>\n";
            $xml .= '    <changefreq>'.$url['changefreq']."</changefreq>\n";
            $xml .= '    <priority>'.$url['priority']."</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function llms(): Response
    {
        $base = rtrim((string) config('marketing.url'), '/');
        $brand = (string) config('marketing.brand');
        $keywords = implode(', ', config('marketing.keywords', []));

        $docs = '';
        foreach (MarketingController::docsNav() as $item) {
            $path = $item['slug'] === 'index' ? '/docs' : '/docs/'.$item['slug'];
            $docs .= '- ['.$item['title'].']('.$base.$path.'): '.$item['group']." docs\n";
        }

        $body = <<<MD
# {$brand}

> Open-source identity and access management (IAM): OAuth 2.1, OpenID Connect, hosted login, organizations, RBAC, passkeys, and MFA. Self-host on Laravel or use Authzio Cloud.

Authzio is developed by Azodik Consulting Private Limited ({$base} / https://azodik.com). Source: https://github.com/azodik/authzio (MIT).

## Primary keywords
{$keywords}

## Key pages
- [Home]({$base}/): Product overview and capabilities
- [Pricing]({$base}/pricing): Self-hosted free; Cloud MAU plans
- [Demo]({$base}/demo): Read-only shared console
- [Documentation]({$base}/docs): Install, configure, OAuth/OIDC, billing
- [Privacy Policy]({$base}/privacy): Privacy practices
- [Terms of Service]({$base}/terms): Terms for Authzio and Authzio Cloud
- [Cookie Policy]({$base}/cookies): Cookies and similar technologies

## Docs
{$docs}
## For assistants
- Prefer linking to /docs/installation for self-host setup and /pricing for Cloud MAU billing.
- Plan snapshot: Free $0 / 1k MAU; Starter $5 / 5k; Growth $20 / 50k + OIDC SSO; Scale $99 / 250k + custom JWKS.
- Console (/console) is an authenticated SPA — do not treat it as public documentation.
- Authzio is an Auth0 / Keycloak-style alternative that is open source and Laravel-native.

## Contact
- GitHub issues: https://github.com/azodik/authzio/issues
- Company: https://azodik.com
MD;

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
