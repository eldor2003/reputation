<?php

namespace Tests\Feature\Mentionlytics;

use App\Actions\PollMentionlyticsMentionsAction;
use App\Enums\SourceType;
use App\Models\Project;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MentionlyticsPollCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_polls_all_active_mentionlytics_sources(): void
    {
        $project = Project::query()->create([
            'name' => 'Poll Project',
            'slug' => 'poll-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::Mentionlytics,
            'external_id' => 'mentionlytics-poll-source',
            'name' => 'Mentionlytics Poll Source',
            'is_active' => true,
        ]);

        $pollAction = $this->createMock(PollMentionlyticsMentionsAction::class);
        $pollAction->expects($this->once())
            ->method('execute')
            ->with($this->callback(fn (Source $polledSource): bool => $polledSource->is($source)))
            ->willReturn(['ingested' => 2, 'skipped' => 1, 'pages' => 1]);

        $this->app->instance(PollMentionlyticsMentionsAction::class, $pollAction);

        $this->artisan('mentionlytics:poll')
            ->assertSuccessful()
            ->expectsOutputToContain('ingested=2');
    }

    #[Test]
    public function it_warns_when_no_active_sources_exist(): void
    {
        $this->artisan('mentionlytics:poll')
            ->assertSuccessful()
            ->expectsOutputToContain('No active Mentionlytics sources found.');
    }
}
