<?php

namespace App\Contracts;

use App\DTO\ThreatAssessmentContextDTO;
use App\DTO\ThreatResultDTO;
use App\Models\MentionThreatResult;

interface ThreatResultStorageInterface
{
    public function store(int $mentionId, int $aiResultId, ThreatResultDTO $result): MentionThreatResult;
}
