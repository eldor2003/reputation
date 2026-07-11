<?php

namespace App\Contracts;

interface MentionlyticsResponseCacheInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(string $method, string $path, array $query = []): ?array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function put(string $method, string $path, array $query, array $payload): void;
}
