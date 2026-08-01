<?php

namespace Tests\Unit;

use App\Services\DomainDnsVerifier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DomainDnsVerifierTest extends TestCase
{
    #[Test]
    public function it_matches_exact_txt_on_host(): void
    {
        $token = 'authzio-verify=abc123';

        $verifier = (new DomainDnsVerifier)->usingTxtLookup(
            fn (string $host): array => $host === 'auth.acme.com' ? [$token] : [],
        );

        $this->assertTrue($verifier->tokenPresent('auth.acme.com', $token));
    }

    #[Test]
    public function it_matches_txt_on_challenge_subdomain(): void
    {
        $token = 'authzio-verify=abc123';

        $verifier = (new DomainDnsVerifier)->usingTxtLookup(
            fn (string $host): array => $host === '_authzio-challenge.auth.acme.com'
                ? ['"'.$token.'"']
                : [],
        );

        $this->assertTrue($verifier->tokenPresent('auth.acme.com', $token));
    }

    #[Test]
    public function it_fails_when_token_missing(): void
    {
        config(['authzio.domains.dns_verify' => true]);

        $verifier = (new DomainDnsVerifier)->usingTxtLookup(
            fn (): array => ['unrelated-txt'],
        );

        $this->assertFalse($verifier->tokenPresent('auth.acme.com', 'authzio-verify=missing'));
    }

    #[Test]
    public function it_skips_lookup_when_dns_verify_disabled(): void
    {
        config(['authzio.domains.dns_verify' => false]);

        $called = false;
        $verifier = (new DomainDnsVerifier)->usingTxtLookup(function () use (&$called): array {
            $called = true;

            return [];
        });

        $this->assertTrue($verifier->tokenPresent('auth.acme.com', 'authzio-verify=x'));
        $this->assertFalse($called);
    }
}
