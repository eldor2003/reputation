<?php

namespace App\Services;

use App\Contracts\AiResultStorageInterface;
use App\DTO\ClassificationResultDTO;
use App\DTO\LlmExecutionMetadataDTO;
use App\DTO\ValidationMetadataDTO;
use App\Models\AiResult;

class AiResultStorage implements AiResultStorageInterface
{
    public function store(
        int $mentionId,
        ClassificationResultDTO $result,
        string $model,
        ?LlmExecutionMetadataDTO $metadata = null,
        ?ValidationMetadataDTO $validationMetadata = null,
    ): AiResult {
        return AiResult::query()->create([
            'mention_id' => $mentionId,
            'provider' => (string) config('claude.provider'),
            'model' => $model,
            'cascade_tier' => $metadata?->cascadeTier,
            'processing_time_ms' => $metadata?->processingTimeMs,
            'input_tokens' => $metadata?->inputTokens,
            'output_tokens' => $metadata?->outputTokens,
            'estimated_cost' => $metadata?->estimatedCost,
            'escalation_reason' => $metadata?->escalationReason,
            'validation_status' => $validationMetadata?->validationStatus,
            'validation_retry_count' => $validationMetadata?->validationRetryCount ?? 0,
            'injection_detected' => $validationMetadata?->injectionDetected ?? false,
            'guard_reason' => $validationMetadata?->guardReason,
            'summary' => $result->summary,
            'sentiment' => $result->sentiment,
            'severity' => $result->severity,
            'language' => $result->language,
            'category' => $result->category,
            'person' => $result->person,
            'confidence' => $result->confidence,
            'reasoning' => $result->reasoning,
            'raw_response' => $result->rawResponse,
            'processed_at' => now(),
        ]);
    }
}
