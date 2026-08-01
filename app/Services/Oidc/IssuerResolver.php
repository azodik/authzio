<?php

namespace App\Services\Oidc;

use App\Models\Organization;
use App\Models\OrganizationDomain;
use Illuminate\Http\Request;

class IssuerResolver
{
    public function resolveOrganization(Request $request): Organization
    {
        $host = strtolower($request->getHost());

        $domain = OrganizationDomain::query()
            ->whereRaw('LOWER(host) = ?', [$host])
            ->whereNotNull('verified_at')
            ->first();

        if ($domain !== null) {
            return $domain->organization;
        }

        $root = strtolower((string) config('authzio.domains.root', 'authzio.test'));

        if (str_ends_with($host, '.'.$root)) {
            $subdomain = substr($host, 0, -strlen('.'.$root));

            $organization = Organization::query()->where('subdomain', $subdomain)->first();
            if ($organization !== null) {
                return $organization;
            }
        }

        // Local / single-host fallback: first org, or explicit header for tooling.
        $orgId = $request->header('X-Authzio-Organization');
        if (is_string($orgId) && $orgId !== '') {
            return Organization::query()->findOrFail($orgId);
        }

        $fallback = Organization::query()->orderBy('created_at')->first();
        abort_if($fallback === null, 404, 'No organization found for this host. Configure a subdomain or custom domain.');

        return $fallback;
    }

    public function issuerUrl(Organization $organization): string
    {
        $host = $organization->primary_domain;

        if ($host === null || $host === '') {
            $subdomain = $organization->subdomain ?: $organization->slug;
            $host = $subdomain.'.'.config('authzio.domains.root', 'authzio.test');
        }

        $scheme = app()->environment('local') && ! request()->secure() ? 'http' : 'https';

        // Prefer request scheme when serving discovery on that host.
        if (request()->getHost() === $host) {
            $scheme = request()->getScheme();
        }

        return $scheme.'://'.$host;
    }

    /**
     * @return array{
     *     issuer: string,
     *     authorization_endpoint: string,
     *     token_endpoint: string,
     *     userinfo_endpoint: string,
     *     revocation_endpoint: string,
     *     introspection_endpoint: string,
     *     jwks_uri: string,
     *     end_session_endpoint?: string
     * }
     */
    public function endpoints(Organization $organization): array
    {
        $issuer = rtrim($this->issuerUrl($organization), '/');

        return [
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer.'/oauth/authorize',
            'token_endpoint' => $issuer.'/api/oauth/token',
            'userinfo_endpoint' => $issuer.'/api/oauth/userinfo',
            'revocation_endpoint' => $issuer.'/api/oauth/revoke',
            'introspection_endpoint' => $issuer.'/api/oauth/introspect',
            'jwks_uri' => $issuer.'/.well-known/jwks.json',
            'end_session_endpoint' => $issuer.'/oauth/logout',
        ];
    }
}
