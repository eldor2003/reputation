<?php

namespace App\Contracts;

use Illuminate\Contracts\Cache\Lock;

interface IngestIdempotencyServiceInterface
{
    public function exists(string $idempotencyKey): bool;

    public function acquireLock(string $idempotencyKey): ?Lock;

    public function record(
        string $idempotencyKey,
        int $mentionId,
        string $provider,
        int $sourceId,
        string $externalId,
    ): void;
}
