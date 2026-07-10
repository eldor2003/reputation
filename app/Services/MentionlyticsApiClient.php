<?php

namespace App\Services;

use App\Contracts\MentionlyticsClientInterface;
use App\DTO\MentionlyticsConnectionInfoDTO;
use App\DTO\MentionlyticsMentionDTO;
use App\DTO\MentionlyticsMentionsPageDTO;
use App\DTO\MentionlyticsMentionsQueryDTO;
use App\DTO\MentionlyticsVerificationResultDTO;
use App\Exceptions\MentionlyticsApiException;
use App\Support\LogSanitizer;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MentionlyticsApiClient implements MentionlyticsClientInterface
{
    public function __construct(
        private readonly MentionlyticsTokenManager $tokenManager,
    ) {}

    public function testConnection(): MentionlyticsConnectionInfoDTO
    {
        $verification = $this->verify();

        return new MentionlyticsConnectionInfoDTO(
            mentionsOnPage: $verification->mentionsOnPage,
            hasMoreMentions: $verification->hasMorePages,
        );
    }

    public function verify(): MentionlyticsVerificationResultDTO
    {
        $this->tokenManager->invalidateCachedBearerToken();

        $lookbackDays = (int) config('mentionlytics.polling.default_lookback_days');
        $perPage = (int) config('mentionlytics.polling.default_per_page');
        $startDate = Carbon::now()->subDays($lookbackDays)->format('Ymd');
        $endDate = Carbon::now()->format('Ymd');

        $query = new MentionlyticsMentionsQueryDTO(
            startDate: $startDate,
            endDate: $endDate,
            perPage: $perPage,
        );

        $firstPage = $this->getMentions($query);
        $tokenRefreshUsed = $this->tokenManager->wasRefreshUsedOnLastOperation();
        $paginationVerified = false;

        if ($firstPage->hasMore && $firstPage->resultsAfter !== null) {
            $this->getMentions(new MentionlyticsMentionsQueryDTO(
                startDate: $startDate,
                endDate: $endDate,
                perPage: $perPage,
                resultsAfter: $firstPage->resultsAfter,
            ));

            $paginationVerified = true;
            $tokenRefreshUsed = $tokenRefreshUsed || $this->tokenManager->wasRefreshUsedOnLastOperation();
        }

        [$lastMentionTimestamp, $lastMentionId] = $this->resolveLatestMention($firstPage->mentions);

        return new MentionlyticsVerificationResultDTO(
            queryStartDate: $startDate,
            queryEndDate: $endDate,
            mentionsOnPage: count($firstPage->mentions),
            totalMentionsInPeriod: $this->extractTotalMentionsCount($firstPage),
            hasMorePages: $firstPage->hasMore,
            paginationCursor: $firstPage->resultsAfter,
            paginationVerified: $paginationVerified,
            lastMentionTimestamp: $lastMentionTimestamp,
            lastMentionId: $lastMentionId,
            tokenRefreshUsed: $tokenRefreshUsed,
            authenticationMethod: $this->resolveAuthenticationMethod($tokenRefreshUsed),
        );
    }

    private function resolveAuthenticationMethod(bool $tokenRefreshUsed): string
    {
        if ($tokenRefreshUsed) {
            return 'refresh_token';
        }

        $refreshToken = config('mentionlytics.refresh_token');

        if (! is_string($refreshToken) || $refreshToken === '') {
            return 'account_access_token';
        }

        return 'bearer_token';
    }

    public function getMentions(MentionlyticsMentionsQueryDTO $query): MentionlyticsMentionsPageDTO
    {
        $queryParameters = [
            'startDate' => $query->startDate,
            'endDate' => $query->endDate,
        ];

        if ($query->pageNo !== null) {
            $queryParameters['page_no'] = $query->pageNo;
        }

        if ($query->perPage !== null) {
            $queryParameters['per_page'] = $query->perPage;
        }

        if ($query->resultsAfter !== null) {
            $queryParameters['results_after'] = $query->resultsAfter;
        }

        if ($query->sentiment !== null) {
            $queryParameters['sentiment'] = $query->sentiment;
        }

        if ($query->channels !== null && $query->channels !== []) {
            $queryParameters['channels'] = '['.implode(',', $query->channels).']';
        }

        if ($query->commtracks !== null) {
            $queryParameters['commtracks'] = $query->commtracks;
        }

        if ($query->country !== null) {
            $queryParameters['country'] = $query->country;
        }

        if ($query->language !== null) {
            $queryParameters['language'] = $query->language;
        }

        $payload = $this->get('/mentions', $queryParameters);

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->extractMentionRows($payload);

        $mentions = array_map(
            fn (array $row): MentionlyticsMentionDTO => $this->mapMention($row),
            $rows,
        );

        $resultsAfter = $payload['results_after']
            ?? $payload['resultsAfter']
            ?? (is_array($payload['pagination'] ?? null) ? ($payload['pagination']['results_after'] ?? null) : null);
        $resultsAfterString = is_string($resultsAfter) || is_numeric($resultsAfter)
            ? (string) $resultsAfter
            : null;

        return new MentionlyticsMentionsPageDTO(
            mentions: $mentions,
            hasMore: $resultsAfterString !== null && $resultsAfterString !== '',
            resultsAfter: $resultsAfterString,
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

            return $this->parsePayload($response);
        } catch (RequestException $exception) {
            if ($exception->response?->status() === 401) {
                $this->tokenManager->invalidateCachedBearerToken();

                try {
                    $response = $this->http(forceRefresh: true)
                        ->get($path, $query)
                        ->throw();

                    return $this->parsePayload($response);
                } catch (RequestException $retryException) {
                    $exception = $retryException;
                }
            }

            Log::error('Mentionlytics API request failed.', [
                'path' => $path,
                'query' => $query,
                'status' => $exception->response?->status(),
                'body' => $exception->response?->json(),
                'exception' => LogSanitizer::redactSecrets($exception->getMessage()),
            ]);

            throw new MentionlyticsApiException(
                $this->resolveRequestFailureMessage($exception),
                $exception,
            );
        }
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

    private function http(bool $forceRefresh = false): PendingRequest
    {
        $bearerToken = $forceRefresh
            ? $this->tokenManager->getBearerToken(forceRefresh: true)
            : $this->tokenManager->getBearerToken();

        return Http::baseUrl(rtrim((string) config('mentionlytics.base_url'), '/'))
            ->timeout((int) config('mentionlytics.timeout'))
            ->acceptJson()
            ->withToken($bearerToken)
            ->retry(
                (int) config('mentionlytics.retry.times'),
                (int) config('mentionlytics.retry.sleep_ms'),
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

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapMention(array $row): MentionlyticsMentionDTO
    {
        $id = $row['id'] ?? $row['uu_id'] ?? null;
        $text = $this->extractMentionText($row);

        if (! is_string($id) && ! is_int($id)) {
            throw new MentionlyticsApiException('Mentionlytics mention row is missing id.');
        }

        if ($text === null) {
            throw new MentionlyticsApiException('Mentionlytics mention row is missing text.');
        }

        /** @var array<string, mixed>|null $profile */
        $profile = is_array($row['profile'] ?? null) ? $row['profile'] : null;

        $publishedAt = $row['pub_datetime'] ?? $row['pub_date'] ?? null;

        return new MentionlyticsMentionDTO(
            id: (string) $id,
            uuid: isset($row['uu_id']) ? (string) $row['uu_id'] : null,
            text: $text,
            url: $this->optionalString($row, 'link', 'url', 'murl'),
            title: $this->optionalString($row, 'title', 'campaign', 'commtrack'),
            authorName: $this->optionalString($row, 'profile_name', 'screen_name', 'profile_username', 'name')
                ?? ($profile !== null ? $this->optionalString($profile, 'name', 'username') : null),
            authorId: $this->optionalString($row, 'uid', 'profile_uu_id', 'profile_id')
                ?? ($profile !== null ? $this->optionalString($profile, 'uu_id', 'id', 'username') : null),
            publishedAt: is_string($publishedAt) ? $publishedAt : null,
            language: $this->optionalString($row, 'language_code', 'language', 'lang'),
            sentiment: $this->optionalString($row, 'sentiment_text', 'emotion_text', 'sentiment'),
            channel: $this->optionalString($row, 'channel_name', 'mchannel', 'subChannel'),
            channelId: isset($row['channel_id']) && is_numeric($row['channel_id'])
                ? (int) $row['channel_id']
                : (isset($row['mchannel_id']) && is_numeric($row['mchannel_id']) ? (int) $row['mchannel_id'] : null),
            engagement: $this->extractEngagement($row),
            raw: $row,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function extractMentionText(array $row): ?string
    {
        $text = $this->optionalString($row, 'description', 'ftext', 'content', 'body', 'text');

        if ($text !== null) {
            return $text;
        }

        return $this->optionalString($row, 'title', 'campaign', 'commtrack');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function extractEngagement(array $row): ?int
    {
        /** @var array<string, mixed>|null $metrics */
        $metrics = is_array($row['metrics'] ?? null) ? $row['metrics'] : null;

        if ($metrics !== null && isset($metrics['engagement']) && is_numeric($metrics['engagement'])) {
            return (int) $metrics['engagement'];
        }

        if (isset($row['mEngagement']) && is_numeric($row['mEngagement'])) {
            return (int) $row['mEngagement'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function optionalString(array $row, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
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

        if ($status === 401) {
            return false;
        }

        if ($status === 520 && $this->responseIndicatesInvalidToken($exception)) {
            return false;
        }

        return $status === 429 || $status >= 500;
    }

    private function responseIndicatesInvalidToken(RequestException $exception): bool
    {
        /** @var array<string, mixed>|null $body */
        $body = $exception->response?->json();

        if (! is_array($body)) {
            return false;
        }

        $message = strtolower((string) ($body['message'] ?? ''));
        $error = strtolower((string) ($body['error'] ?? ''));

        return str_contains($message, 'invalid token')
            || str_contains($error, 'invalid_token')
            || str_contains($error, 'invalid token');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function extractMentionRows(array $payload): array
    {
        if (is_array($payload['mentions'] ?? null)) {
            return $payload['mentions'];
        }

        /** @var array<string, mixed>|null $data */
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : null;

        if ($data !== null && is_array($data['mentions'] ?? null)) {
            return $data['mentions'];
        }

        return [];
    }

    /**
     * @param  list<MentionlyticsMentionDTO>  $mentions
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveLatestMention(array $mentions): array
    {
        $latestTimestamp = null;
        $latestId = null;

        foreach ($mentions as $mention) {
            if ($mention->publishedAt === null) {
                continue;
            }

            if ($latestTimestamp === null || $mention->publishedAt > $latestTimestamp) {
                $latestTimestamp = $mention->publishedAt;
                $latestId = $mention->uuid ?? $mention->id;
            }
        }

        if ($latestTimestamp !== null) {
            return [$latestTimestamp, $latestId];
        }

        $first = $mentions[0] ?? null;

        if ($first === null) {
            return [null, null];
        }

        return [$first->publishedAt, $first->uuid ?? $first->id];
    }

    private function extractTotalMentionsCount(MentionlyticsMentionsPageDTO $page): ?int
    {
        if ($page->mentions === []) {
            return 0;
        }

        return count($page->mentions);
    }
}
