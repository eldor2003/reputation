<?php

namespace Tests\Feature\Ingest;

use App\Enums\SourceType;
use App\Jobs\ProcessMentionJob;
use App\Models\IngestIdempotencyKey;
use App\Models\Mention;
use App\Models\Project;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IngestIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ingest.api_token' => 'test-ingest-token',
            'ingest.idempotency.lock_ttl_seconds' => 300,
        ]);
    }

    #[Test]
    public function it_ignores_duplicate_youscan_webhooks_and_returns_success(): void
    {
        Queue::fake();
        Log::spy();

        $source = $this->createSource(SourceType::YouScan);
        $payload = $this->youScanPayload($source);

        $this->postJson('/api/v1/ingest/youscan', $payload, $this->authorizedHeaders())
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->postJson('/api/v1/ingest/youscan', $payload, $this->authorizedHeaders())
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->assertDatabaseCount('mentions', 1);
        $this->assertDatabaseCount('ingest_idempotency_keys', 1);
        Queue::assertPushed(ProcessMentionJob::class, 1);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('Duplicate ingest webhook ignored.', \Mockery::on(function (array $context): bool {
                return $context['provider'] === 'youscan'
                    && $context['external_id'] === 'mention-123'
                    && $context['reason'] === 'database';
            }));
    }

    #[Test]
    public function it_ignores_duplicate_brand24_webhooks_and_returns_success(): void
    {
        Queue::fake();
        Log::spy();

        $source = $this->createSource(SourceType::Brand24);
        $payload = $this->brand24Payload($source);

        $this->postJson('/api/v1/ingest/brand24', $payload, $this->authorizedHeaders())
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->postJson('/api/v1/ingest/brand24', $payload, $this->authorizedHeaders())
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->assertDatabaseCount('mentions', 1);
        $this->assertDatabaseCount('ingest_idempotency_keys', 1);
        Queue::assertPushed(ProcessMentionJob::class, 1);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('Duplicate ingest webhook ignored.', \Mockery::on(function (array $context): bool {
                return $context['provider'] === 'brand24'
                    && $context['external_id'] === 'b24-mention-123'
                    && $context['reason'] === 'database';
            }));
    }

    #[Test]
    public function it_honors_explicit_idempotency_key_for_duplicate_detection(): void
    {
        Queue::fake();

        $source = $this->createSource(SourceType::YouScan);

        $payload = array_merge($this->youScanPayload($source), [
            'id' => 'mention-999',
            'idempotency_key' => 'shared-webhook-key',
        ]);

        $duplicatePayload = array_merge($this->youScanPayload($source), [
            'id' => 'mention-888',
            'text' => 'Different body',
            'idempotency_key' => 'shared-webhook-key',
        ]);

        $this->postJson('/api/v1/ingest/youscan', $payload, $this->authorizedHeaders())->assertOk();
        $this->postJson('/api/v1/ingest/youscan', $duplicatePayload, $this->authorizedHeaders())->assertOk();

        $this->assertDatabaseCount('mentions', 1);
        $this->assertSame('mention-999', Mention::query()->value('external_id'));
        $this->assertSame('shared-webhook-key', IngestIdempotencyKey::query()->value('idempotency_key'));
        Queue::assertPushed(ProcessMentionJob::class, 1);
    }

    private function createSource(SourceType $type): Source
    {
        $project = Project::query()->create([
            'name' => 'Test Project',
            'slug' => 'test-project',
            'is_active' => true,
        ]);

        return Source::query()->create([
            'project_id' => $project->id,
            'type' => $type,
            'external_id' => $type->value.'-source-1',
            'name' => $type->name.' Source',
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function youScanPayload(Source $source): array
    {
        return [
            'source_uuid' => $source->uuid,
            'id' => 'mention-123',
            'text' => 'Sample mention text.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function brand24Payload(Source $source): array
    {
        return [
            'source_uuid' => $source->uuid,
            'mention_id' => 'b24-mention-123',
            'content' => 'Sample Brand24 mention text.',
        ];
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
