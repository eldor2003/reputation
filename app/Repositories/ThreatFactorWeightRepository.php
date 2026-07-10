<?php

namespace App\Repositories;

use App\Contracts\ThreatFactorWeightRepositoryInterface;
use App\Models\ThreatFactorWeight;
use Illuminate\Support\Collection;

class ThreatFactorWeightRepository implements ThreatFactorWeightRepositoryInterface
{
    public function activeWeights(?int $projectId = null): Collection
    {
        $projectWeights = $projectId === null
            ? collect()
            : ThreatFactorWeight::query()
                ->where('project_id', $projectId)
                ->where('is_active', true)
                ->get()
                ->keyBy('factor_key');

        $globalWeights = ThreatFactorWeight::query()
            ->whereNull('project_id')
            ->where('is_active', true)
            ->get()
            ->keyBy('factor_key');

        if ($projectWeights->isEmpty()) {
            return $globalWeights->values();
        }

        return $globalWeights
            ->merge($projectWeights)
            ->values();
    }
}
