<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'subdomain',
        'primary_domain',
        'billing_email',
        'is_demo',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_demo' => 'boolean',
        ];
    }

    public function isDemo(): bool
    {
        return (bool) $this->is_demo;
    }

    /**
     * @return HasMany<OrganizationMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /**
     * @return HasMany<OrganizationInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /**
     * @return HasMany<OrganizationDomain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(OrganizationDomain::class);
    }

    /**
     * @return HasMany<EmailTemplate, $this>
     */
    public function emailTemplates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class);
    }

    /**
     * @return HasMany<Role, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * @return HasMany<OAuthClient, $this>
     */
    public function oauthClients(): HasMany
    {
        return $this->hasMany(OAuthClient::class);
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * @return HasMany<OrganizationSigningKey, $this>
     */
    public function signingKeys(): HasMany
    {
        return $this->hasMany(OrganizationSigningKey::class);
    }

    /**
     * @return HasOne<OrganizationSubscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(OrganizationSubscription::class);
    }

    /**
     * @return HasOne<OrganizationEmailProvider, $this>
     */
    public function emailProvider(): HasOne
    {
        return $this->hasOne(OrganizationEmailProvider::class);
    }

    /**
     * @return HasMany<OrganizationSsoConnection, $this>
     */
    public function ssoConnections(): HasMany
    {
        return $this->hasMany(OrganizationSsoConnection::class);
    }

    /**
     * @return HasMany<ApplicationUser, $this>
     */
    public function applicationUsers(): HasMany
    {
        return $this->hasMany(ApplicationUser::class);
    }
}
