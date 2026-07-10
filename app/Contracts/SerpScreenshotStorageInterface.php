<?php

namespace App\Contracts;

interface SerpScreenshotStorageInterface
{
    public function store(string $snapshotUuid, string $contents, string $extension = 'png'): string;

    public function exists(string $relativePath): bool;

    public function delete(string $relativePath): bool;
}
