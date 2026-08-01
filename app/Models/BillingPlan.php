<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingPlan extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
        'description',
        'mau_limit',
        'application_limit',
        'allows_custom_domains',
        'allows_email_customization',
        'allows_login_customization',
        'allows_custom_jwks',
        'allows_custom_email_provider',
        'allows_sso',
        'email_daily_limit',
        'email_monthly_limit',
        'price_cents_monthly',
        'currency',
        'dodo_product_id',
        'is_public',
        'is_self_serve',
        'features',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mau_limit' => 'integer',
            'application_limit' => 'integer',
            'allows_custom_domains' => 'boolean',
            'allows_email_customization' => 'boolean',
            'allows_login_customization' => 'boolean',
            'allows_custom_jwks' => 'boolean',
            'allows_custom_email_provider' => 'boolean',
            'allows_sso' => 'boolean',
            'email_daily_limit' => 'integer',
            'email_monthly_limit' => 'integer',
            'price_cents_monthly' => 'integer',
            'is_public' => 'boolean',
            'is_self_serve' => 'boolean',
            'features' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<OrganizationSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(OrganizationSubscription::class);
    }
}
