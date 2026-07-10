<?php

namespace App\Enums;

enum FuzzyMatchingAlgorithm: string
{
    case SimHash = 'simhash';
    case MinHash = 'minhash';

    public function label(): string
    {
        return match ($this) {
            self::SimHash => 'SimHash',
            self::MinHash => 'MinHash',
        };
    }
}
