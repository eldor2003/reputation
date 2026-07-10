<?php

namespace Tests\Unit\Providers\Mentionlytics;

use App\Exceptions\MentionNormalizationException;
use App\Providers\Mentionlytics\MentionlyticsNormalizer;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MentionlyticsNormalizerTest extends TestCase
{
    private MentionlyticsNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new MentionlyticsNormalizer;
    }

    #[Test]
    public function it_normalizes_a_mentionlytics_payload(): void
    {
        Carbon::setTestNow('2026-07-09 12:00:00');

        $normalized = $this->normalizer->normalize([
            'project_id' => 1,
            'source_id' => 2,
            'mention_id' => 'ml-mention-123',
            'content' => 'Mentionlytics mention content.',
            'url' => 'https://twitter.com/example/status/123',
            'title' => 'Brand mention',
            'language' => 'en',
            'author_name' => 'Jane Smith',
            'author_id' => 'janesmith',
            'date' => '2026-07-09T10:00:00Z',
            'received_at' => '2026-07-09T11:00:00Z',
            'sentiment_text' => 'negative',
            'mchannel' => 'twitter',
        ]);

        $this->assertSame(1, $normalized->projectId);
        $this->assertSame(2, $normalized->sourceId);
        $this->assertSame('ml-mention-123', $normalized->externalId);
        $this->assertSame('Jane Smith', $normalized->author);
        $this->assertSame('janesmith', $normalized->authorId);
        $this->assertSame('en', $normalized->language);
        $this->assertSame('Mentionlytics mention content.', $normalized->text);
        $this->assertSame('Brand mention', $normalized->title);
        $this->assertSame('https://twitter.com/example/status/123', $normalized->url);
        $this->assertSame('2026-07-09 10:00:00', $normalized->publishedAt?->toDateTimeString());
        $this->assertSame('2026-07-09 11:00:00', $normalized->receivedAt->toDateTimeString());
        $this->assertSame([
            'sentiment_text' => 'negative',
            'mchannel' => 'twitter',
        ], $normalized->metadata);
    }

    #[Test]
    public function it_supports_api_style_field_names(): void
    {
        $normalized = $this->normalizer->normalize([
            'project_id' => 10,
            'source_id' => 20,
            'uu_id' => 'uuid-abc',
            'ftext' => 'API mention text.',
            'profile_name' => 'brand_user',
            'uid' => '12345',
            'pub_datetime' => '2026-07-08 15:30:00',
            'link' => 'https://example.com/post/1',
        ]);

        $this->assertSame('uuid-abc', $normalized->externalId);
        $this->assertSame('API mention text.', $normalized->text);
        $this->assertSame('brand_user', $normalized->author);
        $this->assertSame('12345', $normalized->authorId);
        $this->assertSame('https://example.com/post/1', $normalized->url);
    }

    #[Test]
    public function it_throws_when_content_is_missing(): void
    {
        $this->expectException(MentionNormalizationException::class);
        $this->expectExceptionMessage('Mentionlytics payload is missing mention content.');

        $this->normalizer->normalize([
            'project_id' => 1,
            'source_id' => 2,
            'mention_id' => 'mention-123',
        ]);
    }
}
