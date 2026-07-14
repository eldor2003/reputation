<?php

namespace App\Services;

use App\Contracts\SerpScreenshotStorageInterface;
use Illuminate\Support\Facades\Storage;

class S3SerpScreenshotStorage implements SerpScreenshotStorageInterface
{
    public function store(string $snapshotUuid, string $contents, string $extension = 'png'): string
    {
        $relativePath = $this->buildRelativePath($snapshotUuid, $extension);

        $stored = Storage::disk($this->disk())->put($relativePath, $contents);

        if ($stored !== true) {
            throw new \RuntimeException(sprintf('Failed to store SERP asset at [%s].', $relativePath));
        }

        return $relativePath;
    }

    public function exists(string $relativePath): bool
    {
        try {
            return Storage::disk($this->disk())->exists($relativePath);
        } catch (\Throwable) {
            return false;
        }
    }

    public function delete(string $relativePath): bool
    {
        return Storage::disk($this->disk())->delete($relativePath);
    }

    private function buildRelativePath(string $snapshotUuid, string $extension): string
    {
        $basePath = trim((string) config('serpapi.screenshots.path'), '/');

        return $basePath.'/'.$snapshotUuid.'.'.$extension;
    }

    private function disk(): string
    {
        return (string) config('serpapi.screenshots.disk', 's3');
    }
}
