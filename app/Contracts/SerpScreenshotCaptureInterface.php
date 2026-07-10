<?php

namespace App\Contracts;

interface SerpScreenshotCaptureInterface
{
    /**
     * Capture a screenshot for the given URL.
     *
     * Returns raw image bytes or null when capture is unavailable.
     */
    public function capture(string $url): ?string;
}
