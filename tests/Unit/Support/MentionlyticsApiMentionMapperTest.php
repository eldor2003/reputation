<?php

namespace Tests\Unit\Support;

use App\DTO\MentionlyticsMentionDTO;
use App\Support\MentionlyticsApiMentionMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MentionlyticsApiMentionMapperTest extends TestCase
{
    #[Test]
    public function it_maps_api_mention_to_ingest_payload(): void
    {
        $mention = new MentionlyticsMentionDTO(
            id: '123',
            uuid: 'uuid-456',
            text: 'Mapped mention text.',
            url: 'https://example.com/post/123',
            title: 'Tracker name',
            authorName: 'Jane Smith',
            authorId: 'profile-1',
            publishedAt: '2026-07-09 10:00:00',
            language: 'en',
            sentiment: 'negative',
            channel: 'twitter',
            channelId: 2,
            engagement: 42,
            raw: ['id' => '123'],
        );

        $payload = MentionlyticsApiMentionMapper::toIngestPayload($mention, 'source-uuid-1');

        $this->assertSame([
            'source_uuid' => 'source-uuid-1',
            'mention_id' => 'uuid-456',
            'content' => 'Mapped mention text.',
            'url' => 'https://example.com/post/123',
            'title' => 'Tracker name',
            'language' => 'en',
            'author_name' => 'Jane Smith',
            'author_id' => 'profile-1',
            'date' => '2026-07-09 10:00:00',
            'sentiment_text' => 'negative',
            'mchannel' => 'twitter',
            'mchannel_id' => 2,
            'mEngagement' => 42,
        ], $payload);
    }
}
