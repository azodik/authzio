<?php

namespace App\Models;

use App\Enums\EmailProviderDriver;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationEmailProvider extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'driver',
        'credentials',
        'from_address',
        'from_name',
        'is_active',
        'verified_at',
        'last_error',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'credentials',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver' => EmailProviderDriver::class,
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
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
