<?php

namespace App\Services;

use App\Contracts\MentionRouteStorageInterface;
use App\DTO\RoutingDecisionDTO;
use App\DTO\RoutingTargetDecisionDTO;
use App\Models\MentionRoute;
use App\Models\MentionRoutingTarget;

class MentionRouteStorage implements MentionRouteStorageInterface
{
    public function store(int $mentionId, RoutingDecisionDTO $decision): MentionRoute
    {
        $route = MentionRoute::query()->create([
            'mention_id' => $mentionId,
            'routing_rule_id' => $decision->routingRuleId,
            'should_notify' => $decision->shouldNotify,
            'priority' => $decision->priority,
            'channel' => $decision->channel,
            'delivery_mode' => $decision->deliveryMode,
            'skip_moderation' => $decision->skipModeration,
            'reason' => $decision->reason,
        ]);

        foreach ($decision->targets as $target) {
            $this->storeTarget($route->id, $target);
        }

        return $route->load('targets');
    }

    private function storeTarget(int $mentionRouteId, RoutingTargetDecisionDTO $target): MentionRoutingTarget
    {
        return MentionRoutingTarget::query()->create([
            'mention_route_id' => $mentionRouteId,
            'target_type' => $target->targetType,
            'target_config' => $target->targetConfig,
            'sort_order' => $target->sortOrder,
        ]);
    }
}
