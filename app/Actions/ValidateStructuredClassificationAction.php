<?php

namespace App\Actions;

use App\Contracts\AiResultStorageInterface;
use App\Contracts\ClaudeStructuredOutputInterface;
use App\DTO\ClassificationResultDTO;
use App\DTO\LlmCascadeExecutionResultDTO;
use App\DTO\LlmCascadeResultDTO;
use App\DTO\NormalizedMentionDTO;
use App\DTO\PersonMatchResultDTO;
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
        ?PersonMatchResultDTO $personMatch = null,
    ): StructuredClassificationResultDTO {
        $validationRetryCount = 0;
        $validationStatus = ClassificationValidationStatus::Valid;
        $currentExecution = $execution;

        try {
            $structured = $this->applyResolvedPerson(
                $this->validateCascadeResult($currentExecution->cascadeResult),
                $personMatch,
            );
        } catch (SchemaValidationException $firstFailure) {
            Log::warning('Structured classification validation failed, retrying once.', [
                'mention_id' => $mentionId,
                'exception' => $firstFailure->getMessage(),
            ]);

            $validationRetryCount = 1;
            $validationStatus = ClassificationValidationStatus::Retry;
            $currentExecution = $this->executeLlmCascadeAction->execute(
                $mentionId,
                $mention,
                personMatch: $personMatch,
            );

            try {
                $structured = $this->applyResolvedPerson(
                    $this->validateCascadeResult($currentExecution->cascadeResult),
                    $personMatch,
                );
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
                    $personMatch,
                );

                $structured = $this->applyResolvedPerson(
                    $this->validateCascadeResult($currentExecution->cascadeResult),
                    $personMatch,
                );
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

    private function applyResolvedPerson(
        StructuredClassificationResultDTO $structured,
        ?PersonMatchResultDTO $personMatch,
    ): StructuredClassificationResultDTO {
        if ($personMatch === null || $personMatch->isAmbiguous || $personMatch->resolvedPerson === null) {
            return $structured;
        }

        $classification = $structured->classification;
        $resolvedName = $personMatch->resolvedPerson->fullName;

        if ($classification->person === $resolvedName) {
            return $structured;
        }

        return new StructuredClassificationResultDTO(
            classification: new ClassificationResultDTO(
                summary: $classification->summary,
                sentiment: $classification->sentiment,
                severity: $classification->severity,
                language: $classification->language,
                category: $classification->category,
                person: $resolvedName,
                confidence: $classification->confidence,
                reasoning: $classification->reasoning,
                rawResponse: array_merge($classification->rawResponse, ['person' => $resolvedName]),
            ),
            validationStatus: $structured->validationStatus,
            validationRetryCount: $structured->validationRetryCount,
            injectionDetected: $structured->injectionDetected,
            guardReason: $structured->guardReason,
        );
    }
}
