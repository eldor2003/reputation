<?php

namespace App\DTO;

use App\Enums\SerpEngine;

readonly class SerpSearchResultDTO
{
    /**
     * @param  list<SerpPositionDTO>  $positions
     */
    public function __construct(
        public SerpEngine $engine,
        public string $query,
        public string $serpApiSearchId,
        public float $responseTimeMs,
        public array $positions,
        public ?string $rawHtmlUrl = null,
    ) {}
}
