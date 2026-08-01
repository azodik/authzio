<?php

namespace App\Models;

use App\Enums\ApplicationType;
use App\Services\Auth\LoginMethods;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OAuthClient extends Model
{
    use HasUuids;

    protected $table = 'oauth_clients';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'name',
        'application_type',
        'description',
        'logo_url',
        'primary_color',
        'background_color',
        'login_headline',
        'login_description',
        'login_button_label',
        'show_signup_link',
        'show_forgot_password_link',
        'default_locale',
        'allow_locale_switch',
        'login_layout',
        'login_theme',
        'password_policy',
        'security_policy',
        'login_methods',
        'terms_url',
        'privacy_url',
        'require_legal_accept',
        'secret',
        'redirect_uris',
        'grant_types',
        'is_confidential',
        'is_first_party',
        'revoked_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'application_type' => ApplicationType::class,
            'redirect_uris' => 'array',
            'grant_types' => 'array',
            'password_policy' => 'array',
            'security_policy' => 'array',
            'login_methods' => 'array',
            'is_confidential' => 'boolean',
            'is_first_party' => 'boolean',
            'show_signup_link' => 'boolean',
            'show_forgot_password_link' => 'boolean',
            'allow_locale_switch' => 'boolean',
            'require_legal_accept' => 'boolean',
            'revoked_at' => 'datetime',
        ];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * @return array{min_length: int, require_mixed_case: bool, require_numbers: bool, require_symbols: bool}
     */
    public function resolvedPasswordPolicy(): array
    {
        $policy = is_array($this->password_policy) ? $this->password_policy : [];

        return [
            'min_length' => (int) ($policy['min_length'] ?? config('authzio.password.min_length', 12)),
            'require_mixed_case' => (bool) ($policy['require_mixed_case'] ?? config('authzio.password.require_mixed_case', true)),
            'require_numbers' => (bool) ($policy['require_numbers'] ?? config('authzio.password.require_numbers', true)),
            'require_symbols' => (bool) ($policy['require_symbols'] ?? config('authzio.password.require_symbols', true)),
        ];
    }

    /**
     * @return array{mfa_required: bool, session_lifetime_minutes: int, single_device: bool}
     */
    public function resolvedSecurityPolicy(): array
    {
        $policy = is_array($this->security_policy) ? $this->security_policy : [];

        return [
            'mfa_required' => (bool) ($policy['mfa_required'] ?? false),
            'session_lifetime_minutes' => (int) ($policy['session_lifetime_minutes'] ?? config('authzio.session.lifetime_minutes', 120)),
            'single_device' => (bool) ($policy['single_device'] ?? config('authzio.session.single_device', false)),
        ];
    }

    /**
     * @return array{
     *     password: bool,
     *     google: bool,
     *     github: bool,
     *     passkey: bool,
     *     email_otp: bool,
     *     sync_profile: bool,
     *     require_verified_email: bool,
     *     allow_unverified_email_with_otp: bool
     * }
     */
    public function resolvedLoginMethods(): array
    {
        return LoginMethods::forClient($this);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<OAuthAccessToken, $this>
     */
    public function accessTokens(): HasMany
    {
        return $this->hasMany(OAuthAccessToken::class, 'client_id');
    }
}
