<?php

namespace Tests\Feature\Cascade;

use App\Contracts\LLMCascadeInterface;
use App\DTO\ClassificationResultDTO;
use App\DTO\LlmCascadeResultDTO;
use App\DTO\LlmExecutionMetadataDTO;
use App\DTO\NormalizedMentionDTO;
use App\Enums\LlmCascadeTier;
use App\Services\Cascade\LlmCascadeEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LlmCascadeEngineTest extends TestCase
{
    #[Test]
    public function it_selects_haiku_for_simple_mentions_and_persists_metadata(): void
    {
        config([
            'cascade.enabled' => true,
            'cascade.models.haiku.name' => 'claude-test-haiku',
            'claude.base_url' => 'https://api.anthropic.com/v1',
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($this->apiResponse(
                model: 'claude-test-haiku',
                confidence: 92,
                severity: 2,
                inputTokens: 120,
                outputTokens: 45,
            ), 200),
        ]);

        $result = $this->engine()->classify(
            'prompt',
            $this->simpleMention(),
            10,
        );

        $this->assertSame('claude-test-haiku', $result->model);
        $this->assertSame(LlmCascadeTier::Haiku->value, $result->metadata->cascadeTier);
        $this->assertSame(120, $result->metadata->inputTokens);
        $this->assertSame(45, $result->metadata->outputTokens);
        $this->assertGreaterThan(0, $result->metadata->processingTimeMs);
        $this->assertNull($result->metadata->escalationReason);
        Http::assertSentCount(1);
    }

    #[Test]
    public function it_escalates_from_haiku_to_sonnet_when_confidence_is_low(): void
    {
        config([
            'cascade.enabled' => true,
            'cascade.models.haiku.name' => 'claude-test-haiku',
            'cascade.models.sonnet.name' => 'claude-test-sonnet',
            'claude.base_url' => 'https://api.anthropic.com/v1',
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::sequence()
                ->push($this->apiResponse('claude-test-haiku', confidence: 60, severity: 2, inputTokens: 100, outputTokens: 30), 200)
                ->push($this->apiResponse('claude-test-sonnet', confidence: 91, severity: 4, inputTokens: 150, outputTokens: 55), 200),
        ]);

        $result = $this->engine()->classify(
            'prompt',
            $this->simpleMention(),
            11,
        );

        $this->assertSame('claude-test-sonnet', $result->model);
        $this->assertSame(LlmCascadeTier::Sonnet->value, $result->metadata->cascadeTier);
        $this->assertSame(250, $result->metadata->inputTokens);
        $this->assertSame(85, $result->metadata->outputTokens);
        $this->assertNotNull($result->metadata->escalationReason);
        Http::assertSentCount(2);
    }

    #[Test]
    public function it_uses_fallback_model_when_cascade_is_disabled(): void
    {
        config([
            'cascade.enabled' => false,
            'cascade.fallback.model' => 'claude-fallback-model',
            'cascade.fallback.tier' => LlmCascadeTier::Sonnet->value,
            'cascade.models.sonnet.name' => 'claude-fallback-model',
            'claude.base_url' => 'https://api.anthropic.com/v1',
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($this->apiResponse(
                model: 'claude-fallback-model',
                confidence: 88,
                severity: 3,
                inputTokens: 80,
                outputTokens: 20,
            ), 200),
        ]);

        $result = $this->engine()->classify(
            'prompt',
            $this->simpleMention(),
            12,
        );

        $this->assertSame('claude-fallback-model', $result->model);
        $this->assertSame(LlmCascadeTier::Sonnet->value, $result->metadata->cascadeTier);
        Http::assertSentCount(1);
    }

    #[Test]
    public function it_retries_once_on_invalid_response_before_failing(): void
    {
        config([
            'cascade.enabled' => true,
            'cascade.models.haiku.name' => 'claude-test-haiku',
            'claude.base_url' => 'https://api.anthropic.com/v1',
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::sequence()
                ->push(['id' => 'msg_invalid', 'content' => [['type' => 'text', 'text' => 'not-json']], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]], 200)
                ->push($this->apiResponse('claude-test-haiku', confidence: 90, severity: 2, inputTokens: 90, outputTokens: 25), 200),
        ]);

        $result = $this->engine()->classify(
            'prompt',
            $this->simpleMention(),
            13,
        );

        $this->assertSame(90, $result->metadata->inputTokens);
        Http::assertSentCount(2);
    }

    #[Test]
    public function cascade_interface_is_bound_to_engine(): void
    {
        $this->assertInstanceOf(LlmCascadeEngine::class, $this->app->make(LLMCascadeInterface::class));
    }

    private function engine(): LlmCascadeEngine
    {
        return $this->app->make(LlmCascadeEngine::class);
    }

    private function simpleMention(): NormalizedMentionDTO
    {
        return new NormalizedMentionDTO(
            projectId: 1,
            sourceId: 1,
            externalId: 'mention-1',
            author: null,
            authorId: null,
            language: 'en',
            text: 'Short mention.',
            title: null,
            url: null,
            publishedAt: null,
            receivedAt: Carbon::now(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function apiResponse(
        string $model,
        int $confidence,
        int $severity,
        int $inputTokens,
        int $outputTokens,
    ): array {
        return [
            'id' => 'msg_cascade',
            'model' => $model,
            'usage' => [
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
            ],
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'summary' => 'Summary.',
                    'sentiment' => 'negative',
                    'severity' => $severity,
                    'language' => 'en',
                    'category' => 'customer_service',
                    'person' => 'unknown',
                    'confidence' => $confidence,
                    'reasoning' => 'Reasoning.',
                ], JSON_THROW_ON_ERROR),
            ]],
        ];
    }
}
