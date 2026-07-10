<?php

namespace Tests\Feature\Routing;

use App\Actions\ProcessMentionAction;
use App\Enums\MentionStatus;
use App\Enums\RoutingChannel;
use App\Enums\RoutingPriority;
use App\Enums\SourceType;
use App\Events\MentionRouted;
use App\Models\Mention;
use App\Models\MentionRaw;
use App\Models\MentionRoute;
use App\Models\Project;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesTelegram;
use Tests\TestCase;

class MentionRoutingTest extends TestCase
{
    use FakesTelegram;
    use RefreshDatabase;

    #[Test]
    public function it_routes_negative_mentions_after_classification(): void
    {
        config([
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_id' => '-100123456',
            'telegram.base_url' => 'https://api.telegram.org',
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

        $this->assertSame(MentionStatus::Completed, $mention->status);
        $this->assertDatabaseCount('mention_routes', 1);

        $route = MentionRoute::query()->first();

        $this->assertNotNull($route);
        $this->assertSame($mention->id, $route->mention_id);
        $this->assertTrue($route->should_notify);
        $this->assertSame(RoutingPriority::Normal, $route->priority);
        $this->assertSame(RoutingChannel::Notification, $route->channel);
        $this->assertNotEmpty($route->reason);
        $this->assertNotNull($route->created_at);
    }

    #[Test]
    public function it_dispatches_mention_routed_event_after_routing(): void
    {
        Event::fake([MentionRouted::class]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response($this->claudeApiResponse(), 200),
        ]);

        config(['claude.base_url' => 'https://api.anthropic.com/v1']);

        [$mention, $source, $project] = $this->createPendingMention();

        $this->app->make(ProcessMentionAction::class)->execute($mention->id);

        Event::assertDispatched(MentionRouted::class, function (MentionRouted $event) use ($mention, $project, $source): bool {
            return $event->mentionId === $mention->id
                && $event->projectId === $project->id
                && $event->sourceId === $source->id;
        });
    }

    #[Test]
    public function it_does_not_route_duplicate_mentions(): void
    {
        Http::fake();

        config(['claude.base_url' => 'https://api.anthropic.com/v1']);

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

        $this->assertDatabaseCount('mention_routes', 0);
    }

    /**
     * @return array{0: Mention, 1: Source, 2: Project}
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

        return [$mention, $source, $project];
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
