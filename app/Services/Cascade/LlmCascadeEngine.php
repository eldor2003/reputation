<?php

namespace App\Services\Cascade;

use App\Contracts\LLMCascadeInterface;
use App\Contracts\LLMDecisionStrategyInterface;
use App\Contracts\LLMModelInterface;
use App\DTO\ClassificationResultDTO;
use App\DTO\LlmCascadeResultDTO;
use App\DTO\LlmExecutionMetadataDTO;
use App\DTO\NormalizedMentionDTO;
use App\Exceptions\InvalidClassificationResponseException;
use App\Services\ClassificationResponseParser;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

class LlmCascadeEngine implements LLMCascadeInterface
{
    public function __construct(
        private readonly LLMDecisionStrategyInterface $decisionStrategy,
        private readonly ClassificationResponseParser $responseParser,
        private readonly LlmCostCalculator $costCalculator,
        private readonly Container $container,
    ) {}

    public function classify(string $prompt, NormalizedMentionDTO $mention, int $mentionId): LlmCascadeResultDTO
    {
        if (! (bool) config('cascade.enabled', true)) {
            return $this->classifyWithFallback($prompt, $mentionId);
        }

        $startedAt = microtime(true);
        $currentTier = $this->decisionStrategy->selectInitialModel($mention);
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $escalationReason = null;
        $classification = null;
        $finalModel = '';
        $tiersUsed = [];

        while ($currentTier !== null) {
            $model = $this->resolveModel($currentTier);
            $tiersUsed[] = $currentTier;
            $response = $this->sendWithRetry($model, $prompt, $mentionId);
            $classification = $this->responseParser->parse($response);
            $totalInputTokens += $this->extractInputTokens($response);
            $totalOutputTokens += $this->extractOutputTokens($response);
            $finalModel = $model->modelName();

            $nextTier = $this->decisionStrategy->shouldEscalate($classification, $currentTier);

            if ($nextTier === null || $nextTier === $currentTier) {
                break;
            }

            $escalationReason = $this->decisionStrategy->buildEscalationReason(
                $classification,
                $currentTier,
                $nextTier,
            );

            Log::info('LLM cascade escalation.', [
                'mention_id' => $mentionId,
                'from_tier' => $currentTier,
                'to_tier' => $nextTier,
                'reason' => $escalationReason,
            ]);

            $currentTier = $nextTier;
        }

        $processingTimeMs = (int) round((microtime(true) - $startedAt) * 1000);
        $finalTier = $tiersUsed === [] ? (string) config('cascade.fallback.tier') : $tiersUsed[array_key_last($tiersUsed)];
        $estimatedCost = $this->calculateTotalCost($tiersUsed, $totalInputTokens, $totalOutputTokens);

        return new LlmCascadeResultDTO(
            classification: $classification ?? throw new InvalidClassificationResponseException('Cascade produced no classification result.'),
            model: $finalModel,
            metadata: new LlmExecutionMetadataDTO(
                cascadeTier: $finalTier,
                processingTimeMs: $processingTimeMs,
                inputTokens: $totalInputTokens,
                outputTokens: $totalOutputTokens,
                estimatedCost: $estimatedCost,
                escalationReason: $escalationReason,
            ),
        );
    }

    private function classifyWithFallback(string $prompt, int $mentionId): LlmCascadeResultDTO
    {
        $startedAt = microtime(true);
        $fallbackTier = (string) config('cascade.fallback.tier');
        $fallbackModelName = (string) config('cascade.fallback.model');
        $model = $this->resolveModel($fallbackTier);
        $response = $this->sendWithRetry($model, $prompt, $mentionId);
        $classification = $this->responseParser->parse($response);
        $inputTokens = $this->extractInputTokens($response);
        $outputTokens = $this->extractOutputTokens($response);

        return new LlmCascadeResultDTO(
            classification: $classification,
            model: $fallbackModelName,
            metadata: new LlmExecutionMetadataDTO(
                cascadeTier: $fallbackTier,
                processingTimeMs: (int) round((microtime(true) - $startedAt) * 1000),
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                estimatedCost: $this->costCalculator->estimate($fallbackTier, $inputTokens, $outputTokens),
                escalationReason: null,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function sendWithRetry(LLMModelInterface $model, string $prompt, int $mentionId): array
    {
        $response = $model->send($prompt);

        try {
            $this->responseParser->parse($response);

            return $response;
        } catch (InvalidClassificationResponseException $exception) {
            Log::warning('Invalid LLM classification response, retrying once.', [
                'mention_id' => $mentionId,
                'model' => $model->modelName(),
                'tier' => $model->tier(),
                'exception' => $exception->getMessage(),
            ]);

            return $model->send($prompt);
        }
    }

    private function resolveModel(string $tier): LLMModelInterface
    {
        $adapterClass = config("cascade.models.{$tier}.adapter");

        if (! is_string($adapterClass) || $adapterClass === '') {
            throw new InvalidClassificationResponseException("LLM cascade adapter is not configured for tier [{$tier}].");
        }

        return $this->container->make($adapterClass);
    }

    /**
     * @param  list<string>  $tiersUsed
     */
    private function calculateTotalCost(array $tiersUsed, int $totalInputTokens, int $totalOutputTokens): float
    {
        if ($tiersUsed === []) {
            return 0.0;
        }

        $finalTier = $tiersUsed[array_key_last($tiersUsed)];

        return $this->costCalculator->estimate($finalTier, $totalInputTokens, $totalOutputTokens);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractInputTokens(array $response): int
    {
        return (int) ($response['usage']['input_tokens'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractOutputTokens(array $response): int
    {
        return (int) ($response['usage']['output_tokens'] ?? 0);
    }
}
