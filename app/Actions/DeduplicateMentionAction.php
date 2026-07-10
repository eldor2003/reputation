<?php

namespace App\Actions;

use App\Contracts\DeduplicationEngineInterface;
use App\Contracts\MentionClusterBuilderInterface;
use App\DTO\DeduplicationResultDTO;
use App\DTO\NormalizedMentionDTO;
use App\Events\MentionClustered;
use App\Events\MentionDeduplicated;

class DeduplicateMentionAction
{
    public function __construct(
        private readonly DeduplicationEngineInterface $deduplicationEngine,
        private readonly MentionClusterBuilderInterface $clusterBuilder,
    ) {}

    public function execute(int $mentionId, NormalizedMentionDTO $mention): DeduplicationResultDTO
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
