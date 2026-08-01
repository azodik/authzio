<?php

namespace App\Enums;

enum LoginLayout: string
{
    case Centered = 'centered';
    case FormRight = 'form_right';
    case FormLeft = 'form_left';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $layout): string => $layout->value, self::cases());
    }

    public function cssModifier(): string
    {
        return match ($this) {
            self::Centered => 'centered',
            self::FormRight => 'form-right',
            self::FormLeft => 'form-left',
        };
    }
}
