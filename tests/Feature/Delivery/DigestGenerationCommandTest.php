<?php

namespace Tests\Feature\Delivery;

use App\Enums\DeliveryDigestItemStatus;
use App\Enums\DigestType;
use App\Enums\RoutingDeliveryMode;
use App\Models\DeliveryDigest;
use App\Models\DeliveryDigestItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesDeliverableMention;
use Tests\TestCase;

class DigestGenerationCommandTest extends TestCase
{
    use CreatesDeliverableMention;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'delivery.telegram.telegram_delivery.bot_token' => 'delivery-bot-token',
            'delivery.telegram.telegram_delivery.chat_ids' => ['-100333222'],
            'telegram.base_url' => 'https://api.telegram.org',
        ]);
    }

    #[Test]
    public function it_generates_digest_via_artisan_command(): void
    {
        Http::fake([
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 808, 'chat' => ['id' => -100333222]],
            ], 200),
        ]);

        [$mention] = $this->createDeliverableMention(deliveryMode: RoutingDeliveryMode::Digest, skipModeration: true);

        DeliveryDigestItem::query()->create([
            'mention_id' => $mention->id,
            'project_id' => $mention->project_id,
            'digest_type' => DigestType::Morning,
            'status' => DeliveryDigestItemStatus::Queued,
            'card_payload' => [
                'mention_id' => $mention->id,
                'project_id' => $mention->project_id,
                'person' => 'John Doe',
                'threat_level' => 'P4',
                'threat_score' => 25,
                'source' => 'Delivery Source',
                'summary' => 'Queued digest item',
                'url' => 'https://example.com/post/1',
                'sentiment' => 'negative',
                'severity' => 2,
                'serp_position' => null,
                'cluster_size' => 1,
                'published_at' => now()->subDay()->toIso8601String(),
                'processed_at' => now()->toIso8601String(),
            ],
            'sort_order' => 0,
            'queued_at' => now(),
        ]);

        $this->artisan('delivery:generate-digest', [
            'type' => 'morning',
            '--project' => $mention->project_id,
        ])->assertSuccessful();

        $digest = DeliveryDigest::query()->first();

        $this->assertNotNull($digest);
        $this->assertSame(DigestType::Morning, $digest->digest_type);
        $this->assertSame(1, $digest->item_count);
    }
}
