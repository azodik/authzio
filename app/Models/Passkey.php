<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Passkey extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'credential_id',
        'public_key',
        'sign_count',
        'transports',
        'attestation_format',
        'aaguid',
        'last_used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sign_count' => 'integer',
            'transports' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
