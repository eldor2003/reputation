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

        return $this->renderPreviewPng($response->body(), $url);
    }

    private function renderPreviewPng(string $html, string $sourceUrl): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $width = 800;
        $height = 200;
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            return null;
        }

        $background = imagecolorallocate($image, 245, 245, 245);
        $textColor = imagecolorallocate($image, 30, 30, 30);
        imagefilledrectangle($image, 0, 0, $width, $height, $background);

        $label = 'SERP snapshot archive';
        $hash = substr(hash('sha256', $html), 0, 16);
        $source = mb_substr($sourceUrl, 0, 90);

        imagestring($image, 3, 10, 20, $label, $textColor);
        imagestring($image, 2, 10, 50, 'Source: '.$source, $textColor);
        imagestring($image, 2, 10, 80, 'Content hash: '.$hash, $textColor);
        imagestring($image, 2, 10, 110, 'Bytes: '.strlen($html), $textColor);

        ob_start();
        imagepng($image);
        $png = ob_get_clean() ?: null;
        imagedestroy($image);

        return is_string($png) && $png !== '' ? $png : null;
    }
}
