<?php

namespace App\Actions;

use App\Contracts\DeduplicationEngineInterface;
use App\Contracts\MentionClusterBuilderInterface;
use App\DTO\DeduplicationResultDTO;
use App\DTO\NormalizedMentionDTO;
use App\Events\MentionClustered;
use App\Events\MentionDeduplicated;
use Illuminate\Support\Facades\Cache;

class DeduplicateMentionAction
{
    public function __construct(
        private readonly DeduplicationEngineInterface $deduplicationEngine,
        private readonly MentionClusterBuilderInterface $clusterBuilder,
    ) {}

    public function execute(int $mentionId, NormalizedMentionDTO $mention): DeduplicationResultDTO
    {
        $fingerprint = $this->clusterBuilder->buildFingerprint($mention);
        $lockKey = sprintf(
            'dedup:content:%d:%s',
            $mention->projectId,
            $fingerprint->contentFingerprint,
        );

        return Cache::lock($lockKey, 30)->block(10, function () use ($mentionId, $mention): DeduplicationResultDTO {
            return $this->deduplicateWithinLock($mentionId, $mention);
        });
    }

    private function deduplicateWithinLock(int $mentionId, NormalizedMentionDTO $mention): DeduplicationResultDTO
    {
        $result = $this->deduplicationEngine->check($mention);
        $result = $this->clusterBuilder->assign($mentionId, $mention, $result);

        MentionDeduplicated::dispatch(
            $mentionId,
            $mention->projectId,
            $mention->sourceId,
            now(),
        );

        if ($result->clusterId !== null) {
            MentionClustered::dispatch(
                $mentionId,
                $mention->projectId,
                $mention->sourceId,
                $result->clusterId,
                now(),
            );
        }

        return $result;
    }
}
