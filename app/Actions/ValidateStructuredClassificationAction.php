<?php

namespace App\Actions;

use App\Contracts\AiResultStorageInterface;
use App\Contracts\ClaudeStructuredOutputInterface;
use App\DTO\LlmCascadeExecutionResultDTO;
use App\DTO\LlmCascadeResultDTO;
use App\DTO\NormalizedMentionDTO;
use App\DTO\StructuredClassificationResultDTO;
use App\DTO\ValidationMetadataDTO;
use App\Enums\ClassificationValidationStatus;
use App\Events\MentionClassified;
use App\Exceptions\SchemaValidationException;
use App\Services\Classification\CascadeTierEscalator;
use Illuminate\Support\Facades\Log;

class ValidateStructuredClassificationAction
{
    public function __construct(
        private readonly ClaudeStructuredOutputInterface $structuredOutput,
        private readonly ExecuteLlmCascadeAction $executeLlmCascadeAction,
        private readonly AiResultStorageInterface $aiResultStorage,
        private readonly CascadeTierEscalator $tierEscalator,
    ) {}

    public function execute(
        int $mentionId,
        NormalizedMentionDTO $mention,
        LlmCascadeExecutionResultDTO $execution,
    ): StructuredClassificationResultDTO {
        $validationRetryCount = 0;
        $validationStatus = ClassificationValidationStatus::Valid;
        $currentExecution = $execution;

        try {
            $structured = $this->validateCascadeResult($currentExecution->cascadeResult);
        } catch (SchemaValidationException $firstFailure) {
            Log::warning('Structured classification validation failed, retrying once.', [
                'mention_id' => $mentionId,
                'exception' => $firstFailure->getMessage(),
            ]);

            $validationRetryCount = 1;
            $validationStatus = ClassificationValidationStatus::Retry;
            $currentExecution = $this->executeLlmCascadeAction->execute($mentionId, $mention);

            try {
                $structured = $this->validateCascadeResult($currentExecution->cascadeResult);
            } catch (SchemaValidationException $secondFailure) {
                if (! (bool) config('classification.validation.escalate_on_failure', true)) {
                    throw $secondFailure;
                }

                Log::warning('Structured classification validation failed after retry, escalating cascade tier.', [
                    'mention_id' => $mentionId,
                    'exception' => $secondFailure->getMessage(),
                ]);

                $validationStatus = ClassificationValidationStatus::Escalated;
                $escalatedTier = $this->tierEscalator->defaultEscalationTier(
                    $currentExecution->cascadeResult->metadata->cascadeTier,
                );

                $currentExecution = $this->executeLlmCascadeAction->execute(
                    $mentionId,
                    $mention,
                    $escalatedTier,
                );

                $structured = $this->validateCascadeResult($currentExecution->cascadeResult);
            }
        }

        $result = new StructuredClassificationResultDTO(
            classification: $structured->classification,
            validationStatus: $validationStatus,
            validationRetryCount: $validationRetryCount,
            injectionDetected: $currentExecution->guardResult->injectionDetected,
            guardReason: $currentExecution->guardResult->reason,
        );

        $this->aiResultStorage->store(
            $mentionId,
            $result->classification,
            $currentExecution->cascadeResult->model,
            $currentExecution->cascadeResult->metadata,
            new ValidationMetadataDTO(
                validationStatus: $result->validationStatus->value,
                validationRetryCount: $result->validationRetryCount,
                injectionDetected: $result->injectionDetected,
                guardReason: $result->guardReason,
            ),
        );

        MentionClassified::dispatch(
            $mentionId,
            $mention->projectId,
            $mention->sourceId,
            now(),
        );

        return $result;
    }

    private function validateCascadeResult(LlmCascadeResultDTO $cascadeResult): StructuredClassificationResultDTO
    {
        return $this->structuredOutput->parse($cascadeResult->classification->rawResponse);
    }
}
