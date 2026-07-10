<?php

namespace App\Repositories;

use App\Contracts\RoutingRuleRepositoryInterface;
use App\Models\RoutingRule;
use Illuminate\Support\Collection;

class RoutingRuleRepository implements RoutingRuleRepositoryInterface
{
    public function activeRules(?int $projectId = null, ?int $personId = null): Collection
    {
        $query = RoutingRule::query()
            ->with(['conditions', 'targets' => fn ($builder) => $builder->where('is_active', true)->orderBy('sort_order')])
            ->where('is_active', true)
            ->where('is_fallback', false)
            ->orderBy('rule_priority');

        $query->where(function ($builder) use ($projectId, $personId): void {
            $builder->whereNull('project_id');

            if ($projectId !== null) {
                $builder->orWhere('project_id', $projectId);
            }
        });

        $query->where(function ($builder) use ($personId): void {
            $builder->whereNull('person_id');

            if ($personId !== null) {
                $builder->orWhere('person_id', $personId);
            }
        });

        return $query->get();
    }

    public function fallbackRule(?int $projectId = null): ?RoutingRule
    {
        $projectFallback = $projectId === null
            ? null
            : RoutingRule::query()
                ->with(['conditions', 'targets' => fn ($builder) => $builder->where('is_active', true)->orderBy('sort_order')])
                ->where('project_id', $projectId)
                ->where('is_active', true)
                ->where('is_fallback', true)
                ->orderBy('rule_priority')
                ->first();

        if ($projectFallback !== null) {
            return $projectFallback;
        }

        return RoutingRule::query()
            ->with(['conditions', 'targets' => fn ($builder) => $builder->where('is_active', true)->orderBy('sort_order')])
            ->whereNull('project_id')
            ->where('is_active', true)
            ->where('is_fallback', true)
            ->orderBy('rule_priority')
            ->first();
    }
}
