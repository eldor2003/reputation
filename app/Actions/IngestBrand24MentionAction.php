<?php

namespace App\Actions;

use App\DTO\Brand24IngestData;
use App\DTO\ProviderIngestData;
use App\Enums\SourceType;
use App\Services\IngestIdempotencyKeyBuilder;

class IngestBrand24MentionAction
{
    public function __construct(
        private readonly IngestMentionAction $ingestMentionAction,
        private readonly IngestIdempotencyKeyBuilder $idempotencyKeyBuilder,
    ) {}

    public function execute(Brand24IngestData $data): void
    {
        $this->ingestMentionAction->execute(new ProviderIngestData(
            sourceType: SourceType::Brand24,
            sourceUuid: $data->sourceUuid,
            externalId: $data->externalId,
            idempotencyKey: $this->idempotencyKeyBuilder->build(
                SourceType::Brand24,
                $data->sourceUuid,
                $data->externalId,
                $data->idempotencyKey,
            ),
            payload: $data->payload,
        ));
    }
}
