<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DodoWebhook extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $hidden = [
        'secret',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dodo_webhook_id',
        'url',
        'secret',
        'environment',
        'description',
        'filter_types',
        'metadata',
        'is_active',
        'last_delivered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'filter_types' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'last_delivered_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<DodoWebhookEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(DodoWebhookEvent::class);
    }

    public static function activeSecret(?string $environment = null): ?string
    {
        $environment ??= (string) config('billing.dodo.environment', 'test_mode');

        $fromDb = static::query()
            ->where('is_active', true)
            ->where('environment', $environment)
            ->whereNotNull('secret')
            ->orderByDesc('updated_at')
            ->value('secret');

        if (is_string($fromDb) && $fromDb !== '') {
            return $fromDb;
        }

        $fromEnv = config('billing.dodo.webhook_secret');

        return is_string($fromEnv) && $fromEnv !== '' ? $fromEnv : null;
    }

    public static function activeForEnvironment(?string $environment = null): ?self
    {
        $environment ??= (string) config('billing.dodo.environment', 'test_mode');

        return static::query()
            ->where('is_active', true)
            ->where('environment', $environment)
            ->orderByDesc('updated_at')
            ->first();
    }
}
