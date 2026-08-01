<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DodoWebhookEvent extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dodo_webhook_id',
        'webhook_id',
        'event_type',
        'payload',
        'headers',
        'processed_at',
        'processing_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'headers' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DodoWebhook, $this>
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(DodoWebhook::class, 'dodo_webhook_id');
    }
}
