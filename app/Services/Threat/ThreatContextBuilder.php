<?php

namespace App\Services\Threat;

use App\Contracts\ThreatContextBuilderInterface;
use App\DTO\ThreatAssessmentContextDTO;
use App\Exceptions\ThreatConfigurationException;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\MentionClusterItem;
use App\Models\SerpResult;

class ThreatContextBuilder implements ThreatContextBuilderInterface
{
    public function build(int $mentionId): ThreatAssessmentContextDTO
    {
        $mention = Mention::query()
            ->with(['source', 'person'])
            ->find($mentionId);

        if ($mention === null) {
            throw new ThreatConfigurationException("Mention [{$mentionId}] was not found for threat assessment.");
        }

        if ($mention->source === null) {
            throw new ThreatConfigurationException("Mention [{$mentionId}] is missing source context.");
        }

        $aiResult = AiResult::query()
            ->where('mention_id', $mentionId)
            ->latest('processed_at')
            ->first();

        if ($aiResult === null) {
            throw new ThreatConfigurationException("Mention [{$mentionId}] has no AI classification result.");
        }

        return new ThreatAssessmentContextDTO(
            mention: $mention,
            aiResult: $aiResult,
            source: $mention->source,
            clusterSize: $this->resolveClusterSize($mention),
            serpTopPosition: $this->resolveSerpTopPosition($mention),
            person: $mention->person,
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
