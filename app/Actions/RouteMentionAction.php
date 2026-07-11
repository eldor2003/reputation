<?php

namespace App\Actions;

use App\DTO\RoutingDecisionDTO;
use App\Contracts\MentionRouteStorageInterface;
use App\Contracts\MentionRouterInterface;
use App\Contracts\RoutingContextBuilderInterface;
use App\Enums\RoutingDeliveryMode;
use App\Events\MentionRouted;
use App\Models\Mention;
use RuntimeException;

class RouteMentionAction
{
    public function __construct(
        private readonly RoutingContextBuilderInterface $routingContextBuilder,
        private readonly MentionRouterInterface $mentionRouter,
        private readonly MentionRouteStorageInterface $mentionRouteStorage,
    ) {}

    public function execute(int $mentionId): void
    {
        $mention = Mention::query()->find($mentionId);

        if ($mention === null) {
            throw new RuntimeException("Mention [{$mentionId}] not found for routing.");
        }

        $context = $this->routingContextBuilder->build($mentionId);
        $decision = $this->mentionRouter->route($context);

        if ($this->shouldSuppressNotifications($mention)) {
            $decision = new RoutingDecisionDTO(
                shouldNotify: false,
                priority: $decision->priority,
                channel: $decision->channel,
                reason: 'Historical Mentionlytics import: notifications suppressed.',
                routingRuleId: $decision->routingRuleId,
                deliveryMode: RoutingDeliveryMode::Skip,
                skipModeration: true,
                targets: [],
            );
        }

        $this->mentionRouteStorage->store($mentionId, $decision);

        MentionRouted::dispatch(
            $mentionId,
            $mention->project_id,
            $mention->source_id,
            now(),
        );
    }

    private function shouldSuppressNotifications(Mention $mention): bool
    {
        /** @var array<string, mixed>|null $metadata */
        $metadata = is_array($mention->metadata) ? $mention->metadata : null;

        if ($metadata === null) {
            return false;
        }

        return ($metadata['suppress_notifications'] ?? false) === true;
    }
}
