<?php

namespace App\Contracts;

use App\DTO\RoutingDecisionDTO;
use App\Models\MentionRoute;

interface MentionRouteStorageInterface
{
    public function store(int $mentionId, RoutingDecisionDTO $decision): MentionRoute;
}
