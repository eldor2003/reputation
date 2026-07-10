<?php

namespace App\Actions;

use App\DTO\ProviderIngestData;
use App\DTO\YouScanIngestData;
use App\Enums\SourceType;
use App\Services\IngestIdempotencyKeyBuilder;

class IngestYouScanMentionAction
{
    public function __construct(
        private readonly IngestMentionAction $ingestMentionAction,
        private readonly IngestIdempotencyKeyBuilder $idempotencyKeyBuilder,
    ) {}

    public function execute(YouScanIngestData $data): void
    {
        $this->ingestMentionAction->execute(new ProviderIngestData(
            sourceType: SourceType::YouScan,
            sourceUuid: $data->sourceUuid,
            externalId: $data->externalId,
            idempotencyKey: $this->idempotencyKeyBuilder->build(
                SourceType::YouScan,
                $data->sourceUuid,
                $data->externalId,
                $data->idempotencyKey,
            ),
            payload: $data->payload,
        ));
    }
}
