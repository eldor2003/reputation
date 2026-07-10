<?php

namespace App\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;

class MentionClustered extends MentionDomainEvent
{
    use Dispatchable;

    public function __construct(
        int $mentionId,
        int $projectId,
        int $sourceId,
        public readonly int $clusterId,
        DateTimeInterface $timestamp,
    ) {
        parent::__construct($mentionId, $projectId, $sourceId, $timestamp);
    }
}
