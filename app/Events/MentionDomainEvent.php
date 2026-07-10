<?php

namespace App\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;

abstract class MentionDomainEvent
{
    use Dispatchable;

    public function __construct(
        public readonly int $mentionId,
        public readonly int $projectId,
        public readonly int $sourceId,
        public readonly DateTimeInterface $timestamp,
    ) {}
}
