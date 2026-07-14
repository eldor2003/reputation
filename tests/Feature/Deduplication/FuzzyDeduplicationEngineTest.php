<?php

namespace Tests\Feature\Deduplication;

use App\Actions\DeduplicateMentionAction;
use App\Contracts\DeduplicationEngineInterface;
use App\DTO\NormalizedMentionDTO;
use App\Enums\DeduplicationMatchMethod;
use App\Enums\MentionStatus;
use App\Enums\SourceType;
use App\Models\Mention;
use App\Models\MentionCluster;
use App\Models\Project;
use App\Models\Source;
use App\Services\ExactDeduplicationEngine;
use App\Services\FuzzyDeduplicationEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FuzzyDeduplicationEngineTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Source $brand24Source;

    private Source $mentionlyticsSource;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'deduplication.exact_fallback.enabled' => true,
            'deduplication.fuzzy.enabled' => true,
            'deduplication.similarity.threshold' => 0.85,
            'deduplication.similarity.minimum' => 0.70,
            'deduplication.time_window_hours' => 72,
        ]);

        $this->project = Project::query()->create([
            'name' => 'Dedup Project',
            'slug' => 'dedup-project',
            'is_active' => true,
        ]);

        $this->brand24Source = Source::query()->create([
            'project_id' => $this->project->id,
            'type' => SourceType::Brand24,
            'external_id' => 'brand24-source',
            'name' => 'Brand24 Source',
            'is_active' => true,
        ]);

        $this->mentionlyticsSource = Source::query()->create([
            'project_id' => $this->project->id,
            'type' => SourceType::Mentionlytics,
            'external_id' => 'mentionlytics-source',
            'name' => 'Mentionlytics Source',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_detects_same_brand24_mention_via_exact_fallback(): void
    {
        $publishedAt = Carbon::parse('2026-07-09 10:00:00');

        $original = $this->createStoredMention(
            source: $this->brand24Source,
            externalId: 'brand24-1',
            content: 'Brand24 article body',
            title: 'Brand24 title',
            url: 'https://news.example.com/article/1',
            author: 'Reporter',
            publishedAt: $publishedAt,
        );

        $result = $this->engine()->check($this->dto(
            source: $this->brand24Source,
            externalId: 'brand24-1',
            content: 'Brand24 article body',
            title: 'Brand24 title',
            url: 'https://news.example.com/article/1',
            author: 'Reporter',
            publishedAt: $publishedAt,
        ));

        $this->assertTrue($result->isDuplicate);
        $this->assertSame($original->id, $result->originalMentionId);
        $this->assertSame(DeduplicationMatchMethod::Exact, $result->matchMethod);
    }

    #[Test]
    public function it_detects_same_mentionlytics_mention_via_exact_fallback(): void
    {
        $publishedAt = Carbon::parse('2026-07-09 11:00:00');

        $original = $this->createStoredMention(
            source: $this->mentionlyticsSource,
            externalId: 'ml-1',
            content: 'Mentionlytics article body',
            title: 'Mentionlytics title',
            url: 'https://news.example.com/article/2',
            author: 'Editor',
            publishedAt: $publishedAt,
        );

        $result = $this->engine()->check($this->dto(
            source: $this->mentionlyticsSource,
            externalId: 'ml-1',
            content: 'Mentionlytics article body',
            title: 'Mentionlytics title',
            url: 'https://news.example.com/article/2',
            author: 'Editor',
            publishedAt: $publishedAt,
        ));

        $this->assertTrue($result->isDuplicate);
        $this->assertSame($original->id, $result->originalMentionId);
        $this->assertSame(DeduplicationMatchMethod::Exact, $result->matchMethod);
    }

    #[Test]
    public function it_detects_same_content_from_same_source_with_different_external_id(): void
    {
        $publishedAt = Carbon::parse('2026-07-09 10:30:00');
        $content = 'RT @silupescu:Il presidente del Kazakhstan shared identical text';

        $original = $this->createStoredMention(
            source: $this->mentionlyticsSource,
            externalId: 'ml-original',
            content: $content,
            title: null,
            url: 'https://news.example.com/rt-post',
            author: 'Reporter',
            publishedAt: $publishedAt,
        );

        $result = $this->engine()->check($this->dto(
            source: $this->mentionlyticsSource,
            externalId: 'ml-duplicate',
            content: $content,
            title: null,
            url: 'https://news.example.com/rt-post-copy',
            author: 'Reporter',
            publishedAt: $publishedAt,
        ));

        $this->assertTrue($result->isDuplicate);
        $this->assertSame($original->id, $result->originalMentionId);
        $this->assertSame(DeduplicationMatchMethod::Exact, $result->matchMethod);
    }

    #[Test]
    public function it_detects_same_article_from_two_providers_via_fuzzy_matching(): void
    {
        $publishedAt = Carbon::parse('2026-07-09 12:00:00');
        $content = 'Acme Corp launched a new sustainability initiative for global operations.';
        $url = 'https://news.example.com/acme-sustainability';

        $original = $this->createStoredMention(
            source: $this->brand24Source,
            externalId: 'brand24-cross-1',
            content: $content,
            title: 'Acme sustainability push',
            url: $url,
            author: 'Jane Reporter',
            publishedAt: $publishedAt,
        );

        $result = $this->engine()->check($this->dto(
            source: $this->mentionlyticsSource,
            externalId: 'ml-cross-1',
            content: $content,
            title: 'Acme sustainability push',
            url: $url,
            author: 'Jane Reporter',
            publishedAt: $publishedAt,
        ));

        $this->assertTrue($result->isDuplicate);
        $this->assertSame($original->id, $result->originalMentionId);
        $this->assertSame(DeduplicationMatchMethod::Exact, $result->matchMethod);
    }

    #[Test]
    public function it_detects_slightly_modified_article_as_duplicate(): void
    {
        $publishedAt = Carbon::parse('2026-07-09 13:00:00');
        $url = 'https://news.example.com/product-recall';

        $original = $this->createStoredMention(
            source: $this->brand24Source,
            externalId: 'brand24-mod-1',
            content: 'Company X issued a product recall for batch 2026-A due to safety concerns.',
            title: 'Product recall announced',
            url: $url,
            author: 'News Desk',
            publishedAt: $publishedAt,
        );

        $result = $this->engine()->check($this->dto(
            source: $this->mentionlyticsSource,
            externalId: 'ml-mod-1',
            content: 'Company X issued a product recall for batch 2026-A due to safety concerns!',
            title: 'Product recall announced',
            url: $url,
            author: 'News Desk',
            publishedAt: $publishedAt->copy()->addHour(),
        ));

        $this->assertTrue($result->isDuplicate);
        $this->assertSame($original->id, $result->originalMentionId);
        $this->assertSame(DeduplicationMatchMethod::Fuzzy, $result->matchMethod);
    }

    #[Test]
    public function it_detects_rewritten_cross_provider_article_when_simhash_differs(): void
    {
        $publishedAt = Carbon::parse('2026-07-09 13:00:00');
        $url = 'https://news.example.com/product-recall';
        $originalContent = 'Company X issued a product recall for batch 2026-A due to safety concerns.';
        $rewrittenContent = 'Company X issued a product recall for batch 2026-A because of safety concerns.';

        $simHashGenerator = $this->app->make(\App\Services\Deduplication\SimHashGenerator::class);
        $originalSimhash = $simHashGenerator->generate($originalContent);
        $rewrittenSimhash = $simHashGenerator->generate($rewrittenContent);

        $this->assertNotSame($originalSimhash, $rewrittenSimhash);
        $this->assertLessThanOrEqual(
            (int) config('deduplication.simhash.max_hamming_distance', 8),
            $simHashGenerator->hammingDistance($originalSimhash, $rewrittenSimhash),
        );

        $original = $this->createStoredMention(
            source: $this->brand24Source,
            externalId: 'brand24-rewrite-1',
            content: $originalContent,
            title: 'Product recall announced',
            url: $url,
            author: 'News Desk',
            publishedAt: $publishedAt,
        );

        $result = $this->engine()->check($this->dto(
            source: $this->mentionlyticsSource,
            externalId: 'ml-rewrite-1',
            content: $rewrittenContent,
            title: 'Product recall announced',
            url: $url,
            author: 'News Desk',
            publishedAt: $publishedAt->copy()->addHour(),
        ));

        $this->assertTrue($result->isDuplicate);
        $this->assertSame($original->id, $result->originalMentionId);
        $this->assertSame(DeduplicationMatchMethod::Fuzzy, $result->matchMethod);
    }

    #[Test]
    public function it_does_not_match_a_different_article(): void
    {
        $publishedAt = Carbon::parse('2026-07-09 14:00:00');

        $this->createStoredMention(
            source: $this->brand24Source,
            externalId: 'brand24-diff-1',
            content: 'Local sports team wins championship after overtime thriller.',
            title: 'Sports headline',
            url: 'https://news.example.com/sports',
            author: 'Sports Editor',
            publishedAt: $publishedAt,
        );

        $result = $this->engine()->check($this->dto(
            source: $this->mentionlyticsSource,
            externalId: 'ml-diff-1',
            content: 'Central bank raises interest rates to combat inflation.',
            title: 'Economy headline',
            url: 'https://news.example.com/economy',
            author: 'Finance Editor',
            publishedAt: $publishedAt,
        ));

        $this->assertFalse($result->isDuplicate);
        $this->assertNull($result->originalMentionId);
    }

    #[Test]
    public function deduplicate_action_creates_cluster_for_unique_mention(): void
    {
        $publishedAt = Carbon::parse('2026-07-09 15:00:00');

        $mention = Mention::query()->create([
            'project_id' => $this->project->id,
            'source_id' => $this->brand24Source->id,
            'external_id' => 'cluster-create-1',
            'content' => '',
            'received_at' => now(),
            'status' => MentionStatus::Processing,
        ]);

        $result = $this->app->make(DeduplicateMentionAction::class)->execute(
            $mention->id,
            $this->dto(
                source: $this->brand24Source,
                externalId: 'cluster-create-1',
                content: 'Unique article for cluster creation.',
                title: 'Unique title',
                url: 'https://news.example.com/unique',
                author: 'Author',
                publishedAt: $publishedAt,
            ),
        );

        $mention->refresh();

        $this->assertFalse($result->isDuplicate);
        $this->assertNotNull($result->clusterId);
        $this->assertSame($result->clusterId, $mention->mention_cluster_id);
        $this->assertDatabaseHas('mention_cluster_items', [
            'mention_id' => $mention->id,
            'mention_cluster_id' => $result->clusterId,
            'is_canonical' => true,
        ]);
    }

    #[Test]
    public function deduplicate_action_expands_existing_cluster_for_cross_provider_duplicate(): void
    {
        $publishedAt = Carbon::parse('2026-07-09 16:00:00');
        $content = 'Shared publication detected across monitoring providers.';
        $url = 'https://news.example.com/shared-publication';

        $original = $this->createStoredMention(
            source: $this->brand24Source,
            externalId: 'expand-original',
            content: $content,
            title: 'Shared publication',
            url: $url,
            author: 'Shared Author',
            publishedAt: $publishedAt,
        );

        $this->app->make(DeduplicateMentionAction::class)->execute(
            $original->id,
            $this->dto(
                source: $this->brand24Source,
                externalId: 'expand-original',
                content: $content,
                title: 'Shared publication',
                url: $url,
                author: 'Shared Author',
                publishedAt: $publishedAt,
            ),
        );

        $duplicateMention = Mention::query()->create([
            'project_id' => $this->project->id,
            'source_id' => $this->mentionlyticsSource->id,
            'external_id' => 'expand-duplicate',
            'content' => '',
            'received_at' => now(),
            'status' => MentionStatus::Processing,
        ]);

        $result = $this->app->make(DeduplicateMentionAction::class)->execute(
            $duplicateMention->id,
            $this->dto(
                source: $this->mentionlyticsSource,
                externalId: 'expand-duplicate',
                content: $content,
                title: 'Shared publication',
                url: $url,
                author: 'Shared Author',
                publishedAt: $publishedAt,
            ),
        );

        $original->refresh();
        $duplicateMention->refresh();

        $this->assertTrue($result->isDuplicate);
        $this->assertSame($original->id, $result->originalMentionId);
        $this->assertNotNull($original->mention_cluster_id);
        $this->assertSame($original->mention_cluster_id, $duplicateMention->mention_cluster_id);
        $this->assertSame(2, MentionCluster::query()->first()?->items()->count());
    }

    #[Test]
    public function it_merges_clusters_through_repository(): void
    {
        $fingerprint = new \App\DTO\MentionFingerprintDTO(
            simhash: 'aaa111',
            contentFingerprint: 'fp-a',
            dedupHash: 'fp-a',
        );

        $leftMention = Mention::query()->create([
            'project_id' => $this->project->id,
            'source_id' => $this->brand24Source->id,
            'external_id' => 'merge-left',
            'content' => 'Left',
            'received_at' => now(),
            'status' => MentionStatus::Completed,
        ]);

        $rightMention = Mention::query()->create([
            'project_id' => $this->project->id,
            'source_id' => $this->mentionlyticsSource->id,
            'external_id' => 'merge-right',
            'content' => 'Right',
            'received_at' => now(),
            'status' => MentionStatus::Completed,
        ]);

        $repository = $this->app->make(\App\Contracts\MentionClusterRepositoryInterface::class);
        $leftCluster = $repository->createCluster($this->project->id, $leftMention->id, $fingerprint);
        $rightCluster = $repository->createCluster(
            $this->project->id,
            $rightMention->id,
            new \App\DTO\MentionFingerprintDTO('bbb222', 'fp-b', 'fp-b'),
        );

        $merged = $repository->mergeClusters($leftCluster, $rightCluster);

        $this->assertSame($leftCluster->id, $merged->id);
        $this->assertDatabaseMissing('mention_clusters', ['id' => $rightCluster->id]);
        $this->assertSame($merged->id, $rightMention->fresh()?->mention_cluster_id);
    }

    #[Test]
    public function exact_engine_remains_available_as_fallback(): void
    {
        $exact = $this->app->make(ExactDeduplicationEngine::class);

        $this->assertInstanceOf(DeduplicationEngineInterface::class, $exact);
        $this->assertInstanceOf(FuzzyDeduplicationEngine::class, $this->app->make(DeduplicationEngineInterface::class));
    }

    private function engine(): FuzzyDeduplicationEngine
    {
        return $this->app->make(FuzzyDeduplicationEngine::class);
    }

    private function dto(
        Source $source,
        string $externalId,
        string $content,
        ?string $title = null,
        ?string $url = null,
        ?string $author = null,
        ?Carbon $publishedAt = null,
    ): NormalizedMentionDTO {
        return new NormalizedMentionDTO(
            projectId: $this->project->id,
            sourceId: $source->id,
            externalId: $externalId,
            author: $author,
            authorId: null,
            language: 'en',
            text: $content,
            title: $title,
            url: $url,
            publishedAt: $publishedAt,
            receivedAt: Carbon::parse('2026-07-09 17:00:00'),
        );
    }

    private function createStoredMention(
        Source $source,
        string $externalId,
        string $content,
        ?string $title,
        ?string $url,
        ?string $author,
        Carbon $publishedAt,
    ): Mention {
        $fingerprint = hash('sha256', mb_strtolower(trim($content)));
        $simhash = $this->app->make(\App\Services\Deduplication\SimHashGenerator::class)->generate($content);

        return Mention::query()->create([
            'project_id' => $this->project->id,
            'source_id' => $source->id,
            'external_id' => $externalId,
            'content' => $content,
            'title' => $title,
            'url' => $url,
            'author' => $author,
            'published_at' => $publishedAt,
            'received_at' => now(),
            'status' => MentionStatus::Completed,
            'dedup_hash' => hash('sha256', $source->id.'|'.$externalId),
            'content_fingerprint' => $fingerprint,
            'simhash' => $simhash,
            'is_duplicate' => false,
        ]);
    }
}
