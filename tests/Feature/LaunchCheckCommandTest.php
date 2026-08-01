<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesBillingFixtures;
use Tests\TestCase;

class LaunchCheckCommandTest extends TestCase
{
    use CreatesBillingFixtures;
    use RefreshDatabase;

    #[Test]
    public function launch_check_runs_in_testing_environment(): void
    {
        $this->seedBillingPlans();

        config([
            'billing.enabled' => true,
            'billing.dodo.api_key' => 'test_key',
            'billing.dodo.webhook_secret' => 'whsec_dGVzdF9zZWNyZXQ=',
            'authzio.mfa.enabled' => true,
        ]);

        $this->plan('starter')->update(['dodo_product_id' => 'pdt_starter']);
        $this->plan('growth')->update(['dodo_product_id' => 'pdt_growth']);
        $this->plan('scale')->update(['dodo_product_id' => 'pdt_scale']);

        $this->artisan('authzio:launch-check')
            ->expectsOutputToContain('Authzio launch checklist')
            ->assertSuccessful();
    }
}
