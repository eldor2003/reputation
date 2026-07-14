<?php

namespace Tests\Feature\Serp;

use App\DTO\SerpPositionDTO;
use App\DTO\SerpSearchRequestDTO;
use App\DTO\SerpSearchResultDTO;
use App\Enums\SerpEngine;
use App\Services\SerpSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SerpSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_html_archive_when_screenshot_is_unavailable(): void
    {
        Http::fake([
            'https://serpapi.com/raw.html' => Http::response('<html><body>SERP archive</body></html>', 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $client = $this->createMock(\App\Contracts\SerpApiClientInterface::class);
        $client->method('search')->willReturn(new SerpSearchResultDTO(
            engine: SerpEngine::Google,
            query: 'brand reputation',
            serpApiSearchId: 'search-archive',
            responseTimeMs: 500.0,
            positions: [
                new SerpPositionDTO(1, 'Title', 'https://example.com', 'Snippet'),
            ],
            rawHtmlUrl: 'https://serpapi.com/raw.html',
            screenshotUrl: null,
        ));
        $this->app->instance(\App\Contracts\SerpApiClientInterface::class, $client);

        $snapshot = $this->app->make(SerpSnapshotService::class)->takeSnapshot(
            new SerpSearchRequestDTO('brand reputation', SerpEngine::Google),
        );

        $this->assertNull($snapshot->screenshot_path);
        $this->assertFalse($snapshot->metadata['screenshot_available'] ?? true);
        $this->assertSame('serp-screenshots/'.$snapshot->uuid.'.html', $snapshot->metadata['archive_path'] ?? null);
    }
}
