<?php

namespace App\Services;

use Closure;

class DomainDnsVerifier
{
    /**
     * @var (Closure(string): list<string>)|null
     */
    private ?Closure $txtLookup = null;

    /**
     * @param  Closure(string): list<string>  $txtLookup
     */
    public function usingTxtLookup(Closure $txtLookup): self
    {
        $clone = clone $this;
        $clone->txtLookup = $txtLookup;

        return $clone;
    }

    public function enabled(): bool
    {
        return (bool) config('authzio.domains.dns_verify', true);
    }

    /**
     * Confirm the verification token appears in DNS TXT for the host
     * (or the _authzio-challenge.<host> helper name).
     */
    public function tokenPresent(string $host, string $token): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        $token = trim($token);
        if ($token === '') {
            return false;
        }

        foreach ($this->lookupNames($host) as $name) {
            foreach ($this->txtRecords($name) as $txt) {
                if ($this->recordMatchesToken($txt, $token)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function lookupNames(string $host): array
    {
        $host = strtolower(trim($host));

        return array_values(array_unique([
            $host,
            '_authzio-challenge.'.$host,
        ]));
    }

    protected function recordMatchesToken(string $txt, string $token): bool
    {
        $normalized = trim($txt);
        $normalized = trim($normalized, '"');

        return hash_equals($token, $normalized) || str_contains($normalized, $token);
    }

    /**
     * @return list<string>
     */
    protected function txtRecords(string $host): array
    {
        if ($this->txtLookup !== null) {
            return ($this->txtLookup)($host);
        }

        if (! function_exists('dns_get_record')) {
            return [];
        }

        $records = @dns_get_record($host, DNS_TXT);
        if ($records === false) {
            return [];
        }

        $values = [];

        foreach ($records as $record) {
            if (isset($record['txt']) && is_string($record['txt'])) {
                $values[] = $record['txt'];

                continue;
            }

            if (isset($record['entries']) && is_array($record['entries'])) {
                $parts = array_filter($record['entries'], is_string(...));
                if ($parts !== []) {
                    $values[] = implode('', $parts);
                }
            }
        }

        return $values;
    }
}
