<?php

namespace App\Contracts;

use App\Models\ThreatFactorWeight;
use Illuminate\Support\Collection;

interface ThreatFactorWeightRepositoryInterface
{
    /**
     * @return Collection<int, ThreatFactorWeight>
     */
    public function activeWeights(?int $projectId = null): Collection;
}
