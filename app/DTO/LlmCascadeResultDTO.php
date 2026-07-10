<?php

namespace App\DTO;

readonly class LlmCascadeResultDTO
{
    public function __construct(
        public ClassificationResultDTO $classification,
        public string $model,
        public LlmExecutionMetadataDTO $metadata,
    ) {}
}
