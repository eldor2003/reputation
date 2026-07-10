<?php

namespace App\DTO;

use App\Enums\RoutingChannel;
use App\Enums\RoutingDeliveryMode;
use App\Enums\RoutingPriority;

readonly class RoutingDecisionDTO
{
    /**
     * @param  list<RoutingTargetDecisionDTO>  $targets
     */
    public function __construct(
        public bool $shouldNotify,
        public RoutingPriority $priority,
        public RoutingChannel $channel,
        public string $reason,
        public ?int $routingRuleId = null,
        public RoutingDeliveryMode $deliveryMode = RoutingDeliveryMode::Skip,
        public bool $skipModeration = false,
        public array $targets = [],
    ) {}
}
