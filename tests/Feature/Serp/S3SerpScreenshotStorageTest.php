<?php

namespace Tests\Feature\Serp;

use App\Contracts\SerpScreenshotStorageInterface;
use App\Services\S3SerpScreenshotStorage;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class S3SerpScreenshotStorageTest extends TestCase
{
    #[Test]
    public function it_stores_screenshots_on_the_s3_disk(): void
    {
        Storage::fake('s3');

        config([
            'serpapi.screenshots.disk' => 's3',
            'serpapi.screenshots.path' => 'serp-screenshots',
        ]);

        $storage = $this->app->make(S3SerpScreenshotStorage::class);
        $path = $storage->store('snapshot-uuid-s3', 'fake-image-bytes', 'png');

        $this->assertSame('serp-screenshots/snapshot-uuid-s3.png', $path);
        $this->assertTrue($storage->exists($path));
        Storage::disk('s3')->assertExists($path);
    }

    #[Test]
    public function it_resolves_s3_storage_from_configuration(): void
    {
        Storage::fake('s3');

        config([
            'serpapi.screenshots.disk' => 's3',
            'serpapi.screenshots.path' => 'serp-screenshots',
        ]);

        $storage = $this->app->make(SerpScreenshotStorageInterface::class);

        $this->assertInstanceOf(S3SerpScreenshotStorage::class, $storage);
    }
}
