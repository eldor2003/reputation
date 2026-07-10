<?php

namespace App\Services;

use App\Contracts\SerpScreenshotStorageInterface;
use Illuminate\Support\Facades\Storage;

class LocalSerpScreenshotStorage implements SerpScreenshotStorageInterface
{
    public function store(string $snapshotUuid, string $contents, string $extension = 'png'): string
    {
        $relativePath = $this->buildRelativePath($snapshotUuid, $extension);

        Storage::disk($this->disk())->put($relativePath, $contents);

        return $relativePath;
    }

    public function exists(string $relativePath): bool
    {
        return Storage::disk($this->disk())->exists($relativePath);
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
        return (string) config('serpapi.screenshots.disk', 'local');
    }
}
