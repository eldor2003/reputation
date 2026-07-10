<?php

namespace App\Contracts;

use App\Models\ThreatRule;
use Illuminate\Support\Collection;

interface ThreatRuleRepositoryInterface
{
    /**
     * @return Collection<int, ThreatRule>
     */
    public function activeRules(?int $projectId = null): Collection;
}
