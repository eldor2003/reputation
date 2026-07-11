<?php

namespace App\Services\Mentionlytics;

use App\Contracts\MentionlyticsAuthServiceInterface;
use App\Contracts\MentionlyticsRateLimiterInterface;
use App\Contracts\MentionlyticsResponseCacheInterface;
use App\Exceptions\MentionlyticsApiException;
use App\Support\LogSanitizer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MentionlyticsHttpTransport
{
    public function __construct(
        private readonly MentionlyticsAuthServiceInterface $authService,
        private readonly MentionlyticsRateLimiterInterface $rateLimiter,
        private readonly MentionlyticsResponseCacheInterface $responseCache,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $cached = $this->responseCache->get('GET', $path, $query);

        if ($cached !== null) {
            return $cached;
        }

        $response = $this->send('GET', $path, $query);
        $payload = $this->parsePayload($response);

        $this->responseCache->put('GET', $path, $query, $payload);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function send(string $method, string $path, array $query = [], bool $retriedAfterUnauthorized = false): Response
    {
        $maxAttempts = (int) config('mentionlytics.retry.max_attempts');
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $this->rateLimiter->acquire();

                $response = $this->request($method, $path, $query);
                $this->rateLimiter->recordResponse($response);

                if ($response->status() === 401 && ! $retriedAfterUnauthorized) {
                    $this->authService->forceRefresh();

                    return $this->send($method, $path, $query, retriedAfterUnauthorized: true);
                }

                if ($response->status() === 429 && $attempt < $maxAttempts) {
                    usleep($this->rateLimiter->delayForRateLimitResponse($response, $attempt) * 1000);

                    continue;
                }

                $response->throw();

                return $response;
            } catch (RequestException $exception) {
                $status = $exception->response?->status();

                if ($status === 429 && $attempt < $maxAttempts) {
                    usleep($this->rateLimiter->delayForRateLimitResponse($exception->response, $attempt) * 1000);

                    continue;
                }

                if ($status === 401 && ! $retriedAfterUnauthorized) {
                    $this->authService->forceRefresh();

                    return $this->send($method, $path, $query, retriedAfterUnauthorized: true);
                }

                Log::error('Mentionlytics API request failed.', [
                    'method' => $method,
                    'path' => $path,
                    'query' => $query,
                    'status' => $status,
                    'body' => $exception->response?->json(),
                    'exception' => LogSanitizer::redactSecrets($exception->getMessage()),
                ]);

                throw new MentionlyticsApiException(
                    $this->resolveRequestFailureMessage($exception),
                    $exception,
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function request(string $method, string $path, array $query): Response
    {
        $client = $this->httpClient($this->authService->getAccessToken());

        return match (strtoupper($method)) {
            'GET' => $client->get($path, $query),
            default => throw new MentionlyticsApiException('Unsupported Mentionlytics HTTP method: '.$method),
        };
    }

    private function httpClient(string $accessToken): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('mentionlytics.base_url'), '/'))
            ->timeout((int) config('mentionlytics.timeout'))
            ->acceptJson()
            ->withToken($accessToken);
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePayload(Response $response): array
    {
        /** @var array<string, mixed>|null $payload */
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new MentionlyticsApiException('Mentionlytics API returned an invalid JSON payload.');
        }

        if (isset($payload['error']) && is_array($payload['error'])) {
            $message = is_string($payload['error']['message'] ?? null)
                ? $payload['error']['message']
                : 'Mentionlytics API returned an error response.';

            throw new MentionlyticsApiException($message);
        }

        if (isset($payload['error']) && is_string($payload['error']) && $payload['error'] !== '') {
            $message = is_string($payload['message'] ?? null) && $payload['message'] !== ''
                ? $payload['message']
                : $payload['error'];

            throw new MentionlyticsApiException($message);
        }

        return $payload;
    }

    private function resolveRequestFailureMessage(RequestException $exception): string
    {
        $status = $exception->response?->status();
        /** @var array<string, mixed>|null $body */
        $body = $exception->response?->json();

        if (is_array($body)) {
            if (is_string($body['message'] ?? null) && $body['message'] !== '') {
                return 'Mentionlytics API request failed: '.$body['message'];
            }

            if (is_array($body['error'] ?? null) && is_string($body['error']['message'] ?? null)) {
                return 'Mentionlytics API request failed: '.$body['error']['message'];
            }
        }

        if ($status === 401) {
            return 'Mentionlytics API authentication failed. Verify the configured token and refresh token settings.';
        }

        return 'Mentionlytics API request failed.';
    }
}
