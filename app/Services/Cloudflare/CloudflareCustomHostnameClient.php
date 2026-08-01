<?php

namespace App\Services\Cloudflare;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudflareCustomHostnameClient
{
    public function enabled(): bool
    {
        if (! (bool) config('authzio.cloudflare.enabled', false)) {
            return false;
        }

        $token = config('authzio.cloudflare.api_token');
        $zoneId = config('authzio.cloudflare.zone_id');

        return is_string($token) && $token !== '' && is_string($zoneId) && $zoneId !== '';
    }

    /**
     * @return array{
     *     id: string,
     *     hostname: string,
     *     status: string,
     *     ssl_status: string|null,
     *     ownership_verification: array{type: string, name: string, value: string}|null,
     *     ssl_validation_records: list<array{type: string, name: string, value: string}>,
     *     verification_errors: list<string>
     * }
     */
    public function create(string $hostname): array
    {
        $sslMethod = (string) config('authzio.cloudflare.ssl_method', 'txt');
        if (! in_array($sslMethod, ['txt', 'http', 'email'], true)) {
            $sslMethod = 'txt';
        }

        $response = $this->http()->post($this->baseUrl(), [
            'hostname' => $hostname,
            'ssl' => [
                'method' => $sslMethod,
                'type' => 'dv',
                'settings' => [
                    'min_tls_version' => '1.2',
                ],
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Cloudflare custom hostname create failed: '.$this->errorMessage($response->json()),
            );
        }

        return $this->normalize($response->json('result'));
    }

    /**
     * @return array{
     *     id: string,
     *     hostname: string,
     *     status: string,
     *     ssl_status: string|null,
     *     ownership_verification: array{type: string, name: string, value: string}|null,
     *     ssl_validation_records: list<array{type: string, name: string, value: string}>,
     *     verification_errors: list<string>
     * }
     */
    public function get(string $customHostnameId): array
    {
        $response = $this->http()->get($this->baseUrl().'/'.$customHostnameId);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Cloudflare custom hostname fetch failed: '.$this->errorMessage($response->json()),
            );
        }

        return $this->normalize($response->json('result'));
    }

    public function delete(string $customHostnameId): void
    {
        $response = $this->http()->delete($this->baseUrl().'/'.$customHostnameId);

        if ($response->status() === 404) {
            return;
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'Cloudflare custom hostname delete failed: '.$this->errorMessage($response->json()),
            );
        }
    }

    private function http(): PendingRequest
    {
        $token = (string) config('authzio.cloudflare.api_token');

        return Http::baseUrl('https://api.cloudflare.com/client/v4')
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    private function baseUrl(): string
    {
        $zoneId = (string) config('authzio.cloudflare.zone_id');

        return "/zones/{$zoneId}/custom_hostnames";
    }

    /**
     * @return array{
     *     id: string,
     *     hostname: string,
     *     status: string,
     *     ssl_status: string|null,
     *     ownership_verification: array{type: string, name: string, value: string}|null,
     *     ssl_validation_records: list<array{type: string, name: string, value: string}>,
     *     verification_errors: list<string>
     * }
     */
    private function normalize(mixed $result): array
    {
        if (! is_array($result)) {
            throw new RuntimeException('Cloudflare returned an empty custom hostname payload.');
        }

        $id = $result['id'] ?? null;
        $hostname = $result['hostname'] ?? null;
        $status = $result['status'] ?? null;

        if (! is_string($id) || ! is_string($hostname) || ! is_string($status)) {
            throw new RuntimeException('Cloudflare custom hostname payload is missing required fields.');
        }

        $ownership = null;
        $ownershipRaw = $result['ownership_verification'] ?? null;
        if (is_array($ownershipRaw)
            && isset($ownershipRaw['type'], $ownershipRaw['name'], $ownershipRaw['value'])
            && is_string($ownershipRaw['type'])
            && is_string($ownershipRaw['name'])
            && is_string($ownershipRaw['value'])
        ) {
            $ownership = [
                'type' => strtoupper($ownershipRaw['type']),
                'name' => $ownershipRaw['name'],
                'value' => $ownershipRaw['value'],
            ];
        }

        $sslRecords = [];
        $ssl = $result['ssl'] ?? null;
        $sslStatus = null;
        if (is_array($ssl)) {
            $sslStatus = isset($ssl['status']) && is_string($ssl['status']) ? $ssl['status'] : null;
            $validationRecords = $ssl['validation_records'] ?? [];
            if (is_array($validationRecords)) {
                foreach ($validationRecords as $record) {
                    if (! is_array($record)) {
                        continue;
                    }
                    $txtName = $record['txt_name'] ?? $record['name'] ?? null;
                    $txtValue = $record['txt_value'] ?? $record['value'] ?? null;
                    if (is_string($txtName) && is_string($txtValue) && $txtName !== '' && $txtValue !== '') {
                        $sslRecords[] = [
                            'type' => 'TXT',
                            'name' => $txtName,
                            'value' => $txtValue,
                        ];
                    }
                }
            }
        }

        $errors = [];
        $verificationErrors = $result['verification_errors'] ?? [];
        if (is_array($verificationErrors)) {
            foreach ($verificationErrors as $error) {
                if (is_string($error) && $error !== '') {
                    $errors[] = $error;
                }
            }
        }

        return [
            'id' => $id,
            'hostname' => $hostname,
            'status' => $status,
            'ssl_status' => $sslStatus,
            'ownership_verification' => $ownership,
            'ssl_validation_records' => $sslRecords,
            'verification_errors' => $errors,
        ];
    }

    private function errorMessage(mixed $json): string
    {
        if (! is_array($json)) {
            return 'unknown error';
        }

        $errors = $json['errors'] ?? null;
        if (is_array($errors) && isset($errors[0]) && is_array($errors[0])) {
            $message = $errors[0]['message'] ?? null;
            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        return 'HTTP error';
    }
}
