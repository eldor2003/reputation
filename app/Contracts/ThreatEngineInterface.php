<?php

namespace App\Contracts;

use App\DTO\ThreatAssessmentContextDTO;
use App\DTO\ThreatResultDTO;

interface ThreatEngineInterface
{
    public function evaluate(ThreatAssessmentContextDTO $context): ThreatResultDTO;
}
