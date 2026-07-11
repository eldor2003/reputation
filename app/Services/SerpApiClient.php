<?php

namespace App\Services;

use App\Contracts\SerpApiClientInterface;
use App\DTO\SerpApiAccountInfoDTO;
use App\DTO\SerpPositionDTO;
use App\DTO\SerpSearchRequestDTO;
use App\DTO\SerpSearchResultDTO;
use App\Enums\SerpEngine;
use App\Exceptions\SerpApiException;
use App\Support\LogSanitizer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SerpApiClient implements SerpApiClientInterface
{
    public function testConnection(): SerpApiAccountInfoDTO
    {
        return $this->getAccountInfo();
    }

    public function getAccountInfo(): SerpApiAccountInfoDTO
    {
        $payload = $this->get('/account.json');

        return $this->mapAccountInfo($payload);
    }

    public function search(SerpSearchRequestDTO $request): SerpSearchResultDTO
    {
        $startedAt = microtime(true);

        $payload = $this->get('/search.json', $request->toQueryParameters());

        $responseTimeMs = round((microtime(true) - $startedAt) * 1000, 2);

        return $this->mapSearchResult($request, $payload, $responseTimeMs);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        try {
            $response = $this->http()
                ->get($path, $query)
                ->throw();

            return $this->parsePayload($response);
        } catch (RequestException $exception) {
            Log::error('SerpApi request failed.', [
                'path' => $path,
                'query' => $this->redactQuery($query),
                'status' => $exception->response?->status(),
                'body' => $exception->response?->json(),
                'exception' => LogSanitizer::redactSecrets($exception->getMessage()),
            ]);

            $apiMessage = $exception->response?->json('error');

            if (is_string($apiMessage) && $apiMessage !== '') {
                throw new SerpApiException($apiMessage, $exception);
            }

            throw new SerpApiException('SerpApi request failed.', $exception);
        }
    }

    private function http(): PendingRequest
    {
        $apiKey = config('serpapi.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new SerpApiException('SerpApi API key is not configured.');
        }

        return Http::baseUrl(rtrim((string) config('serpapi.base_url'), '/'))
            ->timeout((int) config('serpapi.timeout'))
            ->acceptJson()
            ->withQueryParameters([
                'api_key' => $apiKey,
            ])
            ->retry(
                (int) config('serpapi.retry.times'),
                (int) config('serpapi.retry.sleep_ms'),
                fn (?\Exception $exception): bool => $this->shouldRetry($exception),
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePayload(Response $response): array
    {
        /** @var array<string, mixed>|null $payload */
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new SerpApiException('SerpApi returned an invalid JSON payload.');
        }

        if (isset($payload['error']) && is_string($payload['error']) && $payload['error'] !== '') {
            throw new SerpApiException($payload['error']);
        }

        /** @var array<string, mixed>|null $metadata */
        $metadata = is_array($payload['search_metadata'] ?? null) ? $payload['search_metadata'] : null;

        if (($metadata['status'] ?? null) === 'Error') {
            $message = is_string($metadata['error'] ?? null)
                ? $metadata['error']
                : 'SerpApi search failed.';

            throw new SerpApiException($message);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapAccountInfo(array $payload): SerpApiAccountInfoDTO
    {
        return new SerpApiAccountInfoDTO(
            accountId: (string) ($payload['account_id'] ?? ''),
            planName: (string) ($payload['plan_name'] ?? 'Unknown'),
            searchesPerMonth: (int) ($payload['searches_per_month'] ?? 0),
            planSearchesLeft: (int) ($payload['plan_searches_left'] ?? 0),
            totalSearchesLeft: (int) ($payload['total_searches_left'] ?? 0),
            thisMonthUsage: (int) ($payload['this_month_usage'] ?? 0),
            accountRateLimitPerHour: (int) ($payload['account_rate_limit_per_hour'] ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapSearchResult(
        SerpSearchRequestDTO $request,
        array $payload,
        float $responseTimeMs,
    ): SerpSearchResultDTO {
        /** @var array<string, mixed>|null $metadata */
        $metadata = is_array($payload['search_metadata'] ?? null) ? $payload['search_metadata'] : [];

        $serpApiSearchId = (string) ($metadata['id'] ?? '');
        $rawHtmlUrl = isset($metadata['raw_html_file']) ? (string) $metadata['raw_html_file'] : null;
        $screenshotUrl = isset($metadata['screenshot']) ? (string) $metadata['screenshot'] : null;

        if ($serpApiSearchId === '') {
            throw new SerpApiException('SerpApi search response is missing search ID.');
        }

        /** @var list<array<string, mixed>> $organicResults */
        $organicResults = is_array($payload['organic_results'] ?? null) ? $payload['organic_results'] : [];

        $positions = array_map(
            fn (array $row): SerpPositionDTO => $this->mapPosition($row),
            $organicResults,
        );

        return new SerpSearchResultDTO(
            engine: $request->engine,
            query: $request->query,
            serpApiSearchId: $serpApiSearchId,
            responseTimeMs: $responseTimeMs,
            positions: $positions,
            rawHtmlUrl: $rawHtmlUrl,
            screenshotUrl: $screenshotUrl !== '' ? $screenshotUrl : null,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapPosition(array $row): SerpPositionDTO
    {
        $position = $row['position'] ?? null;
        $title = $row['title'] ?? null;
        $url = $row['link'] ?? $row['url'] ?? null;

        if (! is_numeric($position) || ! is_string($title) || $title === '' || ! is_string($url) || $url === '') {
            throw new SerpApiException('SerpApi organic result row is missing required fields.');
        }

        $snippet = $row['snippet'] ?? $row['description'] ?? $row['abstract'] ?? null;

        return new SerpPositionDTO(
            position: (int) $position,
            title: $title,
            url: $url,
            snippet: is_string($snippet) && $snippet !== '' ? $snippet : null,
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function redactQuery(array $query): array
    {
        unset($query['api_key']);

        return $query;
    }

    private function shouldRetry(?\Exception $exception): bool
    {
        if (! $exception instanceof RequestException) {
            return true;
        }

        $status = $exception->response?->status();

        if ($status === null) {
            return true;
        }

        if ($status === 401 || $status === 403) {
            return false;
        }

        return $status === 429 || $status >= 500;
    }
}
