<?php

namespace App\Contracts;

use App\DTO\ThreatAssessmentContextDTO;

interface ThreatFactorScorerInterface
{
    public function score(string $factorKey, array $scoringConfig, ThreatAssessmentContextDTO $context): float;

    public function extractRawValue(string $factorKey, ThreatAssessmentContextDTO $context): mixed;
}
