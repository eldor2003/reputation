<?php

namespace Tests\Concerns;

use App\Enums\MentionStatus;
use App\Enums\ModerationAction;
use App\Enums\RoutingChannel;
use App\Enums\RoutingPriority;
use App\Enums\SourceType;
use App\Enums\TelegramNotificationStatus;
use App\Events\MentionApproved;
use App\Events\MentionClassified;
use App\Events\MentionDeduplicated;
use App\Events\MentionNormalized;
use App\Events\MentionProcessingFailed;
use App\Events\MentionReceived;
use App\Events\MentionRouted;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\MentionRaw;
use App\Models\MentionRoute;
use App\Models\ModerationLog;
use App\Models\Project;
use App\Models\Source;
use App\Models\TelegramNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

trait InteractsWithReputationPipeline
{
    /** @var array<string, list<object>> */
    protected array $recordedPipelineEvents = [];

    protected function configureReputationPipeline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 14:00:00', 'UTC'));

        config([
            'app.timezone' => 'UTC',
            'ingest.api_token' => 'test-ingest-token',
            'ingest.idempotency.lock_ttl_seconds' => 300,
            'claude.base_url' => 'https://api.anthropic.com/v1',
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_ids' => ['-100123456'],
            'telegram.chat_id' => '-100123456',
            'telegram.base_url' => 'https://api.telegram.org',
            'telegram.webhook_secret' => 'test-webhook-secret',
            'delivery.telegram.telegram_delivery.bot_token' => 'delivery-bot-token',
            'delivery.telegram.telegram_delivery.chat_ids' => ['-100987654'],
        ]);
    }

    protected function clearReputationPipelineTime(): void
    {
        Carbon::setTestNow();
    }

    protected function startPipelineEventRecording(): void
    {
        $this->recordedPipelineEvents = [];

        foreach ([
            MentionReceived::class,
            MentionNormalized::class,
            MentionDeduplicated::class,
            MentionClassified::class,
            MentionApproved::class,
            MentionProcessingFailed::class,
        ] as $eventClass) {
            Event::listen($eventClass, function (object $event) use ($eventClass): void {
                $this->recordedPipelineEvents[$eventClass][] = $event;
            });
        }
    }

    protected function assertEventDispatchedTimes(string $eventClass, int $times): void
    {
        $this->assertCount($times, $this->recordedPipelineEvents[$eventClass] ?? []);
    }

    protected function assertEventNotDispatched(string $eventClass): void
    {
        $this->assertEmpty($this->recordedPipelineEvents[$eventClass] ?? []);
    }

    protected function assertEventDispatchedForMention(string $eventClass, int $mentionId): void
    {
        $dispatched = collect($this->recordedPipelineEvents[$eventClass] ?? [])
            ->contains(fn (object $event): bool => property_exists($event, 'mentionId') && $event->mentionId === $mentionId);

        $this->assertTrue($dispatched, "Expected {$eventClass} to be dispatched for mention {$mentionId}.");
    }

    protected function createSource(SourceType $type): Source
    {
        $project = Project::query()->create([
            'name' => 'E2E Project',
            'slug' => 'e2e-project',
            'is_active' => true,
        ]);

        return Source::query()->create([
            'project_id' => $project->id,
            'type' => $type,
            'external_id' => $type->value.'-source-1',
            'name' => $type->name.' Source',
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function negativeClaudeApiResponse(string $summary = 'Customer complaint about service quality.'): array
    {
        return [
            'id' => 'msg_e2e_123',
            'model' => 'claude-test-model',
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'summary' => $summary,
                    'sentiment' => 'negative',
                    'severity' => 4,
                    'language' => 'en',
                    'category' => 'customer_service',
                    'person' => 'John Doe',
                    'confidence' => 91,
                    'reasoning' => 'The mention describes poor service and requests action.',
                ], JSON_THROW_ON_ERROR),
            ]],
        ];
    }

    protected function fakeSuccessfulClaudeAndTelegramApis(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($this->negativeClaudeApiResponse(), 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 501,
                    'chat' => ['id' => -100123456],
                ],
            ], 200),
            'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true], 200),
        ]);
    }

    protected function assertSuccessfulPipelineArtifacts(Mention $mention, string $expectedContent): void
    {
        $mention->refresh();

        $this->assertSame(MentionStatus::Completed, $mention->status);
        $this->assertSame($expectedContent, $mention->content);

        $this->assertDatabaseCount('mention_raws', 1);

        $raw = MentionRaw::query()->first();

        $this->assertNotNull($raw);
        $this->assertSame($mention->id, $raw->mention_id);

        $this->assertDatabaseCount('ai_results', 1);

        $aiResult = AiResult::query()->first();

        $this->assertNotNull($aiResult);
        $this->assertSame($mention->id, $aiResult->mention_id);
        $this->assertSame('negative', $aiResult->sentiment);

        $route = MentionRoute::query()->first();

        $this->assertNotNull($route);
        $this->assertSame($mention->id, $route->mention_id);
        $this->assertTrue($route->should_notify);
        $this->assertSame(RoutingPriority::Normal, $route->priority);
        $this->assertSame(RoutingChannel::Notification, $route->channel);

        $notification = TelegramNotification::query()->first();

        $this->assertNotNull($notification);
        $this->assertSame($mention->id, $notification->mention_id);
        $this->assertSame(TelegramNotificationStatus::Sent, $notification->status);
        $this->assertSame('501', $notification->message_id);
    }

    protected function assertPipelineEventsDispatched(int $mentionId): void
    {
        $this->assertEventDispatchedForMention(MentionReceived::class, $mentionId);
        $this->assertEventDispatchedForMention(MentionNormalized::class, $mentionId);
        $this->assertEventDispatchedForMention(MentionDeduplicated::class, $mentionId);
        $this->assertEventDispatchedForMention(MentionClassified::class, $mentionId);

        $this->assertDatabaseHas('mention_routes', [
            'mention_id' => $mentionId,
        ]);
    }

    protected function submitModerationApproval(Mention $mention): void
    {
        $this->postJson('/api/v1/telegram/webhook', [
            'callback_query' => [
                'id' => 'callback-query-e2e',
                'from' => [
                    'id' => 987654321,
                    'username' => 'moderator_user',
                ],
                'message' => [
                    'message_id' => 501,
                    'chat' => ['id' => -100123456],
                ],
                'data' => "moderation:approve:{$mention->id}",
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret',
        ])->assertOk();

        $log = ModerationLog::query()->where('mention_id', $mention->id)->first();

        $this->assertNotNull($log);
        $this->assertSame(ModerationAction::Approve, $log->action);

        $this->assertEventDispatchedForMention(MentionApproved::class, $mention->id);
    }

    /**
     * @return array<string, string>
     */
    protected function ingestHeaders(): array
    {
        return [
            'Authorization' => 'Bearer test-ingest-token',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function youScanPayload(Source $source): array
    {
        return [
            'source_uuid' => $source->uuid,
            'id' => 'e2e-youscan-mention-1',
            'text' => 'The service was terrible and I want a refund.',
            'url' => 'https://example.com/youscan/1',
            'title' => 'Bad experience',
            'language' => 'en',
            'author' => 'Jane Doe',
            'published' => '2026-06-29T10:00:00Z',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function brand24Payload(Source $source): array
    {
        return [
            'source_uuid' => $source->uuid,
            'mention_id' => 'e2e-brand24-mention-1',
            'content' => 'The product quality is unacceptable.',
            'url' => 'https://example.com/brand24/1',
            'title' => 'Bad review',
            'language' => 'en',
            'author_name' => 'John Doe',
            'author_id' => 'johndoe',
            'date' => '2026-06-29T10:00:00Z',
        ];
    }
}
