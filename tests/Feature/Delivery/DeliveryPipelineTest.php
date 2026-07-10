<?php

namespace Tests\Feature\Delivery;

use App\Enums\DeliveryMessageStatus;
use App\Enums\MentionStatus;
use App\Enums\ModerationAction;
use App\Enums\SourceType;
use App\Events\MentionApproved;
use App\Models\DeliveryMessage;
use App\Models\Mention;
use App\Models\ModerationLog;
use App\Models\Project;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesDeliverableMention;
use Tests\TestCase;

class DeliveryPipelineTest extends TestCase
{
    use CreatesDeliverableMention;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/telegram/webhook';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'telegram.webhook_secret' => 'test-webhook-secret',
            'telegram.bot_token' => 'moderation-bot-token',
            'delivery.telegram.telegram_moderation.chat_ids' => ['-100123456'],
            'delivery.telegram.telegram_delivery.bot_token' => 'delivery-bot-token',
            'delivery.telegram.telegram_delivery.chat_ids' => ['-100555444'],
            'telegram.base_url' => 'https://api.telegram.org',
        ]);
    }

    #[Test]
    public function it_delivers_approved_mention_to_delivery_bot(): void
    {
        Http::fake([
            'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true], 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 777, 'chat' => ['id' => -100555444]],
            ], 200),
        ]);

        [$mention] = $this->createDeliverableMention();

        $response = $this->postJson(
            self::ENDPOINT,
            $this->callbackPayload('approve', $mention->id),
            $this->authorizedHeaders(),
        );

        $response->assertOk();

        $this->assertDatabaseCount('moderation_logs', 1);
        $this->assertDatabaseCount('delivery_messages', 1);

        $delivery = DeliveryMessage::query()->first();

        $this->assertNotNull($delivery);
        $this->assertSame($mention->id, $delivery->mention_id);
        $this->assertSame(DeliveryMessageStatus::Sent, $delivery->status);
        $this->assertNotNull($delivery->moderation_log_id);
        $this->assertStringContainsString('John Doe', $delivery->message_text);
        $this->assertStringContainsString('https://example.com/post/1', $delivery->message_text);
        $this->assertStringContainsString('🕒 Publication Date:', $delivery->message_text);
        $this->assertStringContainsString('⚙️ Processed At:', $delivery->message_text);
        $this->assertStringNotContainsString('🕒 Publication Date: Unknown', $delivery->message_text);
    }

    #[Test]
    public function it_does_not_deliver_when_moderation_is_rejected(): void
    {
        Event::fake([MentionApproved::class]);

        Http::fake([
            'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true], 200),
        ]);

        [$mention] = $this->createDeliverableMention();

        $this->postJson(
            self::ENDPOINT,
            $this->callbackPayload('reject', $mention->id),
            $this->authorizedHeaders(),
        )->assertOk();

        $this->assertDatabaseCount('delivery_messages', 0);
        Event::assertNotDispatched(MentionApproved::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function callbackPayload(string $action, int $mentionId): array
    {
        return [
            'callback_query' => [
                'id' => 'callback-query-1',
                'from' => [
                    'id' => 987654321,
                    'username' => 'moderator_user',
                ],
                'message' => [
                    'message_id' => 42,
                    'chat' => ['id' => -100123456],
                ],
                'data' => "moderation:{$action}:{$mentionId}",
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function authorizedHeaders(): array
    {
        return [
            'X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret',
        ];
    }
}
