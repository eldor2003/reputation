<?php

namespace App\Actions;

use App\Contracts\DeliveryEngineInterface;
use App\DTO\DeliveryResultDTO;
use App\Enums\RoutingDeliveryMode;
use App\Models\MentionRoute;

class QueueRoutedMentionForDigestAction
{
    public function __construct(
        private readonly DeliveryEngineInterface $deliveryEngine,
    ) {}

    public function execute(int $mentionId): ?DeliveryResultDTO
    {
        $route = MentionRoute::query()->where('mention_id', $mentionId)->first();

        if ($route === null) {
            return null;
        }

        if ($route->delivery_mode === RoutingDeliveryMode::Immediate
            || $route->delivery_mode === RoutingDeliveryMode::Skip) {
            return null;
        }

        if (! $route->skip_moderation) {
            return null;
        }

        $digestType = $route->delivery_mode === RoutingDeliveryMode::Deferred
            ? config('delivery.digest.default_type_for_routing_deferred', 'evening')
            : config('delivery.digest.default_type_for_routing_digest', 'morning');

        return $this->deliveryEngine->queueForDigest($mentionId, (string) $digestType);
    }
}
