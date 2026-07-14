<?php

namespace Tests\Feature\Serp;

use App\Actions\TakeSerpSnapshotAction;
use App\Contracts\SerpApiClientInterface;
use App\Contracts\SerpScreenshotCaptureInterface;
use App\Contracts\SerpScreenshotStorageInterface;
use App\DTO\SerpPositionDTO;
use App\DTO\SerpSearchRequestDTO;
use App\DTO\SerpSearchResultDTO;
use App\Enums\SerpEngine;
use App\Models\SerpResult;
use App\Models\SerpSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TakeSerpSnapshotActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_takes_a_snapshot_and_persists_results(): void
    {
        $client = $this->createMock(SerpApiClientInterface::class);
        $client->expects($this->once())
            ->method('search')
            ->with($this->callback(function (SerpSearchRequestDTO $request): bool {
                return $request->query === 'brand reputation'
                    && $request->engine === SerpEngine::Google;
            }))
            ->willReturn(new SerpSearchResultDTO(
                engine: SerpEngine::Google,
                query: 'brand reputation',
                serpApiSearchId: 'search-999',
                responseTimeMs: 1234.56,
                positions: [
                    new SerpPositionDTO(1, 'Title 1', 'https://example.com/1', 'Snippet 1'),
                    new SerpPositionDTO(2, 'Title 2', 'https://example.com/2', null),
                ],
                rawHtmlUrl: 'https://serpapi.com/raw.html',
                screenshotUrl: 'https://serpapi.com/screenshot.png',
            ));

        $this->app->instance(SerpApiClientInterface::class, $client);

        $screenshotCapture = $this->createMock(SerpScreenshotCaptureInterface::class);
        $screenshotCapture->method('capture')->willReturn('png-bytes');
        $this->app->instance(SerpScreenshotCaptureInterface::class, $screenshotCapture);

        $screenshotStorage = $this->createMock(SerpScreenshotStorageInterface::class);
        $screenshotStorage->expects($this->once())
            ->method('store')
            ->willReturn('serp-screenshots/test.png');
        $this->app->instance(SerpScreenshotStorageInterface::class, $screenshotStorage);

        $snapshot = $this->app->make(TakeSerpSnapshotAction::class)->execute(
            new SerpSearchRequestDTO('brand reputation', SerpEngine::Google),
        );

        $this->assertInstanceOf(SerpSnapshot::class, $snapshot);
        $this->assertDatabaseCount('serp_snapshots', 1);
        $this->assertDatabaseCount('serp_results', 2);
        $this->assertSame('brand reputation', $snapshot->query);
        $this->assertSame(SerpEngine::Google, $snapshot->search_engine);
        $this->assertSame('search-999', $snapshot->serpapi_search_id);
        $this->assertSame('serp-screenshots/test.png', $snapshot->screenshot_path);

        $firstResult = SerpResult::query()->where('position', 1)->first();
        $this->assertNotNull($firstResult);
        $this->assertSame('Title 1', $firstResult->title);
        $this->assertSame('https://example.com/1', $firstResult->url);
        $this->assertSame('Snippet 1', $firstResult->snippet);
    }
}
