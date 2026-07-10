<?php

namespace App\Actions;

use App\Contracts\ThreatContextBuilderInterface;
use App\Contracts\ThreatEngineInterface;
use App\Contracts\ThreatResultStorageInterface;
use App\DTO\ThreatResultDTO;
use App\Events\MentionThreatAssessed;
use App\Models\MentionThreatResult;

class EvaluateMentionThreatAction
{
    public function __construct(
        private readonly ThreatContextBuilderInterface $contextBuilder,
        private readonly ThreatEngineInterface $threatEngine,
        private readonly ThreatResultStorageInterface $threatResultStorage,
    ) {}

    public function execute(int $mentionId): ThreatResultDTO
    {
        $context = $this->contextBuilder->build($mentionId);
        $result = $this->threatEngine->evaluate($context);

        $stored = $this->threatResultStorage->store(
            $mentionId,
            $context->aiResult->id,
            $result,
        );

        MentionThreatAssessed::dispatch(
            $mentionId,
            $context->mention->project_id,
            $context->mention->source_id,
            $stored->threat_level->value,
            $stored->threat_score,
            now(),
        );

        return $result;
    }
}
