<?php

namespace App\Services\Mentionlytics;

use App\Contracts\MentionlyticsRefreshServiceInterface;
use App\DTO\MentionlyticsTokenPairDTO;
use App\Exceptions\MentionlyticsApiException;
use App\Support\LogSanitizer;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MentionlyticsRefreshService implements MentionlyticsRefreshServiceInterface
{
    public function __construct(
        private readonly MentionlyticsTokenResponseParser $parser,
    ) {}

    public function refresh(string $refreshToken): MentionlyticsTokenPairDTO
    {
        if ($refreshToken === '') {
            throw new MentionlyticsApiException('Mentionlytics refresh token is not configured.');
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('mentionlytics.base_url'), '/'))
                ->timeout((int) config('mentionlytics.timeout'))
                ->acceptJson()
                ->post('/auth/refresh', [
                    'refresh_token' => $refreshToken,
                ])
                ->throw();

            /** @var array<string, mixed>|null $payload */
            $payload = $response->json();

            if (! is_array($payload)) {
                throw new MentionlyticsApiException('Mentionlytics token refresh returned invalid JSON.');
            }

            return $this->parser->parseRefreshResponse($payload);
        } catch (MentionlyticsApiException $exception) {
            throw $exception;
        } catch (RequestException $exception) {
            $apiMessage = $exception->response?->json('message');

            Log::error('Mentionlytics token refresh failed.', [
                'status' => $exception->response?->status(),
                'body' => $exception->response?->json(),
                'exception' => LogSanitizer::redactSecrets($exception->getMessage()),
            ]);

            if (is_string($apiMessage) && $apiMessage !== '') {
                throw new MentionlyticsApiException(
                    'Mentionlytics token refresh failed: '.$apiMessage,
                    $exception,
                );
            }

            throw new MentionlyticsApiException(
                'Mentionlytics token refresh failed.',
                $exception,
            );
        }
    }
}
