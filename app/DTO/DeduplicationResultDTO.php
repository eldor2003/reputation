<?php

namespace App\DTO;

use App\Enums\DeduplicationMatchMethod;

readonly class DeduplicationResultDTO
{
    public function __construct(
        public bool $isDuplicate,
        public ?int $originalMentionId,
        public string $dedupHash,
        public ?int $clusterId = null,
        public ?float $similarityScore = null,
        public ?DeduplicationMatchMethod $matchMethod = null,
        public ?MentionFingerprintDTO $fingerprint = null,
    ) {}
}
