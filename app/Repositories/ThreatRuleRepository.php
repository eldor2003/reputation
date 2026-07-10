<?php

namespace App\Repositories;

use App\Contracts\ThreatRuleRepositoryInterface;
use App\Models\ThreatRule;
use Illuminate\Support\Collection;

class ThreatRuleRepository implements ThreatRuleRepositoryInterface
{
    public function activeRules(?int $projectId = null): Collection
    {
        $projectRules = $projectId === null
            ? collect()
            : ThreatRule::query()
                ->where('project_id', $projectId)
                ->where('is_active', true)
                ->get()
                ->keyBy('level');

        $globalRules = ThreatRule::query()
            ->whereNull('project_id')
            ->where('is_active', true)
            ->get()
            ->keyBy('level');

        if ($projectRules->isEmpty()) {
            return $globalRules
                ->sortBy('priority')
                ->values();
        }

        return $globalRules
            ->merge($projectRules)
            ->sortBy('priority')
            ->values();
    }
}
