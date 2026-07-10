<?php

namespace App\Services;

use App\Enums\SourceType;

class IngestIdempotencyKeyBuilder
{
    public function build(
        SourceType $sourceType,
        string $sourceUuid,
        string $externalId,
        ?string $providedKey = null,
    ): string {
        if (is_string($providedKey) && $providedKey !== '') {
            return $providedKey;
        }

        return hash('sha256', implode('|', [
            $sourceType->value,
            $sourceUuid,
            $externalId,
        ]));
    }
}
