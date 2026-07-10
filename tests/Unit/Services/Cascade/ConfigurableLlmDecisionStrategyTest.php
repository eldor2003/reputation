<?php

namespace Tests\Unit\Services\Cascade;

use App\DTO\ClassificationResultDTO;
use App\DTO\NormalizedMentionDTO;
use App\Enums\LlmCascadeTier;
use App\Services\Cascade\ConfigurableLlmDecisionStrategy;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfigurableLlmDecisionStrategyTest extends TestCase
{
    private ConfigurableLlmDecisionStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cascade.initial_selection.rules' => [
                ['tier' => LlmCascadeTier::Haiku->value, 'max_text_length' => 500],
                ['tier' => LlmCascadeTier::Sonnet->value, 'max_text_length' => 2000],
                ['tier' => LlmCascadeTier::Opus->value],
            ],
            'cascade.escalation.enabled' => true,
            'cascade.escalation.rules' => [
                'haiku' => [
                    'to' => LlmCascadeTier::Sonnet->value,
                    'max_confidence' => 70,
                    'escalate_on_severity_min' => 4,
                ],
                'sonnet' => [
                    'to' => LlmCascadeTier::Opus->value,
                    'max_confidence' => 75,
                    'escalate_on_severity_min' => 5,
                ],
            ],
            'cascade.order' => [
                LlmCascadeTier::Haiku->value,
                LlmCascadeTier::Sonnet->value,
                LlmCascadeTier::Opus->value,
            ],
            'cascade.fallback.tier' => LlmCascadeTier::Sonnet->value,
        ]);

        $this->strategy = new ConfigurableLlmDecisionStrategy;
    }

    #[Test]
    public function it_selects_haiku_for_simple_mentions(): void
    {
        $mention = $this->makeMention('Short mention text.');

        $this->assertSame(LlmCascadeTier::Haiku->value, $this->strategy->selectInitialModel($mention));
    }

    #[Test]
    public function it_selects_sonnet_for_medium_mentions(): void
    {
        $mention = $this->makeMention(str_repeat('Medium complexity mention. ', 30));

        $this->assertSame(LlmCascadeTier::Sonnet->value, $this->strategy->selectInitialModel($mention));
    }

    #[Test]
    public function it_selects_opus_for_high_complexity_mentions(): void
    {
        $mention = $this->makeMention(str_repeat('High complexity mention with many details. ', 120));

        $this->assertSame(LlmCascadeTier::Opus->value, $this->strategy->selectInitialModel($mention));
    }

    #[Test]
    public function it_escalates_from_haiku_when_confidence_is_low(): void
    {
        $result = $this->makeClassification(confidence: 65, severity: 2);

        $this->assertSame(
            LlmCascadeTier::Sonnet->value,
            $this->strategy->shouldEscalate($result, LlmCascadeTier::Haiku->value),
        );
    }

    #[Test]
    public function it_escalates_from_sonnet_when_severity_is_high(): void
    {
        $result = $this->makeClassification(confidence: 90, severity: 5);

        $this->assertSame(
            LlmCascadeTier::Opus->value,
            $this->strategy->shouldEscalate($result, LlmCascadeTier::Sonnet->value),
        );
    }

    #[Test]
    public function it_does_not_escalate_when_result_is_confident(): void
    {
        $result = $this->makeClassification(confidence: 92, severity: 2);

        $this->assertNull($this->strategy->shouldEscalate($result, LlmCascadeTier::Haiku->value));
    }

    private function makeMention(string $text): NormalizedMentionDTO
    {
        return new NormalizedMentionDTO(
            projectId: 1,
            sourceId: 1,
            externalId: 'mention-1',
            author: null,
            authorId: null,
            language: 'en',
            text: $text,
            title: null,
            url: null,
            publishedAt: null,
            receivedAt: Carbon::now(),
        );
    }

    private function makeClassification(int $confidence, int $severity): ClassificationResultDTO
    {
        return new ClassificationResultDTO(
            summary: 'Summary.',
            sentiment: 'negative',
            severity: $severity,
            language: 'en',
            category: 'customer_service',
            person: 'unknown',
            confidence: $confidence,
            reasoning: 'Reasoning.',
            rawResponse: ['id' => 'msg_test'],
        );
    }
}
