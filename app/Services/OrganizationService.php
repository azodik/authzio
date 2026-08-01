<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\DomainType;
use App\Enums\EmailTemplateSlug;
use App\Enums\OrgPermission;
use App\Models\EmailTemplate;
use App\Models\Organization;
use App\Models\OrganizationDomain;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionChecker;
use App\Services\Billing\BillingService;
use App\Services\Cloudflare\CustomDomainCloudflareService;
use App\Services\Mail\TransactionalMailer;
use App\Services\Oidc\SigningKeyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizationService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly BillingService $billingService,
        private readonly SigningKeyService $signingKeys,
        private readonly TransactionalMailer $mailer,
        private readonly PermissionChecker $permissions,
        private readonly CustomDomainCloudflareService $cloudflareDomains,
    ) {}

    public function create(User $owner, string $name, ?string $slug = null): Organization
    {
        return DB::transaction(function () use ($owner, $name, $slug): Organization {
            $slug = $this->resolveSlug($name, $slug);

            $organization = Organization::create([
                'name' => $name,
                'slug' => $slug,
                'subdomain' => $slug,
            ]);

            $roles = $this->createDefaultRoles($organization);

            OrganizationMember::create([
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
                'role_id' => $roles['owner']->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $this->provisionSubdomain($organization, $owner);
            $this->seedEmailTemplates($organization);
            $this->billingService->ensureSubscription($organization);
            $this->signingKeys->ensureActiveKey($organization);

            $this->auditLogger->log(
                AuditAction::OrganizationCreated,
                $owner,
                $organization,
                Organization::class,
                $organization->id,
            );

            return $organization->fresh(['domains', 'emailTemplates']) ?? $organization;
        });
    }

    public function invite(
        Organization $organization,
        User $actor,
        string $email,
        Role $role,
    ): OrganizationInvitation {
        $email = Str::lower(trim($email));

        if ($role->organization_id !== $organization->id) {
            throw ValidationException::withMessages([
                'role_id' => [__('Selected role does not belong to this organization.')],
            ]);
        }

        if ($role->is_owner) {
            throw ValidationException::withMessages([
                'role_id' => [__('Owner role cannot be assigned via invitation.')],
            ]);
        }

        $alreadyMember = $organization->members()
            ->whereHas('user', fn ($query) => $query->where('email', $email))
            ->exists();

        if ($alreadyMember) {
            throw ValidationException::withMessages([
                'email' => [__('This user is already a member of the organization.')],
            ]);
        }

        $invitation = OrganizationInvitation::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'email' => $email,
            ],
            [
                'invited_by' => $actor->id,
                'role_id' => $role->id,
                'token' => Str::random(64),
                'expires_at' => now()->addDays((int) config('authzio.invitations.expires_days', 7)),
                'accepted_at' => null,
                'revoked_at' => null,
            ],
        );

        $this->auditLogger->log(
            AuditAction::MemberInvited,
            $actor,
            $organization,
            OrganizationInvitation::class,
            $invitation->id,
            ['email' => $email, 'role_id' => $role->id, 'role' => $role->slug],
        );

        $this->sendInviteEmail($organization, $actor, $invitation);

        return $invitation->load('role');
    }

    public function resendInvitation(OrganizationInvitation $invitation, User $actor): OrganizationInvitation
    {
        if ($invitation->accepted_at !== null) {
            throw ValidationException::withMessages([
                'invitation' => [__('This invitation was already accepted.')],
            ]);
        }

        if ($invitation->revoked_at !== null) {
            throw ValidationException::withMessages([
                'invitation' => [__('This invitation was revoked.')],
            ]);
        }

        $invitation->update([
            'invited_by' => $actor->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDays((int) config('authzio.invitations.expires_days', 7)),
        ]);

        $invitation->load(['organization', 'role']);

        $this->sendInviteEmail($invitation->organization, $actor, $invitation);

        return $invitation;
    }

    public function acceptInvitation(OrganizationInvitation $invitation, User $user): OrganizationMember
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'token' => [__('This invitation is no longer valid.')],
            ]);
        }

        if (Str::lower($user->email) !== Str::lower($invitation->email)) {
            throw ValidationException::withMessages([
                'email' => [__('Sign in with the invited email address to accept.')],
            ]);
        }

        return DB::transaction(function () use ($invitation, $user): OrganizationMember {
            $role = $invitation->role;
            if ($role === null || $role->is_owner) {
                $role = Role::query()
                    ->where('organization_id', $invitation->organization_id)
                    ->where('slug', 'member')
                    ->firstOrFail();
            }

            $member = OrganizationMember::query()->updateOrCreate(
                [
                    'organization_id' => $invitation->organization_id,
                    'user_id' => $user->id,
                ],
                [
                    'role_id' => $role->id,
                    'status' => 'active',
                    'joined_at' => now(),
                ],
            );

            $invitation->update(['accepted_at' => now()]);

            $this->auditLogger->log(
                AuditAction::MemberJoined,
                $user,
                $invitation->organization,
                OrganizationMember::class,
                $member->id,
                ['invitation_id' => $invitation->id],
            );

            $this->permissions->forget($user, $invitation->organization);

            return $member->load(['role', 'user']);
        });
    }

    public function syncPermissionCatalog(): void
    {
        foreach (OrgPermission::catalog() as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'group' => $permission['group'],
                    'description' => $permission['description'],
                ],
            );
        }

        // Remove obsolete permission slugs from the previous catalog.
        Permission::query()
            ->whereNotIn('slug', OrgPermission::allSlugs())
            ->delete();
    }

    public function addCustomDomain(
        Organization $organization,
        User $actor,
        string $host,
    ): OrganizationDomain {
        $host = Str::lower(trim($host));
        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $host = rtrim($host, '/');

        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $host)) {
            throw ValidationException::withMessages([
                'host' => ['Enter a valid domain such as auth.example.com.'],
            ]);
        }

        $token = 'authzio-verify='.Str::random(32);
        $cnameTarget = $this->cloudflareDomains->cnameTarget();

        $domain = OrganizationDomain::create([
            'organization_id' => $organization->id,
            'host' => $host,
            'type' => DomainType::Custom,
            'is_primary' => false,
            'verification_token' => $token,
            'verified_at' => null,
            'dns_records' => [
                [
                    'purpose' => 'cname',
                    'type' => 'CNAME',
                    'name' => $host,
                    'value' => $cnameTarget,
                    'caption' => 'Point traffic at Authzio',
                ],
                [
                    'purpose' => 'ownership',
                    'type' => 'TXT',
                    'name' => $host,
                    'value' => $token,
                    'caption' => 'Ownership verification (hostname apex)',
                ],
                [
                    'purpose' => 'ownership',
                    'type' => 'TXT',
                    'name' => '_authzio-challenge.'.$host,
                    'value' => $token,
                    'caption' => 'Or ownership verification (challenge subdomain)',
                ],
            ],
        ]);

        if ($this->cloudflareDomains->enabled()) {
            $domain = $this->cloudflareDomains->provision($domain);
        }

        $this->auditLogger->log(
            AuditAction::DomainAdded,
            $actor,
            $organization,
            OrganizationDomain::class,
            $domain->id,
            ['host' => $host],
        );

        return $domain;
    }

    public function setSubdomain(
        Organization $organization,
        User $actor,
        string $subdomain,
    ): OrganizationDomain {
        $subdomain = Str::slug($subdomain);

        if ($subdomain === '' || strlen($subdomain) < 2) {
            throw ValidationException::withMessages([
                'subdomain' => ['Subdomain must be at least 2 characters.'],
            ]);
        }

        $root = config('authzio.domains.root', 'authzio.test');
        $host = $subdomain.'.'.$root;

        if (
            Organization::query()
                ->where('subdomain', $subdomain)
                ->where('id', '!=', $organization->id)
                ->exists()
            || OrganizationDomain::query()->where('host', $host)->where('organization_id', '!=', $organization->id)->exists()
        ) {
            throw ValidationException::withMessages([
                'subdomain' => ['That subdomain is already taken.'],
            ]);
        }

        $organization->update([
            'subdomain' => $subdomain,
        ]);

        $domain = OrganizationDomain::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'type' => DomainType::Subdomain,
            ],
            [
                'host' => $host,
                'is_primary' => true,
                'verification_token' => null,
                'verified_at' => now(),
            ],
        );

        OrganizationDomain::query()
            ->where('organization_id', $organization->id)
            ->where('id', '!=', $domain->id)
            ->update(['is_primary' => false]);

        $organization->update([
            'primary_domain' => $host,
        ]);

        $this->auditLogger->log(
            AuditAction::DomainAdded,
            $actor,
            $organization,
            OrganizationDomain::class,
            $domain->id,
            ['host' => $host, 'type' => 'subdomain'],
        );

        return $domain;
    }

    /**
     * Align the platform subdomain host with AUTHZIO_DOMAIN_ROOT
     * (e.g. after moving from authzio.test → authzio.com).
     */
    public function syncSubdomainHost(Organization $organization): ?OrganizationDomain
    {
        $label = $organization->subdomain;
        if (! is_string($label) || $label === '') {
            return null;
        }

        $root = (string) config('authzio.domains.root', 'authzio.test');
        $expectedHost = $label.'.'.$root;

        $domain = OrganizationDomain::query()
            ->where('organization_id', $organization->id)
            ->where('type', DomainType::Subdomain)
            ->first();

        if ($domain === null) {
            return null;
        }

        $previousHost = $domain->host;

        if ($previousHost !== $expectedHost) {
            $domain->update([
                'host' => $expectedHost,
                'verified_at' => now(),
                'verification_token' => null,
            ]);
        }

        $customPrimary = OrganizationDomain::query()
            ->where('organization_id', $organization->id)
            ->where('type', DomainType::Custom)
            ->where('is_primary', true)
            ->whereNotNull('verified_at')
            ->exists();

        if (! $customPrimary) {
            $primary = (string) ($organization->primary_domain ?? '');
            if ($primary === '' || $primary === $previousHost || str_starts_with($primary, $label.'.')) {
                if ($organization->primary_domain !== $expectedHost) {
                    $organization->update(['primary_domain' => $expectedHost]);
                }
            }
        }

        return $domain->fresh();
    }

    public function ensurePlatformDefaults(Organization $organization, User $actor): void
    {
        if ($organization->subdomain === null || $organization->subdomain === '') {
            $this->setSubdomain($organization, $actor, $organization->slug);
        } elseif ($organization->domains()->where('type', 'subdomain')->doesntExist()) {
            $this->setSubdomain($organization, $actor, $organization->subdomain);
        } else {
            $this->syncSubdomainHost($organization);
        }

        $this->seedEmailTemplates($organization);
        $this->billingService->ensureSubscription($organization);
        $this->signingKeys->ensureActiveKey($organization);
    }

    private function provisionSubdomain(Organization $organization, User $actor): void
    {
        $this->setSubdomain($organization, $actor, $organization->slug);
    }

    private function seedEmailTemplates(Organization $organization): void
    {
        foreach (EmailTemplateSlug::organizationCustomizable() as $slug) {
            EmailTemplate::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'slug' => $slug->value,
                ],
                [
                    'name' => $slug->defaultName(),
                    'subject' => $slug->defaultSubject(),
                    'body_html' => $slug->defaultHtml(),
                    'body_text' => strip_tags(str_replace(['</p>', '<br>', '<br/>'], "\n", $slug->defaultHtml())),
                    'is_active' => true,
                ],
            );
        }
    }

    private function sendInviteEmail(
        Organization $organization,
        User $actor,
        OrganizationInvitation $invitation,
    ): void {
        $inviteUrl = rtrim((string) config('app.url'), '/').'/console/invites/'.$invitation->token;

        $this->mailer->sendPlatform($invitation->email, EmailTemplateSlug::InviteMember, [
            'organization_name' => $organization->name,
            'inviter_name' => $actor->name,
            'role' => $invitation->role?->name ?? 'Member',
            'invite_url' => $inviteUrl,
            'expires_at' => $invitation->expires_at->toDayDateTimeString(),
        ]);
    }

    private function resolveSlug(string $name, ?string $slug): string
    {
        if ($slug !== null && trim($slug) !== '') {
            $normalized = Str::slug(trim($slug));

            if ($normalized === '' || strlen($normalized) < 2) {
                throw ValidationException::withMessages([
                    'slug' => [__('Slug must be at least 2 characters.')],
                ]);
            }

            if (in_array($normalized, $this->reservedSlugs(), true)) {
                throw ValidationException::withMessages([
                    'slug' => [__('That slug is reserved. Choose another.')],
                ]);
            }

            if (Organization::query()->where('slug', $normalized)->exists()) {
                throw ValidationException::withMessages([
                    'slug' => [__('That slug is already taken.')],
                ]);
            }

            return $normalized;
        }

        return $this->uniqueSlug($name);
    }

    /**
     * @return list<string>
     */
    private function reservedSlugs(): array
    {
        return ['www', 'api', 'app', 'console', 'mail', 'admin', 'status', 'docs', 'support'];
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug !== '' ? $baseSlug : 'org';
        $counter = 1;

        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @return array{owner: Role, admin: Role, member: Role}
     */
    private function createDefaultRoles(Organization $organization): array
    {
        $this->syncPermissionCatalog();
        $permissionMap = Permission::query()->pluck('id', 'slug');

        $ownerRole = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Owner',
            'slug' => 'owner',
            'description' => 'Full access to the organization.',
            'is_system' => true,
            'is_owner' => true,
        ]);

        $adminRole = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Administer members, applications, and settings.',
            'is_system' => true,
            'is_owner' => false,
        ]);

        $memberRole = Role::create([
            'organization_id' => $organization->id,
            'name' => 'Member',
            'slug' => 'member',
            'description' => 'Standard organization member access.',
            'is_system' => true,
            'is_owner' => false,
        ]);

        $ownerRole->permissions()->sync($permissionMap->values()->all());
        $adminRole->permissions()->sync(
            $permissionMap->only(OrgPermission::adminDefaults())->values()->all(),
        );
        $memberRole->permissions()->sync(
            $permissionMap->only(OrgPermission::memberDefaults())->values()->all(),
        );

        return [
            'owner' => $ownerRole,
            'admin' => $adminRole,
            'member' => $memberRole,
        ];
    }
}
