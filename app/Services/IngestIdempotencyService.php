<?php

namespace App\Services;

use App\Contracts\IngestIdempotencyServiceInterface;
use App\Models\IngestIdempotencyKey;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

class IngestIdempotencyService implements IngestIdempotencyServiceInterface
{
    public function exists(string $idempotencyKey): bool
    {
        return IngestIdempotencyKey::query()
            ->where('idempotency_key', $idempotencyKey)
            ->exists();
    }

    public function acquireLock(string $idempotencyKey): ?Lock
    {
        $lock = Cache::lock(
            $this->lockName($idempotencyKey),
            (int) config('ingest.idempotency.lock_ttl_seconds'),
        );

        return $lock->get() ? $lock : null;
    }

    public function record(
        string $idempotencyKey,
        int $mentionId,
        string $provider,
        int $sourceId,
        string $externalId,
    ): void {
        IngestIdempotencyKey::query()->create([
            'idempotency_key' => $idempotencyKey,
            'mention_id' => $mentionId,
            'provider' => $provider,
            'source_id' => $sourceId,
            'external_id' => $externalId,
        ]);
    }

    private function lockName(string $idempotencyKey): string
    {
        return 'ingest:idempotency:'.hash('sha256', $idempotencyKey);
    }
}
