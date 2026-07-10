<?php

namespace Tests\Unit\Services\Threat;

use App\Contracts\ThreatEngineInterface;
use App\DTO\ThreatAssessmentContextDTO;
use App\Enums\MentionStatus;
use App\Enums\SourceType;
use App\Enums\ThreatLevel;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\Project;
use App\Models\Source;
use App\Models\ThreatRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ThreatEngineTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_calculates_weighted_threat_score_from_database_weights(): void
    {
        $context = $this->makeHighRiskContext();

        $result = $this->app->make(ThreatEngineInterface::class)->evaluate($context);

        $this->assertGreaterThanOrEqual(80, $result->threatScore);
        $this->assertSame(ThreatLevel::P1, $result->threatLevel);
        $this->assertCount(7, $result->factors);
    }

    #[Test]
    public function it_applies_updated_threshold_rules_from_database(): void
    {
        ThreatRule::query()->where('level', 'P1')->update(['min_score' => 95]);

        $context = $this->makeHighRiskContext();

        $result = $this->app->make(ThreatEngineInterface::class)->evaluate($context);

        $this->assertSame(ThreatLevel::P2, $result->threatLevel);
    }

    #[Test]
    public function it_assigns_p4_for_low_risk_mentions(): void
    {
        $context = $this->makeLowRiskContext();

        $result = $this->app->make(ThreatEngineInterface::class)->evaluate($context);

        $this->assertLessThan(45, $result->threatScore);
        $this->assertSame(ThreatLevel::P4, $result->threatLevel);
    }

    private function makeHighRiskContext(): ThreatAssessmentContextDTO
    {
        $source = Source::query()->make([
            'id' => 1,
            'project_id' => 1,
            'type' => SourceType::YouScan,
            'config' => [],
        ]);

        $mention = Mention::query()->make([
            'id' => 1,
            'project_id' => 1,
            'source_id' => 1,
            'content' => 'Critical issue',
            'published_at' => now()->subMinutes(30),
            'received_at' => now()->subMinutes(30),
            'status' => MentionStatus::Processing,
        ]);

        $aiResult = AiResult::query()->make([
            'id' => 1,
            'mention_id' => 1,
            'sentiment' => 'negative',
            'severity' => 5,
        ]);

        return new ThreatAssessmentContextDTO(
            mention: $mention,
            aiResult: $aiResult,
            source: $source,
            clusterSize: 12,
            serpTopPosition: 1,
            person: null,
        );
    }

    private function makeLowRiskContext(): ThreatAssessmentContextDTO
    {
        $source = Source::query()->make([
            'id' => 1,
            'project_id' => 1,
            'type' => SourceType::Mentionlytics,
            'config' => [],
        ]);

        $mention = Mention::query()->make([
            'id' => 1,
            'project_id' => 1,
            'source_id' => 1,
            'content' => 'Positive update',
            'published_at' => now()->subDays(10),
            'received_at' => now()->subDays(10),
            'status' => MentionStatus::Processing,
        ]);

        $aiResult = AiResult::query()->make([
            'id' => 1,
            'mention_id' => 1,
            'sentiment' => 'positive',
            'severity' => 1,
        ]);

        return new ThreatAssessmentContextDTO(
            mention: $mention,
            aiResult: $aiResult,
            source: $source,
            clusterSize: 1,
            serpTopPosition: null,
            person: null,
        );
    }
}
