<?php

namespace Tests\Feature\Serp;

use App\DTO\RankingHistoryQueryDTO;
use App\Enums\PersonAliasType;
use App\Enums\PersonLanguage;
use App\Enums\SerpEngine;
use App\Models\Person;
use App\Models\PersonAlias;
use App\Models\Project;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use App\Services\RankingHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RankingHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_position_history_and_ranking_deltas_for_a_keyword(): void
    {
        $olderSnapshot = SerpSnapshot::query()->create([
            'search_engine' => SerpEngine::Google,
            'query' => 'Jane Analyst',
            'fetched_at' => now()->subDay(),
            'response_time_ms' => 100,
            'serpapi_search_id' => 'search-old',
            'metadata' => ['person_id' => 1],
        ]);

        SerpResult::query()->create([
            'serp_snapshot_id' => $olderSnapshot->id,
            'position' => 5,
            'title' => 'Older Result',
            'url' => 'https://example.com/profile',
            'snippet' => 'Older snippet',
            'fetched_at' => $olderSnapshot->fetched_at,
        ]);

        $newerSnapshot = SerpSnapshot::query()->create([
            'search_engine' => SerpEngine::Google,
            'query' => 'Jane Analyst',
            'fetched_at' => now(),
            'response_time_ms' => 120,
            'serpapi_search_id' => 'search-new',
            'metadata' => ['person_id' => 1],
        ]);

        SerpResult::query()->create([
            'serp_snapshot_id' => $newerSnapshot->id,
            'position' => 2,
            'title' => 'Newer Result',
            'url' => 'https://example.com/profile',
            'snippet' => 'Newer snippet',
            'fetched_at' => $newerSnapshot->fetched_at,
        ]);

        $service = $this->app->make(RankingHistoryService::class);
        $result = $service->query(new RankingHistoryQueryDTO(
            keyword: 'Jane Analyst',
            engine: SerpEngine::Google,
        ));

        $this->assertCount(2, $result->snapshots);
        $this->assertCount(2, $result->positionHistory);
        $this->assertCount(1, $result->rankingDeltas);
        $this->assertSame(3, $result->rankingDeltas[0]->delta);
        $this->assertSame(2, $result->rankingDeltas[0]->currentPosition);
        $this->assertSame(5, $result->rankingDeltas[0]->previousPosition);
    }

    #[Test]
    public function it_resolves_keywords_from_a_person_and_filters_by_date_range(): void
    {
        $project = Project::query()->create([
            'name' => 'Ranking Project',
            'slug' => 'ranking-project',
            'is_active' => true,
        ]);

        $person = Person::query()->create([
            'project_id' => $project->id,
            'full_name' => 'Alex Morgan',
            'primary_language' => PersonLanguage::English,
            'is_active' => true,
        ]);

        PersonAlias::query()->create([
            'person_id' => $person->id,
            'alias' => 'A. Morgan',
            'normalized_alias' => 'a morgan',
            'type' => PersonAliasType::Alias,
            'language' => PersonLanguage::English,
        ]);

        $inRangeSnapshot = SerpSnapshot::query()->create([
            'search_engine' => SerpEngine::Bing,
            'query' => 'Alex Morgan',
            'fetched_at' => now()->subHours(6),
            'response_time_ms' => 90,
            'serpapi_search_id' => 'search-in-range',
            'metadata' => ['person_id' => $person->id],
        ]);

        SerpResult::query()->create([
            'serp_snapshot_id' => $inRangeSnapshot->id,
            'position' => 1,
            'title' => 'In Range',
            'url' => 'https://example.com/in-range',
            'snippet' => 'In range snippet',
            'fetched_at' => $inRangeSnapshot->fetched_at,
        ]);

        $outOfRangeSnapshot = SerpSnapshot::query()->create([
            'search_engine' => SerpEngine::Bing,
            'query' => 'Alex Morgan',
            'fetched_at' => now()->subDays(10),
            'response_time_ms' => 90,
            'serpapi_search_id' => 'search-out-range',
            'metadata' => ['person_id' => $person->id],
        ]);

        SerpResult::query()->create([
            'serp_snapshot_id' => $outOfRangeSnapshot->id,
            'position' => 8,
            'title' => 'Out of Range',
            'url' => 'https://example.com/out-range',
            'snippet' => 'Out of range snippet',
            'fetched_at' => $outOfRangeSnapshot->fetched_at,
        ]);

        $service = $this->app->make(RankingHistoryService::class);
        $snapshots = $service->getHistoricalSnapshots(new RankingHistoryQueryDTO(
            personId: $person->id,
            engine: SerpEngine::Bing,
            from: now()->subDay(),
            to: now(),
        ));

        $this->assertCount(1, $snapshots);
        $this->assertSame('Alex Morgan', $snapshots->first()->query);
    }
}
