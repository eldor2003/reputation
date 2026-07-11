<?php

namespace App\DTO;

use App\Enums\SerpEngine;
use Carbon\CarbonInterface;

readonly class RankingHistoryQueryDTO
{
    public function __construct(
        public ?int $personId = null,
        public ?SerpEngine $engine = null,
        public ?string $keyword = null,
        public ?CarbonInterface $from = null,
        public ?CarbonInterface $to = null,
    ) {}
}
