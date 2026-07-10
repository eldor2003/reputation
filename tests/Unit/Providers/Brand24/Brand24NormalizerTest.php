<?php

namespace Tests\Unit\Providers\Brand24;

use App\Exceptions\MentionNormalizationException;
use App\Providers\Brand24\Brand24Normalizer;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Brand24NormalizerTest extends TestCase
{
    private Brand24Normalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new Brand24Normalizer;
    }

    #[Test]
    public function it_normalizes_a_brand24_payload(): void
    {
        Carbon::setTestNow('2026-06-29 12:00:00');

        $normalized = $this->normalizer->normalize([
            'project_id' => 1,
            'source_id' => 2,
            'mention_id' => 'b24-mention-123',
            'content' => 'Brand24 mention content.',
            'url' => 'https://twitter.com/example/status/123',
            'title' => 'Brand mention',
            'language' => 'en',
            'author_name' => 'Jane Smith',
            'author_id' => 'janesmith',
            'date' => '2026-06-29T10:00:00Z',
            'received_at' => '2026-06-29T11:00:00Z',
            'sentiment' => 'negative',
        ]);

        $this->assertSame(1, $normalized->projectId);
        $this->assertSame(2, $normalized->sourceId);
        $this->assertSame('b24-mention-123', $normalized->externalId);
        $this->assertSame('Jane Smith', $normalized->author);
        $this->assertSame('janesmith', $normalized->authorId);
        $this->assertSame('en', $normalized->language);
        $this->assertSame('Brand24 mention content.', $normalized->text);
        $this->assertSame('Brand mention', $normalized->title);
        $this->assertSame('https://twitter.com/example/status/123', $normalized->url);
        $this->assertSame('2026-06-29 10:00:00', $normalized->publishedAt?->toDateTimeString());
        $this->assertSame('2026-06-29 11:00:00', $normalized->receivedAt->toDateTimeString());
        $this->assertSame(['sentiment' => 'negative'], $normalized->metadata);
    }

    #[Test]
    public function it_supports_structured_brand24_text_field(): void
    {
        $normalized = $this->normalizer->normalize([
            'project_id' => 10,
            'source_id' => 20,
            'mention_id' => 'abc',
            'text' => [
                'before' => 'Prefix ',
                'keyword' => 'Brand',
                'after' => ' suffix',
            ],
            'username' => 'brand_user',
        ]);

        $this->assertSame('Prefix Brand suffix', $normalized->text);
        $this->assertSame('brand_user', $normalized->author);
        $this->assertSame('brand_user', $normalized->authorId);
    }

    #[Test]
    public function it_throws_when_content_is_missing(): void
    {
        $this->expectException(MentionNormalizationException::class);
        $this->expectExceptionMessage('Brand24 payload is missing mention content.');

        $this->normalizer->normalize([
            'project_id' => 1,
            'source_id' => 2,
            'mention_id' => 'mention-123',
        ]);
    }
}
