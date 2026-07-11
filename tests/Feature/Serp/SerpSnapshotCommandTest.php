<?php

namespace Tests\Feature\Serp;

use App\Actions\TakeSerpSnapshotAction;
use App\DTO\SerpPositionDTO;
use App\DTO\SerpSearchResultDTO;
use App\Contracts\SerpApiClientInterface;
use App\Enums\PersonLanguage;
use App\Enums\SerpEngine;
use App\Models\Person;
use App\Models\Project;
use App\Models\SerpSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SerpSnapshotCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'serpapi.api_key' => 'test-serp-key',
            'serpapi.snapshots.enabled' => true,
            'serpapi.snapshots.include_persons' => false,
            'serpapi.snapshots.queries' => ['brand reputation'],
            'serpapi.snapshots.engines' => ['google', 'yandex'],
        ]);
    }

    #[Test]
    public function it_captures_snapshots_for_all_configured_engines_and_queries(): void
    {
        $takeSnapshotAction = $this->createMock(TakeSerpSnapshotAction::class);
        $takeSnapshotAction->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function () {
                return SerpSnapshot::query()->make([
                    'search_engine' => SerpEngine::Google,
                    'query' => 'brand reputation',
                    'fetched_at' => now(),
                    'response_time_ms' => 100,
                    'serpapi_search_id' => 'search-1',
                ]);
            });

        $this->app->instance(TakeSerpSnapshotAction::class, $takeSnapshotAction);

        $this->artisan('serp:snapshot')
            ->assertSuccessful()
            ->expectsOutputToContain('created=2');
    }

    #[Test]
    public function it_includes_active_person_names_as_snapshot_queries(): void
    {
        config([
            'serpapi.snapshots.include_persons' => true,
            'serpapi.snapshots.queries' => [],
            'serpapi.snapshots.engines' => ['google'],
        ]);

        $project = Project::query()->create([
            'name' => 'SERP Project',
            'slug' => 'serp-project',
            'is_active' => true,
        ]);

        Person::query()->create([
            'project_id' => $project->id,
            'full_name' => 'Jane Analyst',
            'primary_language' => PersonLanguage::English,
            'is_active' => true,
        ]);

        $client = $this->createMock(SerpApiClientInterface::class);
        $client->expects($this->once())
            ->method('search')
            ->willReturn(new SerpSearchResultDTO(
                engine: SerpEngine::Google,
                query: 'Jane Analyst',
                serpApiSearchId: 'search-person-1',
                responseTimeMs: 100,
                positions: [
                    new SerpPositionDTO(1, 'Result', 'https://example.com', 'Snippet'),
                ],
                rawHtmlUrl: null,
            ));

        $this->app->instance(SerpApiClientInterface::class, $client);

        $this->artisan('serp:snapshot')
            ->assertSuccessful()
            ->expectsOutputToContain('created=1');
    }
}
