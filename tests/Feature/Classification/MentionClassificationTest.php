<?php

namespace Tests\Feature\Classification;

use App\Actions\ProcessMentionAction;
use App\Enums\MentionStatus;
use App\Enums\RoutingChannel;
use App\Enums\RoutingPriority;
use App\Enums\SourceType;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\MentionRoute;
use App\Models\MentionRaw;
use App\Models\Project;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesTelegram;
use Tests\TestCase;

class MentionClassificationTest extends TestCase
{
    use FakesTelegram;
    use RefreshDatabase;

    #[Test]
    public function it_classifies_a_mention_and_stores_ai_results(): void
    {
        config([
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_id' => '-100123456',
            'telegram.base_url' => 'https://api.telegram.org',
            'claude.base_url' => 'https://api.anthropic.com/v1',
            'cascade.escalation.enabled' => false,
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

        $this->assertSame(MentionStatus::Completed, $mention->status);
        $this->assertDatabaseCount('ai_results', 1);

        $aiResult = AiResult::query()->first();

        $this->assertNotNull($aiResult);
        $this->assertSame($mention->id, $aiResult->mention_id);
        $this->assertSame('anthropic', $aiResult->provider);
        $this->assertSame('claude-test-model', $aiResult->model);
        $this->assertSame('negative', $aiResult->sentiment);
        $this->assertSame('Customer complaint about service quality.', $aiResult->summary);
        $this->assertSame(4, $aiResult->severity);
        $this->assertSame('en', $aiResult->language);
        $this->assertSame('customer_service', $aiResult->category);
        $this->assertSame('unknown', $aiResult->person);
        $this->assertSame(91, $aiResult->confidence);
        $this->assertSame('The mention describes poor service and requests a refund.', $aiResult->reasoning);
        $this->assertNotNull($aiResult->processed_at);
        $this->assertSame('msg_123', $aiResult->raw_response['id']);

        $route = MentionRoute::query()->first();

        $this->assertNotNull($route);
        $this->assertSame($mention->id, $route->mention_id);
        $this->assertTrue($route->should_notify);
        $this->assertSame(RoutingPriority::Normal, $route->priority);
        $this->assertSame(RoutingChannel::Notification, $route->channel);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'anthropic.com'));
    }

    #[Test]
    public function it_skips_classification_for_duplicate_mentions(): void
    {
        Http::fake();

        config([
            'claude.base_url' => 'https://api.anthropic.com/v1',
        ]);

        [$mention, $source] = $this->createPendingMention(externalId: 'mention-dup');

        $dedupHash = hash('sha256', $source->id.'|mention-dup');

        Mention::query()->create([
            'project_id' => $mention->project_id,
            'source_id' => $source->id,
            'external_id' => 'mention-dup-original',
            'content' => 'Original mention',
            'received_at' => now(),
            'status' => MentionStatus::Completed,
            'dedup_hash' => $dedupHash,
            'is_duplicate' => false,
        ]);

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        $mention->refresh();

        $this->assertTrue($mention->is_duplicate);
        $this->assertSame(MentionStatus::Completed, $mention->status);
        $this->assertDatabaseCount('ai_results', 0);
        Http::assertNothingSent();
    }

    #[Test]
    public function it_marks_mention_as_failed_when_claude_api_fails(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response(['error' => 'server error'], 500),
        ]);

        config([
            'claude.base_url' => 'https://api.anthropic.com/v1',
        ]);

        [$mention] = $this->createPendingMention();

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        $mention->refresh();

        $this->assertSame(MentionStatus::Failed, $mention->status);
        $this->assertDatabaseCount('ai_results', 0);
    }

    #[Test]
    public function it_retries_claude_once_when_first_classification_response_is_invalid(): void
    {
        config([
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_id' => '-100123456',
            'telegram.base_url' => 'https://api.telegram.org',
            'claude.base_url' => 'https://api.anthropic.com/v1',
            'cascade.escalation.enabled' => false,
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::sequence()
                ->push([
                    'id' => 'msg_invalid',
                    'content' => [['type' => 'text', 'text' => 'not-json']],
                ], 200)
                ->push($this->claudeApiResponse(), 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 1, 'chat' => ['id' => -100123456]],
            ], 200),
        ]);

        [$mention] = $this->createPendingMention();

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        $mention->refresh();

        $this->assertSame(MentionStatus::Completed, $mention->status);
        $this->assertDatabaseCount('ai_results', 1);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'anthropic.com'));
        $this->assertSame(2, collect(Http::recorded())->filter(
            fn (array $record) => str_contains($record[0]->url(), 'anthropic.com'),
        )->count());
    }

    /**
     * @return array{0: Mention, 1: Source}
     */
    private function createPendingMention(string $externalId = 'mention-123'): array
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
            'external_id' => $externalId,
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
                'id' => $externalId,
                'text' => 'The service was terrible and I want a refund.',
                'title' => 'Bad experience',
                'language' => 'en',
                'received_at' => now()->toIso8601String(),
            ],
        ]);

        return [$mention, $source];
    }

    /**
     * @return array<string, mixed>
     */
    private function claudeApiResponse(): array
    {
        return [
            'id' => 'msg_123',
            'model' => 'claude-test-model',
            'content' => [
                [
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
                ],
            ],
        ];
    }
}
