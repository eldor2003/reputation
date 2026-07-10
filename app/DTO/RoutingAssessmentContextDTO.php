<?php

namespace App\DTO;

use App\Enums\RoutingDeliveryMode;
use App\Enums\RoutingPriority;
use App\Enums\RoutingTargetType;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\MentionThreatResult;
use App\Models\Person;
use App\Models\Source;
use Carbon\CarbonInterface;

readonly class RoutingAssessmentContextDTO
{
    public function __construct(
        public Mention $mention,
        public AiResult $aiResult,
        public MentionThreatResult $threatResult,
        public Source $source,
        public ?Person $person,
        public CarbonInterface $evaluatedAt,
    ) {}
}
