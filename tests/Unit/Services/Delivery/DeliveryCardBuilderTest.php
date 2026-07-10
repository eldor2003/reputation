<?php

namespace Tests\Unit\Services\Delivery;

use App\Contracts\DeliveryCardBuilderInterface;
use App\Contracts\DeliveryContextBuilderInterface;
use App\DTO\DeliveryCardDTO;
use App\Enums\MentionStatus;
use App\Enums\SourceType;
use App\Enums\ThreatLevel;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\MentionRoute;
use App\Models\MentionThreatResult;
use App\Models\Project;
use App\Models\Source;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeliveryCardBuilderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_displays_publication_date_and_processed_at(): void
    {
        Carbon::setTestNow('2026-07-10 16:00:00');
        config(['app.timezone' => 'UTC']);

        $publishedAt = Carbon::parse('2026-07-10 14:35:00', 'UTC');
        $context = $this->makeContext($publishedAt);

        $card = $this->app->make(DeliveryCardBuilderInterface::class)->build($context);
        $message = $this->app->make(DeliveryCardBuilderInterface::class)->formatCard($card);

        $this->assertSame($publishedAt->toIso8601String(), $card->publishedAt?->toIso8601String());
        $this->assertStringContainsString('🕒 Publication Date:', $message);
        $this->assertStringContainsString('10.07.2026 14:35 UTC', $message);
        $this->assertStringContainsString('⚙️ Processed At:', $message);
        $this->assertStringContainsString('10.07.2026 16:00 UTC', $message);

        Carbon::setTestNow();
    }

    #[Test]
    public function it_displays_unknown_when_publication_date_is_missing(): void
    {
        config(['app.timezone' => 'UTC']);

        $context = $this->makeContext(null);
        $message = $this->app->make(DeliveryCardBuilderInterface::class)->formatCard(
            $this->app->make(DeliveryCardBuilderInterface::class)->build($context),
        );

        $this->assertStringContainsString('🕒 Publication Date:', $message);
        $this->assertStringContainsString('Unknown', $message);
        $this->assertStringContainsString('⚙️ Processed At:', $message);
    }

    #[Test]
    public function it_formats_timestamps_in_configured_timezone(): void
    {
        Carbon::setTestNow('2026-07-10 12:00:00');
        config(['app.timezone' => 'Europe/Moscow']);

        $publishedAt = Carbon::parse('2026-07-10 14:35:00', 'UTC');
        $context = $this->makeContext($publishedAt);

        $message = $this->app->make(DeliveryCardBuilderInterface::class)->formatCard(
            $this->app->make(DeliveryCardBuilderInterface::class)->build($context),
        );

        $this->assertStringContainsString('10.07.2026 17:35 MSK', $message);
        $this->assertStringContainsString('10.07.2026 15:00 MSK', $message);

        Carbon::setTestNow();
    }

    #[Test]
    public function it_restores_both_timestamps_from_card_payload(): void
    {
        $payload = [
            'mention_id' => 1,
            'project_id' => 1,
            'person' => 'Jane Doe',
            'threat_level' => 'P2',
            'threat_score' => 70,
            'source' => 'Brand24',
            'summary' => 'Summary',
            'url' => 'https://example.com',
            'sentiment' => 'negative',
            'severity' => 3,
            'serp_position' => 5,
            'cluster_size' => 2,
            'published_at' => '2026-07-10T14:35:00+00:00',
            'processed_at' => '2026-07-10T16:00:00+00:00',
        ];

        $card = DeliveryCardDTO::fromPayload($payload);
        $message = $this->app->make(DeliveryCardBuilderInterface::class)->formatCard($card);

        $this->assertStringContainsString('10.07.2026 14:35', $message);
        $this->assertStringContainsString('10.07.2026 16:00', $message);
    }

    private function makeContext(?Carbon $publishedAt): \App\DTO\DeliveryContextDTO
    {
        $project = Project::query()->create([
            'name' => 'Card Project',
            'slug' => 'card-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::Brand24,
            'external_id' => 'card-source',
            'name' => 'Brand24 Source',
            'is_active' => true,
        ]);

        $mention = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'card-mention',
            'content' => 'Content',
            'url' => 'https://example.com/post',
            'published_at' => $publishedAt,
            'received_at' => now(),
            'status' => MentionStatus::Completed,
        ]);

        $aiResult = AiResult::query()->create([
            'mention_id' => $mention->id,
            'provider' => 'anthropic',
            'model' => 'claude-test-model',
            'summary' => 'Summary text',
            'sentiment' => 'negative',
            'severity' => 3,
            'language' => 'en',
            'category' => 'other',
            'person' => 'Jane Doe',
            'confidence' => 90,
            'reasoning' => 'Reasoning',
            'raw_response' => ['id' => 'msg_123'],
            'processed_at' => now(),
        ]);

        MentionThreatResult::query()->create([
            'mention_id' => $mention->id,
            'ai_result_id' => $aiResult->id,
            'threat_level' => ThreatLevel::P2,
            'threat_score' => 70.0,
            'factor_scores' => [],
            'assessed_at' => now(),
        ]);

        MentionRoute::query()->create([
            'mention_id' => $mention->id,
            'should_notify' => true,
            'priority' => 'normal',
            'channel' => 'notification',
            'delivery_mode' => 'immediate',
            'skip_moderation' => false,
            'reason' => 'Test',
        ]);

        return $this->app->make(DeliveryContextBuilderInterface::class)->buildForMention($mention->id);
    }
}
