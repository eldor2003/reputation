<?php

namespace Tests\Concerns;

use App\Enums\MentionStatus;
use App\Enums\ModerationAction;
use App\Enums\RoutingChannel;
use App\Enums\RoutingDeliveryMode;
use App\Enums\RoutingPriority;
use App\Enums\SourceType;
use App\Enums\ThreatLevel;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\MentionRoute;
use App\Models\MentionThreatResult;
use App\Models\ModerationLog;
use App\Models\Project;
use App\Models\Source;

trait CreatesDeliverableMention
{
    /**
     * @return array{0: Mention, 1: Project, 2: Source}
     */
    protected function createDeliverableMention(
        ThreatLevel $threatLevel = ThreatLevel::P2,
        RoutingDeliveryMode $deliveryMode = RoutingDeliveryMode::Immediate,
        bool $skipModeration = false,
    ): array {
        $project = Project::query()->create([
            'name' => 'Delivery Project '.uniqid(),
            'slug' => 'delivery-project-'.uniqid(),
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::YouScan,
            'external_id' => 'delivery-source',
            'name' => 'Delivery Source',
            'is_active' => true,
        ]);

        $mention = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'delivery-mention',
            'content' => 'Delivery mention content',
            'url' => 'https://example.com/post/1',
            'published_at' => now()->subHours(3),
            'received_at' => now(),
            'status' => MentionStatus::Completed,
        ]);

        $aiResult = AiResult::query()->create([
            'mention_id' => $mention->id,
            'provider' => 'anthropic',
            'model' => 'claude-test-model',
            'summary' => 'Critical customer complaint summary.',
            'sentiment' => 'negative',
            'severity' => 4,
            'language' => 'en',
            'category' => 'customer_service',
            'person' => 'John Doe',
            'confidence' => 90,
            'reasoning' => 'Reasoning',
            'raw_response' => ['id' => 'msg_123'],
            'processed_at' => now(),
        ]);

        MentionThreatResult::query()->create([
            'mention_id' => $mention->id,
            'ai_result_id' => $aiResult->id,
            'threat_level' => $threatLevel,
            'threat_score' => 72.5,
            'factor_scores' => [],
            'assessed_at' => now(),
        ]);

        MentionRoute::query()->create([
            'mention_id' => $mention->id,
            'should_notify' => true,
            'priority' => RoutingPriority::Normal,
            'channel' => RoutingChannel::Notification,
            'delivery_mode' => $deliveryMode,
            'skip_moderation' => $skipModeration,
            'reason' => 'Test route',
        ]);

        return [$mention, $project, $source];
    }

    protected function approveMention(Mention $mention): ModerationLog
    {
        return ModerationLog::query()->create([
            'mention_id' => $mention->id,
            'action' => ModerationAction::Approve,
            'moderator_id' => '12345',
            'moderator_username' => 'moderator',
            'telegram_chat_id' => '-100111111',
            'telegram_message_id' => '99',
            'callback_query_id' => 'callback-1',
        ]);
    }
}
