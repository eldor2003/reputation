<?php

namespace App\Contracts;

use App\DTO\DeduplicationResultDTO;
use App\DTO\MentionFingerprintDTO;
use App\DTO\NormalizedMentionDTO;

interface MentionClusterBuilderInterface
{
    public function buildFingerprint(NormalizedMentionDTO $mention): MentionFingerprintDTO;

    public function assign(
        int $mentionId,
        NormalizedMentionDTO $mention,
        DeduplicationResultDTO $result,
    ): DeduplicationResultDTO;
}
