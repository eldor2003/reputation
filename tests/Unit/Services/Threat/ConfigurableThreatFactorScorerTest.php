<?php

namespace Tests\Unit\Services\Threat;

use App\DTO\ThreatAssessmentContextDTO;
use App\Enums\MentionStatus;
use App\Enums\SourceType;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\Project;
use App\Models\Source;
use App\Services\Threat\ConfigurableThreatFactorScorer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfigurableThreatFactorScorerTest extends TestCase
{
    #[Test]
    public function it_scores_sentiment_using_database_map_configuration(): void
    {
        $context = $this->makeContext(sentiment: 'negative', severity: 3);

        $score = $this->app->make(ConfigurableThreatFactorScorer::class)->score(
            'sentiment',
            [
                'type' => 'map',
                'values' => ['negative' => 100, 'neutral' => 40, 'positive' => 10],
                'default' => 30,
            ],
            $context,
        );

        $this->assertSame(100.0, $score);
    }

    #[Test]
    public function it_scores_severity_using_linear_configuration(): void
    {
        $context = $this->makeContext(sentiment: 'neutral', severity: 5);

        $score = $this->app->make(ConfigurableThreatFactorScorer::class)->score(
            'severity',
            ['type' => 'linear', 'min' => 1, 'max' => 5, 'multiplier' => 20],
            $context,
        );

        $this->assertSame(100.0, $score);
    }

    #[Test]
    public function it_scores_source_credibility_from_source_config_override(): void
    {
        $context = $this->makeContext(
            sentiment: 'neutral',
            severity: 2,
            sourceConfig: ['credibility_score' => 92],
        );

        $score = $this->app->make(ConfigurableThreatFactorScorer::class)->score(
            'source_credibility',
            [
                'type' => 'map',
                'values' => ['youscan' => 85],
                'default' => 50,
            ],
            $context,
        );

        $this->assertSame(92.0, $score);
    }

    private function makeContext(
        string $sentiment,
        int $severity,
        array $sourceConfig = [],
    ): ThreatAssessmentContextDTO {
        $project = Project::query()->make(['id' => 1]);
        $source = Source::query()->make([
            'id' => 1,
            'project_id' => 1,
            'type' => SourceType::YouScan,
            'config' => $sourceConfig,
        ]);
        $source->setRelation('project', $project);

        $mention = Mention::query()->make([
            'id' => 1,
            'project_id' => 1,
            'source_id' => 1,
            'content' => 'Sample mention',
            'received_at' => now()->subHour(),
            'status' => MentionStatus::Processing,
        ]);
        $mention->setRelation('source', $source);

        $aiResult = AiResult::query()->make([
            'id' => 1,
            'mention_id' => 1,
            'sentiment' => $sentiment,
            'severity' => $severity,
        ]);

        return new ThreatAssessmentContextDTO(
            mention: $mention,
            aiResult: $aiResult,
            source: $source,
            clusterSize: 3,
            serpTopPosition: 2,
            person: null,
        );
    }
}
