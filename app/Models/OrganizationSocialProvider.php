<?php

namespace App\Models;

use App\Enums\SocialProvider;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSocialProvider extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'provider',
        'client_id',
        'client_secret',
        'enabled',
        'scopes',
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
            'provider' => SocialProvider::class,
            'client_secret' => 'encrypted',
            'enabled' => 'boolean',
            'scopes' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
