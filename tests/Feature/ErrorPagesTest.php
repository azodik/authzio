<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function marketing_unknown_path_renders_branded_404(): void
    {
        $this->get('/this-page-definitely-does-not-exist-authzio')
            ->assertNotFound()
            ->assertSee('Page not found', false)
            ->assertSee('Authzio', false)
            ->assertSee('Back to home', false);
    }

    #[Test]
    public function server_error_view_is_available(): void
    {
        $html = view('errors.500')->render();

        $this->assertStringContainsString('Something went wrong', $html);
        $this->assertStringContainsString('500', $html);
        $this->assertStringContainsString('Authzio', $html);
    }
}
