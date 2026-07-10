<?php

namespace App\Services\Delivery;

use App\Contracts\DeliveryContextBuilderInterface;
use App\DTO\DeliveryContextDTO;
use App\Enums\ModerationAction;
use App\Exceptions\DeliveryConfigurationException;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\MentionClusterItem;
use App\Models\MentionRoute;
use App\Models\MentionThreatResult;
use App\Models\ModerationLog;
use App\Models\SerpResult;

class DeliveryContextBuilder implements DeliveryContextBuilderInterface
{
    public function buildForApproval(int $mentionId): DeliveryContextDTO
    {
        $context = $this->buildForMention($mentionId);

        $moderationLog = ModerationLog::query()
            ->where('mention_id', $mentionId)
            ->where('action', ModerationAction::Approve)
            ->latest('created_at')
            ->first();

        if ($moderationLog === null) {
            throw new DeliveryConfigurationException("Mention [{$mentionId}] has no approval moderation log.");
        }

        return new DeliveryContextDTO(
            mention: $context->mention,
            aiResult: $context->aiResult,
            threatResult: $context->threatResult,
            source: $context->source,
            person: $context->person,
            route: $context->route,
            moderationLog: $moderationLog,
            clusterSize: $context->clusterSize,
            serpPosition: $context->serpPosition,
            timestamp: now(),
        );
    }

    public function buildForMention(int $mentionId): DeliveryContextDTO
    {
        $mention = Mention::query()
            ->with(['source', 'person', 'route'])
            ->find($mentionId);

        if ($mention === null) {
            throw new DeliveryConfigurationException("Mention [{$mentionId}] was not found for delivery.");
        }

        if ($mention->source === null) {
            throw new DeliveryConfigurationException("Mention [{$mentionId}] is missing source context.");
        }

        $aiResult = AiResult::query()
            ->where('mention_id', $mentionId)
            ->latest('processed_at')
            ->first();

        if ($aiResult === null) {
            throw new DeliveryConfigurationException("Mention [{$mentionId}] has no AI classification result.");
        }

        $threatResult = MentionThreatResult::query()
            ->where('mention_id', $mentionId)
            ->latest('assessed_at')
            ->first();

        if ($threatResult === null) {
            throw new DeliveryConfigurationException("Mention [{$mentionId}] has no threat assessment result.");
        }

        $route = $mention->route ?? MentionRoute::query()->where('mention_id', $mentionId)->first();

        return new DeliveryContextDTO(
            mention: $mention,
            aiResult: $aiResult,
            threatResult: $threatResult,
            source: $mention->source,
            person: $mention->person,
            route: $route,
            moderationLog: null,
            clusterSize: $this->resolveClusterSize($mention),
            serpPosition: $this->resolveSerpTopPosition($mention),
            timestamp: now(),
        );
    }

    private function resolveClusterSize(Mention $mention): int
    {
        if ($mention->mention_cluster_id === null) {
            return 1;
        }

        return max(1, MentionClusterItem::query()
            ->where('mention_cluster_id', $mention->mention_cluster_id)
            ->count());
    }

    private function resolveSerpTopPosition(Mention $mention): ?int
    {
        if (! is_string($mention->url) || $mention->url === '') {
            return null;
        }

        $host = parse_url($mention->url, PHP_URL_HOST);

        $query = SerpResult::query()->orderBy('position');

        if (is_string($host) && $host !== '') {
            $query->where(function ($builder) use ($mention, $host): void {
                $builder->where('url', $mention->url)
                    ->orWhere('url', 'like', '%'.$host.'%');
            });
        } else {
            $query->where('url', $mention->url);
        }

        $position = $query->value('position');

        return is_numeric($position) ? (int) $position : null;
    }
}
