<?php

namespace Tests\Feature\Serp;

use App\Contracts\SerpSnapshotRepositoryInterface;
use App\DTO\SerpPositionDTO;
use App\DTO\SerpSnapshotDTO;
use App\Enums\SerpEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SerpSnapshotRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_snapshot_and_related_results(): void
    {
        Carbon::setTestNow('2026-07-09 12:00:00');

        $snapshot = $this->app->make(SerpSnapshotRepositoryInterface::class)->store(
            new SerpSnapshotDTO(
                searchEngine: SerpEngine::Bing,
                query: 'reputation test',
                fetchedAt: now(),
                responseTimeMs: 987.65,
                serpApiSearchId: 'bing-search-1',
                positions: [
                    new SerpPositionDTO(1, 'Bing Result', 'https://bing.example', 'Bing snippet'),
                ],
                screenshotPath: 'serp-screenshots/test.png',
                metadata: ['raw_html_url' => 'https://serpapi.com/raw.html'],
            ),
        );

        $this->assertSame('reputation test', $snapshot->query);
        $this->assertSame(SerpEngine::Bing, $snapshot->search_engine);
        $this->assertSame('serp-screenshots/test.png', $snapshot->screenshot_path);
        $this->assertCount(1, $snapshot->results);
        $this->assertSame('Bing Result', $snapshot->results->first()?->title);
    }
}
