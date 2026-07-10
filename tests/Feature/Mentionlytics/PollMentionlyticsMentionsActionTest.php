<?php

namespace Tests\Feature\Mentionlytics;

use App\Actions\PollMentionlyticsMentionsAction;
use App\Contracts\MentionlyticsClientInterface;
use App\DTO\MentionlyticsMentionDTO;
use App\DTO\MentionlyticsMentionsPageDTO;
use App\DTO\MentionlyticsMentionsQueryDTO;
use App\Enums\SourceType;
use App\Jobs\ProcessMentionJob;
use App\Models\Project;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PollMentionlyticsMentionsActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_polls_mentions_and_ingests_new_records(): void
    {
        Queue::fake();

        $project = Project::query()->create([
            'name' => 'Test Project',
            'slug' => 'test-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::Mentionlytics,
            'external_id' => 'mentionlytics-source-1',
            'name' => 'Mentionlytics Source',
            'is_active' => true,
            'config' => ['commtracks' => '31016', 'lookback_days' => 3],
        ]);

        $client = $this->createMock(MentionlyticsClientInterface::class);
        $client->expects($this->exactly(2))
            ->method('getMentions')
            ->willReturnOnConsecutiveCalls(
                new MentionlyticsMentionsPageDTO(
                    mentions: [
                        new MentionlyticsMentionDTO(
                            id: '1',
                            uuid: 'uuid-1',
                            text: 'First mention.',
                            url: null,
                            title: null,
                            authorName: null,
                            authorId: null,
                            publishedAt: '2026-07-09 10:00:00',
                            language: 'en',
                            sentiment: 'negative',
                            channel: 'twitter',
                            channelId: 2,
                            engagement: 1,
                            raw: [],
                        ),
                    ],
                    hasMore: true,
                    resultsAfter: 'cursor-2',
                ),
                new MentionlyticsMentionsPageDTO(
                    mentions: [
                        new MentionlyticsMentionDTO(
                            id: '2',
                            uuid: 'uuid-2',
                            text: 'Second mention.',
                            url: null,
                            title: null,
                            authorName: null,
                            authorId: null,
                            publishedAt: '2026-07-09 11:00:00',
                            language: 'en',
                            sentiment: 'neutral',
                            channel: 'web',
                            channelId: 1,
                            engagement: 0,
                            raw: [],
                        ),
                    ],
                    hasMore: false,
                    resultsAfter: null,
                ),
            );

        $this->app->instance(MentionlyticsClientInterface::class, $client);

        $result = $this->app->make(PollMentionlyticsMentionsAction::class)->execute($source);

        $this->assertSame(2, $result['ingested']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(2, $result['pages']);
        $this->assertDatabaseCount('mentions', 2);
        Queue::assertPushed(ProcessMentionJob::class, 2);
    }

    #[Test]
    public function it_skips_already_ingested_mentions_during_polling(): void
    {
        Queue::fake();
        config(['ingest.api_token' => 'test-ingest-token']);

        $project = Project::query()->create([
            'name' => 'Test Project',
            'slug' => 'test-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::Mentionlytics,
            'external_id' => 'mentionlytics-source-1',
            'name' => 'Mentionlytics Source',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/ingest/mentionlytics', [
            'source_uuid' => $source->uuid,
            'mention_id' => 'uuid-1',
            'content' => 'Existing mention.',
        ], [
            'Authorization' => 'Bearer test-ingest-token',
        ])->assertOk();

        Queue::fake();

        $client = $this->createMock(MentionlyticsClientInterface::class);
        $client->expects($this->once())
            ->method('getMentions')
            ->willReturn(new MentionlyticsMentionsPageDTO(
                mentions: [
                    new MentionlyticsMentionDTO(
                        id: '1',
                        uuid: 'uuid-1',
                        text: 'Existing mention.',
                        url: null,
                        title: null,
                        authorName: null,
                        authorId: null,
                        publishedAt: null,
                        language: null,
                        sentiment: null,
                        channel: null,
                        channelId: null,
                        engagement: null,
                        raw: [],
                    ),
                ],
                hasMore: false,
                resultsAfter: null,
            ));

        $this->app->instance(MentionlyticsClientInterface::class, $client);

        $result = $this->app->make(PollMentionlyticsMentionsAction::class)->execute(
            $source,
            new MentionlyticsMentionsQueryDTO('20260701', '20260709'),
        );

        $this->assertSame(0, $result['ingested']);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseCount('mentions', 1);
        Queue::assertNothingPushed();
    }
}
