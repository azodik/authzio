<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSsoConnection extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'protocol',
        'issuer',
        'client_id',
        'client_secret',
        'authorization_endpoint',
        'token_endpoint',
        'userinfo_endpoint',
        'jwks_uri',
        'scopes',
        'email_domains',
        'enabled',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'client_secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'scopes' => 'array',
            'email_domains' => 'array',
            'enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function identityProviderKey(): string
    {
        return 'sso:'.$this->id;
    }

    /**
     * @return list<string>
     */
    public function normalizedEmailDomains(): array
    {
        $domains = $this->email_domains ?? [];

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $domain): string => strtolower(trim((string) $domain)),
            $domains,
        ))));
    }
}
