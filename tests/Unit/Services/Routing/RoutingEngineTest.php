<?php

namespace Tests\Unit\Services\Routing;

use App\Contracts\RoutingEngineInterface;
use App\DTO\RoutingAssessmentContextDTO;
use App\Enums\MentionStatus;
use App\Enums\RoutingDeliveryMode;
use App\Enums\RoutingPriority;
use App\Enums\RoutingTargetType;
use App\Enums\SourceType;
use App\Enums\ThreatLevel;
use App\Models\AiResult;
use App\Models\Mention;
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

class RoutingEngineTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_routes_p1_mentions_with_immediate_priority_and_multiple_targets(): void
    {
        $context = $this->makeContext(ThreatLevel::P1, 92.0);

        $decision = $this->app->make(RoutingEngineInterface::class)->route($context);

        $this->assertTrue($decision->shouldNotify);
        $this->assertSame(RoutingPriority::Immediate, $decision->priority);
        $this->assertSame(RoutingDeliveryMode::Immediate, $decision->deliveryMode);
        $this->assertFalse($decision->skipModeration);
        $this->assertCount(2, $decision->targets);
        $this->assertSame(RoutingTargetType::TelegramModeration, $decision->targets[0]->targetType);
        $this->assertSame(RoutingTargetType::TelegramDelivery, $decision->targets[1]->targetType);
        $this->assertNotNull($decision->routingRuleId);
    }

    #[Test]
    public function it_applies_project_specific_routing_rules(): void
    {
        $context = $this->makeContext(ThreatLevel::P2, 70.0);

        $projectRule = RoutingRule::query()->create([
            'project_id' => $context->mention->project_id,
            'name' => 'Project VIP Immediate',
            'rule_priority' => 5,
            'routing_priority' => RoutingPriority::Immediate,
            'delivery_mode' => RoutingDeliveryMode::Immediate,
            'auto_skip' => false,
            'skip_moderation' => false,
            'reason_template' => 'Project override for {threat_level}.',
            'is_active' => true,
            'is_fallback' => false,
        ]);

        RoutingCondition::query()->create([
            'routing_rule_id' => $projectRule->id,
            'condition_type' => 'threat_level',
            'operator' => 'in',
            'value' => ['values' => ['P2']],
        ]);

        RoutingTarget::query()->create([
            'routing_rule_id' => $projectRule->id,
            'target_type' => RoutingTargetType::TelegramModeration,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $decision = $this->app->make(RoutingEngineInterface::class)->route($context);

        $this->assertSame($projectRule->id, $decision->routingRuleId);
        $this->assertSame(RoutingPriority::Immediate, $decision->priority);
        $this->assertStringContainsString('Project override', $decision->reason);
    }

    #[Test]
    public function it_applies_night_mode_routing_for_p3_mentions(): void
    {
        Carbon::setTestNow('2026-07-10 23:30:00');

        $context = $this->makeContext(
            ThreatLevel::P3,
            50.0,
            evaluatedAt: Carbon::parse('2026-07-10 23:30:00'),
        );

        $decision = $this->app->make(RoutingEngineInterface::class)->route($context);

        $this->assertFalse($decision->shouldNotify);
        $this->assertSame(RoutingPriority::Deferred, $decision->priority);
        $this->assertSame(RoutingDeliveryMode::Digest, $decision->deliveryMode);
        $this->assertTrue($decision->skipModeration);
        $this->assertCount(1, $decision->targets);
        $this->assertSame(RoutingTargetType::TelegramDelivery, $decision->targets[0]->targetType);

        Carbon::setTestNow();
    }

    #[Test]
    public function it_routes_p3_during_working_hours_to_moderation(): void
    {
        Carbon::setTestNow('2026-07-10 14:00:00');

        $context = $this->makeContext(
            ThreatLevel::P3,
            50.0,
            evaluatedAt: Carbon::parse('2026-07-10 14:00:00'),
        );

        $decision = $this->app->make(RoutingEngineInterface::class)->route($context);

        $this->assertTrue($decision->shouldNotify);
        $this->assertSame(RoutingPriority::Normal, $decision->priority);
        $this->assertSame(RoutingDeliveryMode::Immediate, $decision->deliveryMode);
        $this->assertFalse($decision->skipModeration);

        Carbon::setTestNow();
    }

    #[Test]
    public function it_defers_p4_mentions_with_low_priority(): void
    {
        $context = $this->makeContext(
            ThreatLevel::P4,
            20.0,
            evaluatedAt: Carbon::parse('2026-07-10 14:00:00', 'UTC'),
        );

        $decision = $this->app->make(RoutingEngineInterface::class)->route($context);

        $this->assertFalse($decision->shouldNotify);
        $this->assertSame(RoutingPriority::Low, $decision->priority);
        $this->assertSame(RoutingDeliveryMode::Deferred, $decision->deliveryMode);
    }

    #[Test]
    public function it_uses_fallback_rule_when_no_conditions_match(): void
    {
        RoutingRule::query()->where('is_fallback', false)->update(['is_active' => false]);

        $context = $this->makeContext(ThreatLevel::P1, 95.0);

        $decision = $this->app->make(RoutingEngineInterface::class)->route($context);

        $this->assertFalse($decision->shouldNotify);
        $this->assertSame(RoutingDeliveryMode::Skip, $decision->deliveryMode);
        $this->assertStringContainsString('Не найдено подходящее правило', $decision->reason);
    }

    private function makeContext(
        ThreatLevel $threatLevel,
        float $threatScore,
        ?Carbon $evaluatedAt = null,
    ): RoutingAssessmentContextDTO {
        $project = Project::query()->create([
            'name' => 'Routing Project',
            'slug' => 'routing-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::YouScan,
            'external_id' => 'source-routing',
            'name' => 'Routing Source',
            'is_active' => true,
        ]);

        $mention = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'mention-routing',
            'content' => 'Routing content',
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

        $threatResult = MentionThreatResult::query()->create([
            'mention_id' => $mention->id,
            'ai_result_id' => $aiResult->id,
            'threat_level' => $threatLevel,
            'threat_score' => $threatScore,
            'factor_scores' => [],
            'assessed_at' => now(),
        ]);

        return new RoutingAssessmentContextDTO(
            mention: $mention->fresh(['source', 'person']),
            aiResult: $aiResult,
            threatResult: $threatResult,
            source: $source,
            person: null,
            evaluatedAt: $evaluatedAt ?? now(),
        );
    }
}
