<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocaleCatalogTest extends TestCase
{
    #[Test]
    public function lang_path_points_at_resources_lang(): void
    {
        $this->assertSame(
            realpath(resource_path('lang')),
            realpath(app()->langPath()),
        );
        $this->assertFileExists(lang_path('en.json'));
        $this->assertFileExists(lang_path('de.json'));
        $this->assertFileExists(lang_path('es.json'));
        $this->assertFileExists(lang_path('fr.json'));
        $this->assertFileExists(lang_path('hi.json'));
    }

    #[Test]
    public function locale_endpoint_returns_catalog_messages(): void
    {
        $this->getJson('/api/v1/locales/en')
            ->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonStructure(['messages']);

        $payload = $this->getJson('/api/v1/locales/en')->json('messages');
        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload);
    }

    #[Test]
    public function unsupported_locale_is_rejected(): void
    {
        $this->getJson('/api/v1/locales/zz')->assertStatus(422);
    }
}
