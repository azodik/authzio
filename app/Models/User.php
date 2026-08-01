<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'uuid',
    'name',
    'email',
    'avatar_url',
    'password',
    'is_active',
    'is_demo',
    'mfa_enabled',
    'mfa_secret',
    'mfa_confirmed_at',
    'last_login_at',
    'last_login_ip',
    'email_verified_at',
    'preferred_locale',
    'theme',
])]
#[Hidden(['password', 'remember_token', 'mfa_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuid, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
            'mfa_enabled' => 'boolean',
            'mfa_secret' => 'encrypted',
            'mfa_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function isDemo(): bool
    {
        return (bool) $this->is_demo;
    }

    /**
     * @return HasMany<OrganizationMember, $this>
     */
    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot(['role_id', 'status', 'invited_at', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<UserIdentity, $this>
     */
    public function identities(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }

    /**
     * @return HasMany<Passkey, $this>
     */
    public function passkeys(): HasMany
    {
        return $this->hasMany(Passkey::class);
    }

    /**
     * @return HasMany<MfaRecoveryCode, $this>
     */
    public function mfaRecoveryCodes(): HasMany
    {
        return $this->hasMany(MfaRecoveryCode::class);
    }

    /**
     * @return HasMany<OAuthClient, $this>
     */
    public function oauthClients(): HasMany
    {
        return $this->hasMany(OAuthClient::class);
    }

    /**
     * @return HasMany<OAuthAccessToken, $this>
     */
    public function oauthAccessTokens(): HasMany
    {
        return $this->hasMany(OAuthAccessToken::class);
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_user_id');
    }
}
