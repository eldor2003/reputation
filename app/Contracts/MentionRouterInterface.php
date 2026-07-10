<?php

namespace App\Contracts;

use App\DTO\RoutingAssessmentContextDTO;
use App\DTO\RoutingDecisionDTO;

interface MentionRouterInterface
{
    public function route(RoutingAssessmentContextDTO $context): RoutingDecisionDTO;
}
