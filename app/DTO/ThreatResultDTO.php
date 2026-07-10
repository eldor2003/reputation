<?php

namespace App\DTO;

use App\Enums\ThreatLevel;

readonly class ThreatResultDTO
{
    /**
     * @param  list<ThreatFactorDTO>  $factors
     */
    public function __construct(
        public ThreatLevel $threatLevel,
        public float $threatScore,
        public array $factors,
    ) {}
}
