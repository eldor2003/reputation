<?php

namespace App\Events;

use DateTimeInterface;

class MentionThreatAssessed extends MentionDomainEvent
{
    public function __construct(
        int $mentionId,
        int $projectId,
        int $sourceId,
        public readonly string $threatLevel,
        public readonly float $threatScore,
        DateTimeInterface $timestamp,
    ) {
        parent::__construct($mentionId, $projectId, $sourceId, $timestamp);
    }
}
