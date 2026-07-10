<?php

namespace App\DTO;

use App\Enums\DeduplicationMatchMethod;

readonly class ClusterAssignmentDTO
{
    public function __construct(
        public int $clusterId,
        public int $mentionId,
        public bool $isCanonical,
        public ?float $similarityScore,
        public DeduplicationMatchMethod $matchMethod,
    ) {}
}
