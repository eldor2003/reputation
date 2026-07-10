<?php

namespace App\Services;

use App\Exceptions\MentionlyticsApiException;
use App\Support\LogSanitizer;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MentionlyticsTokenManager
{
    private const CACHE_KEY = 'mentionlytics.bearer_token';

    private bool $lastRefreshUsed = false;

    public function wasRefreshUsedOnLastOperation(): bool
    {
        return $this->lastRefreshUsed;
    }

    public function getBearerToken(bool $forceRefresh = false): string
    {
        $this->lastRefreshUsed = false;

        if ($forceRefresh) {
            $this->lastRefreshUsed = true;

            return $this->refreshBearerToken();
        }

        $configured = config('mentionlytics.bearer_token');

        if (is_string($configured) && $configured !== '') {
            $cached = Cache::get(self::CACHE_KEY);

            if ($cached === $configured) {
                return $configured;
            }

            Cache::forget(self::CACHE_KEY);
            $this->storeBearerToken($configured);

            return $configured;
        }

        $cached = Cache::get(self::CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $this->lastRefreshUsed = true;

        return $this->refreshBearerToken();
    }

    public function refreshBearerToken(): string
    {
        $refreshToken = config('mentionlytics.refresh_token');

        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new MentionlyticsApiException('Mentionlytics refresh token is not configured.');
        }

        $lastException = null;

        foreach ($this->refreshEndpoints() as $endpoint) {
            try {
                $response = Http::baseUrl(rtrim((string) config('mentionlytics.base_url'), '/'))
                    ->timeout((int) config('mentionlytics.timeout'))
                    ->acceptJson()
                    ->post($endpoint, [
                        'refresh_token' => $refreshToken,
                    ])
                    ->throw();

                /** @var array<string, mixed>|null $payload */
                $payload = $response->json();

                if (! is_array($payload)) {
                    throw new MentionlyticsApiException('Mentionlytics token refresh returned invalid JSON.');
                }

                $bearerToken = $this->extractBearerToken($payload);

                if ($bearerToken === null) {
                    throw new MentionlyticsApiException('Mentionlytics token refresh response is missing bearer token.');
                }

                $this->storeBearerToken($bearerToken);

                return $bearerToken;
            } catch (RequestException $exception) {
                $apiMessage = $exception->response?->json('message');

                if (is_string($apiMessage) && $apiMessage !== '') {
                    $lastException = new MentionlyticsApiException(
                        'Mentionlytics token refresh failed: '.$apiMessage,
                        $exception,
                    );

                    if (in_array($apiMessage, ['invalid_refresh_token', 'missing_refresh_token'], true)) {
                        break;
                    }

                    continue;
                }

                $lastException = $exception;
            }
        }

        Log::error('Mentionlytics token refresh failed.', [
            'status' => $lastException instanceof RequestException ? $lastException->response?->status() : null,
            'body' => $lastException instanceof RequestException ? $lastException->response?->json() : null,
            'exception' => LogSanitizer::redactSecrets($lastException?->getMessage() ?? 'unknown'),
        ]);

        if ($lastException instanceof MentionlyticsApiException) {
            throw $lastException;
        }

        throw new MentionlyticsApiException(
            'Mentionlytics token refresh failed.',
            $lastException instanceof \Throwable ? $lastException : null,
        );
    }

    public function invalidateCachedBearerToken(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractBearerToken(array $payload): ?string
    {
        foreach (['bearer_token', 'access_token', 'token'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        /** @var array<string, mixed>|null $data */
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : null;

        if ($data !== null) {
            foreach (['bearer_token', 'access_token', 'token'] as $key) {
                $value = $data[$key] ?? null;

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function storeBearerToken(string $token): void
    {
        Cache::put(
            self::CACHE_KEY,
            $token,
            now()->addSeconds((int) config('mentionlytics.bearer_ttl_seconds')),
        );
    }

    /**
     * @return list<string>
     */
    private function refreshEndpoints(): array
    {
        return [
            '/auth/refresh',
        ];
    }
}
