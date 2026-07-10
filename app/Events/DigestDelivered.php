<?php

namespace App\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;

class DigestDelivered
{
    use Dispatchable;

    public function __construct(
        public readonly int $projectId,
        public readonly string $digestType,
        public readonly int $itemCount,
        public readonly DateTimeInterface $timestamp,
    ) {}
}
