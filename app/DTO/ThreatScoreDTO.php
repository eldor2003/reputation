<?php

namespace App\DTO;

readonly class ThreatScoreDTO
{
    /**
     * @param  list<ThreatFactorDTO>  $factors
     */
    public function __construct(
        public float $totalScore,
        public array $factors,
    ) {}
}
