<?php

namespace Tests\Unit\Providers\Mentionlytics;

use App\Contracts\MentionlyticsClientInterface;
use App\DTO\MentionlyticsConnectionInfoDTO;
use App\DTO\MentionlyticsMentionsPageDTO;
use App\DTO\MentionlyticsMentionsQueryDTO;
use App\DTO\NormalizedMentionDTO;
use App\Enums\SourceType;
use App\Providers\Mentionlytics\MentionlyticsProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MentionlyticsProviderTest extends TestCase
{
    #[Test]
    public function it_exposes_provider_metadata(): void
    {
        $provider = new MentionlyticsProvider(
            new \App\Providers\Mentionlytics\MentionlyticsNormalizer,
            $this->createMock(MentionlyticsClientInterface::class),
        );

        $this->assertSame('mentionlytics', $provider->name());
        $this->assertTrue($provider->supports(SourceType::Mentionlytics));
        $this->assertFalse($provider->supports(SourceType::Brand24));
    }

    #[Test]
    public function it_delegates_normalization_to_the_normalizer(): void
    {
        $provider = new MentionlyticsProvider(
            new \App\Providers\Mentionlytics\MentionlyticsNormalizer,
            $this->createMock(MentionlyticsClientInterface::class),
        );

        $normalized = $provider->normalize([
            'project_id' => 1,
            'source_id' => 2,
            'mention_id' => 'ml-123',
            'content' => 'Mentionlytics mention text.',
        ]);

        $this->assertInstanceOf(NormalizedMentionDTO::class, $normalized);
        $this->assertSame('ml-123', $normalized->externalId);
    }

    #[Test]
    public function it_delegates_api_calls_to_the_client(): void
    {
        $client = $this->createMock(MentionlyticsClientInterface::class);
        $client->expects($this->once())
            ->method('testConnection')
            ->willReturn(new MentionlyticsConnectionInfoDTO(1, false));

        $client->expects($this->once())
            ->method('getMentions')
            ->with($this->isInstanceOf(MentionlyticsMentionsQueryDTO::class))
            ->willReturn(new MentionlyticsMentionsPageDTO([], false, null));

        $provider = new MentionlyticsProvider(
            new \App\Providers\Mentionlytics\MentionlyticsNormalizer,
            $client,
        );

        $this->assertSame(1, $provider->testConnection()->mentionsOnPage);
        $this->assertSame([], $provider->getMentions(new MentionlyticsMentionsQueryDTO('20260101', '20260131'))->mentions);
    }
}
