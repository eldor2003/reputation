<?php

namespace App\DTO;

use App\Enums\RoutingTargetType;

readonly class RoutingTargetDecisionDTO
{
    /**
     * @param  array<string, mixed>|null  $targetConfig
     */
    public function __construct(
        public RoutingTargetType $targetType,
        public ?array $targetConfig = null,
        public int $sortOrder = 0,
    ) {}
}
