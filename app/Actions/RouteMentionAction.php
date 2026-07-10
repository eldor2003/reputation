<?php

namespace App\Actions;

use App\Contracts\MentionRouteStorageInterface;
use App\Contracts\MentionRouterInterface;
use App\Contracts\RoutingContextBuilderInterface;
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

        $this->mentionRouteStorage->store($mentionId, $decision);

        MentionRouted::dispatch(
            $mentionId,
            $mention->project_id,
            $mention->source_id,
            now(),
        );
    }
}
