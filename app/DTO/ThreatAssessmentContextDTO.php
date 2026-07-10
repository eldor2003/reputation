<?php

namespace App\DTO;

use App\Models\AiResult;
use App\Models\Mention;
use App\Models\Person;
use App\Models\Source;

readonly class ThreatAssessmentContextDTO
{
    public function __construct(
        public Mention $mention,
        public AiResult $aiResult,
        public Source $source,
        public int $clusterSize,
        public ?int $serpTopPosition,
        public ?Person $person,
    ) {}
}
