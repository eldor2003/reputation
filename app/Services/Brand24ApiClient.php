<?php

namespace App\Services;

use App\Contracts\Brand24ClientInterface;
use App\DTO\Brand24AccountInfoDTO;
use App\DTO\Brand24MentionDTO;
use App\DTO\Brand24MentionsPageDTO;
use App\DTO\Brand24MentionsQueryDTO;
use App\DTO\Brand24ProjectDTO;
use App\DTO\Brand24ProjectsListDTO;
use App\Exceptions\Brand24ApiException;
use App\Support\LogSanitizer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Brand24ApiClient implements Brand24ClientInterface
{
    public function testConnection(): Brand24AccountInfoDTO
    {
        $payload = $this->get('/api-data/v1/account/mentions-usage-estimation');

        /** @var array<string, mixed> $message */
        $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];

        $estimation = $message['mentions_usage_estimation_at_the_end'] ?? null;

        if (! is_numeric($estimation)) {
            throw new Brand24ApiException('Brand24 account usage response is missing projected mentions usage.');
        }

        return new Brand24AccountInfoDTO(
            mentionsUsageEstimationAtTheEnd: (int) $estimation,
        );
    }

    public function getProjects(int $accountId): Brand24ProjectsListDTO
    {
        $payload = $this->get("/api-data/v1/account/{$accountId}/projects_list/");

        /** @var array<string, mixed> $data */
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        /** @var array<string, string> $projectsList */
        $projectsList = is_array($data['projects_list'] ?? null)
            ? $data['projects_list']
            : $this->extractProjectsListFromData($data);

        $projects = [];

        foreach ($projectsList as $projectId => $projectName) {
            $projects[] = new Brand24ProjectDTO(
                id: (string) $projectId,
                name: (string) $projectName,
            );
        }

        return new Brand24ProjectsListDTO($projects);
    }

    public function getMentions(Brand24MentionsQueryDTO $query): Brand24MentionsPageDTO
    {
        $queryParameters = [
            'date_from' => $query->dateFrom,
            'date_to' => $query->dateTo,
        ];

        if ($query->limit !== null) {
            $queryParameters['limit'] = $query->limit;
        }

        if ($query->cursor !== null) {
            $queryParameters['cursor'] = $query->cursor;
        }

        if ($query->sentiment !== null) {
            $queryParameters['sentiment'] = $query->sentiment;
        }

        if ($query->category !== null) {
            $queryParameters['category'] = $query->category;
        }

        $payload = $this->get(
            "/api-data/v1/project/{$query->projectId}/mentions",
            $queryParameters,
        );

        /** @var array<string, mixed> $message */
        $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];

        /** @var list<array<string, mixed>> $results */
        $results = is_array($message['results'] ?? null) ? $message['results'] : [];

        $mentions = array_map(
            fn (array $row): Brand24MentionDTO => $this->mapMention($row),
            $results,
        );

        return new Brand24MentionsPageDTO(
            results: $mentions,
            hasMoreMentions: (bool) ($message['has_more_mentions'] ?? false),
            cursor: isset($message['cursor']) ? (string) $message['cursor'] : null,
        );
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

            return $this->parseSuccessPayload($response);
        } catch (RequestException $exception) {
            Log::error('Brand24 API request failed.', [
                'path' => $path,
                'query' => $query,
                'status' => $exception->response?->status(),
                'body' => $exception->response?->json(),
                'exception' => LogSanitizer::redactSecrets($exception->getMessage()),
            ]);

            throw new Brand24ApiException('Brand24 API request failed.', $exception);
        }
    }

    private function http(): PendingRequest
    {
        $apiKey = config('brand24.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new Brand24ApiException('Brand24 API key is not configured.');
        }

        return Http::baseUrl(rtrim((string) config('brand24.base_url'), '/'))
            ->timeout((int) config('brand24.timeout'))
            ->withHeaders([
                'X-Api-Key' => $apiKey,
                'Accept' => 'application/json',
            ])
            ->retry(
                (int) config('brand24.retry.times'),
                (int) config('brand24.retry.sleep_ms'),
                fn (?\Exception $exception): bool => $this->shouldRetry($exception),
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function parseSuccessPayload(Response $response): array
    {
        /** @var array<string, mixed>|null $payload */
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new Brand24ApiException('Brand24 API returned an invalid JSON payload.');
        }

        if (($payload['status'] ?? null) !== 'success') {
            Log::warning('Brand24 API returned a non-success status.', [
                'status' => $payload['status'] ?? null,
                'body' => $payload,
            ]);

            throw new Brand24ApiException('Brand24 API returned a non-success status.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapMention(array $row): Brand24MentionDTO
    {
        /** @var list<string> $tags */
        $tags = is_array($row['tags'] ?? null) ? array_map(strval(...), $row['tags']) : [];

        return new Brand24MentionDTO(
            date: (string) ($row['date'] ?? ''),
            time: (string) ($row['time'] ?? ''),
            title: isset($row['title']) ? (string) $row['title'] : null,
            content: isset($row['content']) ? (string) $row['content'] : null,
            source: isset($row['source']) ? (string) $row['source'] : null,
            host: (string) ($row['host'] ?? ''),
            category: (string) ($row['category'] ?? ''),
            sentiment: (int) ($row['sentiment'] ?? 0),
            tags: $tags,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function extractProjectsListFromData(array $data): array
    {
        $projectsList = [];

        foreach ($data as $projectId => $projectName) {
            if ((is_string($projectId) || is_int($projectId))
                && (is_string($projectName) || is_numeric($projectName))) {
                $projectsList[(string) $projectId] = (string) $projectName;
            }
        }

        return $projectsList;
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

        return $status === 429 || $status >= 500;
    }
}
