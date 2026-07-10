<?php

namespace App\Console\Commands;

use App\Support\ClaudeApiErrorExplainer;
use App\Support\LogSanitizer;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeTestCommand extends Command
{
    private const TEST_PROMPT = "Reply with exactly:\n\nOK";

    protected $signature = 'claude:test';

    protected $description = 'Verify Anthropic Claude API connectivity with a minimal live request';

    public function handle(): int
    {
        $apiKey = config('claude.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            $this->components->error('API Connection Status: FAILED');
            $this->line('Anthropic API key is not configured. Set ANTHROPIC_API_KEY in .env.');

            return self::FAILURE;
        }

        $model = (string) config('claude.model');
        $startedAt = microtime(true);

        try {
            $response = Http::baseUrl(rtrim((string) config('claude.base_url'), '/'))
                ->timeout((int) config('claude.timeout'))
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('/messages', [
                    'model' => $model,
                    'max_tokens' => 16,
                    'temperature' => 0,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => self::TEST_PROMPT,
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            $this->displayFailure(
                model: $model,
                responseTimeMs: $this->elapsedMilliseconds($startedAt),
                explanation: 'Could not reach the Anthropic API. Check network connectivity and base URL.',
                response: null,
                apiError: LogSanitizer::redactSecrets($exception->getMessage()),
            );

            Log::error('Claude connectivity test failed.', [
                'exception' => LogSanitizer::redactSecrets($exception->getMessage()),
            ]);

            return self::FAILURE;
        }

        $responseTimeMs = $this->elapsedMilliseconds($startedAt);
        /** @var array<string, mixed>|null $body */
        $body = $response->json();

        if ($response->successful()) {
            $this->displaySuccess($model, $responseTimeMs, $body ?? []);

            return self::SUCCESS;
        }

        $status = $response->status();

        $this->displayFailure(
            model: $model,
            responseTimeMs: $responseTimeMs,
            explanation: ClaudeApiErrorExplainer::explain($status, $body),
            response: null,
            apiError: ClaudeApiErrorExplainer::explain($status, $body),
            httpStatus: $status,
        );

        Log::error('Claude connectivity test failed.', [
            'status' => $status,
            'body' => $body,
        ]);

        return self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function displaySuccess(string $model, float $responseTimeMs, array $body): void
    {
        $this->components->info('✓ Claude Connected');
        $this->newLine();

        $this->components->info('API Connection Status: OK');
        $this->components->twoColumnDetail('Model used', $model);
        $this->components->twoColumnDetail('Response time', $responseTimeMs.' ms');
        $this->displayTokenUsage($body);
        $this->newLine();
        $this->components->info('Response');
        $this->line($this->extractResponseText($body));
    }

    private function displayFailure(
        string $model,
        float $responseTimeMs,
        string $explanation,
        ?string $response,
        string $apiError,
        ?int $httpStatus = null,
    ): void {
        $this->components->error('API Connection Status: FAILED');
        $this->newLine();

        $this->components->twoColumnDetail('Model used', $model);
        $this->components->twoColumnDetail('Response time', $responseTimeMs.' ms');

        if ($httpStatus !== null) {
            $this->components->twoColumnDetail('HTTP status', (string) $httpStatus);
        }

        $this->newLine();
        $this->components->warn('Explanation');
        $this->line($explanation);

        if ($response !== null) {
            $this->newLine();
            $this->components->info('Response');
            $this->line($response);
        }

        $this->newLine();
        $this->components->error('API error');
        $this->line($apiError);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function displayTokenUsage(array $body): void
    {
        /** @var array<string, mixed>|null $usage */
        $usage = is_array($body['usage'] ?? null) ? $body['usage'] : null;

        if ($usage === null) {
            $this->components->twoColumnDetail('Tokens used', 'Not returned');

            return;
        }

        $inputTokens = $usage['input_tokens'] ?? null;
        $outputTokens = $usage['output_tokens'] ?? null;

        if (is_numeric($inputTokens) && is_numeric($outputTokens)) {
            $this->components->twoColumnDetail(
                'Tokens used',
                'input: '.(int) $inputTokens.', output: '.(int) $outputTokens,
            );

            return;
        }

        $this->components->twoColumnDetail('Tokens used', json_encode($usage, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractResponseText(array $body): string
    {
        /** @var list<array<string, mixed>>|null $content */
        $content = is_array($body['content'] ?? null) ? $body['content'] : null;

        if ($content === null || $content === []) {
            return '(empty response)';
        }

        foreach ($content as $block) {
            if (($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                return trim($block['text']);
            }
        }

        return '(no text block in response)';
    }

    private function elapsedMilliseconds(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }
}
