<?php

namespace App\Services;

use App\DTO\RankingDeltaDTO;
use App\DTO\RankingHistoryQueryDTO;
use App\DTO\RankingHistoryResultDTO;
use App\DTO\RankingPositionPointDTO;
use App\Models\Person;
use App\Models\PersonAlias;
use App\Models\SerpSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RankingHistoryService
{
    public function query(RankingHistoryQueryDTO $query): RankingHistoryResultDTO
    {
        $snapshots = $this->getHistoricalSnapshots($query);
        $positionHistory = $this->getPositionHistory($query);
        $rankingDeltas = $this->getRankingDeltas($query);

        return new RankingHistoryResultDTO(
            snapshots: $snapshots->all(),
            positionHistory: $positionHistory,
            rankingDeltas: $rankingDeltas,
        );
    }

    /**
     * @return Collection<int, SerpSnapshot>
     */
    public function getHistoricalSnapshots(RankingHistoryQueryDTO $query): Collection
    {
        return $this->applyFilters(SerpSnapshot::query()->with('results'), $query)
            ->orderByDesc('fetched_at')
            ->get();
    }

    /**
     * @return list<RankingPositionPointDTO>
     */
    public function getPositionHistory(RankingHistoryQueryDTO $query): array
    {
        $snapshots = $this->getHistoricalSnapshots($query);
        $history = [];

        foreach ($snapshots as $snapshot) {
            foreach ($snapshot->results as $result) {
                $history[] = new RankingPositionPointDTO(
                    fetchedAt: $snapshot->fetched_at,
                    position: $result->position,
                    url: $result->url,
                    title: $result->title,
                    engine: $snapshot->search_engine,
                    query: $snapshot->query,
                    snapshotId: $snapshot->id,
                );
            }
        }

        usort(
            $history,
            fn (RankingPositionPointDTO $left, RankingPositionPointDTO $right): int => $right->fetchedAt <=> $left->fetchedAt,
        );

        return $history;
    }

    /**
     * @return list<RankingDeltaDTO>
     */
    public function getRankingDeltas(RankingHistoryQueryDTO $query): array
    {
        $snapshots = $this->getHistoricalSnapshots($query)
            ->sortBy('fetched_at')
            ->values();

        if ($snapshots->count() < 2) {
            return [];
        }

        $deltas = [];

        for ($index = 1; $index < $snapshots->count(); $index++) {
            /** @var SerpSnapshot $previousSnapshot */
            $previousSnapshot = $snapshots[$index - 1];
            /** @var SerpSnapshot $currentSnapshot */
            $currentSnapshot = $snapshots[$index];

            if ($previousSnapshot->search_engine !== $currentSnapshot->search_engine) {
                continue;
            }

            if ($previousSnapshot->query !== $currentSnapshot->query) {
                continue;
            }

            $previousByUrl = $previousSnapshot->results->keyBy('url');
            $currentByUrl = $currentSnapshot->results->keyBy('url');

            foreach ($currentByUrl as $url => $currentResult) {
                $previousResult = $previousByUrl->get($url);

                if ($previousResult === null) {
                    continue;
                }

                $delta = $previousResult->position - $currentResult->position;

                if ($delta === 0) {
                    continue;
                }

                $deltas[] = new RankingDeltaDTO(
                    currentPosition: $currentResult->position,
                    previousPosition: $previousResult->position,
                    delta: $delta,
                    url: (string) $url,
                    query: $currentSnapshot->query,
                    engine: $currentSnapshot->search_engine,
                );
            }
        }

        return $deltas;
    }

    /**
     * @param  Builder<SerpSnapshot>  $builder
     * @return Builder<SerpSnapshot>
     */
    private function applyFilters(Builder $builder, RankingHistoryQueryDTO $query): Builder
    {
        if ($query->engine !== null) {
            $builder->where('search_engine', $query->engine->value);
        }

        if ($query->from !== null) {
            $builder->where('fetched_at', '>=', $query->from);
        }

        if ($query->to !== null) {
            $builder->where('fetched_at', '<=', $query->to);
        }

        $keywords = $this->resolveKeywords($query);

        if ($keywords !== []) {
            $builder->where(function (Builder $keywordQuery) use ($keywords, $query): void {
                foreach ($keywords as $keyword) {
                    $keywordQuery->orWhere('query', $keyword);
                }

                if ($query->personId !== null) {
                    $keywordQuery->orWhere('metadata->person_id', $query->personId);
                }
            });
        }

        return $builder;
    }

    /**
     * @return list<string>
     */
    private function resolveKeywords(RankingHistoryQueryDTO $query): array
    {
        $keywords = [];

        if (is_string($query->keyword) && trim($query->keyword) !== '') {
            $keywords[] = trim($query->keyword);
        }

        if ($query->personId === null) {
            return array_values(array_unique($keywords));
        }

        $person = Person::query()->find($query->personId);

        if ($person === null) {
            return array_values(array_unique($keywords));
        }

        $keywords[] = trim($person->full_name);

        $aliases = PersonAlias::query()
            ->where('person_id', $person->id)
            ->pluck('alias');

        foreach ($aliases as $alias) {
            if (is_string($alias) && trim($alias) !== '') {
                $keywords[] = trim($alias);
            }
        }

        return array_values(array_unique(array_filter($keywords)));
    }
}
