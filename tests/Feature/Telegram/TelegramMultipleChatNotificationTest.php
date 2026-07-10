<?php

namespace Tests\Feature\Telegram;

use App\Enums\MentionStatus;
use App\Enums\SourceType;
use App\Enums\TelegramNotificationStatus;
use App\Events\MentionRouted;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\MentionRoute;
use App\Models\Project;
use App\Models\Source;
use App\Models\TelegramNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramMultipleChatNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sends_notifications_to_all_configured_chat_ids(): void
    {
        config([
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_ids' => ['-100111111', '-100222222'],
            'telegram.base_url' => 'https://api.telegram.org',
            'telegram.retry.times' => 0,
        ]);

        Http::fake([
            'api.telegram.org/bot*/sendMessage' => function ($request) {
                $chatId = (string) ($request->data()['chat_id'] ?? '');

                return Http::response([
                    'ok' => true,
                    'result' => [
                        'message_id' => $chatId === '-100111111' ? 11 : 22,
                        'chat' => ['id' => $chatId],
                    ],
                ], 200);
            },
        ]);

        $mention = $this->createRoutedMention();

        Event::dispatch(new MentionRouted(
            $mention->id,
            $mention->project_id,
            $mention->source_id,
            now(),
        ));

        $this->assertDatabaseCount('telegram_notifications', 2);

        $notifications = TelegramNotification::query()->orderBy('chat_id')->get();

        $this->assertSame('-100111111', $notifications[0]->chat_id);
        $this->assertSame(TelegramNotificationStatus::Sent, $notifications[0]->status);
        $this->assertSame('11', $notifications[0]->message_id);

        $this->assertSame('-100222222', $notifications[1]->chat_id);
        $this->assertSame(TelegramNotificationStatus::Sent, $notifications[1]->status);
        $this->assertSame('22', $notifications[1]->message_id);

        Http::assertSentCount(2);
    }

    #[Test]
    public function it_continues_sending_when_one_chat_fails(): void
    {
        Log::spy();

        config([
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_ids' => ['-100111111', '-100222222'],
            'telegram.base_url' => 'https://api.telegram.org',
            'telegram.retry.times' => 0,
        ]);

        Http::fake([
            'api.telegram.org/bot*/sendMessage' => function ($request) {
                $chatId = (string) ($request->data()['chat_id'] ?? '');

                if ($chatId === '-100111111') {
                    return Http::response(['ok' => false, 'description' => 'Bad Request'], 400);
                }

                return Http::response([
                    'ok' => true,
                    'result' => [
                        'message_id' => 22,
                        'chat' => ['id' => $chatId],
                    ],
                ], 200);
            },
        ]);

        $mention = $this->createRoutedMention();

        Event::dispatch(new MentionRouted(
            $mention->id,
            $mention->project_id,
            $mention->source_id,
            now(),
        ));

        $failed = TelegramNotification::query()->where('chat_id', '-100111111')->first();
        $sent = TelegramNotification::query()->where('chat_id', '-100222222')->first();

        $this->assertNotNull($failed);
        $this->assertSame(TelegramNotificationStatus::Failed, $failed->status);

        $this->assertNotNull($sent);
        $this->assertSame(TelegramNotificationStatus::Sent, $sent->status);

        Log::shouldHaveReceived('error')
            ->with('Telegram notification failed after retry.', \Mockery::on(
                fn (array $context): bool => $context['chat_id'] === '-100111111',
            ));

        Log::shouldHaveReceived('info')
            ->with('Telegram notification sent.', \Mockery::on(
                fn (array $context): bool => $context['chat_id'] === '-100222222',
            ));
    }

    #[Test]
    public function it_supports_legacy_single_telegram_chat_id_configuration(): void
    {
        config([
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_ids' => [],
            'telegram.chat_id' => '-100123456',
            'telegram.base_url' => 'https://api.telegram.org',
            'telegram.retry.times' => 0,
        ]);

        Http::fake([
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 99,
                    'chat' => ['id' => -100123456],
                ],
            ], 200),
        ]);

        $mention = $this->createRoutedMention();

        Event::dispatch(new MentionRouted(
            $mention->id,
            $mention->project_id,
            $mention->source_id,
            now(),
        ));

        $this->assertDatabaseCount('telegram_notifications', 1);
        $this->assertSame('-100123456', TelegramNotification::query()->value('chat_id'));
    }

    #[Test]
    public function telegram_test_command_sends_to_all_configured_chat_ids(): void
    {
        config([
            'app.env' => 'local',
            'brand24.api_key' => 'test-brand24-api-key',
            'brand24.base_url' => 'https://api-data.brand24.com',
            'brand24.timeout' => 5,
            'brand24.retry.times' => 0,
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_ids' => ['-100111111', '-100222222'],
            'telegram.base_url' => 'https://api.telegram.org',
            'telegram.retry.times' => 0,
        ]);

        Http::fake([
            'api-data.brand24.com/api-data/v1/account/mentions-usage-estimation' => Http::response([
                'status' => 'success',
                'message' => ['mentions_usage_estimation_at_the_end' => 100],
            ], 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 1, 'chat' => ['id' => -100111111]],
            ], 200),
        ]);

        $this->artisan('telegram:test')
            ->expectsOutputToContain('Тест Telegram завершён: 2/2 чатов получили сообщение.')
            ->assertSuccessful();

        $this->assertSame(2, collect(Http::recorded())->filter(
            fn (array $record) => str_contains($record[0]->url(), 'sendMessage'),
        )->count());
    }

    private function createRoutedMention(): Mention
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

        AiResult::query()->create([
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

        return $mention;
    }
}
