<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthRefreshToken extends Model
{
    protected $table = 'oauth_refresh_tokens';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'access_token_id',
        'revoked',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revoked' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OAuthAccessToken, $this>
     */
    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(OAuthAccessToken::class, 'access_token_id');
    }
}
