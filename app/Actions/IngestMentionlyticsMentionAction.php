<?php

namespace App\Actions;

use App\DTO\MentionlyticsIngestData;
use App\DTO\ProviderIngestData;
use App\Enums\SourceType;
use App\Services\IngestIdempotencyKeyBuilder;

class IngestMentionlyticsMentionAction
{
    public function __construct(
        private readonly IngestMentionAction $ingestMentionAction,
        private readonly IngestIdempotencyKeyBuilder $idempotencyKeyBuilder,
    ) {}

    public function execute(MentionlyticsIngestData $data): void
    {
        $this->ingestMentionAction->execute(new ProviderIngestData(
            sourceType: SourceType::Mentionlytics,
            sourceUuid: $data->sourceUuid,
            externalId: $data->externalId,
            idempotencyKey: $this->idempotencyKeyBuilder->build(
                SourceType::Mentionlytics,
                $data->sourceUuid,
                $data->externalId,
                $data->idempotencyKey,
            ),
            payload: $data->payload,
        ));
    }
}
