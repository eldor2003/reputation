<?php

namespace Tests\Unit\Providers\Brand24;

use App\Contracts\Brand24ClientInterface;
use App\DTO\Brand24AccountInfoDTO;
use App\Enums\SourceType;
use App\Providers\Brand24\Brand24Normalizer;
use App\Providers\Brand24\Brand24Provider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Brand24ProviderTest extends TestCase
{
    #[Test]
    public function it_exposes_brand24_metadata(): void
    {
        $provider = new Brand24Provider(new Brand24Normalizer, $this->mockClient());

        $this->assertSame('brand24', $provider->name());
        $this->assertTrue($provider->supports(SourceType::Brand24));
        $this->assertFalse($provider->supports(SourceType::YouScan));
    }

    #[Test]
    public function it_normalizes_payload_through_brand24_normalizer(): void
    {
        $provider = new Brand24Provider(new Brand24Normalizer, $this->mockClient());

        $normalized = $provider->normalize([
            'project_id' => 1,
            'source_id' => 2,
            'mention_id' => 'mention-123',
            'content' => 'Sample Brand24 mention.',
        ]);

        $this->assertSame('mention-123', $normalized->externalId);
        $this->assertSame('Sample Brand24 mention.', $normalized->text);
    }

    #[Test]
    public function it_delegates_api_operations_to_brand24_client(): void
    {
        $client = $this->createMock(Brand24ClientInterface::class);
        $client->expects($this->once())
            ->method('testConnection')
            ->willReturn(new Brand24AccountInfoDTO(14820));

        $provider = new Brand24Provider(new Brand24Normalizer, $client);

        $accountInfo = $provider->testConnection();

        $this->assertSame(14820, $accountInfo->mentionsUsageEstimationAtTheEnd);
    }

    private function mockClient(): Brand24ClientInterface
    {
        return $this->createMock(Brand24ClientInterface::class);
    }
}
