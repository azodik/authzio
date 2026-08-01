<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageMonthlySummary extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'year_month',
        'mau_count',
        'authentication_count',
        'user_created_count',
        'token_issued_count',
        'computed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mau_count' => 'integer',
            'authentication_count' => 'integer',
            'user_created_count' => 'integer',
            'token_issued_count' => 'integer',
            'computed_at' => 'datetime',
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
