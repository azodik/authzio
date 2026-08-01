<?php

namespace App\Enums;

enum ApplicationType: string
{
    case Web = 'web';
    case Spa = 'spa';
    case Native = 'native';
    case Machine = 'machine';

    /**
     * @return list<string>
     */
    public function defaultGrantTypes(): array
    {
        return match ($this) {
            self::Web, self::Spa, self::Native => ['authorization_code', 'refresh_token'],
            self::Machine => ['client_credentials'],
        };
    }

    public function isConfidentialByDefault(): bool
    {
        return match ($this) {
            self::Web, self::Machine => true,
            self::Spa, self::Native => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Traditional web',
            self::Spa => 'Single-page app',
            self::Native => 'Native / mobile',
            self::Machine => 'Machine-to-machine',
        };
    }
}
