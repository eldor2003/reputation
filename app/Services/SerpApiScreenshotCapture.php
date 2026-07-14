<?php

namespace App\Services;

use App\Contracts\SerpScreenshotCaptureInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SerpApiScreenshotCapture implements SerpScreenshotCaptureInterface
{
    public function capture(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        try {
            $response = Http::timeout((int) config('serpapi.screenshots.timeout', 30))
                ->accept('*/*')
                ->get($url);
        } catch (\Throwable $exception) {
            Log::warning('SERP screenshot capture request failed.', [
                'url' => $url,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));

        if (str_starts_with($contentType, 'image/')) {
            return $response->body();
        }

        return null;
    }
}
