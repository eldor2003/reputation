<?php

namespace App\Services;

use App\Contracts\DeduplicationEngineInterface;
use App\DTO\DeduplicationResultDTO;
use App\DTO\NormalizedMentionDTO;
use App\Models\Mention;

class ExactDeduplicationEngine implements DeduplicationEngineInterface
{
    public function check(NormalizedMentionDTO $mention): DeduplicationResultDTO
    {
        $dedupHash = $this->generateHash($mention);

        $original = Mention::query()
            ->where('dedup_hash', $dedupHash)
            ->where('is_duplicate', false)
            ->oldest('id')
            ->first();

        if ($original !== null) {
            return new DeduplicationResultDTO(
                isDuplicate: true,
                originalMentionId: $original->id,
                dedupHash: $dedupHash,
            );
        }

        return new DeduplicationResultDTO(
            isDuplicate: false,
            originalMentionId: null,
            dedupHash: $dedupHash,
        );
    }

    private function generateHash(NormalizedMentionDTO $mention): string
    {
        return hash('sha256', $mention->sourceId.'|'.$mention->externalId);
    }
}
