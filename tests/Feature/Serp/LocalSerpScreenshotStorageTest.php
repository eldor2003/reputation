<?php

namespace Tests\Feature\Serp;

use App\Contracts\SerpScreenshotStorageInterface;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocalSerpScreenshotStorageTest extends TestCase
{
    #[Test]
    public function it_stores_screenshots_on_the_local_disk(): void
    {
        Storage::fake('local');

        config([
            'serpapi.screenshots.disk' => 'local',
            'serpapi.screenshots.path' => 'serp-screenshots',
        ]);

        $storage = $this->app->make(SerpScreenshotStorageInterface::class);
        $path = $storage->store('snapshot-uuid-1', 'fake-image-bytes', 'png');

        $this->assertSame('serp-screenshots/snapshot-uuid-1.png', $path);
        $this->assertTrue($storage->exists($path));
        Storage::disk('local')->assertExists($path);
    }
}
