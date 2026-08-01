<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSigningKey extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $hidden = [
        'private_key',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'kid',
        'alg',
        'public_jwk',
        'private_key',
        'is_active',
        'is_custom',
        'rotated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'public_jwk' => 'array',
            'private_key' => 'encrypted',
            'is_active' => 'boolean',
            'is_custom' => 'boolean',
            'rotated_at' => 'datetime',
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
