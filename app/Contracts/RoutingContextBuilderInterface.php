<?php

namespace App\Contracts;

use App\DTO\RoutingAssessmentContextDTO;

interface RoutingContextBuilderInterface
{
    public function build(int $mentionId): RoutingAssessmentContextDTO;
}
