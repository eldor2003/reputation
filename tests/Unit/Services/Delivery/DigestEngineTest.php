<?php

namespace Tests\Unit\Services\Delivery;

use App\Contracts\DeliveryEngineInterface;
use App\Contracts\DigestEngineInterface;
use App\Enums\DeliveryDigestItemStatus;
use App\Enums\DeliveryDigestStatus;
use App\Enums\DeliveryMessageStatus;
use App\Enums\DigestType;
use App\Enums\RoutingDeliveryMode;
use App\Enums\ThreatLevel;
use App\Models\DeliveryDigest;
use App\Models\DeliveryDigestItem;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesDeliverableMention;
use Tests\TestCase;

class DigestEngineTest extends TestCase
{
    use CreatesDeliverableMention;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'delivery.telegram.telegram_delivery.bot_token' => 'delivery-bot-token',
            'delivery.telegram.telegram_delivery.chat_ids' => ['-100777666'],
            'telegram.base_url' => 'https://api.telegram.org',
        ]);
    }

    #[Test]
    public function it_generates_and_sends_morning_digest_for_project(): void
    {
        Http::fake([
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 900, 'chat' => ['id' => -100777666]],
            ], 200),
        ]);

        [$mention] = $this->createDeliverableMention(
            threatLevel: ThreatLevel::P4,
            deliveryMode: RoutingDeliveryMode::Digest,
            skipModeration: true,
        );

        $this->app->make(DeliveryEngineInterface::class)->queueForDigest($mention->id, DigestType::Morning->value);

        $result = $this->app->make(DigestEngineInterface::class)->generate(DigestType::Morning, $mention->project_id);

        $this->assertTrue($result->success);
        $this->assertNotNull($result->digest);
        $this->assertSame(DeliveryDigestStatus::Sent, $result->digest->status);
        $this->assertSame(1, $result->digest->item_count);
        $this->assertStringContainsString('Утренний дайджест', (string) $result->digest->message_text);

        $item = DeliveryDigestItem::query()->first();
        $this->assertSame(DeliveryDigestItemStatus::Sent, $item->status);
        $this->assertSame(DeliveryMessageStatus::Sent, $result->message?->status);
    }

    #[Test]
    public function it_generates_digests_for_multiple_projects(): void
    {
        Http::fake([
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 901, 'chat' => ['id' => -100777666]],
            ], 200),
        ]);

        [$mentionA, $projectA] = $this->createDeliverableMention(
            deliveryMode: RoutingDeliveryMode::Digest,
            skipModeration: true,
        );
        [$mentionB] = $this->createDeliverableMention(
            deliveryMode: RoutingDeliveryMode::Digest,
            skipModeration: true,
        );

        $projectB = Project::query()->find($mentionB->project_id);
        $this->assertNotNull($projectB);

        $engine = $this->app->make(DeliveryEngineInterface::class);
        $engine->queueForDigest($mentionA->id, DigestType::Evening->value);
        $engine->queueForDigest($mentionB->id, DigestType::Evening->value);

        $this->app->make(DigestEngineInterface::class)->generate(DigestType::Evening);

        $this->assertSame(2, DeliveryDigest::query()->count());
        $this->assertSame(2, DeliveryDigestItem::query()->where('status', DeliveryDigestItemStatus::Sent)->count());
    }

    #[Test]
    public function it_marks_digest_as_failed_when_delivery_bot_is_unavailable(): void
    {
        Http::fake([
            'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => false], 500),
        ]);

        [$mention] = $this->createDeliverableMention(
            deliveryMode: RoutingDeliveryMode::Digest,
            skipModeration: true,
        );

        $this->app->make(DeliveryEngineInterface::class)->queueForDigest($mention->id, DigestType::Manual->value);

        $result = $this->app->make(DigestEngineInterface::class)->generate(DigestType::Manual, $mention->project_id);

        $this->assertFalse($result->success);
        $this->assertSame(DeliveryDigestStatus::Failed, $result->digest?->status);
        $this->assertSame(DeliveryDigestItemStatus::Failed, DeliveryDigestItem::query()->first()?->status);
    }
}
