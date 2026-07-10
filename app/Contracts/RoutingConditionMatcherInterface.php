<?php

namespace App\Contracts;

use App\DTO\RoutingAssessmentContextDTO;
use App\Models\RoutingCondition;

interface RoutingConditionMatcherInterface
{
    public function matches(RoutingCondition $condition, RoutingAssessmentContextDTO $context): bool;
}
