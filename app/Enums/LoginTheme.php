<?php

namespace App\Enums;

enum LoginTheme: string
{
    case Light = 'light';
    case Dark = 'dark';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $theme): string => $theme->value, self::cases());
    }
}
