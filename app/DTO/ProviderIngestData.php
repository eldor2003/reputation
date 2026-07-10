<?php

namespace App\DTO;

use App\Enums\SourceType;

readonly class ProviderIngestData
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public SourceType $sourceType,
        public string $sourceUuid,
        public string $externalId,
        public string $idempotencyKey,
        public array $payload,
    ) {}
}
