<?php

namespace Tests\Feature\Routing;

use App\Actions\RouteMentionAction;
use App\Enums\MentionStatus;
use App\Enums\RoutingDeliveryMode;
use App\Enums\RoutingPriority;
use App\Enums\RoutingTargetType;
use App\Enums\SourceType;
use App\Enums\ThreatLevel;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\MentionRoute;
use App\Models\MentionRoutingTarget;
use App\Models\MentionThreatResult;
use App\Models\Project;
use App\Models\RoutingCondition;
use App\Models\RoutingRule;
use App\Models\RoutingTarget;
use App\Models\Source;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdvancedRoutingEngineTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_routing_decision_with_rule_targets_and_delivery_mode(): void
    {
        [$mention] = $this->createRoutableMention(ThreatLevel::P1, 91.0);

        $this->app->make(RouteMentionAction::class)->execute($mention->id);

        $route = MentionRoute::query()->where('mention_id', $mention->id)->first();

        $this->assertNotNull($route);
        $this->assertNotNull($route->routing_rule_id);
        $this->assertTrue($route->should_notify);
        $this->assertSame(RoutingPriority::Immediate, $route->priority);
        $this->assertSame(RoutingDeliveryMode::Immediate, $route->delivery_mode);
        $this->assertFalse($route->skip_moderation);
        $this->assertNotEmpty($route->reason);

        $targets = MentionRoutingTarget::query()->where('mention_route_id', $route->id)->orderBy('sort_order')->get();
        $this->assertCount(2, $targets);
        $this->assertSame(RoutingTargetType::TelegramModeration, $targets[0]->target_type);
        $this->assertSame(RoutingTargetType::TelegramDelivery, $targets[1]->target_type);
    }

    #[Test]
    public function it_persists_night_mode_routing_without_immediate_notification(): void
    {
        Carbon::setTestNow('2026-07-10 23:15:00');

        [$mention] = $this->createRoutableMention(ThreatLevel::P4, 25.0);

        $this->app->make(RouteMentionAction::class)->execute($mention->id);

        $route = MentionRoute::query()->where('mention_id', $mention->id)->first();

        $this->assertNotNull($route);
        $this->assertFalse($route->should_notify);
        $this->assertSame(RoutingDeliveryMode::Digest, $route->delivery_mode);
        $this->assertTrue($route->skip_moderation);
        $this->assertSame(RoutingPriority::Deferred, $route->priority);

        Carbon::setTestNow();
    }

    #[Test]
    public function it_supports_future_email_and_slack_targets_in_architecture(): void
    {
        $rule = RoutingRule::query()->create([
            'name' => 'Architecture Targets',
            'rule_priority' => 1,
            'routing_priority' => RoutingPriority::Normal,
            'delivery_mode' => RoutingDeliveryMode::Immediate,
            'auto_skip' => false,
            'skip_moderation' => false,
            'reason_template' => 'Multi-channel architecture route.',
            'is_active' => true,
            'is_fallback' => false,
        ]);

        RoutingCondition::query()->create([
            'routing_rule_id' => $rule->id,
            'condition_type' => 'threat_level',
            'operator' => 'in',
            'value' => ['values' => ['P2']],
        ]);

        foreach ([RoutingTargetType::TelegramModeration, RoutingTargetType::Email, RoutingTargetType::Slack] as $index => $targetType) {
            RoutingTarget::query()->create([
                'routing_rule_id' => $rule->id,
                'target_type' => $targetType,
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
        }

        RoutingRule::query()
            ->where('id', '!=', $rule->id)
            ->where('is_fallback', false)
            ->update(['is_active' => false]);

        [$mention] = $this->createRoutableMention(ThreatLevel::P2, 68.0);

        $this->app->make(RouteMentionAction::class)->execute($mention->id);

        $targets = MentionRoutingTarget::query()
            ->whereHas('mentionRoute', fn ($query) => $query->where('mention_id', $mention->id))
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(3, $targets);
        $this->assertSame(RoutingTargetType::Email, $targets[1]->target_type);
        $this->assertSame(RoutingTargetType::Slack, $targets[2]->target_type);
    }

    /**
     * @return array{0: Mention}
     */
    private function createRoutableMention(ThreatLevel $threatLevel, float $threatScore): array
    {
        $project = Project::query()->create([
            'name' => 'Persist Project',
            'slug' => 'persist-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::YouScan,
            'external_id' => 'source-persist',
            'name' => 'Persist Source',
            'is_active' => true,
        ]);

        $mention = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'mention-persist',
            'content' => 'Persist content',
            'received_at' => now(),
            'status' => MentionStatus::Processing,
        ]);

        $aiResult = AiResult::query()->create([
            'mention_id' => $mention->id,
            'provider' => 'anthropic',
            'model' => 'claude-test-model',
            'summary' => 'Summary',
            'sentiment' => 'negative',
            'severity' => 4,
            'language' => 'en',
            'category' => 'other',
            'person' => 'unknown',
            'confidence' => 90,
            'reasoning' => 'Reasoning',
            'raw_response' => ['id' => 'msg_123'],
            'processed_at' => now(),
        ]);

        MentionThreatResult::query()->create([
            'mention_id' => $mention->id,
            'ai_result_id' => $aiResult->id,
            'threat_level' => $threatLevel,
            'threat_score' => $threatScore,
            'factor_scores' => [],
            'assessed_at' => now(),
        ]);

        return [$mention];
    }
}
