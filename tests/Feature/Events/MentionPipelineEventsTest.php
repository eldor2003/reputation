<?php

namespace Tests\Feature\Events;

use App\Actions\ProcessMentionAction;
use App\Enums\MentionStatus;
use App\Enums\SourceType;
use App\Events\MentionClassified;
use App\Events\MentionDeduplicated;
use App\Events\MentionNormalized;
use App\Events\MentionProcessingCompleted;
use App\Events\MentionProcessingFailed;
use App\Events\MentionReceived;
use App\Events\MentionRouted;
use App\Models\Mention;
use App\Models\MentionRaw;
use App\Models\Project;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MentionPipelineEventsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_dispatches_mention_received_on_ingest(): void
    {
        Event::fake([MentionReceived::class]);

        config(['ingest.api_token' => 'test-ingest-token']);

        $project = Project::query()->create([
            'name' => 'Test Project',
            'slug' => 'test-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::YouScan,
            'external_id' => 'youscan-source-1',
            'name' => 'YouScan Source',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/ingest/youscan', [
            'source_uuid' => $source->uuid,
            'id' => 'mention-123',
            'text' => 'Sample mention text.',
        ], [
            'Authorization' => 'Bearer test-ingest-token',
        ])->assertOk();

        Event::assertDispatched(MentionReceived::class, function (MentionReceived $event) use ($project, $source): bool {
            return $event->projectId === $project->id
                && $event->sourceId === $source->id
                && $event->mentionId > 0;
        });
    }

    #[Test]
    public function it_dispatches_pipeline_events_for_successful_processing(): void
    {
        Event::fake([
            MentionNormalized::class,
            MentionDeduplicated::class,
            MentionClassified::class,
            MentionRouted::class,
            MentionProcessingCompleted::class,
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($this->claudeApiResponse(), 200),
        ]);

        config(['claude.base_url' => 'https://api.anthropic.com/v1']);

        [$mention, $source, $project] = $this->createPendingMention();

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        Event::assertDispatched(MentionNormalized::class, fn (MentionNormalized $event) => $this->matchesContext($event, $mention, $project, $source));
        Event::assertDispatched(MentionDeduplicated::class, fn (MentionDeduplicated $event) => $this->matchesContext($event, $mention, $project, $source));
        Event::assertDispatched(MentionClassified::class, fn (MentionClassified $event) => $this->matchesContext($event, $mention, $project, $source));
        Event::assertDispatched(MentionRouted::class, fn (MentionRouted $event) => $this->matchesContext($event, $mention, $project, $source));
        Event::assertDispatched(MentionProcessingCompleted::class, fn (MentionProcessingCompleted $event) => $this->matchesContext($event, $mention, $project, $source));
    }

    #[Test]
    public function it_dispatches_completion_without_classification_for_duplicates(): void
    {
        Event::fake([
            MentionNormalized::class,
            MentionDeduplicated::class,
            MentionClassified::class,
            MentionRouted::class,
            MentionProcessingCompleted::class,
        ]);

        [$mention, $source, $project] = $this->createPendingMention(externalId: 'mention-dup');

        $dedupHash = hash('sha256', $source->id.'|mention-dup');

        Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'mention-dup-original',
            'content' => 'Original mention',
            'received_at' => now(),
            'status' => MentionStatus::Completed,
            'dedup_hash' => $dedupHash,
            'is_duplicate' => false,
        ]);

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        Event::assertDispatched(MentionNormalized::class);
        Event::assertDispatched(MentionDeduplicated::class);
        Event::assertDispatched(MentionProcessingCompleted::class);
        Event::assertNotDispatched(MentionClassified::class);
        Event::assertNotDispatched(MentionRouted::class);
    }

    #[Test]
    public function it_dispatches_processing_failed_when_normalization_fails(): void
    {
        Event::fake([
            MentionNormalized::class,
            MentionProcessingFailed::class,
        ]);

        [$mention] = $this->createPendingMention();

        MentionRaw::query()->where('mention_id', $mention->id)->update([
            'payload' => [
                'project_id' => $mention->project_id,
                'source_id' => $mention->source_id,
                'id' => $mention->external_id,
            ],
        ]);

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        Event::assertNotDispatched(MentionNormalized::class);
        Event::assertDispatched(MentionProcessingFailed::class, fn (MentionProcessingFailed $event) => $event->mentionId === $mention->id);
    }

    /**
     * @return array{0: Mention, 1: Source, 2: Project}
     */
    private function createPendingMention(string $externalId = 'mention-123'): array
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
            'external_id' => $externalId,
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
                'id' => $externalId,
                'text' => 'The service was terrible and I want a refund.',
                'title' => 'Bad experience',
                'language' => 'en',
                'received_at' => now()->toIso8601String(),
            ],
        ]);

        return [$mention, $source, $project];
    }

    /**
     * @return array<string, mixed>
     */
    private function claudeApiResponse(): array
    {
        return [
            'id' => 'msg_123',
            'model' => 'claude-test-model',
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode([
                        'summary' => 'Customer complaint about service quality.',
                        'sentiment' => 'negative',
                        'severity' => 4,
                        'language' => 'en',
                        'category' => 'customer_service',
                        'person' => 'unknown',
                        'confidence' => 91,
                        'reasoning' => 'The mention describes poor service and requests a refund.',
                    ], JSON_THROW_ON_ERROR),
                ],
            ],
        ];
    }

    private function matchesContext(
        object $event,
        Mention $mention,
        Project $project,
        Source $source,
    ): bool {
        return $event->mentionId === $mention->id
            && $event->projectId === $project->id
            && $event->sourceId === $source->id;
    }
}
