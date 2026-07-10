<?php

namespace App\DTO;

use App\Enums\MentionStatus;
use Carbon\Carbon;

readonly class MentionIngestData
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public int $projectId,
        public int $sourceId,
        public string $externalId,
        public Carbon $receivedAt,
        public MentionStatus $status,
        public string $provider,
        public array $rawPayload,
    ) {}
}
