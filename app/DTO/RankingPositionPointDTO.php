<?php

namespace App\DTO;

use App\Enums\SerpEngine;
use Carbon\CarbonInterface;

readonly class RankingPositionPointDTO
{
    public function __construct(
        public CarbonInterface $fetchedAt,
        public int $position,
        public string $url,
        public string $title,
        public SerpEngine $engine,
        public string $query,
        public int $snapshotId,
    ) {}
}
