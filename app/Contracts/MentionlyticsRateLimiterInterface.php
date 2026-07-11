<?php

namespace App\Contracts;

use Illuminate\Http\Client\Response;

interface MentionlyticsRateLimiterInterface
{
    public function acquire(): void;

    public function recordResponse(Response $response): void;

    public function delayForRateLimitResponse(Response $response, int $attempt): int;
}
