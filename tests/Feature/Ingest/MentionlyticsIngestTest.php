<?php

namespace Tests\Feature\Ingest;

use App\Enums\MentionStatus;
use App\Enums\SourceType;
use App\Jobs\ProcessMentionJob;
use App\Models\Mention;
use App\Models\MentionRaw;
use App\Models\Project;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class MentionlyticsIngestTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/ingest/mentionlytics';

    private Source $source;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ingest.api_token' => 'test-ingest-token']);

        $project = Project::query()->create([
            'name' => 'Test Project',
            'slug' => 'test-project',
            'is_active' => true,
        ]);

        $this->source = Source::query()->create([
            'project_id' => $project->id,
            'type' => SourceType::Mentionlytics,
            'external_id' => 'mentionlytics-source-1',
            'name' => 'Mentionlytics Source',
            'is_active' => true,
            'config' => ['commtracks' => '31016'],
        ]);
    }

    public function test_it_ingests_mentionlytics_mention_successfully(): void
    {
        Queue::fake();

        $payload = $this->validPayload();

        $response = $this->postJson(self::ENDPOINT, $payload, $this->authorizedHeaders());

        $response->assertOk()
            ->assertExactJson(['success' => true]);

        $this->assertDatabaseCount('mentions', 1);
        $this->assertDatabaseCount('mention_raws', 1);

        $mention = Mention::query()->first();

        $this->assertNotNull($mention);
        $this->assertSame($this->source->project_id, $mention->project_id);
        $this->assertSame($this->source->id, $mention->source_id);
        $this->assertSame('ml-mention-123', $mention->external_id);
        $this->assertSame('', $mention->content);
        $this->assertSame(MentionStatus::Pending, $mention->status);

        $raw = MentionRaw::query()->first();

        $this->assertNotNull($raw);
        $this->assertSame($mention->id, $raw->mention_id);
        $this->assertSame('mentionlytics', $raw->provider);
        $this->assertSame('Sample Mentionlytics mention text.', $raw->payload['content']);
        $this->assertSame($this->source->project_id, $raw->payload['project_id']);
        $this->assertSame($this->source->id, $raw->payload['source_id']);
        $this->assertArrayHasKey('received_at', $raw->payload);

        Queue::assertPushed(ProcessMentionJob::class, function (ProcessMentionJob $job) use ($mention): bool {
            return $job->mentionId === $mention->id;
        });
    }

    public function test_it_rejects_unauthorized_requests(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->validPayload());

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Не авторизован.']);

        $this->assertDatabaseCount('mentions', 0);
    }

    public function test_it_validates_required_fields(): void
    {
        $response = $this->postJson(self::ENDPOINT, [], $this->authorizedHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['source_uuid', 'mention_id', 'content']);
    }

    public function test_it_rejects_unknown_source(): void
    {
        $payload = $this->validPayload([
            'source_uuid' => (string) Str::uuid(),
        ]);

        $response = $this->postJson(self::ENDPOINT, $payload, $this->authorizedHeaders());

        $response->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'Источник mentionlytics недоступен: '.$payload['source_uuid'],
            ]);
    }

    public function test_it_rejects_non_mentionlytics_source(): void
    {
        $this->source->update(['type' => SourceType::Brand24]);

        $response = $this->postJson(self::ENDPOINT, $this->validPayload(), $this->authorizedHeaders());

        $response->assertUnprocessable();
        $this->assertDatabaseCount('mentions', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'source_uuid' => $this->source->uuid,
            'mention_id' => 'ml-mention-123',
            'content' => 'Sample Mentionlytics mention text.',
            'url' => 'https://example.com/post/123',
            'title' => 'Sample title',
            'language' => 'en',
            'author_name' => 'John Doe',
            'author_id' => 'johndoe',
            'date' => '2026-07-09T10:00:00Z',
        ], $overrides);
    }

    /**
     * @return array<string, string>
     */
    private function authorizedHeaders(): array
    {
        return [
            'Authorization' => 'Bearer test-ingest-token',
        ];
    }
}
