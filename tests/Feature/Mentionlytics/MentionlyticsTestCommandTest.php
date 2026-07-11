<?php

namespace Tests\Feature\Mentionlytics;

use App\Enums\SourceType;
use App\Models\Project;
use App\Models\Source;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesTelegram;
use Tests\TestCase;

class MentionlyticsTestCommandTest extends TestCase
{
    use FakesTelegram;
    use RefreshDatabase;

    #[Test]
    public function pipeline_mode_requires_an_existing_source(): void
    {
        $this->configureMentionlyticsClient();
        $source = $this->createMentionlyticsSource();

        Http::fake([
            'api.mentionlytics.com/v2/mentions*' => Http::response([
                'mentions' => [
                    ['id' => '1', 'ftext' => 'Test mention.'],
                ],
                'results_after' => null,
            ], 200),
        ]);

        $this->artisan('mentionlytics:test', ['--pipeline' => true])
            ->expectsOutputToContain('A Mentionlytics source is required for pipeline verification.')
            ->expectsOutputToContain('Provide an existing source with --source=<id> or --source=<uuid>.')
            ->expectsOutputToContain('Available Mentionlytics sources:')
            ->expectsOutputToContain('Mentionlytics Test Source')
            ->expectsOutputToContain((string) $source->id)
            ->assertFailed();

        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseCount('sources', 1);
    }

    #[Test]
    public function pipeline_mode_rejects_unknown_source_option(): void
    {
        $this->configureMentionlyticsClient();
        $source = $this->createMentionlyticsSource();

        Http::fake([
            'api.mentionlytics.com/v2/mentions*' => Http::response([
                'mentions' => [
                    ['id' => '1', 'ftext' => 'Test mention.'],
                ],
                'results_after' => null,
            ], 200),
        ]);

        $this->artisan('mentionlytics:test', [
            '--pipeline' => true,
            '--source' => '99999',
        ])
            ->expectsOutputToContain('Mentionlytics source not found: 99999')
            ->expectsOutputToContain('Available Mentionlytics sources:')
            ->expectsOutputToContain((string) $source->id)
            ->assertFailed();
    }

    #[Test]
    public function pipeline_mode_accepts_source_by_numeric_id(): void
    {
        $source = $this->createMentionlyticsSource();
        $this->fakePipelineDependencies();

        $this->artisan('mentionlytics:test', [
            '--pipeline' => true,
            '--source' => (string) $source->id,
        ])
            ->expectsOutputToContain('Pipeline Verification Report')
            ->assertSuccessful();
    }

    #[Test]
    public function pipeline_mode_accepts_source_by_uuid(): void
    {
        $source = $this->createMentionlyticsSource();
        $this->fakePipelineDependencies();

        $this->artisan('mentionlytics:test', [
            '--pipeline' => true,
            '--source' => $source->uuid,
        ])
            ->expectsOutputToContain('Pipeline Verification Report')
            ->assertSuccessful();
    }

    #[Test]
    public function connectivity_mode_does_not_require_source_option(): void
    {
        $this->configureMentionlyticsClient();

        Http::fake([
            'api.mentionlytics.com/v2/mentions*' => Http::response([
                'mentions' => [
                    ['id' => '1', 'ftext' => 'Test mention.'],
                ],
                'results_after' => '2',
            ], 200),
        ]);

        $this->artisan('mentionlytics:test')
            ->expectsOutputToContain('API Connection Status: OK')
            ->assertSuccessful();
    }

    private function createMentionlyticsSource(): Source
    {
        $project = Project::query()->create([
            'name' => 'Test Project',
            'slug' => 'test-project',
            'is_active' => true,
        ]);

        return Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::Mentionlytics,
            'external_id' => 'mentionlytics-test-source',
            'name' => 'Mentionlytics Test Source',
            'is_active' => true,
        ]);
    }

    private function fakePipelineDependencies(): void
    {
        Queue::fake();

        Carbon::setTestNow(Carbon::parse('2026-07-10 14:00:00', 'UTC'));

        $this->configureMentionlyticsClient([
            'app.timezone' => 'UTC',
            'telegram.bot_token' => 'test-bot-token',
            'telegram.chat_id' => '-100123456',
            'telegram.base_url' => 'https://api.telegram.org',
            'claude.base_url' => 'https://api.anthropic.com/v1',
        ]);

        Http::fake([
            'api.mentionlytics.com/v2/mentions*' => Http::response([
                'mentions' => [
                    [
                        'id' => '1',
                        'ftext' => 'Negative pipeline mention with enough detail for testing.',
                        'emotion_text' => 'negative',
                        'pub_datetime' => '2026-07-09 10:00:00',
                    ],
                ],
                'results_after' => null,
            ], 200),
            'api.anthropic.com/v1/messages' => Http::response([
                'id' => 'msg_123',
                'model' => 'claude-test-model',
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'summary' => 'Negative Mentionlytics mention about product quality.',
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
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configureMentionlyticsClient(array $overrides = []): void
    {
        config(array_merge([
            'mentionlytics.bearer_token' => 'test-bearer-token',
            'mentionlytics.refresh_token' => 'test-refresh-token',
            'mentionlytics.base_url' => 'https://api.mentionlytics.com/v2',
            'mentionlytics.timeout' => 5,
            'mentionlytics.access_token_ttl_seconds' => 3600,
            'mentionlytics.proactive_refresh_buffer_seconds' => 300,
            'mentionlytics.retry.max_attempts' => 5,
            'mentionlytics.retry.base_delay_ms' => 0,
            'mentionlytics.retry.max_delay_ms' => 0,
            'ingest.api_token' => 'test-ingest-token',
        ], $overrides));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
