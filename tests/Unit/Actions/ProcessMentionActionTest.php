<?php

namespace Tests\Unit\Actions;

use App\Actions\DeduplicateMentionAction;
use App\Actions\EvaluateMentionThreatAction;
use App\Actions\ExecuteLlmCascadeAction;
use App\Actions\ProcessMentionAction;
use App\Actions\ResolveMentionPersonAction;
use App\Actions\RouteMentionAction;
use App\Actions\ValidateStructuredClassificationAction;
use App\DTO\ClassificationResultDTO;
use App\DTO\DeduplicationResultDTO;
use App\DTO\LlmCascadeExecutionResultDTO;
use App\DTO\LlmCascadeResultDTO;
use App\DTO\LlmExecutionMetadataDTO;
use App\DTO\MentionFingerprintDTO;
use App\DTO\PersonMatchResultDTO;
use App\DTO\PromptGuardResultDTO;
use App\DTO\StructuredClassificationResultDTO;
use App\DTO\ThreatResultDTO;
use App\Enums\ClassificationValidationStatus;
use App\Enums\ThreatLevel;
use App\Enums\MentionStatus;
use App\Enums\SourceType;
use App\Models\Mention;
use App\Models\MentionCluster;
use App\Models\MentionRaw;
use App\Models\Project;
use App\Models\Source;
use App\Factories\ProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessMentionActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_normalizes_deduplicates_classifies_and_persists_original_mention(): void
    {
        $project = Project::query()->create([
            'name' => 'Test Project',
            'slug' => 'test-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::YouScan,
            'external_id' => 'source-1',
            'name' => 'YouScan Source',
            'is_active' => true,
        ]);

        $mention = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'mention-123',
            'content' => '',
            'received_at' => now(),
            'status' => MentionStatus::Pending,
        ]);

        MentionRaw::query()->create([
            'mention_id' => $mention->id,
            'provider' => SourceType::YouScan->value,
            'payload' => [
                'project_id' => $project->id,
                'source_id' => $source->id,
                'id' => 'mention-123',
                'text' => 'Normalized body',
                'author' => 'Jane Doe',
                'author_id' => '999',
                'language' => 'en',
                'title' => 'Title',
                'url' => 'https://example.com',
                'published' => '2026-06-29T10:00:00Z',
                'received_at' => '2026-06-29T11:00:00Z',
            ],
        ]);

        $cluster = MentionCluster::query()->create([
            'project_id' => $project->id,
            'canonical_mention_id' => $mention->id,
            'simhash' => 'abc123',
            'content_fingerprint' => 'content-hash-123',
        ]);

        $fingerprint = new MentionFingerprintDTO(
            simhash: 'abc123',
            contentFingerprint: 'content-hash-123',
            dedupHash: 'content-hash-123',
        );

        /** @var MockInterface&ResolveMentionPersonAction $resolveMentionPersonAction */
        $resolveMentionPersonAction = Mockery::mock(ResolveMentionPersonAction::class);
        $resolveMentionPersonAction->shouldReceive('execute')
            ->once()
            ->andReturn(new PersonMatchResultDTO(resolvedPerson: null, isAmbiguous: false, candidates: []));

        /** @var MockInterface&DeduplicateMentionAction $deduplicateMentionAction */
        $deduplicateMentionAction = Mockery::mock(DeduplicateMentionAction::class);
        $deduplicateMentionAction->shouldReceive('execute')
            ->once()
            ->andReturn(new DeduplicationResultDTO(
                isDuplicate: false,
                originalMentionId: null,
                dedupHash: 'content-hash-123',
                clusterId: $cluster->id,
                fingerprint: $fingerprint,
            ));

        $execution = new LlmCascadeExecutionResultDTO(
            cascadeResult: new LlmCascadeResultDTO(
                classification: new ClassificationResultDTO(
                    summary: 'Summary.',
                    sentiment: 'neutral',
                    severity: 2,
                    language: 'en',
                    category: 'general',
                    person: 'unknown',
                    confidence: 80,
                    reasoning: 'Reasoning.',
                    rawResponse: ['id' => 'msg_test'],
                ),
                model: 'claude-test-model',
                metadata: new LlmExecutionMetadataDTO('haiku', 10, 5, 5, 0.001, null),
            ),
            guardResult: new PromptGuardResultDTO(false, null),
        );

        /** @var MockInterface&ExecuteLlmCascadeAction $executeLlmCascadeAction */
        $executeLlmCascadeAction = Mockery::mock(ExecuteLlmCascadeAction::class);
        $executeLlmCascadeAction->shouldReceive('execute')->once()->andReturn($execution);

        /** @var MockInterface&ValidateStructuredClassificationAction $validateStructuredClassificationAction */
        $validateStructuredClassificationAction = Mockery::mock(ValidateStructuredClassificationAction::class);
        $validateStructuredClassificationAction->shouldReceive('execute')
            ->once()
            ->andReturn(new StructuredClassificationResultDTO(
                classification: $execution->cascadeResult->classification,
                validationStatus: ClassificationValidationStatus::Valid,
                validationRetryCount: 0,
                injectionDetected: false,
                guardReason: null,
            ));

        /** @var MockInterface&EvaluateMentionThreatAction $evaluateMentionThreatAction */
        $evaluateMentionThreatAction = Mockery::mock(EvaluateMentionThreatAction::class);
        $evaluateMentionThreatAction->shouldReceive('execute')
            ->once()
            ->with($mention->id)
            ->andReturn(new ThreatResultDTO(
                threatLevel: ThreatLevel::P2,
                threatScore: 70.0,
                factors: [],
            ));

        /** @var MockInterface&RouteMentionAction $routeMentionAction */
        $routeMentionAction = Mockery::mock(RouteMentionAction::class);
        $routeMentionAction->shouldReceive('execute')->once()->with($mention->id);

        $action = new ProcessMentionAction(
            new ProviderFactory($this->app),
            $resolveMentionPersonAction,
            $deduplicateMentionAction,
            $executeLlmCascadeAction,
            $validateStructuredClassificationAction,
            $evaluateMentionThreatAction,
            $routeMentionAction,
        );

        $action->execute($mention->id);

        $mention->refresh();

        $this->assertSame(MentionStatus::Completed, $mention->status);
        $this->assertSame('Normalized body', $mention->content);
        $this->assertSame('content-hash-123', $mention->dedup_hash);
        $this->assertSame('abc123', $mention->simhash);
        $this->assertSame($cluster->id, $mention->mention_cluster_id);
        $this->assertFalse($mention->is_duplicate);
        $this->assertNull($mention->original_mention_id);
    }

    #[Test]
    public function it_marks_mention_as_duplicate_and_skips_classification(): void
    {
        Log::shouldReceive('info')->once();

        $project = Project::query()->create([
            'name' => 'Test Project',
            'slug' => 'test-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::YouScan,
            'external_id' => 'source-1',
            'name' => 'YouScan Source',
            'is_active' => true,
        ]);

        $original = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'mention-original',
            'content' => 'Original',
            'received_at' => now(),
            'status' => MentionStatus::Completed,
        ]);

        $mention = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'mention-copy',
            'content' => '',
            'received_at' => now(),
            'status' => MentionStatus::Pending,
        ]);

        MentionRaw::query()->create([
            'mention_id' => $mention->id,
            'provider' => SourceType::YouScan->value,
            'payload' => [
                'project_id' => $project->id,
                'source_id' => $source->id,
                'id' => 'mention-copy',
                'text' => 'Duplicate body',
            ],
        ]);

        $dedupHash = 'duplicate-hash';

        $cluster = MentionCluster::query()->create([
            'project_id' => $project->id,
            'canonical_mention_id' => $original->id,
            'simhash' => 'dup-simhash',
            'content_fingerprint' => 'dup-fingerprint',
        ]);

        /** @var MockInterface&ResolveMentionPersonAction $resolveMentionPersonAction */
        $resolveMentionPersonAction = Mockery::mock(ResolveMentionPersonAction::class);
        $resolveMentionPersonAction->shouldReceive('execute')
            ->once()
            ->andReturn(new PersonMatchResultDTO(resolvedPerson: null, isAmbiguous: false, candidates: []));

        /** @var MockInterface&DeduplicateMentionAction $deduplicateMentionAction */
        $deduplicateMentionAction = Mockery::mock(DeduplicateMentionAction::class);
        $deduplicateMentionAction->shouldReceive('execute')
            ->once()
            ->andReturn(new DeduplicationResultDTO(
                isDuplicate: true,
                originalMentionId: $original->id,
                dedupHash: $dedupHash,
                clusterId: $cluster->id,
            ));

        /** @var MockInterface&ExecuteLlmCascadeAction $executeLlmCascadeAction */
        $executeLlmCascadeAction = Mockery::mock(ExecuteLlmCascadeAction::class);
        $executeLlmCascadeAction->shouldReceive('execute')->never();

        /** @var MockInterface&ValidateStructuredClassificationAction $validateStructuredClassificationAction */
        $validateStructuredClassificationAction = Mockery::mock(ValidateStructuredClassificationAction::class);
        $validateStructuredClassificationAction->shouldReceive('execute')->never();

        /** @var MockInterface&EvaluateMentionThreatAction $evaluateMentionThreatAction */
        $evaluateMentionThreatAction = Mockery::mock(EvaluateMentionThreatAction::class);
        $evaluateMentionThreatAction->shouldReceive('execute')->never();

        /** @var MockInterface&RouteMentionAction $routeMentionAction */
        $routeMentionAction = Mockery::mock(RouteMentionAction::class);
        $routeMentionAction->shouldReceive('execute')->never();

        $action = new ProcessMentionAction(
            new ProviderFactory($this->app),
            $resolveMentionPersonAction,
            $deduplicateMentionAction,
            $executeLlmCascadeAction,
            $validateStructuredClassificationAction,
            $evaluateMentionThreatAction,
            $routeMentionAction,
        );

        $action->execute($mention->id);

        $mention->refresh();

        $this->assertSame(MentionStatus::Completed, $mention->status);
        $this->assertTrue($mention->is_duplicate);
        $this->assertSame($original->id, $mention->original_mention_id);
        $this->assertSame($dedupHash, $mention->dedup_hash);
        $this->assertSame($cluster->id, $mention->mention_cluster_id);
        $this->assertSame('Duplicate body', $mention->content);
        $this->assertSame('mention-copy', $mention->external_id);
    }

    #[Test]
    public function it_marks_mention_as_failed_when_normalization_fails(): void
    {
        Log::shouldReceive('error')->once();

        $project = Project::query()->create([
            'name' => 'Test Project',
            'slug' => 'test-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::YouScan,
            'external_id' => 'source-1',
            'name' => 'YouScan Source',
            'is_active' => true,
        ]);

        $mention = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'mention-123',
            'content' => '',
            'received_at' => now(),
            'status' => MentionStatus::Pending,
        ]);

        MentionRaw::query()->create([
            'mention_id' => $mention->id,
            'provider' => SourceType::YouScan->value,
            'payload' => [
                'project_id' => $project->id,
                'source_id' => $source->id,
                'id' => 'mention-123',
            ],
        ]);

        /** @var MockInterface&ResolveMentionPersonAction $resolveMentionPersonAction */
        $resolveMentionPersonAction = Mockery::mock(ResolveMentionPersonAction::class);
        $resolveMentionPersonAction->shouldReceive('execute')->never();

        /** @var MockInterface&DeduplicateMentionAction $deduplicateMentionAction */
        $deduplicateMentionAction = Mockery::mock(DeduplicateMentionAction::class);
        $deduplicateMentionAction->shouldReceive('execute')->never();

        /** @var MockInterface&ExecuteLlmCascadeAction $executeLlmCascadeAction */
        $executeLlmCascadeAction = Mockery::mock(ExecuteLlmCascadeAction::class);
        $executeLlmCascadeAction->shouldReceive('execute')->never();

        /** @var MockInterface&ValidateStructuredClassificationAction $validateStructuredClassificationAction */
        $validateStructuredClassificationAction = Mockery::mock(ValidateStructuredClassificationAction::class);
        $validateStructuredClassificationAction->shouldReceive('execute')->never();

        /** @var MockInterface&EvaluateMentionThreatAction $evaluateMentionThreatAction */
        $evaluateMentionThreatAction = Mockery::mock(EvaluateMentionThreatAction::class);
        $evaluateMentionThreatAction->shouldReceive('execute')->never();

        /** @var MockInterface&RouteMentionAction $routeMentionAction */
        $routeMentionAction = Mockery::mock(RouteMentionAction::class);
        $routeMentionAction->shouldReceive('execute')->never();

        $action = new ProcessMentionAction(
            new ProviderFactory($this->app),
            $resolveMentionPersonAction,
            $deduplicateMentionAction,
            $executeLlmCascadeAction,
            $validateStructuredClassificationAction,
            $evaluateMentionThreatAction,
            $routeMentionAction,
        );

        $action->execute($mention->id);

        $mention->refresh();

        $this->assertSame(MentionStatus::Failed, $mention->status);
    }
}
