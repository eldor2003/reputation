<?php

namespace App\Services;

use App\Contracts\ThreatResultStorageInterface;
use App\DTO\ThreatFactorDTO;
use App\DTO\ThreatResultDTO;
use App\Models\MentionThreatResult;

class ThreatResultStorage implements ThreatResultStorageInterface
{
    public function store(int $mentionId, int $aiResultId, ThreatResultDTO $result): MentionThreatResult
    {
        return MentionThreatResult::query()->create([
            'mention_id' => $mentionId,
            'ai_result_id' => $aiResultId,
            'threat_level' => $result->threatLevel->value,
            'threat_score' => $result->threatScore,
            'factor_scores' => array_map(
                fn (ThreatFactorDTO $factor): array => [
                    'key' => $factor->key,
                    'raw_value' => $factor->rawValue,
                    'score' => $factor->score,
                    'weight' => $factor->weight,
                    'weighted_score' => $factor->weightedScore,
                ],
                $result->factors,
            ),
            'assessed_at' => now(),
        ]);
    }
}
