<?php

namespace Tests\Feature\E2E;

use App\Enums\MentionStatus;
use App\Enums\SourceType;
use App\Enums\TelegramNotificationStatus;
use App\Events\MentionClassified;
use App\Events\MentionProcessingFailed;
use App\Events\MentionReceived;
use App\Models\Mention;
use App\Models\MentionRaw;
use App\Models\TelegramNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithReputationPipeline;
use Tests\TestCase;

class ReputationPipelineE2ETest extends TestCase
{
    use InteractsWithReputationPipeline;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->clearReputationPipelineTime();

        parent::tearDown();
    }

    #[Test]
    public function scenario_1_youscan_webhook_runs_the_full_pipeline_with_moderation(): void
    {
        $this->configureReputationPipeline();
        $this->fakeSuccessfulClaudeAndTelegramApis();

        $source = $this->createSource(SourceType::YouScan);

        $this->startPipelineEventRecording();

        $this->postJson('/api/v1/ingest/youscan', $this->youScanPayload($source), $this->ingestHeaders())
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $mention = Mention::query()->first();

        $this->assertNotNull($mention);
        $this->assertSame('e2e-youscan-mention-1', $mention->external_id);
        $this->assertSame('youscan', MentionRaw::query()->value('provider'));

        $this->assertSuccessfulPipelineArtifacts($mention, 'The service was terrible and I want a refund.');
        $this->assertPipelineEventsDispatched($mention->id);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && isset($request->data()['reply_markup']['inline_keyboard']));

        $this->submitModerationApproval($mention);
    }

    #[Test]
    public function scenario_2_brand24_webhook_runs_the_full_pipeline_with_moderation(): void
    {
        $this->configureReputationPipeline();
        $this->fakeSuccessfulClaudeAndTelegramApis();

        $source = $this->createSource(SourceType::Brand24);

        $this->startPipelineEventRecording();

        $this->postJson('/api/v1/ingest/brand24', $this->brand24Payload($source), $this->ingestHeaders())
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $mention = Mention::query()->first();

        $this->assertNotNull($mention);
        $this->assertSame('e2e-brand24-mention-1', $mention->external_id);
        $this->assertSame('brand24', MentionRaw::query()->value('provider'));

        $this->assertSuccessfulPipelineArtifacts($mention, 'The product quality is unacceptable.');
        $this->assertPipelineEventsDispatched($mention->id);

        $this->submitModerationApproval($mention);
    }

    #[Test]
    public function scenario_3_duplicate_webhook_is_ignored_without_reprocessing(): void
    {
        Log::spy();

        $this->configureReputationPipeline();
        $this->fakeSuccessfulClaudeAndTelegramApis();

        $source = $this->createSource(SourceType::YouScan);
        $payload = $this->youScanPayload($source);

        $this->startPipelineEventRecording();

        $this->postJson('/api/v1/ingest/youscan', $payload, $this->ingestHeaders())->assertOk();
        $this->postJson('/api/v1/ingest/youscan', $payload, $this->ingestHeaders())->assertOk();

        $this->assertDatabaseCount('mentions', 1);
        $this->assertDatabaseCount('mention_raws', 1);
        $this->assertDatabaseCount('ingest_idempotency_keys', 1);
        $this->assertDatabaseCount('ai_results', 1);
        $this->assertDatabaseCount('mention_routes', 1);
        $this->assertDatabaseCount('telegram_notifications', 1);

        $this->assertEventDispatchedTimes(MentionReceived::class, 1);
        $this->assertEventDispatchedTimes(MentionClassified::class, 1);

        Log::shouldHaveReceived('info')
            ->with('Duplicate ingest webhook ignored.', \Mockery::on(fn (array $context): bool => $context['provider'] === 'youscan'
                && $context['external_id'] === 'e2e-youscan-mention-1'
                && $context['reason'] === 'database'));
    }

    #[Test]
    public function scenario_4_claude_failure_after_retry_marks_mention_as_failed(): void
    {
        $this->configureReputationPipeline();

        config([
            'claude.base_url' => 'https://api.anthropic.com/v1',
            'telegram.retry.times' => 0,
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::sequence()
                ->push([
                    'id' => 'msg_invalid_1',
                    'content' => [['type' => 'text', 'text' => 'not-json']],
                ], 200)
                ->push([
                    'id' => 'msg_invalid_2',
                    'content' => [['type' => 'text', 'text' => 'still-not-json']],
                ], 200),
        ]);

        $source = $this->createSource(SourceType::YouScan);

        $this->startPipelineEventRecording();

        $this->postJson('/api/v1/ingest/youscan', $this->youScanPayload($source), $this->ingestHeaders())
            ->assertOk();

        $mention = Mention::query()->first();

        $this->assertNotNull($mention);
        $this->assertSame(MentionStatus::Failed, $mention->status);

        $this->assertDatabaseCount('mention_raws', 1);
        $this->assertDatabaseCount('ai_results', 0);
        $this->assertDatabaseCount('mention_routes', 0);
        $this->assertDatabaseCount('telegram_notifications', 0);

        $this->assertEventDispatchedForMention(MentionProcessingFailed::class, $mention->id);
        $this->assertEventNotDispatched(MentionClassified::class);

        $this->assertSame(2, collect(Http::recorded())->filter(
            fn (array $record) => str_contains($record[0]->url(), 'anthropic.com'),
        )->count());
    }

    #[Test]
    public function scenario_5_telegram_failure_after_retry_marks_notification_as_failed(): void
    {
        $this->configureReputationPipeline();

        config([
            'telegram.retry.times' => 0,
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($this->negativeClaudeApiResponse(), 200),
            'api.telegram.org/bot*/sendMessage' => Http::sequence()
                ->push(['ok' => false, 'description' => 'Bad Request'], 400)
                ->push(['ok' => false, 'description' => 'Bad Request'], 400),
        ]);

        $source = $this->createSource(SourceType::YouScan);

        $this->startPipelineEventRecording();

        $this->postJson('/api/v1/ingest/youscan', $this->youScanPayload($source), $this->ingestHeaders())
            ->assertOk();

        $mention = Mention::query()->first();

        $this->assertNotNull($mention);
        $this->assertSame(MentionStatus::Completed, $mention->status);

        $this->assertDatabaseCount('ai_results', 1);
        $this->assertDatabaseCount('mention_routes', 1);

        $notification = TelegramNotification::query()->first();

        $this->assertNotNull($notification);
        $this->assertSame(TelegramNotificationStatus::Failed, $notification->status);
        $this->assertNull($notification->message_id);
        $this->assertNull($notification->sent_at);

        $this->assertDatabaseHas('mention_routes', [
            'mention_id' => $mention->id,
            'should_notify' => true,
        ]);
        $this->assertEventNotDispatched(MentionApproved::class);

        $this->assertSame(2, collect(Http::recorded())->filter(
            fn (array $record) => str_contains($record[0]->url(), 'sendMessage'),
        )->count());
    }
}
