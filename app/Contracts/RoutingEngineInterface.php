<?php

namespace App\Contracts;

use App\DTO\RoutingAssessmentContextDTO;
use App\DTO\RoutingDecisionDTO;

interface RoutingEngineInterface
{
    public function route(RoutingAssessmentContextDTO $context): RoutingDecisionDTO;
}
