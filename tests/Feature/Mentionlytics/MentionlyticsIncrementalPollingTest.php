<?php

namespace Tests\Feature\Mentionlytics;

use App\Actions\PollMentionlyticsMentionsAction;
use App\Contracts\MentionlyticsClientInterface;
use App\DTO\MentionlyticsMentionDTO;
use App\DTO\MentionlyticsMentionsPageDTO;
use App\DTO\MentionlyticsMentionsQueryDTO;
use App\DTO\MentionlyticsPollingCheckpointDTO;
use App\Enums\MentionStatus;
use App\Enums\ThreatLevel;
use App\Enums\RoutingDeliveryMode;
use App\Enums\SourceType;
use App\Jobs\ProcessMentionJob;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\MentionRoute;
use App\Models\MentionThreatResult;
use App\Models\Project;
use App\Models\Source;
use App\Models\TelegramNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MentionlyticsIncrementalPollingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function bootstrap_poll_suppresses_notifications_and_saves_checkpoint(): void
    {
        Queue::fake();

        $source = $this->createMentionlyticsSource();

        $client = $this->createMock(MentionlyticsClientInterface::class);
        $client->expects($this->once())
            ->method('getMentions')
            ->willReturn(new MentionlyticsMentionsPageDTO(
                mentions: [
                    new MentionlyticsMentionDTO(
                        id: '1',
                        uuid: 'uuid-1',
                        text: 'Bootstrap mention.',
                        url: null,
                        title: null,
                        authorName: null,
                        authorId: null,
                        publishedAt: '2026-07-11 10:00:00',
                        language: 'en',
                        sentiment: 'negative',
                        channel: 'web',
                        channelId: 1,
                        engagement: 0,
                        raw: [],
                    ),
                ],
                hasMore: false,
                resultsAfter: null,
            ));

        $this->app->instance(MentionlyticsClientInterface::class, $client);

        $result = $this->app->make(PollMentionlyticsMentionsAction::class)->execute($source);

        $this->assertSame('bootstrap', $result['mode']);
        $this->assertSame(1, $result['ingested']);

        $source->refresh();
        $checkpoint = $source->config['mentionlytics_polling'] ?? null;

        $this->assertIsArray($checkpoint);
        $this->assertSame('uuid-1', $checkpoint['last_processed_mention_id']);
        $this->assertNotEmpty($checkpoint['bootstrap_completed_at']);
        $this->assertSame(
            '2026-07-11 10:00:00',
            \Carbon\Carbon::parse((string) $checkpoint['last_processed_at'])->format('Y-m-d H:i:s'),
        );
    }

    #[Test]
    public function incremental_poll_skips_mentions_at_or_before_checkpoint(): void
    {
        Queue::fake();

        $source = $this->createMentionlyticsSource([
            'mentionlytics_polling' => (new MentionlyticsPollingCheckpointDTO(
                lastProcessedAt: '2026-07-11T10:00:00+00:00',
                lastProcessedMentionId: 'uuid-1',
                bootstrapCompletedAt: '2026-07-11T09:00:00+00:00',
            ))->toArray(),
        ]);

        $client = $this->createMock(MentionlyticsClientInterface::class);
        $client->expects($this->once())
            ->method('getMentions')
            ->with($this->callback(function (MentionlyticsMentionsQueryDTO $query): bool {
                return $query->startDate === '20260711'
                    && $query->endDate === now()->format('Ymd');
            }))
            ->willReturn(new MentionlyticsMentionsPageDTO(
                mentions: [
                    new MentionlyticsMentionDTO(
                        id: '1',
                        uuid: 'uuid-1',
                        text: 'Old mention.',
                        url: null,
                        title: null,
                        authorName: null,
                        authorId: null,
                        publishedAt: '2026-07-11 10:00:00',
                        language: 'en',
                        sentiment: 'negative',
                        channel: 'web',
                        channelId: 1,
                        engagement: 0,
                        raw: [],
                    ),
                    new MentionlyticsMentionDTO(
                        id: '2',
                        uuid: 'uuid-2',
                        text: 'New mention.',
                        url: null,
                        title: null,
                        authorName: null,
                        authorId: null,
                        publishedAt: '2026-07-11 11:00:00',
                        language: 'en',
                        sentiment: 'negative',
                        channel: 'web',
                        channelId: 1,
                        engagement: 0,
                        raw: [],
                    ),
                ],
                hasMore: false,
                resultsAfter: null,
            ));

        $this->app->instance(MentionlyticsClientInterface::class, $client);

        $result = $this->app->make(PollMentionlyticsMentionsAction::class)->execute($source);

        $this->assertSame('incremental', $result['mode']);
        $this->assertSame(1, $result['ingested']);
        $this->assertSame(1, $result['skipped_checkpoint']);
        Queue::assertPushed(ProcessMentionJob::class, 1);
    }

    #[Test]
    public function second_incremental_poll_imports_zero_old_mentions(): void
    {
        Queue::fake();
        config(['ingest.api_token' => 'test-ingest-token']);

        $source = $this->createMentionlyticsSource([
            'mentionlytics_polling' => (new MentionlyticsPollingCheckpointDTO(
                lastProcessedAt: '2026-07-11T11:00:00+00:00',
                lastProcessedMentionId: 'uuid-2',
                bootstrapCompletedAt: '2026-07-11T09:00:00+00:00',
            ))->toArray(),
        ]);

        $this->postJson('/api/v1/ingest/mentionlytics', [
            'source_uuid' => $source->uuid,
            'mention_id' => 'uuid-2',
            'content' => 'Already imported mention.',
            'date' => '2026-07-11 11:00:00',
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
                        text: 'Old mention.',
                        url: null,
                        title: null,
                        authorName: null,
                        authorId: null,
                        publishedAt: '2026-07-11 10:00:00',
                        language: 'en',
                        sentiment: 'negative',
                        channel: 'web',
                        channelId: 1,
                        engagement: 0,
                        raw: [],
                    ),
                    new MentionlyticsMentionDTO(
                        id: '2',
                        uuid: 'uuid-2',
                        text: 'Already imported mention.',
                        url: null,
                        title: null,
                        authorName: null,
                        authorId: null,
                        publishedAt: '2026-07-11 11:00:00',
                        language: 'en',
                        sentiment: 'negative',
                        channel: 'web',
                        channelId: 1,
                        engagement: 0,
                        raw: [],
                    ),
                ],
                hasMore: false,
                resultsAfter: null,
            ));

        $this->app->instance(MentionlyticsClientInterface::class, $client);

        $result = $this->app->make(PollMentionlyticsMentionsAction::class)->execute($source);

        $this->assertSame('incremental', $result['mode']);
        $this->assertSame(0, $result['ingested']);
        $this->assertSame(2, $result['skipped_checkpoint']);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function bootstrap_routed_mentions_do_not_send_telegram_notifications(): void
    {
        $project = Project::query()->create([
            'name' => 'Route Project',
            'slug' => 'route-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::Mentionlytics,
            'external_id' => 'mentionlytics-route-source',
            'name' => 'Mentionlytics Route Source',
            'is_active' => true,
        ]);

        $mention = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'bootstrap-mention',
            'content' => 'Bootstrap mention',
            'received_at' => now(),
            'status' => MentionStatus::Processing,
            'metadata' => ['suppress_notifications' => true],
        ]);

        AiResult::query()->create([
            'mention_id' => $mention->id,
            'provider' => 'anthropic',
            'model' => 'claude-test',
            'sentiment' => 'negative',
            'severity' => 3,
            'confidence' => 90,
            'summary' => 'Summary',
            'category' => 'general',
            'language' => 'en',
            'raw_response' => ['id' => 'msg_123'],
            'processed_at' => now(),
        ]);

        MentionThreatResult::query()->create([
            'mention_id' => $mention->id,
            'ai_result_id' => AiResult::query()->where('mention_id', $mention->id)->value('id'),
            'threat_level' => ThreatLevel::P1,
            'threat_score' => 90.0,
            'factor_scores' => [],
            'assessed_at' => now(),
        ]);

        $this->app->make(\App\Actions\RouteMentionAction::class)->execute($mention->id);

        $route = MentionRoute::query()->where('mention_id', $mention->id)->first();

        $this->assertNotNull($route);
        $this->assertFalse($route->should_notify);
        $this->assertSame(RoutingDeliveryMode::Skip, $route->delivery_mode);
        $this->assertSame(0, TelegramNotification::query()->where('mention_id', $mention->id)->count());
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function createMentionlyticsSource(array $config = []): Source
    {
        $project = Project::query()->create([
            'name' => 'Polling Project',
            'slug' => 'polling-project',
            'is_active' => true,
        ]);

        return Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::Mentionlytics,
            'external_id' => 'mentionlytics-polling-source',
            'name' => 'Mentionlytics Polling Source',
            'is_active' => true,
            'config' => $config,
        ]);
    }
}
