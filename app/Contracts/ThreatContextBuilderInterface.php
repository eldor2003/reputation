<?php

namespace App\Contracts;

use App\DTO\ThreatAssessmentContextDTO;

interface ThreatContextBuilderInterface
{
    public function build(int $mentionId): ThreatAssessmentContextDTO;
}
