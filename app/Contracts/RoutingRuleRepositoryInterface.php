<?php

namespace App\Contracts;

use App\Models\RoutingRule;
use Illuminate\Support\Collection;

interface RoutingRuleRepositoryInterface
{
    /**
     * @return Collection<int, RoutingRule>
     */
    public function activeRules(?int $projectId = null, ?int $personId = null): Collection;

    public function fallbackRule(?int $projectId = null): ?RoutingRule;
}
