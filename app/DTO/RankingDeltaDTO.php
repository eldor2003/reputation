<?php

namespace App\DTO;

use App\Enums\SerpEngine;

readonly class RankingDeltaDTO
{
    public function __construct(
        public int $currentPosition,
        public int $previousPosition,
        public int $delta,
        public string $url,
        public string $query,
        public SerpEngine $engine,
    ) {}
}
