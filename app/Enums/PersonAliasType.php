<?php

namespace App\Enums;

enum PersonAliasType: string
{
    case FullName = 'full_name';
    case Alias = 'alias';
    case Transliteration = 'transliteration';
    case TypoVariant = 'typo_variant';

    public function label(): string
    {
        return match ($this) {
            self::FullName => 'Full Name',
            self::Alias => 'Alias',
            self::Transliteration => 'Transliteration',
            self::TypoVariant => 'Typo Variant',
        };
    }

    public function matchConfidence(): float
    {
        return match ($this) {
            self::FullName => 1.0,
            self::Alias => 0.95,
            self::Transliteration => 0.85,
            self::TypoVariant => 0.75,
        };
    }
}
