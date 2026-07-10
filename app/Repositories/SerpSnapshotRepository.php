<?php

namespace App\Repositories;

use App\Contracts\SerpSnapshotRepositoryInterface;
use App\DTO\SerpPositionDTO;
use App\DTO\SerpSnapshotDTO;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use Illuminate\Support\Facades\DB;

class SerpSnapshotRepository implements SerpSnapshotRepositoryInterface
{
    public function store(SerpSnapshotDTO $snapshot): SerpSnapshot
    {
        return DB::transaction(function () use ($snapshot): SerpSnapshot {
            $storedSnapshot = SerpSnapshot::query()->create([
                'search_engine' => $snapshot->searchEngine->value,
                'query' => $snapshot->query,
                'fetched_at' => $snapshot->fetchedAt,
                'response_time_ms' => $snapshot->responseTimeMs,
                'serpapi_search_id' => $snapshot->serpApiSearchId,
                'screenshot_path' => $snapshot->screenshotPath,
                'metadata' => $snapshot->metadata,
            ]);

            foreach ($snapshot->positions as $position) {
                $this->storeResult($storedSnapshot, $position, $snapshot->fetchedAt);
            }

            return $storedSnapshot->load('results');
        });
    }

    private function storeResult(
        SerpSnapshot $snapshot,
        SerpPositionDTO $position,
        \Carbon\Carbon $fetchedAt,
    ): SerpResult {
        return SerpResult::query()->create([
            'serp_snapshot_id' => $snapshot->id,
            'position' => $position->position,
            'title' => $position->title,
            'url' => $position->url,
            'snippet' => $position->snippet,
            'fetched_at' => $fetchedAt,
        ]);
    }
}
