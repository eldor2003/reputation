<?php

namespace Tests\Unit\Actions;

use App\Actions\RouteMentionAction;
use App\Contracts\MentionRouteStorageInterface;
use App\Contracts\MentionRouterInterface;
use App\Contracts\RoutingContextBuilderInterface;
use App\DTO\RoutingAssessmentContextDTO;
use App\DTO\RoutingDecisionDTO;
use App\Enums\MentionStatus;
use App\Enums\RoutingChannel;
use App\Enums\RoutingPriority;
use App\Enums\SourceType;
use App\Enums\ThreatLevel;
use App\Events\MentionRouted;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\MentionThreatResult;
use App\Models\Project;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RouteMentionActionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_routes_stores_and_dispatches_mention_routed_event(): void
    {
        Event::fake([MentionRouted::class]);

        [$mention, $context] = $this->createMentionContext();

        $decision = new RoutingDecisionDTO(
            shouldNotify: true,
            priority: RoutingPriority::Immediate,
            channel: RoutingChannel::Notification,
            reason: 'Critical threat requires immediate notification.',
        );

        /** @var MockInterface&RoutingContextBuilderInterface $contextBuilder */
        $contextBuilder = Mockery::mock(RoutingContextBuilderInterface::class);
        $contextBuilder
            ->shouldReceive('build')
            ->once()
            ->with($mention->id)
            ->andReturn($context);

        /** @var MockInterface&MentionRouterInterface $router */
        $router = Mockery::mock(MentionRouterInterface::class);
        $router
            ->shouldReceive('route')
            ->once()
            ->with($context)
            ->andReturn($decision);

        /** @var MockInterface&MentionRouteStorageInterface $storage */
        $storage = Mockery::mock(MentionRouteStorageInterface::class);
        $storage
            ->shouldReceive('store')
            ->once()
            ->with($mention->id, $decision);

        $action = new RouteMentionAction($contextBuilder, $router, $storage);
        $action->execute($mention->id);

        Event::assertDispatched(MentionRouted::class, function (MentionRouted $event) use ($mention): bool {
            return $event->mentionId === $mention->id
                && $event->projectId === $mention->project_id
                && $event->sourceId === $mention->source_id;
        });
    }

    /**
     * @return array{0: Mention, 1: RoutingAssessmentContextDTO}
     */
    private function createMentionContext(): array
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
            'content' => 'Sample content',
            'received_at' => now(),
            'status' => MentionStatus::Processing,
        ]);

        $classification = AiResult::query()->create([
            'mention_id' => $mention->id,
            'provider' => 'anthropic',
            'model' => 'claude-test-model',
            'summary' => 'Summary',
            'sentiment' => 'negative',
            'severity' => 4,
            'language' => 'en',
            'category' => 'customer_service',
            'person' => 'unknown',
            'confidence' => 91,
            'reasoning' => 'Reasoning',
            'raw_response' => ['id' => 'msg_123'],
            'processed_at' => now(),
        ]);

        $threatResult = MentionThreatResult::query()->create([
            'mention_id' => $mention->id,
            'ai_result_id' => $classification->id,
            'threat_level' => ThreatLevel::P1,
            'threat_score' => 90.0,
            'factor_scores' => [],
            'assessed_at' => now(),
        ]);

        $context = new RoutingAssessmentContextDTO(
            mention: $mention,
            aiResult: $classification,
            threatResult: $threatResult,
            source: $source,
            person: null,
            evaluatedAt: now(),
        );

        return [$mention, $context];
    }
}
