<?php

namespace Tests\Feature\Telegram;

use App\Actions\ProcessMentionAction;
use App\Enums\MentionStatus;
use App\Enums\SourceType;
use App\Models\Mention;
use App\Models\MentionRaw;
use App\Models\MentionRoute;
use App\Models\Project;
use App\Models\Source;
use App\Models\TelegramNotification;
use App\Services\Telegram\TelegramCardMessageLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TestTelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_does_not_send_test_notifications_when_disabled(): void
    {
        $this->configureTelegram(enabled: false);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($this->claudeApiResponse('negative'), 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 99, 'chat' => ['id' => -100123456]],
            ], 200),
        ]);

        [$mention] = $this->createPendingMention('The service was terrible and I want a refund.');

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        $this->assertSame(0, $this->sendCountForChat('-100999888'));
        $this->assertGreaterThanOrEqual(1, $this->sendCountForChat('-100123456'));
    }

    #[Test]
    public function it_sends_negative_mentions_to_test_chat_when_enabled(): void
    {
        $this->configureTelegram(enabled: true);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($this->claudeApiResponse('negative'), 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 99, 'chat' => ['id' => -100123456]],
            ], 200),
        ]);

        [$mention] = $this->createPendingMention('The service was terrible and I want a refund.');

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return ($data['chat_id'] ?? null) === '-100999888'
                && str_contains($data['text'] ?? '', '🧪 Acceptance Test')
                && str_contains($data['text'] ?? '', '☹️ Негатив')
                && ! isset($data['reply_markup']);
        });

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return ($data['chat_id'] ?? null) === '-100123456'
                && isset($data['reply_markup']['inline_keyboard']);
        });
    }

    #[Test]
    public function it_sends_positive_mentions_to_test_chat_even_when_moderation_is_skipped(): void
    {
        $this->configureTelegram(enabled: true);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($this->claudeApiResponse('positive'), 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 1, 'chat' => ['id' => -100123456]],
            ], 200),
        ]);

        [$mention] = $this->createPendingMention('Great product, very happy with the purchase.');

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        $route = MentionRoute::query()->where('mention_id', $mention->id)->first();

        $this->assertNotNull($route);
        $this->assertFalse($route->should_notify);
        $this->assertDatabaseCount('telegram_notifications', 0);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return ($data['chat_id'] ?? null) === '-100999888'
                && str_contains($data['text'] ?? '', '🧪 Acceptance Test')
                && str_contains($data['text'] ?? '', '🙂 Позитив');
        });

        $this->assertSame(0, $this->sendCountForChat('-100123456'));
        $this->assertGreaterThanOrEqual(1, $this->sendCountForChat('-100999888'));
    }

    #[Test]
    public function it_sends_neutral_mentions_to_test_chat_when_enabled(): void
    {
        $this->configureTelegram(enabled: true);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($this->claudeApiResponse('neutral'), 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 1, 'chat' => ['id' => -100123456]],
            ], 200),
        ]);

        [$mention] = $this->createPendingMention('The company announced quarterly earnings today.');

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return ($data['chat_id'] ?? null) === '-100999888'
                && str_contains($data['text'] ?? '', '🧪 Acceptance Test')
                && str_contains($data['text'] ?? '', '😐 Нейтрал');
        });
    }

    #[Test]
    public function it_does_not_persist_test_notifications_in_telegram_notifications_table(): void
    {
        $this->configureTelegram(enabled: true);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($this->claudeApiResponse('negative'), 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 99, 'chat' => ['id' => -100123456]],
            ], 200),
        ]);

        [$mention] = $this->createPendingMention('The service was terrible and I want a refund.');

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        $this->assertSame(1, TelegramNotification::query()->count());
        $this->assertSame('-100123456', TelegramNotification::query()->value('chat_id'));
    }

    private function sendCountForChat(string $chatId): int
    {
        return collect(Http::recorded())->filter(function (array $record) use ($chatId): bool {
            $request = $record[0];

            return str_contains($request->url(), 'sendMessage')
                && ($request->data()['chat_id'] ?? null) === $chatId;
        })->count();
    }

    private function configureTelegram(bool $enabled): void
    {
        config([
            'telegram.bot_token' => 'moderation-bot-token',
            'telegram.chat_ids' => ['-100123456'],
            'telegram.base_url' => 'https://api.telegram.org',
            'claude.base_url' => 'https://api.anthropic.com/v1',
            'test_telegram.enabled' => $enabled,
            'test_telegram.bot_token' => 'test-bot-token',
            'test_telegram.chat_ids' => ['-100999888'],
        ]);
    }

    /**
     * @return array{0: Mention}
     */
    private function createPendingMention(string $text): array
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
            'external_id' => 'mention-'.md5($text),
            'content' => '',
            'url' => 'https://example.com/mention',
            'received_at' => now(),
            'status' => MentionStatus::Pending,
        ]);

        MentionRaw::query()->create([
            'mention_id' => $mention->id,
            'provider' => SourceType::YouScan->value,
            'payload' => [
                'project_id' => $project->id,
                'source_id' => $source->id,
                'id' => $mention->external_id,
                'text' => $text,
                'title' => 'Mention title',
                'language' => 'en',
                'url' => 'https://example.com/mention',
                'received_at' => now()->toIso8601String(),
            ],
        ]);

        return [$mention];
    }

    /**
     * @return array<string, mixed>
     */
    private function claudeApiResponse(string $sentiment): array
    {
        $summaries = [
            'negative' => 'Customer complaint about service quality.',
            'positive' => 'Positive product feedback.',
            'neutral' => 'Neutral company announcement coverage.',
        ];

        $severities = [
            'negative' => 4,
            'positive' => 1,
            'neutral' => 2,
        ];

        return [
            'id' => 'msg_'.$sentiment,
            'model' => 'claude-test-model',
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'summary' => $summaries[$sentiment],
                    'sentiment' => $sentiment,
                    'severity' => $severities[$sentiment],
                    'language' => 'en',
                    'category' => 'general',
                    'person' => 'John Doe',
                    'confidence' => 90,
                    'reasoning' => 'Test reasoning.',
                ], JSON_THROW_ON_ERROR),
            ]],
        ];
    }
}
