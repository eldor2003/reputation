<?php

namespace Tests\Feature\Threat;

use App\Actions\ProcessMentionAction;
use App\Enums\MentionStatus;
use App\Enums\SourceType;
use App\Enums\ThreatLevel;
use App\Events\MentionThreatAssessed;
use App\Models\Mention;
use App\Models\MentionRaw;
use App\Models\MentionThreatResult;
use App\Models\Project;
use App\Models\Source;
use App\Models\ThreatFactorWeight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesTelegram;
use Tests\TestCase;

class ThreatPipelineTest extends TestCase
{
    use FakesTelegram;
    use RefreshDatabase;

    #[Test]
    public function it_persists_threat_results_during_pipeline_processing(): void
    {
        config([
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_id' => '-100123456',
            'claude.base_url' => 'https://api.anthropic.com/v1',
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($this->claudeApiResponse(), 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 1, 'chat' => ['id' => -100123456]],
            ], 200),
        ]);

        [$mention] = $this->createPendingMention();

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        $mention->refresh();
        $threatResult = MentionThreatResult::query()->where('mention_id', $mention->id)->first();

        $this->assertSame(MentionStatus::Completed, $mention->status);
        $this->assertNotNull($threatResult);
        $this->assertInstanceOf(ThreatLevel::class, $threatResult->threat_level);
        $this->assertGreaterThan(0, $threatResult->threat_score);
        $this->assertIsArray($threatResult->factor_scores);
        $this->assertNotEmpty($threatResult->factor_scores);
    }

    #[Test]
    public function it_dispatches_mention_threat_assessed_event(): void
    {
        Event::fake([MentionThreatAssessed::class]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($this->claudeApiResponse(), 200),
        ]);

        config(['claude.base_url' => 'https://api.anthropic.com/v1']);

        [$mention] = $this->createPendingMention();

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        Event::assertDispatched(MentionThreatAssessed::class, function (MentionThreatAssessed $event) use ($mention): bool {
            return $event->mentionId === $mention->id;
        });
    }

    #[Test]
    public function it_recalculates_threat_score_when_database_weights_change(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($this->claudeApiResponse(), 200),
        ]);

        config(['claude.base_url' => 'https://api.anthropic.com/v1']);

        [$mention] = $this->createPendingMention();

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        $initialScore = (float) MentionThreatResult::query()
            ->where('mention_id', $mention->id)
            ->value('threat_score');

        ThreatFactorWeight::query()
            ->where('factor_key', 'sentiment')
            ->update(['weight' => 0.0100]);

        $updatedResult = $this->app->make(\App\Actions\EvaluateMentionThreatAction::class)
            ->execute($mention->id);

        $this->assertNotSame($initialScore, $updatedResult->threatScore);
    }

    /**
     * @return array{0: Mention}
     */
    private function createPendingMention(): array
    {
        $project = Project::query()->create([
            'name' => 'Threat Project',
            'slug' => 'threat-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::YouScan,
            'external_id' => 'source-threat',
            'name' => 'Threat Source',
            'is_active' => true,
        ]);

        $mention = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'mention-threat-1',
            'content' => '',
            'received_at' => now()->subHour(),
            'status' => MentionStatus::Pending,
        ]);

        MentionRaw::query()->create([
            'mention_id' => $mention->id,
            'provider' => SourceType::YouScan->value,
            'payload' => [
                'project_id' => $project->id,
                'source_id' => $source->id,
                'id' => 'mention-threat-1',
                'text' => 'Terrible service. I want a refund immediately.',
                'title' => 'Bad experience',
                'language' => 'en',
                'published' => now()->subHour()->toIso8601String(),
                'received_at' => now()->subHour()->toIso8601String(),
            ],
        ]);

        return [$mention];
    }

    /**
     * @return array<string, mixed>
     */
    private function claudeApiResponse(): array
    {
        return [
            'id' => 'msg_threat_test',
            'model' => 'claude-test-model',
            'usage' => ['input_tokens' => 100, 'output_tokens' => 40],
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
        ];
    }
}
