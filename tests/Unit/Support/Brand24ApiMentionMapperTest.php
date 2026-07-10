<?php

namespace Tests\Unit\Support;

use App\DTO\Brand24MentionDTO;
use App\Support\Brand24ApiMentionMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Brand24ApiMentionMapperTest extends TestCase
{
    #[Test]
    public function it_maps_brand24_api_mention_to_ingest_payload(): void
    {
        $payload = Brand24ApiMentionMapper::toIngestPayload(
            mention: new Brand24MentionDTO(
                date: '2026-06-26',
                time: '17:30',
                title: 'Complaint title',
                content: 'The service was terrible and needs attention.',
                source: 'https://example.com/post/1',
                host: 'example.com',
                category: 'news',
                sentiment: -1,
                tags: ['complaint'],
            ),
            externalId: 'e2e-mention-1',
            sourceUuid: '11111111-1111-1111-1111-111111111111',
        );

        $this->assertSame('11111111-1111-1111-1111-111111111111', $payload['source_uuid']);
        $this->assertSame('e2e-mention-1', $payload['mention_id']);
        $this->assertSame('The service was terrible and needs attention.', $payload['content']);
        $this->assertSame('https://example.com/post/1', $payload['url']);
        $this->assertSame('2026-06-26 17:30', $payload['date']);
    }
}
