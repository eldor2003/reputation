<?php

namespace Tests\Unit\Actions;

use App\Actions\ClassifyMentionAction;
use App\Actions\ExecuteLlmCascadeAction;
use App\Actions\ValidateStructuredClassificationAction;
use App\DTO\ClassificationResultDTO;
use App\DTO\LlmCascadeExecutionResultDTO;
use App\DTO\LlmCascadeResultDTO;
use App\DTO\LlmExecutionMetadataDTO;
use App\DTO\NormalizedMentionDTO;
use App\DTO\PromptGuardResultDTO;
use App\DTO\StructuredClassificationResultDTO;
use App\Enums\ClassificationValidationStatus;
use App\Exceptions\SchemaValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClassifyMentionActionTest extends TestCase
{
    #[Test]
    public function it_runs_cascade_and_structured_validation(): void
    {
        $mention = new NormalizedMentionDTO(
            projectId: 1,
            sourceId: 2,
            externalId: 'mention-123',
            author: null,
            authorId: null,
            language: 'en',
            text: 'Sample text.',
            title: null,
            url: null,
            publishedAt: null,
            receivedAt: Carbon::now(),
        );

        $execution = $this->makeExecution();

        /** @var MockInterface&ExecuteLlmCascadeAction $executeLlmCascadeAction */
        $executeLlmCascadeAction = Mockery::mock(ExecuteLlmCascadeAction::class);
        $executeLlmCascadeAction->shouldReceive('execute')
            ->once()
            ->with(10, $mention)
            ->andReturn($execution);

        /** @var MockInterface&ValidateStructuredClassificationAction $validateStructuredClassificationAction */
        $validateStructuredClassificationAction = Mockery::mock(ValidateStructuredClassificationAction::class);
        $validateStructuredClassificationAction->shouldReceive('execute')
            ->once()
            ->with(10, $mention, $execution)
            ->andReturn(new StructuredClassificationResultDTO(
                classification: $execution->cascadeResult->classification,
                validationStatus: ClassificationValidationStatus::Valid,
                validationRetryCount: 0,
                injectionDetected: false,
                guardReason: null,
            ));

        $action = new ClassifyMentionAction(
            $executeLlmCascadeAction,
            $validateStructuredClassificationAction,
        );

        $action->execute(10, $mention);
    }

    #[Test]
    public function it_fails_when_structured_validation_fails(): void
    {
        Log::shouldReceive('error')->once();

        $mention = new NormalizedMentionDTO(
            projectId: 1,
            sourceId: 2,
            externalId: 'mention-123',
            author: null,
            authorId: null,
            language: 'en',
            text: 'Sample text.',
            title: null,
            url: null,
            publishedAt: null,
            receivedAt: Carbon::now(),
        );

        /** @var MockInterface&ExecuteLlmCascadeAction $executeLlmCascadeAction */
        $executeLlmCascadeAction = Mockery::mock(ExecuteLlmCascadeAction::class);
        $executeLlmCascadeAction->shouldReceive('execute')->once()->andReturn($this->makeExecution());

        /** @var MockInterface&ValidateStructuredClassificationAction $validateStructuredClassificationAction */
        $validateStructuredClassificationAction = Mockery::mock(ValidateStructuredClassificationAction::class);
        $validateStructuredClassificationAction->shouldReceive('execute')
            ->once()
            ->andThrow(new SchemaValidationException('Invalid schema.'));

        $action = new ClassifyMentionAction(
            $executeLlmCascadeAction,
            $validateStructuredClassificationAction,
        );

        $this->expectException(SchemaValidationException::class);

        $action->execute(10, $mention);
    }

    private function makeExecution(): LlmCascadeExecutionResultDTO
    {
        return new LlmCascadeExecutionResultDTO(
            cascadeResult: new LlmCascadeResultDTO(
                classification: new ClassificationResultDTO(
                    summary: 'Summary.',
                    sentiment: 'negative',
                    severity: 4,
                    language: 'en',
                    category: 'customer_service',
                    person: 'unknown',
                    confidence: 88,
                    reasoning: 'Reasoning.',
                    rawResponse: ['id' => 'msg_test'],
                ),
                model: 'claude-test-model',
                metadata: new LlmExecutionMetadataDTO(
                    cascadeTier: 'haiku',
                    processingTimeMs: 10,
                    inputTokens: 5,
                    outputTokens: 5,
                    estimatedCost: 0.001,
                    escalationReason: null,
                ),
            ),
            guardResult: new PromptGuardResultDTO(false, null),
        );
    }
}
