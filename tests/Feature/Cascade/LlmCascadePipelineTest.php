<?php

namespace Tests\Feature\Cascade;

use App\Actions\ClassifyMentionAction;
use App\Actions\ProcessMentionAction;
use App\Enums\MentionStatus;
use App\Enums\SourceType;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\MentionRaw;
use App\Models\Project;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LlmCascadePipelineTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_cascade_metadata_during_pipeline_classification(): void
    {
        config([
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_id' => '-100123456',
            'telegram.base_url' => 'https://api.telegram.org',
            'claude.base_url' => 'https://api.anthropic.com/v1',
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::sequence()
                ->push([
                    'id' => 'msg_pipeline_haiku',
                    'model' => 'claude-test-model',
                    'usage' => ['input_tokens' => 120, 'output_tokens' => 40],
                    'content' => [[
                        'type' => 'text',
                        'text' => json_encode([
                            'summary' => 'Customer complaint about service quality.',
                            'sentiment' => 'negative',
                            'severity' => 4,
                            'language' => 'en',
                            'category' => 'customer_service',
                            'person' => 'unknown',
                            'confidence' => 91,
                            'reasoning' => 'The mention describes poor service and requests a refund.',
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ], 200)
                ->push([
                    'id' => 'msg_pipeline_sonnet',
                    'model' => 'claude-test-model',
                    'usage' => ['input_tokens' => 80, 'output_tokens' => 20],
                    'content' => [[
                        'type' => 'text',
                        'text' => json_encode([
                            'summary' => 'Customer complaint about service quality.',
                            'sentiment' => 'negative',
                            'severity' => 4,
                            'language' => 'en',
                            'category' => 'customer_service',
                            'person' => 'unknown',
                            'confidence' => 91,
                            'reasoning' => 'The mention describes poor service and requests a refund.',
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ], 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 1, 'chat' => ['id' => -100123456]],
            ], 200),
        ]);

        [$mention] = $this->createPendingMention();

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        $aiResult = AiResult::query()->first();

        $this->assertNotNull($aiResult);
        $this->assertSame('sonnet', $aiResult->cascade_tier);
        $this->assertSame(200, $aiResult->input_tokens);
        $this->assertSame(60, $aiResult->output_tokens);
        $this->assertNotNull($aiResult->processing_time_ms);
        $this->assertNotNull($aiResult->estimated_cost);
        $this->assertNotNull($aiResult->escalation_reason);
        $this->assertSame(MentionStatus::Completed, $mention->fresh()?->status);
    }

    #[Test]
    public function classify_action_stores_cascade_metadata(): void
    {
        config([
            'cascade.enabled' => true,
            'cascade.models.haiku.name' => 'claude-test-model',
            'claude.base_url' => 'https://api.anthropic.com/v1',
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'id' => 'msg_action',
                'model' => 'claude-test-model',
                'usage' => ['input_tokens' => 50, 'output_tokens' => 20],
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'summary' => 'Summary.',
                        'sentiment' => 'neutral',
                        'severity' => 2,
                        'language' => 'en',
                        'category' => 'general',
                        'person' => 'unknown',
                        'confidence' => 88,
                        'reasoning' => 'Neutral tone.',
                    ], JSON_THROW_ON_ERROR),
                ]],
            ], 200),
        ]);

        $project = Project::query()->create([
            'name' => 'Cascade Project',
            'slug' => 'cascade-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::Brand24,
            'external_id' => 'brand24-cascade',
            'name' => 'Brand24',
            'is_active' => true,
        ]);

        $mention = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'cascade-mention',
            'content' => '',
            'received_at' => now(),
            'status' => MentionStatus::Processing,
        ]);

        $this->app->make(ClassifyMentionAction::class)->execute(
            $mention->id,
            new \App\DTO\NormalizedMentionDTO(
                projectId: $project->id,
                sourceId: $source->id,
                externalId: 'cascade-mention',
                author: null,
                authorId: null,
                language: 'en',
                text: 'Short text.',
                title: null,
                url: null,
                publishedAt: null,
                receivedAt: now(),
            ),
        );

        $aiResult = AiResult::query()->first();

        $this->assertNotNull($aiResult);
        $this->assertSame('claude-test-model', $aiResult->model);
        $this->assertSame('haiku', $aiResult->cascade_tier);
        $this->assertSame(50, $aiResult->input_tokens);
        $this->assertSame(20, $aiResult->output_tokens);
    }

    /**
     * @return array{0: Mention}
     */
    private function createPendingMention(): array
    {
        $project = Project::query()->create([
            'name' => 'Test Project',
            'slug' => 'test-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::YouScan,
            'external_id' => 'source-1',
            'name' => 'YouScan Source',
            'is_active' => true,
        ]);

        $mention = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'mention-123',
            'content' => '',
            'received_at' => now(),
            'status' => MentionStatus::Pending,
        ]);

        MentionRaw::query()->create([
            'mention_id' => $mention->id,
            'provider' => SourceType::YouScan->value,
            'payload' => [
                'project_id' => $project->id,
                'source_id' => $source->id,
                'id' => 'mention-123',
                'text' => 'The service was terrible and I want a refund.',
                'title' => 'Bad experience',
                'language' => 'en',
                'received_at' => now()->toIso8601String(),
            ],
        ]);

        return [$mention];
    }
}
