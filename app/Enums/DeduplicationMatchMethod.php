<?php

namespace App\Enums;

enum DeduplicationMatchMethod: string
{
    case Exact = 'exact';
    case Fuzzy = 'fuzzy';

    public function label(): string
    {
        return match ($this) {
            self::Exact => 'Exact',
            self::Fuzzy => 'Fuzzy',
        };
    }
}
