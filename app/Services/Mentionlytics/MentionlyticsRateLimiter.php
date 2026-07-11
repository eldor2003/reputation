<?php

namespace App\Services\Mentionlytics;

use App\Contracts\MentionlyticsRateLimiterInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

class MentionlyticsRateLimiter implements MentionlyticsRateLimiterInterface
{
    private const SECOND_KEY = 'mentionlytics.ratelimit.second.';

    private const MINUTE_KEY = 'mentionlytics.ratelimit.minute.';

    private ?int $headerRemaining = null;

    private ?int $headerResetAt = null;

    public function acquire(): void
    {
        $this->respectHeaderBackoff();

        while (! $this->canSendRequest()) {
            usleep(50_000);
        }

        $this->recordLocalRequest();
    }

    public function recordResponse(Response $response): void
    {
        $this->headerRemaining = $this->readHeaderInt($response, 'RateLimit-Remaining');
        $this->headerResetAt = $this->readHeaderInt($response, 'RateLimit-Reset');

        if ($this->headerResetAt !== null && $this->headerRemaining !== null && $this->headerRemaining <= 0) {
            $this->sleepUntil($this->headerResetAt);
        }
    }

    public function delayForRateLimitResponse(Response $response, int $attempt): int
    {
        $retryAfter = $this->readHeaderInt($response, 'Retry-After');

        if ($retryAfter !== null && $retryAfter > 0) {
            return $retryAfter * 1000;
        }

        $baseDelay = (int) config('mentionlytics.retry.base_delay_ms');
        $maxDelay = (int) config('mentionlytics.retry.max_delay_ms');

        return min($maxDelay, $baseDelay * (2 ** max(0, $attempt - 1)));
    }

    private function canSendRequest(): bool
    {
        $secondLimit = (int) config('mentionlytics.rate_limit.per_second');
        $minuteLimit = (int) config('mentionlytics.rate_limit.per_minute');

        $secondBucket = now()->format('YmdHis');
        $minuteBucket = now()->format('YmdHi');

        $secondCount = (int) Cache::get(self::SECOND_KEY.$secondBucket, 0);
        $minuteCount = (int) Cache::get(self::MINUTE_KEY.$minuteBucket, 0);

        if ($secondCount >= $secondLimit) {
            return false;
        }

        if ($minuteCount >= $minuteLimit) {
            return false;
        }

        if ($this->headerRemaining !== null && $this->headerRemaining <= 0) {
            return false;
        }

        return true;
    }

    private function recordLocalRequest(): void
    {
        $secondBucket = now()->format('YmdHis');
        $minuteBucket = now()->format('YmdHi');

        Cache::add(self::SECOND_KEY.$secondBucket, 0, now()->addSeconds(2));
        Cache::add(self::MINUTE_KEY.$minuteBucket, 0, now()->addMinutes(2));

        Cache::increment(self::SECOND_KEY.$secondBucket);
        Cache::increment(self::MINUTE_KEY.$minuteBucket);
    }

    private function respectHeaderBackoff(): void
    {
        if ($this->headerRemaining !== null && $this->headerRemaining > 0) {
            return;
        }

        if ($this->headerResetAt === null) {
            return;
        }

        $this->sleepUntil($this->headerResetAt);
    }

    private function sleepUntil(int $unixTimestamp): void
    {
        $delayMs = max(0, ($unixTimestamp - now()->timestamp) * 1000);

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }

    private function readHeaderInt(Response $response, string $header): ?int
    {
        $value = $response->header($header);

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
