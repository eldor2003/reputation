<?php

namespace App\Contracts;

use App\DTO\ClassificationResultDTO;
use App\DTO\NormalizedMentionDTO;

interface LLMDecisionStrategyInterface
{
    public function selectInitialModel(NormalizedMentionDTO $mention): string;

    public function shouldEscalate(ClassificationResultDTO $result, string $currentTier): ?string;

    public function buildEscalationReason(ClassificationResultDTO $result, string $fromTier, string $toTier): string;
}
