<?php

namespace App\DTO;

readonly class ThreatFactorDTO
{
    public function __construct(
        public string $key,
        public mixed $rawValue,
        public float $score,
        public float $weight,
        public float $weightedScore,
    ) {}
}
