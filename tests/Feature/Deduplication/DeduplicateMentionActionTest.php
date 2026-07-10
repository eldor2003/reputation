<?php

namespace Tests\Feature\Deduplication;

use App\Actions\DeduplicateMentionAction;
use App\Events\MentionClustered;
use App\Events\MentionDeduplicated;
use App\Enums\MentionStatus;
use App\Enums\SourceType;
use App\Models\Mention;
use App\Models\Project;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeduplicateMentionActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_dispatches_deduplication_and_cluster_events(): void
    {
        Event::fake([MentionDeduplicated::class, MentionClustered::class]);

        $project = Project::query()->create([
            'name' => 'Event Project',
            'slug' => 'event-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::Brand24,
            'external_id' => 'brand24-event',
            'name' => 'Brand24',
            'is_active' => true,
        ]);

        $mention = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'event-mention',
            'content' => '',
            'received_at' => now(),
            'status' => MentionStatus::Processing,
        ]);

        $this->app->make(DeduplicateMentionAction::class)->execute(
            $mention->id,
            new \App\DTO\NormalizedMentionDTO(
                projectId: $project->id,
                sourceId: $source->id,
                externalId: 'event-mention',
                author: 'Author',
                authorId: null,
                language: 'en',
                text: 'Unique event mention content.',
                title: 'Event title',
                url: 'https://news.example.com/event',
                publishedAt: now(),
                receivedAt: now(),
            ),
        );

        Event::assertDispatched(MentionDeduplicated::class);
        Event::assertDispatched(MentionClustered::class);
    }
}
