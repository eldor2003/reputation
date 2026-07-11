<?php

namespace App\Services\Mentionlytics;

use App\Contracts\MentionlyticsResponseCacheInterface;
use Illuminate\Support\Facades\Cache;

class MentionlyticsResponseCache implements MentionlyticsResponseCacheInterface
{
    private const CACHE_PREFIX = 'mentionlytics.response.';

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    public function get(string $method, string $path, array $query = []): ?array
    {
        /** @var array<string, mixed>|null $payload */
        $payload = Cache::get($this->cacheKey($method, $path, $query));

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $payload
     */
    public function put(string $method, string $path, array $query, array $payload): void
    {
        Cache::put(
            $this->cacheKey($method, $path, $query),
            $payload,
            now()->addSeconds((int) config('mentionlytics.response_cache_seconds')),
        );
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function cacheKey(string $method, string $path, array $query): string
    {
        ksort($query);

        return self::CACHE_PREFIX.hash('sha256', strtoupper($method).'|'.$path.'|'.json_encode($query));
    }
}
