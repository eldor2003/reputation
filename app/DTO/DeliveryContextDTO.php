<?php

namespace App\DTO;

use App\Enums\RoutingDeliveryMode;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\MentionRoute;
use App\Models\MentionThreatResult;
use App\Models\ModerationLog;
use App\Models\Person;
use App\Models\Source;
use Carbon\CarbonInterface;

readonly class DeliveryContextDTO
{
    public function __construct(
        public Mention $mention,
        public AiResult $aiResult,
        public MentionThreatResult $threatResult,
        public Source $source,
        public ?Person $person,
        public ?MentionRoute $route,
        public ?ModerationLog $moderationLog,
        public int $clusterSize,
        public ?int $serpPosition,
        public CarbonInterface $timestamp,
    ) {}

    public function deliveryMode(): RoutingDeliveryMode
    {
        return $this->route?->delivery_mode ?? RoutingDeliveryMode::Immediate;
    }
}
