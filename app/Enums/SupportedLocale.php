<?php

namespace App\Enums;

enum SupportedLocale: string
{
    case En = 'en';
    case Fr = 'fr';
    case De = 'de';
    case Es = 'es';
    case Hi = 'hi';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $locale): string => $locale->value, self::cases());
    }
}
