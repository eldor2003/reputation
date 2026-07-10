<?php

namespace App\DTO;

readonly class LlmExecutionMetadataDTO
{
    public function __construct(
        public string $cascadeTier,
        public int $processingTimeMs,
        public int $inputTokens,
        public int $outputTokens,
        public float $estimatedCost,
        public ?string $escalationReason,
    ) {}
}
