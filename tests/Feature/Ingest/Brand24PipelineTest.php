<?php

namespace Tests\Feature\Ingest;

use App\Enums\MentionStatus;
use App\Enums\RoutingChannel;
use App\Enums\RoutingPriority;
use App\Enums\SourceType;
use App\Enums\TelegramNotificationStatus;
use App\Jobs\ProcessMentionJob;
use App\Models\AiResult;
use App\Models\Mention;
use App\Models\MentionRoute;
use App\Models\Project;
use App\Models\Source;
use App\Models\TelegramNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\FakesTelegram;
use Tests\TestCase;

class Brand24PipelineTest extends TestCase
{
    use FakesTelegram;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/ingest/brand24';

    public function test_it_processes_brand24_mention_through_the_full_pipeline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 14:00:00', 'UTC'));

        config([
            'app.timezone' => 'UTC',
            'ingest.api_token' => 'test-ingest-token',
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_id' => '-100123456',
            'telegram.base_url' => 'https://api.telegram.org',
            'claude.base_url' => 'https://api.anthropic.com/v1',
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'id' => 'msg_123',
                'model' => 'claude-test-model',
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'summary' => 'Negative Brand24 mention about product quality.',
                        'sentiment' => 'negative',
                        'severity' => 4,
                        'language' => 'en',
                        'category' => 'product_feedback',
                        'person' => 'John Doe',
                        'confidence' => 90,
                        'reasoning' => 'The mention expresses dissatisfaction with the product.',
                    ], JSON_THROW_ON_ERROR),
                ]],
            ], 200),
            'api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 88, 'chat' => ['id' => -100123456]],
            ], 200),
        ]);

        Queue::fake();

        $project = Project::query()->create([
            'name' => 'Test Project',
            'slug' => 'test-project',
            'is_active' => true,
        ]);

        $source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::Brand24,
            'external_id' => 'brand24-source-1',
            'name' => 'Brand24 Source',
            'is_active' => true,
        ]);

        $this->postJson(self::ENDPOINT, [
            'source_uuid' => $source->uuid,
            'mention_id' => 'b24-mention-456',
            'content' => 'The product quality is unacceptable.',
            'url' => 'https://example.com/review/456',
            'title' => 'Bad review',
            'language' => 'en',
            'author_name' => 'John Doe',
            'author_id' => 'johndoe',
            'date' => '2026-06-29T10:00:00Z',
        ], [
            'Authorization' => 'Bearer test-ingest-token',
        ])->assertOk();

        Queue::assertPushed(ProcessMentionJob::class);

        $mention = Mention::query()->first();

        $this->assertNotNull($mention);

        $job = new ProcessMentionJob($mention->id);
        $job->handle($this->app->make(\App\Actions\ProcessMentionAction::class));

        $mention->refresh();

        $this->assertSame(MentionStatus::Completed, $mention->status);
        $this->assertSame('The product quality is unacceptable.', $mention->content);
        $this->assertSame('b24-mention-456', $mention->external_id);
        $this->assertSame('John Doe', $mention->author);
        $this->assertSame('johndoe', $mention->author_id);

        $this->assertDatabaseCount('ai_results', 1);

        $aiResult = AiResult::query()->first();

        $this->assertNotNull($aiResult);
        $this->assertSame('negative', $aiResult->sentiment);

        $route = MentionRoute::query()->first();

        $this->assertNotNull($route);
        $this->assertTrue($route->should_notify);
        $this->assertSame(RoutingPriority::Normal, $route->priority);
        $this->assertSame(RoutingChannel::Notification, $route->channel);

        $notification = TelegramNotification::query()->first();

        $this->assertNotNull($notification);
        $this->assertSame(TelegramNotificationStatus::Sent, $notification->status);
        $this->assertSame('88', $notification->message_id);

        Carbon::setTestNow();
    }
}
