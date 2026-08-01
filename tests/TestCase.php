<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Env;
use ReflectionProperty;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CI (and fresh clones) may not have public/build; blade @vite must not 500.
        if ($this->app !== null) {
            $this->withoutVite();
        }
    }

    public function createApplication()
    {
        $this->ensureApplicationKey();

        return parent::createApplication();
    }

    /**
     * Ensure APP_KEY is set before the app boots.
     *
     * php artisan test can boot Laravel before PHPUnit applies phpunit.xml <env>.
     * An empty shell APP_KEY= also leaves $_SERVER['APP_KEY']="", and Laravel's Env
     * repository reads $_SERVER before $_ENV — so PHPUnit force on $_ENV alone is not enough.
     */
    private function ensureApplicationKey(): void
    {
        $key = 'base64:2fl+KtvkdphvQyE8m4YZqKzF2F8xQxN1yR3pL0wV8aE=';

        putenv('APP_KEY='.$key);
        $_ENV['APP_KEY'] = $key;
        $_SERVER['APP_KEY'] = $key;

        $repository = new ReflectionProperty(Env::class, 'repository');
        $repository->setValue(null, null);
    }
}
