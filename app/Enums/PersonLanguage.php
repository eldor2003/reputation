<?php

namespace App\Enums;

enum PersonLanguage: string
{
    case English = 'en';
    case Russian = 'ru';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Russian => 'Russian',
            self::Custom => 'Custom',
        };
    }
}
