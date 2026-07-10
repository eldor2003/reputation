<?php

namespace App\Services\Routing;

use App\Contracts\RoutingContextBuilderInterface;
use App\DTO\RoutingAssessmentContextDTO;
use App\Exceptions\RoutingConfigurationException;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\MentionThreatResult;

class RoutingContextBuilder implements RoutingContextBuilderInterface
{
    public function build(int $mentionId): RoutingAssessmentContextDTO
    {
        $mention = Mention::query()
            ->with(['source', 'person'])
            ->find($mentionId);

        if ($mention === null) {
            throw new RoutingConfigurationException("Mention [{$mentionId}] was not found for routing.");
        }

        if ($mention->source === null) {
            throw new RoutingConfigurationException("Mention [{$mentionId}] is missing source context.");
        }

        $aiResult = AiResult::query()
            ->where('mention_id', $mentionId)
            ->latest('processed_at')
            ->first();

        if ($aiResult === null) {
            throw new RoutingConfigurationException("Mention [{$mentionId}] has no AI classification result.");
        }

        $threatResult = MentionThreatResult::query()
            ->where('mention_id', $mentionId)
            ->latest('assessed_at')
            ->first();

        if ($threatResult === null) {
            throw new RoutingConfigurationException("Mention [{$mentionId}] has no threat assessment result.");
        }

        return new RoutingAssessmentContextDTO(
            mention: $mention,
            aiResult: $aiResult,
            threatResult: $threatResult,
            source: $mention->source,
            person: $mention->person,
            evaluatedAt: now(),
        );
    }
}
