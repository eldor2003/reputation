<?php

namespace Tests\Unit\Providers\YouScan;

use App\Exceptions\MentionNormalizationException;
use App\Providers\YouScan\YouScanNormalizer;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class YouScanNormalizerTest extends TestCase
{
    private YouScanNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new YouScanNormalizer;
    }

    #[Test]
    public function it_normalizes_a_youscan_payload_with_author_object(): void
    {
        Carbon::setTestNow('2026-06-29 12:00:00');

        $normalized = $this->normalizer->normalize([
            'project_id' => 1,
            'source_id' => 2,
            'id' => 'mention-123',
            'text' => 'Sample mention text.',
            'url' => 'https://example.com/post/123',
            'title' => 'Sample title',
            'language' => 'en',
            'author' => [
                'id' => 'author-42',
                'name' => 'John Doe',
            ],
            'published' => '2026-06-29T10:00:00Z',
            'received_at' => '2026-06-29T11:00:00Z',
            'sentiment' => 'negative',
        ]);

        $this->assertSame(1, $normalized->projectId);
        $this->assertSame(2, $normalized->sourceId);
        $this->assertSame('mention-123', $normalized->externalId);
        $this->assertSame('John Doe', $normalized->author);
        $this->assertSame('author-42', $normalized->authorId);
        $this->assertSame('en', $normalized->language);
        $this->assertSame('Sample mention text.', $normalized->text);
        $this->assertSame('Sample title', $normalized->title);
        $this->assertSame('https://example.com/post/123', $normalized->url);
        $this->assertSame('2026-06-29 10:00:00', $normalized->publishedAt?->toDateTimeString());
        $this->assertSame('2026-06-29 11:00:00', $normalized->receivedAt->toDateTimeString());
        $this->assertSame(['sentiment' => 'negative'], $normalized->metadata);
    }

    #[Test]
    public function it_supports_alternative_youscan_field_names(): void
    {
        $normalized = $this->normalizer->normalize([
            'project_id' => 10,
            'source_id' => 20,
            'id' => 'abc',
            'fullText' => 'Full text body',
            'lang' => 'uk',
            'author' => 'Jane Smith',
            'author_id' => '777',
            'publishedAt' => '2026-06-28T08:00:00Z',
        ]);

        $this->assertSame('Full text body', $normalized->text);
        $this->assertSame('uk', $normalized->language);
        $this->assertSame('Jane Smith', $normalized->author);
        $this->assertSame('777', $normalized->authorId);
        $this->assertNull($normalized->metadata);
    }

    #[Test]
    public function it_throws_when_required_text_is_missing(): void
    {
        $this->expectException(MentionNormalizationException::class);
        $this->expectExceptionMessage('YouScan payload is missing mention text.');

        $this->normalizer->normalize([
            'project_id' => 1,
            'source_id' => 2,
            'id' => 'mention-123',
        ]);
    }

    #[Test]
    public function it_throws_when_project_id_is_missing(): void
    {
        $this->expectException(MentionNormalizationException::class);
        $this->expectExceptionMessage('YouScan payload is missing required field: project_id.');

        $this->normalizer->normalize([
            'source_id' => 2,
            'id' => 'mention-123',
            'text' => 'Body',
        ]);
    }
}
