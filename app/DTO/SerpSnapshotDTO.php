<?php

namespace App\DTO;

use App\Enums\SerpEngine;
use Carbon\Carbon;

readonly class SerpSnapshotDTO
{
    /**
     * @param  list<SerpPositionDTO>  $positions
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public SerpEngine $searchEngine,
        public string $query,
        public Carbon $fetchedAt,
        public float $responseTimeMs,
        public string $serpApiSearchId,
        public array $positions,
        public ?string $screenshotPath = null,
        public ?array $metadata = null,
    ) {}
}
