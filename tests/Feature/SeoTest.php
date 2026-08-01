<?php

namespace Tests\Feature;

use Tests\TestCase;

class SeoTest extends TestCase
{
    public function test_robots_txt_allows_marketing_and_points_to_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('Allow: /', false);
        $response->assertSee('Disallow: /console', false);
        $response->assertSee('Sitemap:', false);
        $response->assertSee('/sitemap.xml', false);
        $response->assertSee('LLMs:', false);
        $response->assertSee('/llms.txt', false);
    }

    public function test_sitemap_lists_marketing_and_docs_urls(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<urlset', false);
        $response->assertSee('/pricing', false);
        $response->assertSee('/demo', false);
        $response->assertSee('/docs', false);
        $response->assertSee('/docs/installation', false);
        $response->assertSee('/docs/oauth-oidc', false);
        $response->assertSee('/privacy', false);
        $response->assertSee('/terms', false);
        $response->assertSee('/cookies', false);
        $this->assertStringNotContainsString('<loc>http://localhost/console</loc>', $response->getContent());
    }

    public function test_legal_pages_are_public(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('Privacy Policy', false);
        $this->get('/terms')->assertOk()->assertSee('Terms of Service', false);
        $this->get('/cookies')->assertOk()->assertSee('Cookie Policy', false);
    }

    public function test_llms_txt_describes_product_for_ai_crawlers(): void
    {
        $response = $this->get('/llms.txt');

        $response->assertOk();
        $response->assertSee('Authzio', false);
        $response->assertSee('OAuth 2.1', false);
        $response->assertSee('/docs', false);
        $response->assertSee('Primary keywords', false);
    }

    public function test_home_includes_structured_data_and_indexable_meta(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('application/ld+json', false);
        $response->assertSee('SoftwareApplication', false);
        $response->assertSee('FAQPage', false);
        $response->assertSee('index, follow', false);
        $response->assertSee('rel="canonical"', false);
    }
}
