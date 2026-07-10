<?php

namespace Tests\Feature\Telegram;

use App\Actions\ProcessMentionAction;
use App\Enums\MentionStatus;
use App\Enums\SourceType;
use App\Enums\TelegramNotificationStatus;
use App\Events\MentionRouted;
use App\Models\Mention;
use App\Models\MentionRaw;
use App\Models\MentionRoute;
use App\Models\Project;
use App\Models\Source;
use App\Models\TelegramNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesTelegram;
use Tests\TestCase;

class TelegramNotificationTest extends TestCase
{
    use FakesTelegram;
    use RefreshDatabase;

    #[Test]
    public function it_sends_telegram_notification_when_mention_is_routed_for_notification(): void
    {
        config([
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_id' => '-100123456',
            'telegram.base_url' => 'https://api.telegram.org',
            'claude.base_url' => 'https://api.anthropic.com/v1',
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($this->negativeClaudeApiResponse(), 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 99,
                    'chat' => ['id' => -100123456],
                ],
            ], 200),
        ]);

        [$mention] = $this->createPendingMention();

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        $this->assertDatabaseCount('telegram_notifications', 1);

        $notification = TelegramNotification::query()->first();

        $this->assertNotNull($notification);
        $this->assertSame($mention->id, $notification->mention_id);
        $this->assertSame(TelegramNotificationStatus::Sent, $notification->status);
        $this->assertSame('99', $notification->message_id);
        $this->assertSame('-100123456', $notification->chat_id);
        $this->assertNotNull($notification->sent_at);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_contains($request->url(), 'sendMessage')
                && str_contains($data['text'] ?? '', '🚨 Оповещение о репутации')
                && isset($data['reply_markup']['inline_keyboard'])
                && $data['reply_markup']['inline_keyboard'][0][0]['text'] === '✅ Одобрить'
                && $data['reply_markup']['inline_keyboard'][0][1]['text'] === '❌ Отклонить'
                && $data['reply_markup']['inline_keyboard'][0][2]['text'] === '📌 Пропустить';
        });
    }

    #[Test]
    public function it_skips_telegram_notification_when_should_notify_is_false(): void
    {
        $this->fakeTelegramApi();

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($this->positiveClaudeApiResponse(), 200),
            'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        config(['claude.base_url' => 'https://api.anthropic.com/v1']);

        [$mention] = $this->createPendingMention();

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        $route = MentionRoute::query()->where('mention_id', $mention->id)->first();

        $this->assertNotNull($route);
        $this->assertFalse($route->should_notify);
        $this->assertDatabaseCount('telegram_notifications', 0);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
    }

    #[Test]
    public function it_marks_notification_as_failed_when_telegram_api_fails(): void
    {
        config([
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_id' => '-100123456',
            'telegram.base_url' => 'https://api.telegram.org',
            'telegram.retry.times' => 0,
            'claude.base_url' => 'https://api.anthropic.com/v1',
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($this->negativeClaudeApiResponse(), 200),
            'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => false, 'description' => 'Bad Request'], 400),
        ]);

        [$mention] = $this->createPendingMention();

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        $notification = TelegramNotification::query()->first();

        $this->assertNotNull($notification);
        $this->assertSame(TelegramNotificationStatus::Failed, $notification->status);
        $this->assertNull($notification->message_id);
        $this->assertNull($notification->sent_at);
    }

    #[Test]
    public function it_retries_telegram_notification_once_after_listener_failure(): void
    {
        config([
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_id' => '-100123456',
            'telegram.base_url' => 'https://api.telegram.org',
            'telegram.retry.times' => 0,
            'claude.base_url' => 'https://api.anthropic.com/v1',
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($this->negativeClaudeApiResponse(), 200),
            'api.telegram.org/bot*/sendMessage' => Http::sequence()
                ->push(['ok' => false, 'description' => 'Server error'], 500)
                ->push([
                    'ok' => true,
                    'result' => [
                        'message_id' => 77,
                        'chat' => ['id' => -100123456],
                    ],
                ], 200),
        ]);

        [$mention] = $this->createPendingMention();

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        $notification = TelegramNotification::query()->first();

        $this->assertNotNull($notification);
        $this->assertSame(TelegramNotificationStatus::Sent, $notification->status);
        $this->assertSame('77', $notification->message_id);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
        $this->assertSame(2, collect(Http::recorded())->filter(
            fn (array $record) => str_contains($record[0]->url(), 'sendMessage'),
        )->count());
    }

    #[Test]
    public function it_sends_notification_through_mention_routed_event_listener(): void
    {
        config([
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_id' => '-100123456',
            'telegram.base_url' => 'https://api.telegram.org',
        ]);

        Http::fake([
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 55,
                    'chat' => ['id' => -100123456],
                ],
            ], 200),
        ]);

        [$mention, , , $classification] = $this->createRoutedMention();

        Event::dispatch(new MentionRouted(
            $mention->id,
            $mention->project_id,
            $mention->source_id,
            now(),
        ));

        $notification = TelegramNotification::query()->first();

        $this->assertNotNull($notification);
        $this->assertSame(TelegramNotificationStatus::Sent, $notification->status);
        $this->assertSame('55', $notification->message_id);

        Http::assertSent(function ($request) use ($classification): bool {
            $text = $request->data()['text'] ?? '';

            return str_contains($text, $classification->summary);
        });
    }

    /**
     * @return array{0: Mention}
     */
    private function createPendingMention(): array
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
            'url' => 'https://example.com/complaint',
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
                'text' => 'The service was terrible and I want a refund.',
                'title' => 'Bad experience',
                'language' => 'en',
                'url' => 'https://example.com/complaint',
                'received_at' => now()->toIso8601String(),
            ],
        ]);

        return [$mention];
    }

    /**
     * @return array{0: Mention, 1: Project, 2: Source, 3: \App\Models\AiResult}
     */
    private function createRoutedMention(): array
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
            'content' => 'The service was terrible.',
            'url' => 'https://example.com/complaint',
            'received_at' => now(),
            'status' => MentionStatus::Completed,
        ]);

        $classification = \App\Models\AiResult::query()->create([
            'mention_id' => $mention->id,
            'provider' => 'anthropic',
            'model' => 'claude-test-model',
            'summary' => 'Customer complaint about service quality.',
            'sentiment' => 'negative',
            'severity' => 4,
            'language' => 'en',
            'category' => 'customer_service',
            'person' => 'John Doe',
            'confidence' => 91,
            'reasoning' => 'Reasoning',
            'raw_response' => ['id' => 'msg_123'],
            'processed_at' => now(),
        ]);

        MentionRoute::query()->create([
            'mention_id' => $mention->id,
            'should_notify' => true,
            'priority' => 'immediate',
            'channel' => 'notification',
            'reason' => 'Негативная тональность с критичностью 3 требует оповещения.',
        ]);

        return [$mention, $project, $source, $classification];
    }

    /**
     * @return array<string, mixed>
     */
    private function negativeClaudeApiResponse(): array
    {
        return [
            'id' => 'msg_123',
            'model' => 'claude-test-model',
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'summary' => 'Customer complaint about service quality.',
                    'sentiment' => 'negative',
                    'severity' => 4,
                    'language' => 'en',
                    'category' => 'customer_service',
                    'person' => 'John Doe',
                    'confidence' => 91,
                    'reasoning' => 'The mention describes poor service and requests a refund.',
                ], JSON_THROW_ON_ERROR),
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function positiveClaudeApiResponse(): array
    {
        return [
            'id' => 'msg_456',
            'model' => 'claude-test-model',
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'summary' => 'Positive product feedback.',
                    'sentiment' => 'positive',
                    'severity' => 1,
                    'language' => 'en',
                    'category' => 'product_feedback',
                    'person' => 'unknown',
                    'confidence' => 95,
                    'reasoning' => 'The mention expresses satisfaction.',
                ], JSON_THROW_ON_ERROR),
            ]],
        ];
    }
}
