<?php

namespace Tests\Unit\Services\Delivery;

use App\Contracts\DeliveryEngineInterface;
use App\Enums\DeliveryMessageStatus;
use App\Enums\RoutingDeliveryMode;
use App\Enums\ThreatLevel;
use App\Models\DeliveryDigestItem;
use App\Models\DeliveryMessage;
use App\Services\Telegram\TelegramCardMessageLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesDeliverableMention;
use Tests\TestCase;

class DeliveryEngineTest extends TestCase
{
    use CreatesDeliverableMention;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'delivery.telegram.telegram_delivery.bot_token' => 'delivery-bot-token',
            'delivery.telegram.telegram_delivery.chat_ids' => ['-100999888'],
            'telegram.base_url' => 'https://api.telegram.org',
        ]);
    }

    #[Test]
    public function it_sends_delivery_card_after_approval(): void
    {
        Http::fake([
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 501, 'chat' => ['id' => -100999888]],
            ], 200),
        ]);

        [$mention] = $this->createDeliverableMention();
        $this->approveMention($mention);

        $result = $this->app->make(DeliveryEngineInterface::class)->deliverApproved($mention->id);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->message);
        $this->assertSame(DeliveryMessageStatus::Sent, $result->message->status);
        $this->assertSame('-100999888', $result->message->chat_id);
        $this->assertSame('501', $result->message->telegram_message_id);
        $this->assertStringContainsString('📝 Summary', $result->message->message_text);
        $this->assertStringContainsString('Critical customer complaint summary.', $result->message->message_text);
        $this->assertStringContainsString('P2', $result->message->message_text);
        $this->assertStringContainsString('⏱️', $result->message->message_text);
        $this->assertStringContainsString(TelegramCardMessageLayout::CONFIRMATION_SEPARATOR, $result->message->message_text);
        $this->assertStringContainsString('✓ Подтверждено ·', $result->message->message_text);
        $this->assertStringContainsString(TelegramCardMessageLayout::SEPARATOR, $result->message->message_text);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
    }

    #[Test]
    public function it_queues_digest_delivery_when_route_requires_digest(): void
    {
        [$mention] = $this->createDeliverableMention(
            threatLevel: ThreatLevel::P4,
            deliveryMode: RoutingDeliveryMode::Digest,
        );
        $this->approveMention($mention);

        $result = $this->app->make(DeliveryEngineInterface::class)->deliverApproved($mention->id);

        $this->assertTrue($result->success);
        $this->assertTrue($result->queuedForDigest);
        $this->assertSame(DeliveryMessageStatus::Queued, $result->message?->status);
        $this->assertDatabaseCount('delivery_digest_items', 1);

        $item = DeliveryDigestItem::query()->first();
        $this->assertSame($mention->id, $item->mention_id);
    }

    #[Test]
    public function it_marks_delivery_as_failed_when_telegram_rejects_request(): void
    {
        Http::fake([
            'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => false], 500),
        ]);

        [$mention] = $this->createDeliverableMention();
        $this->approveMention($mention);

        $result = $this->app->make(DeliveryEngineInterface::class)->deliverApproved($mention->id);

        $this->assertFalse($result->success);
        $this->assertSame(DeliveryMessageStatus::Failed, $result->message?->status);
        $this->assertNotEmpty($result->errorMessage);
    }
}
