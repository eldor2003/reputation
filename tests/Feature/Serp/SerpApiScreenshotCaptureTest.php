<?php

namespace Tests\Feature\Serp;

use App\Contracts\SerpScreenshotCaptureInterface;
use App\Services\SerpApiScreenshotCapture;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SerpApiScreenshotCaptureTest extends TestCase
{
    #[Test]
    public function it_returns_image_bytes_when_capture_url_returns_an_image(): void
    {
        Http::fake([
            'https://serpapi.com/screenshot.png' => Http::response('image-bytes', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $capture = $this->app->make(SerpApiScreenshotCapture::class);
        $bytes = $capture->capture('https://serpapi.com/screenshot.png');

        $this->assertSame('image-bytes', $bytes);
    }

    #[Test]
    public function it_generates_a_png_preview_when_capture_url_returns_html(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is required for HTML preview screenshots.');
        }

        Http::fake([
            'https://serpapi.com/raw.html' => Http::response('<html><body>SERP</body></html>', 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $capture = $this->app->make(SerpApiScreenshotCapture::class);
        $bytes = $capture->capture('https://serpapi.com/raw.html');

        $this->assertIsString($bytes);
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", (string) $bytes);
    }

    #[Test]
    public function it_returns_null_when_capture_url_is_unavailable(): void
    {
        Http::fake([
            'https://serpapi.com/missing.png' => Http::response('', 404),
        ]);

        $capture = $this->app->make(SerpScreenshotCaptureInterface::class);
        $bytes = $capture->capture('https://serpapi.com/missing.png');

        $this->assertNull($bytes);
    }
}
