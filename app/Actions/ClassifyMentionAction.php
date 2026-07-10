<?php

namespace App\Actions;

use App\DTO\NormalizedMentionDTO;
use App\Exceptions\ClaudeApiException;
use App\Exceptions\InvalidClassificationResponseException;
use App\Exceptions\SchemaValidationException;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClassifyMentionAction
{
    public function __construct(
        private readonly ExecuteLlmCascadeAction $executeLlmCascadeAction,
        private readonly ValidateStructuredClassificationAction $validateStructuredClassificationAction,
    ) {}

    public function execute(int $mentionId, NormalizedMentionDTO $mention): void
    {
        try {
            $execution = $this->executeLlmCascadeAction->execute($mentionId, $mention);
            $this->validateStructuredClassificationAction->execute($mentionId, $mention, $execution);
        } catch (ClaudeApiException|InvalidClassificationResponseException|SchemaValidationException $exception) {
            Log::error('Mention classification failed.', [
                'mention_id' => $mentionId,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Unexpected mention classification failure.', [
                'mention_id' => $mentionId,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
