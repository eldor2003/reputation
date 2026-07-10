<?php

namespace App\Services;

use App\Contracts\ClaudeClientInterface;
use App\Exceptions\ClaudeApiException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicClaudeClient implements ClaudeClientInterface
{
    public function send(string $prompt, ?string $model = null): array
    {
        $apiKey = config('claude.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new ClaudeApiException('Anthropic API key is not configured.');
        }

        try {
            $response = Http::baseUrl((string) config('claude.base_url'))
                ->timeout((int) config('claude.timeout'))
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->retry(
                    (int) config('claude.retry.times'),
                    (int) config('claude.retry.sleep_ms'),
                    fn (?\Exception $exception, $request) => $this->shouldRetry($exception),
                )
                ->post('/messages', $this->buildRequestPayload($prompt, $model))
                ->throw();

            /** @var array<string, mixed> $payload */
            $payload = $response->json();

            return $payload;
        } catch (RequestException $exception) {
            Log::error('Claude API request failed.', [
                'status' => $exception->response?->status(),
                'body' => $exception->response?->json(),
                'exception' => $exception->getMessage(),
            ]);

            throw new ClaudeApiException('Claude API request failed.', $exception);
        }
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

    /**
     * @return array<string, mixed>
     */
    private function buildRequestPayload(string $prompt, ?string $model): array
    {
        $payload = [
            'model' => $model ?? config('claude.model'),
            'max_tokens' => (int) config('claude.max_tokens'),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        if ((bool) config('claude.include_temperature', false)) {
            $payload['temperature'] = (float) config('claude.temperature');
        }

        return $payload;
    }
}
