<?php

namespace App\Enums;

enum DigestType: string
{
    case Morning = 'morning';
    case Evening = 'evening';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Morning => 'Утренний дайджест',
            self::Evening => 'Вечерний дайджест',
            self::Manual => 'Ручной дайджест',
        };
    }
}
