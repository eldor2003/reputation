<?php

namespace Tests\Feature\Serp;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SerpMigrationsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_serp_snapshot_and_result_tables(): void
    {
        $this->assertTrue(Schema::hasTable('serp_snapshots'));
        $this->assertTrue(Schema::hasTable('serp_results'));
        $this->assertTrue(Schema::hasColumns('serp_snapshots', [
            'uuid',
            'search_engine',
            'query',
            'fetched_at',
            'response_time_ms',
            'serpapi_search_id',
            'screenshot_path',
            'metadata',
        ]));
        $this->assertTrue(Schema::hasColumns('serp_results', [
            'serp_snapshot_id',
            'position',
            'title',
            'url',
            'snippet',
            'fetched_at',
        ]));
    }
}
