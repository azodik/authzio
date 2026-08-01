<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\DomainType;
use App\Http\Controllers\Concerns\EnsuresOrganizationMembership;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDomainRequest;
use App\Http\Requests\UpdateSubdomainRequest;
use App\Models\Organization;
use App\Models\OrganizationDomain;
use App\Services\AuditLogger;
use App\Services\Billing\PlanEntitlements;
use App\Services\Cloudflare\CustomDomainCloudflareService;
use App\Services\DomainDnsVerifier;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DomainController extends Controller
{
    use EnsuresOrganizationMembership;

    public function __construct(
        private readonly OrganizationService $organizationService,
        private readonly AuditLogger $auditLogger,
        private readonly DomainDnsVerifier $dnsVerifier,
        private readonly CustomDomainCloudflareService $cloudflareDomains,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organization = $this->resolveOrganization($request);
        $this->organizationService->syncSubdomainHost($organization);
        $organization->refresh();

        $root = (string) config('authzio.domains.root', 'authzio.test');
        $platformCname = is_string($organization->subdomain) && $organization->subdomain !== ''
            ? $organization->subdomain.'.'.$root
            : null;

        $customCnameTarget = $this->cloudflareDomains->enabled()
            ? $this->cloudflareDomains->cnameTarget()
            : $platformCname;

        return response()->json([
            'organization' => $organization->only(['id', 'name', 'slug', 'subdomain', 'primary_domain']),
            'root_domain' => $root,
            'cname_target' => $customCnameTarget,
            'platform_cname_target' => $platformCname,
            'cloudflare_saas' => $this->cloudflareDomains->enabled(),
            'app_url' => $organization->primary_domain
                ? 'https://'.$organization->primary_domain
                : null,
            'domains' => $organization->domains()->orderByDesc('is_primary')->orderBy('host')->get(),
            'entitlements' => app(PlanEntitlements::class)->forOrganization($organization),
        ]);
    }

    public function store(StoreDomainRequest $request): JsonResponse
    {
        $organization = $this->organizationForUser(
            $request,
            Organization::query()->findOrFail($request->validated('organization_id')),
        );

        app(PlanEntitlements::class)->assertCustomDomains($organization);

        $domain = $this->organizationService->addCustomDomain(
            $organization,
            $request->user(),
            $request->validated('host'),
        );

        return response()->json([
            'data' => $domain,
            'instructions' => [
                'dns' => $domain->dns_records ?? [],
                'steps' => $this->cloudflareDomains->enabled()
                    ? [
                        'Add the CNAME to '.$this->cloudflareDomains->cnameTarget().' (required for Cloudflare for SaaS).',
                        'Add the Cloudflare ownership TXT (and SSL TXT records if shown).',
                        'Wait for DNS, then click Verify. Authzio marks the domain verified when Cloudflare hostname and SSL are active.',
                    ]
                    : [
                        'Publish a TXT ownership record (apex or _authzio-challenge) with the verification token.',
                        'Point a CNAME at '.$this->cloudflareDomains->cnameTarget().' (or your Authzio host).',
                        'Wait for DNS, then click Verify.',
                    ],
            ],
        ], 201);
    }

    public function updateSubdomain(UpdateSubdomainRequest $request): JsonResponse
    {
        $organization = $this->organizationForUser(
            $request,
            Organization::query()->findOrFail($request->validated('organization_id')),
        );

        $domain = $this->organizationService->setSubdomain(
            $organization,
            $request->user(),
            $request->validated('subdomain'),
        );

        return response()->json([
            'data' => $domain,
            'organization' => $organization->fresh()?->only(['id', 'name', 'slug', 'subdomain', 'primary_domain']),
        ]);
    }

    public function destroy(Request $request, Organization $organization, OrganizationDomain $domain): JsonResponse
    {
        $this->organizationForUser($request, $organization);
        $this->assertDomainBelongsToOrganization($domain, $organization);

        if ($domain->type === DomainType::Subdomain) {
            abort(422, 'The Authzio subdomain cannot be deleted. Change it instead.');
        }

        if ($this->cloudflareDomains->enabled()) {
            $this->cloudflareDomains->deleteRemote($domain);
        }

        $domain->delete();

        $this->auditLogger->log(
            AuditAction::DomainRemoved,
            $request->user(),
            $domain->organization,
            OrganizationDomain::class,
            $domain->id,
            ['host' => $domain->host],
        );

        return response()->json([
            'message' => 'Domain removed.',
        ]);
    }

    public function verify(Request $request, Organization $organization, OrganizationDomain $domain): JsonResponse
    {
        $this->organizationForUser($request, $organization);
        $this->assertDomainBelongsToOrganization($domain, $organization);

        if ($domain->type === DomainType::Subdomain) {
            throw ValidationException::withMessages([
                'host' => ['Authzio subdomains are verified automatically.'],
            ]);
        }

        if ($domain->isVerified()) {
            return response()->json([
                'data' => $domain,
            ]);
        }

        if ($this->cloudflareDomains->enabled() && filled($domain->cloudflare_hostname_id)) {
            $domain = $this->cloudflareDomains->refreshAndVerify($domain);

            $this->auditLogger->log(
                AuditAction::DomainVerified,
                $request->user(),
                $domain->organization,
                OrganizationDomain::class,
                $domain->id,
                ['host' => $domain->host, 'via' => 'cloudflare'],
            );

            return response()->json([
                'data' => $domain,
            ]);
        }

        $token = $domain->verification_token;
        if (! is_string($token) || $token === '') {
            throw ValidationException::withMessages([
                'host' => ['This domain has no verification token. Remove it and add it again.'],
            ]);
        }

        if (! $this->dnsVerifier->tokenPresent($domain->host, $token)) {
            $challenge = '_authzio-challenge.'.$domain->host;

            throw ValidationException::withMessages([
                'host' => [
                    "TXT record not found for {$domain->host} (or {$challenge}). "
                    .'Publish the verification token as a TXT value, wait for DNS, then try again.',
                ],
            ]);
        }

        $domain->update([
            'verified_at' => now(),
        ]);

        $this->auditLogger->log(
            AuditAction::DomainVerified,
            $request->user(),
            $domain->organization,
            OrganizationDomain::class,
            $domain->id,
            ['host' => $domain->host],
        );

        return response()->json([
            'data' => $domain->fresh(),
        ]);
    }

    private function assertDomainBelongsToOrganization(OrganizationDomain $domain, Organization $organization): void
    {
        abort_unless($domain->organization_id === $organization->id, 404);
    }
}
