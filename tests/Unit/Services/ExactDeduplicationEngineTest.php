<?php

namespace Tests\Unit\Services;

use App\DTO\NormalizedMentionDTO;
use App\Enums\MentionStatus;
use App\Models\Mention;
use App\Models\Project;
use App\Models\Source;
use App\Enums\SourceType;
use App\Services\ExactDeduplicationEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExactDeduplicationEngineTest extends TestCase
{
    use RefreshDatabase;

    private ExactDeduplicationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new ExactDeduplicationEngine;
    }

    #[Test]
    public function it_generates_a_stable_sha256_hash_from_source_and_external_id(): void
    {
        $dto = $this->makeDto(sourceId: 5, externalId: 'mention-123');

        $result = $this->engine->check($dto);

        $this->assertFalse($result->isDuplicate);
        $this->assertNull($result->originalMentionId);
        $this->assertSame(
            hash('sha256', '5|mention-123'),
            $result->dedupHash,
        );
    }

    #[Test]
    public function it_detects_an_existing_original_mention_as_duplicate(): void
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

        $dedupHash = hash('sha256', $source->id.'|mention-123');

        $original = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'mention-123',
            'content' => 'Original content',
            'received_at' => now(),
            'status' => MentionStatus::Completed,
            'dedup_hash' => $dedupHash,
            'is_duplicate' => false,
        ]);

        $result = $this->engine->check($this->makeDto(
            sourceId: $source->id,
            externalId: 'mention-123',
        ));

        $this->assertTrue($result->isDuplicate);
        $this->assertSame($original->id, $result->originalMentionId);
        $this->assertSame($dedupHash, $result->dedupHash);
    }

    #[Test]
    public function it_ignores_existing_duplicate_mentions_when_finding_original(): void
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

        $dedupHash = hash('sha256', $source->id.'|mention-123');

        $original = Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'mention-123',
            'content' => 'Original content',
            'received_at' => now(),
            'status' => MentionStatus::Completed,
            'dedup_hash' => $dedupHash,
            'is_duplicate' => false,
        ]);

        Mention::query()->create([
            'project_id' => $project->id,
            'source_id' => $source->id,
            'external_id' => 'mention-123-copy',
            'content' => 'Duplicate content',
            'received_at' => now(),
            'status' => MentionStatus::Completed,
            'dedup_hash' => $dedupHash,
            'is_duplicate' => true,
            'original_mention_id' => $original->id,
        ]);

        $result = $this->engine->check($this->makeDto(
            sourceId: $source->id,
            externalId: 'mention-123',
        ));

        $this->assertTrue($result->isDuplicate);
        $this->assertSame($original->id, $result->originalMentionId);
    }

    private function makeDto(int $sourceId, string $externalId): NormalizedMentionDTO
    {
        return new NormalizedMentionDTO(
            projectId: 1,
            sourceId: $sourceId,
            externalId: $externalId,
            author: null,
            authorId: null,
            language: null,
            text: 'Body',
            title: null,
            url: null,
            publishedAt: null,
            receivedAt: Carbon::parse('2026-06-29T11:00:00Z'),
        );
    }
}
