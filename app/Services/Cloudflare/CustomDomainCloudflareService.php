<?php

namespace App\Services\Cloudflare;

use App\Models\OrganizationDomain;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CustomDomainCloudflareService
{
    public function __construct(
        private readonly CloudflareCustomHostnameClient $client,
    ) {}

    public function enabled(): bool
    {
        return $this->client->enabled();
    }

    public function cnameTarget(): string
    {
        return (string) config('authzio.domains.cname_target', 'customers.authzio.com');
    }

    /**
     * Provision Cloudflare Custom Hostname and persist DNS instructions.
     */
    public function provision(OrganizationDomain $domain): OrganizationDomain
    {
        try {
            $created = $this->client->create($domain->host);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'host' => [$exception->getMessage()],
            ]);
        }

        $domain->update($this->attributesFromCloudflare($domain->host, $created));

        return $domain->fresh() ?? $domain;
    }

    /**
     * Refresh status from Cloudflare; mark verified when hostname + SSL are active.
     */
    public function refreshAndVerify(OrganizationDomain $domain): OrganizationDomain
    {
        $hostnameId = $domain->cloudflare_hostname_id;
        if (! is_string($hostnameId) || $hostnameId === '') {
            throw ValidationException::withMessages([
                'host' => ['This domain is not linked to Cloudflare. Remove it and add it again.'],
            ]);
        }

        try {
            $remote = $this->client->get($hostnameId);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'host' => [$exception->getMessage()],
            ]);
        }

        $attributes = $this->attributesFromCloudflare($domain->host, $remote);
        $ready = $remote['status'] === 'active'
            && ($remote['ssl_status'] === null || $remote['ssl_status'] === 'active');

        if ($ready) {
            $attributes['verified_at'] = now();
        }

        $domain->update($attributes);
        $domain = $domain->fresh() ?? $domain;

        if (! $ready) {
            $parts = array_filter([
                'Cloudflare hostname status: '.$remote['status'],
                $remote['ssl_status'] !== null ? 'SSL status: '.$remote['ssl_status'] : null,
                ...$remote['verification_errors'],
            ]);

            throw ValidationException::withMessages([
                'host' => [
                    implode(' ', $parts)
                    .' Publish the DNS records shown for this domain (ownership TXT, SSL TXT if listed, and CNAME to '
                    .$this->cnameTarget()
                    .'), wait for DNS, then try Verify again.',
                ],
            ]);
        }

        return $domain;
    }

    public function deleteRemote(OrganizationDomain $domain): void
    {
        $hostnameId = $domain->cloudflare_hostname_id;
        if (! is_string($hostnameId) || $hostnameId === '') {
            return;
        }

        try {
            $this->client->delete($hostnameId);
        } catch (RuntimeException) {
            // Domain removal should still succeed if Cloudflare already deleted it.
        }
    }

    /**
     * @param  array{
     *     id: string,
     *     hostname: string,
     *     status: string,
     *     ssl_status: string|null,
     *     ownership_verification: array{type: string, name: string, value: string}|null,
     *     ssl_validation_records: list<array{type: string, name: string, value: string}>,
     *     verification_errors: list<string>
     * }  $remote
     * @return array<string, mixed>
     */
    private function attributesFromCloudflare(string $host, array $remote): array
    {
        $records = [
            [
                'purpose' => 'cname',
                'type' => 'CNAME',
                'name' => $host,
                'value' => $this->cnameTarget(),
                'caption' => 'Point traffic at Authzio (Cloudflare for SaaS)',
            ],
        ];

        if ($remote['ownership_verification'] !== null) {
            $records[] = [
                'purpose' => 'ownership',
                'type' => $remote['ownership_verification']['type'],
                'name' => $remote['ownership_verification']['name'],
                'value' => $remote['ownership_verification']['value'],
                'caption' => 'Cloudflare ownership verification',
            ];
        }

        foreach ($remote['ssl_validation_records'] as $sslRecord) {
            $records[] = [
                'purpose' => 'ssl',
                'type' => $sslRecord['type'],
                'name' => $sslRecord['name'],
                'value' => $sslRecord['value'],
                'caption' => 'TLS certificate validation (DCV)',
            ];
        }

        $ownershipValue = $remote['ownership_verification']['value'] ?? null;

        return [
            'cloudflare_hostname_id' => $remote['id'],
            'cloudflare_status' => $remote['status'],
            'cloudflare_ssl_status' => $remote['ssl_status'],
            'dns_records' => $records,
            'verification_token' => is_string($ownershipValue) ? $ownershipValue : null,
        ];
    }
}
